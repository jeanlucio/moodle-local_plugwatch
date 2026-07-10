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
 * PHPUnit tests for the local_plugwatch privacy provider.
 *
 * Uses doc-comment annotations (not PHP attributes) for PHPUnit cross-version
 * compatibility with Moodle 4.5 (PHPUnit 9) and 5.x (PHPUnit 10/11).
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \local_plugwatch\privacy\provider
 */

namespace local_plugwatch\privacy;

use advanced_testcase;
use context_system;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_plugwatch\local\watchlist_manager;

/**
 * Tests for the privacy provider class.
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \local_plugwatch\privacy\provider
 */
final class provider_test extends advanced_testcase {
    /**
     * Reset the database before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * get_contexts_for_userid includes the system context when the user has watch list data.
     *
     * @covers ::get_contexts_for_userid
     */
    public function test_get_contexts_for_userid_returns_system_context_when_data_exists(): void {
        $user = $this->getDataGenerator()->create_user();
        watchlist_manager::add_plugin($user->id, 'block_xp', 1700000000, 'v2.5.1');

        $contextlist = provider::get_contexts_for_userid($user->id);
        $contextids = array_map('intval', $contextlist->get_contextids());

        $this->assertContains((int) context_system::instance()->id, $contextids);
    }

    /**
     * get_contexts_for_userid returns an empty list for a user with no data.
     *
     * @covers ::get_contexts_for_userid
     */
    public function test_get_contexts_for_userid_empty_for_user_without_data(): void {
        $user = $this->getDataGenerator()->create_user();

        $contextlist = provider::get_contexts_for_userid($user->id);

        $this->assertEmpty($contextlist->get_contextids());
    }

    /**
     * get_users_in_context lists only the users that have watch list data.
     *
     * @covers ::get_users_in_context
     */
    public function test_get_users_in_context_includes_only_users_with_data(): void {
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        watchlist_manager::add_plugin($user1->id, 'block_xp', 1700000000, 'v2.5.1');

        $userlist = new userlist(context_system::instance(), 'local_plugwatch');
        provider::get_users_in_context($userlist);
        $userids = array_map('intval', $userlist->get_userids());

        $this->assertContains((int) $user1->id, $userids);
        $this->assertNotContains((int) $user2->id, $userids);
    }

    /**
     * export_user_data exports both the watch list items and the state rows.
     *
     * @covers ::export_user_data
     */
    public function test_export_user_data_exports_items_and_state(): void {
        $user = $this->getDataGenerator()->create_user();
        watchlist_manager::add_plugin($user->id, 'block_xp', 1700000000, 'v2.5.1');

        $context = context_system::instance();
        $approvedcontextlist = new approved_contextlist($user, 'local_plugwatch', [$context->id]);
        provider::export_user_data($approvedcontextlist);

        $this->assertTrue(writer::with_context($context)->has_any_data());
    }

    /**
     * delete_data_for_user removes both the item and the state row for that user only.
     *
     * @covers ::delete_data_for_user
     */
    public function test_delete_data_for_user_removes_rows(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        watchlist_manager::add_plugin($user->id, 'block_xp', 1700000000, 'v2.5.1');

        $context = context_system::instance();
        $approvedcontextlist = new approved_contextlist($user, 'local_plugwatch', [$context->id]);
        provider::delete_data_for_user($approvedcontextlist);

        $this->assertFalse($DB->record_exists('local_plugwatch_items', ['userid' => $user->id]));
        $this->assertFalse($DB->record_exists('local_plugwatch_state', ['userid' => $user->id]));
    }

    /**
     * delete_data_for_users only removes rows for the targeted userids.
     *
     * @covers ::delete_data_for_users
     */
    public function test_delete_data_for_users_only_removes_targeted_users(): void {
        global $DB;

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        watchlist_manager::add_plugin($user1->id, 'block_xp', 1700000000, 'v2.5.1');
        watchlist_manager::add_plugin($user2->id, 'block_xp', 1700000000, 'v2.5.1');

        $approveduserlist = new approved_userlist(context_system::instance(), 'local_plugwatch', [$user1->id]);
        provider::delete_data_for_users($approveduserlist);

        $this->assertFalse($DB->record_exists('local_plugwatch_items', ['userid' => $user1->id]));
        $this->assertTrue($DB->record_exists('local_plugwatch_items', ['userid' => $user2->id]));
    }

    /**
     * export_user_preferences exports the notification frequency preference.
     *
     * @covers ::export_user_preferences
     */
    public function test_export_user_preferences_exports_frequency(): void {
        $user = $this->getDataGenerator()->create_user();
        set_user_preference('local_plugwatch_frequency', 604800, $user->id);

        provider::export_user_preferences($user->id);

        $preferences = writer::with_context(context_system::instance())->get_user_preferences('local_plugwatch');

        $this->assertTrue(property_exists($preferences, 'local_plugwatch_frequency'));
        $this->assertEquals(604800, $preferences->local_plugwatch_frequency->value);
    }
}
