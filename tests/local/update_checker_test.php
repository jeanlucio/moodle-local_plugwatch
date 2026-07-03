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
 * Tests for the update checker engine.
 *
 * Uses doc-comment annotations (not PHP attributes) for PHPUnit cross-version
 * compatibility with Moodle 4.5 (PHPUnit 9) and 5.x (PHPUnit 10/11).
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \local_plugwatch\local\update_checker
 */

namespace local_plugwatch\local;

use advanced_testcase;
use local_plugwatch\api\plugindirectory;
use local_plugwatch\tests\plugwatch_generator;

/**
 * Tests for the update checker engine.
 *
 * To avoid real HTTP calls, the plugindirectory in-memory cache is injected
 * via Reflection before each test that exercises update_checker::execute().
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \local_plugwatch\local\update_checker
 */
final class update_checker_test extends advanced_testcase {
    /**
     * Reset DB and plugin cache before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        // Ensure the plugin is enabled for all tests that exercise execute().
        set_config('enabled', 1, 'local_plugwatch');
        // Clear the in-memory pluglist cache so tests are isolated.
        $this->inject_pluglist(null);
    }

    /**
     * Injects a fake pluglist into plugindirectory's static in-memory cache via Reflection.
     *
     * Pass null to clear the cache (forces a real fetch on the next call, but
     * execute() guards against an empty list so the test is still safe).
     *
     * @param array|null $pluglist The fake plugin list keyed by component, or null to clear.
     */
    private function inject_pluglist(?array $pluglist): void {
        $prop = new \ReflectionProperty(plugindirectory::class, 'pluglist');
        $prop->setAccessible(true);
        $prop->setValue(null, $pluglist);
    }

    /**
     * Returns a minimal API entry for a plugin component.
     *
     * @param string $component Frankenstyle component.
     * @param int $timelastreleased Timestamp of the last release.
     * @param string $release Human-readable release string.
     * @return array
     */
    private function make_api_entry(string $component, int $timelastreleased, string $release): array {
        return [
            'component'        => $component,
            'name'             => $component,
            'timelastreleased' => $timelastreleased,
            'source'           => '',
            'versions'         => [
                ['timecreated' => $timelastreleased, 'release' => $release],
            ],
        ];
    }

    // Tests for get_latest_version_from_api (private helper).

    /**
     * get_latest_version_from_api returns the entry with the highest timecreated.
     *
     * @covers ::get_latest_version_from_api
     */
    public function test_get_latest_version_from_api(): void {
        $method = new \ReflectionMethod(update_checker::class, 'get_latest_version_from_api');
        $method->setAccessible(true);

        $apidata = [
            'versions' => [
                ['timecreated' => 1000, 'release' => '1.0'],
                ['timecreated' => 3000, 'release' => '3.0'],
                ['timecreated' => 2000, 'release' => '2.0'],
            ],
        ];

        $latest = $method->invoke(null, $apidata);
        $this->assertSame('3.0', $latest['release']);
    }

    /**
     * get_latest_version_from_api returns null when versions is empty.
     *
     * @covers ::get_latest_version_from_api
     */
    public function test_get_latest_version_from_api_empty_returns_null(): void {
        $method = new \ReflectionMethod(update_checker::class, 'get_latest_version_from_api');
        $method->setAccessible(true);

        $this->assertNull($method->invoke(null, []));
        $this->assertNull($method->invoke(null, ['versions' => []]));
    }

    // Tests for execute() via injected pluglist.

    /**
     * When the API version matches the saved baseline, no notification is sent.
     *
     * @covers ::execute
     */
    public function test_no_update_does_not_send_notification(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $component = 'block_xp';
        $baseline = 1700000000;

        plugwatch_generator::create_watch_item($user->id, $component, $baseline, 'v2.5.1');

        // API returns the same timelastreleased as the saved baseline — no update.
        $this->inject_pluglist([$component => $this->make_api_entry($component, $baseline, 'v2.5.1')]);

        $sink = $this->redirectMessages();
        update_checker::execute();
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(0, $messages, 'No notification must be sent when there is no update.');

        // The timechecked field must be updated after each execute() call.
        $state = $DB->get_record('local_plugwatch_state', ['userid' => $user->id, 'component' => $component]);
        $this->assertGreaterThan(0, (int) $state->timechecked, 'timechecked must be updated even when no update is found.');
    }

