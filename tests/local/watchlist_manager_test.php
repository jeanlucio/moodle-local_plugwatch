<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * PHPUnit tests for watchlist_manager.
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \local_plugwatch\local\watchlist_manager
 */

namespace local_plugwatch\local;

/**
 * Tests for the watchlist_manager class.
 *
 * Uses doc-comment annotations (not PHP attributes) for PHPUnit cross-version
 * compatibility with Moodle 4.5 (PHPUnit 9) and 5.x (PHPUnit 10/11).
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \local_plugwatch\local\watchlist_manager
 */
final class watchlist_manager_test extends \advanced_testcase {
    /**
     * Reset the database before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Adding a plugin creates one item row and one silent state row with correct baseline.
     *
     * @covers ::add_plugin
     */
    public function test_add_plugin_creates_item_and_state(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $component = 'block_xp';
        $baseline = 1700000000;
        $release = 'v2.5.1';

        watchlist_manager::add_plugin($user->id, $component, $baseline, $release);

        $this->assertTrue(
            $DB->record_exists('local_plugwatch_items', ['userid' => $user->id, 'component' => $component]),
            'Item row must exist after add_plugin.'
        );

        $state = $DB->get_record('local_plugwatch_state', ['userid' => $user->id, 'component' => $component]);
        $this->assertNotFalse($state, 'State row must be created on add_plugin.');
        $this->assertSame($baseline, (int) $state->timelastreleased, 'Baseline timelastreleased must be stored.');
        $this->assertSame($release, $state->releasename, 'Baseline release must be stored.');
        $this->assertSame(0, (int) $state->timelastnotified, 'timelastnotified must be 0 (silent baseline).');
    }

    /**
     * Adding the same plugin twice is idempotent — no duplicate rows created.
     *
     * @covers ::add_plugin
     */
    public function test_add_plugin_duplicate_is_idempotent(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $component = 'block_xp';

        watchlist_manager::add_plugin($user->id, $component, 1700000000, 'v2.5.1');
        watchlist_manager::add_plugin($user->id, $component, 1700000000, 'v2.5.1');

        $this->assertSame(
            1,
            $DB->count_records('local_plugwatch_items', ['userid' => $user->id, 'component' => $component]),
            'Duplicate add must not create a second item row.'
        );
    }

    /**
     * Adding a plugin beyond the per-user limit throws moodle_exception.
     *
     * @covers ::add_plugin
     */
    public function test_add_plugin_over_limit_throws_exception(): void {
        $user = $this->getDataGenerator()->create_user();

        // Set limit to 2 for this test.
        set_config('maxplugins', 2, 'local_plugwatch');

        watchlist_manager::add_plugin($user->id, 'block_xp', 0, '');
        watchlist_manager::add_plugin($user->id, 'mod_game', 0, '');

        $this->expectException(\moodle_exception::class);
        watchlist_manager::add_plugin($user->id, 'block_stash', 0, '');
    }

    /**
     * Removing a plugin deletes both the item and the state row.
     *
     * @covers ::remove_plugin
     */
    public function test_remove_plugin_clears_both_tables(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $component = 'block_xp';
        watchlist_manager::add_plugin($user->id, $component, 1700000000, 'v2.5.1');

        watchlist_manager::remove_plugin($user->id, $component);

        $this->assertFalse(
            $DB->record_exists('local_plugwatch_items', ['userid' => $user->id, 'component' => $component]),
            'Item row must be deleted after remove_plugin.'
        );
        $this->assertFalse(
            $DB->record_exists('local_plugwatch_state', ['userid' => $user->id, 'component' => $component]),
            'State row must be deleted after remove_plugin.'
        );
    }

    /**
     * Removing a plugin that is not in the list does not throw.
     *
     * @covers ::remove_plugin
     */
    public function test_remove_plugin_not_in_list_is_safe(): void {
        $user = $this->getDataGenerator()->create_user();
        // Should not throw.
        watchlist_manager::remove_plugin($user->id, 'block_xp');
        $this->assertTrue(true);
    }

    /**
     * get_watchlist returns correct data including joined state fields.
     *
     * @covers ::get_watchlist
     */
    public function test_get_watchlist_returns_correct_data(): void {
        $user = $this->getDataGenerator()->create_user();

        watchlist_manager::add_plugin($user->id, 'block_xp', 1700000000, 'v2.5.1');
        watchlist_manager::add_plugin($user->id, 'mod_game', 1700000001, 'v1.0.0');

        $list = watchlist_manager::get_watchlist($user->id);

        $this->assertCount(2, $list, 'Watchlist must contain exactly two entries.');

        // Results are ordered by component ASC: block_xp before mod_game.
        $this->assertSame('block_xp', $list[0]->component);
        $this->assertSame('mod_game', $list[1]->component);
        $this->assertSame(1700000000, (int) $list[0]->timelastreleased);
        $this->assertSame('v2.5.1', $list[0]->releasename);
    }

    /**
     * get_watchlist returns empty array for a user with no watched plugins.
     *
     * @covers ::get_watchlist
     */
    public function test_get_watchlist_empty_for_new_user(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->assertSame([], watchlist_manager::get_watchlist($user->id));
    }

    /**
     * has_plugin returns true after adding and false after removing.
     *
     * @covers ::has_plugin
     */
    public function test_has_plugin_reflects_list_state(): void {
        $user = $this->getDataGenerator()->create_user();

        $this->assertFalse(watchlist_manager::has_plugin($user->id, 'block_xp'));
        watchlist_manager::add_plugin($user->id, 'block_xp', 0, '');
        $this->assertTrue(watchlist_manager::has_plugin($user->id, 'block_xp'));
        watchlist_manager::remove_plugin($user->id, 'block_xp');
        $this->assertFalse(watchlist_manager::has_plugin($user->id, 'block_xp'));
    }

    /**
     * update_state updates existing state row and respects timelastnotified.
     *
     * @covers ::update_state
     */
    public function test_update_state_updates_existing_row(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $component = 'block_xp';
        watchlist_manager::add_plugin($user->id, $component, 1700000000, 'v2.5.1');

        $now = time();
        watchlist_manager::update_state($user->id, $component, 1700000999, 'v2.6.0', $now);

        $state = $DB->get_record('local_plugwatch_state', ['userid' => $user->id, 'component' => $component]);
        $this->assertSame(1700000999, (int) $state->timelastreleased);
        $this->assertSame('v2.6.0', $state->releasename);
        $this->assertSame($now, (int) $state->timelastnotified);
    }

    /**
     * get_limit respects the admin setting and falls back to 30 when absent.
     *
     * @covers ::get_limit
     */
    public function test_get_limit_uses_config_with_fallback(): void {
        // Default: no config set.
        unset_config('maxplugins', 'local_plugwatch');
        $this->assertSame(30, watchlist_manager::get_limit());

        // Custom limit.
        set_config('maxplugins', 50, 'local_plugwatch');
        $this->assertSame(50, watchlist_manager::get_limit());
    }

    /**
     * count_plugins returns correct count across multiple users (no cross-user leak).
     *
     * @covers ::count_plugins
     */
    public function test_count_plugins_is_per_user(): void {
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        watchlist_manager::add_plugin($user1->id, 'block_xp', 1700000000, 'v2.5.1');
        watchlist_manager::add_plugin($user1->id, 'mod_game', 1700000001, 'v1.0.0');
        watchlist_manager::add_plugin($user2->id, 'block_xp', 1700000000, 'v2.5.1');

        $this->assertSame(2, watchlist_manager::count_plugins($user1->id));
        $this->assertSame(1, watchlist_manager::count_plugins($user2->id));
    }
}