    /**
     * When the API reports a newer version, a notification is sent and state is updated.
     *
     * @covers ::execute
     */
    public function test_update_detected_sends_notification_and_updates_state(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $component = 'block_xp';
        $baseline = 1700000000;
        $newrelease = 1700000999;

        plugwatch_generator::create_watch_item($user->id, $component, $baseline, 'v2.5.1');

        // API reports a newer version.
        $this->inject_pluglist([$component => $this->make_api_entry($component, $newrelease, 'v2.6.0')]);

        $sink = $this->redirectMessages();
        update_checker::execute();
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $messages, 'Exactly one notification must be sent for the updated plugin.');
        $this->assertSame('local_plugwatch', $messages[0]->component);
        $this->assertSame('plugin_updated', $messages[0]->eventtype);

        // State must be updated to reflect the new version.
        $state = $DB->get_record('local_plugwatch_state', ['userid' => $user->id, 'component' => $component]);
        $this->assertSame($newrelease, (int) $state->timelastreleased, 'State must store the new timelastreleased.');
        $this->assertGreaterThan(0, (int) $state->timelastnotified, 'timelastnotified must be set after notification.');
    }

    /**
     * Frequency check: if timelastnotified is recent, notification is skipped.
     *
     * @covers ::execute
     */
    public function test_frequency_skips_notification_if_notified_recently(): void {
        $user = $this->getDataGenerator()->create_user();
        $component = 'block_xp';
        $baseline = 1700000000;
        $newrelease = 1700000999;

        // Simulate that a notification was sent 2 hours ago.
        $recentlynotified = time() - (2 * 3600);
        plugwatch_generator::create_watch_item($user->id, $component, $baseline, 'v2.5.1');
        watchlist_manager::update_state($user->id, $component, $baseline, 'v2.5.1', $recentlynotified);

        // Set frequency to weekly (7 days). The 2-hour gap is shorter so the notification must be skipped.
        set_user_preference('local_plugwatch_frequency', update_checker::FREQ_WEEKLY, $user->id);

        // API reports a newer version.
        $this->inject_pluglist([$component => $this->make_api_entry($component, $newrelease, 'v2.6.0')]);

        $sink = $this->redirectMessages();
        update_checker::execute();
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(0, $messages, 'Notification must be skipped when frequency interval has not elapsed.');
    }

    /**
     * When the plugin is not in the API list (e.g. removed from Directory), no notification.
     *
     * @covers ::execute
     */
    public function test_plugin_missing_from_api_is_skipped_silently(): void {
        $user = $this->getDataGenerator()->create_user();
        plugwatch_generator::create_watch_item($user->id, 'block_xp', 1700000000, 'v2.5.1');

        // Inject a pluglist that does not contain block_xp.
        $this->inject_pluglist(['mod_game' => $this->make_api_entry('mod_game', 1700000001, 'v1.0.0')]);

        $sink = $this->redirectMessages();
        update_checker::execute();
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(0, $messages, 'Missing plugin must not cause an error or spurious notification.');
    }

    /**
     * When the plugin is disabled via settings, execute() returns immediately.
     *
     * @covers ::execute
     */
    public function test_disabled_plugin_skips_all_processing(): void {
        $user = $this->getDataGenerator()->create_user();
        plugwatch_generator::create_watch_item($user->id, 'block_xp', 1700000000, 'v2.5.1');

        set_config('enabled', 0, 'local_plugwatch');

        // Inject a newer version that would otherwise trigger a notification.
        $this->inject_pluglist(['block_xp' => $this->make_api_entry('block_xp', 1700000999, 'v2.6.0')]);

        $sink = $this->redirectMessages();
        update_checker::execute();
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(0, $messages, 'No processing must occur when the plugin is disabled.');
    }
}
