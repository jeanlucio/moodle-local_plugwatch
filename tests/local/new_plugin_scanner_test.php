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
 * Tests for the new-plugins digest scanner.
 *
 * Uses doc-comment annotations (not PHP attributes) for PHPUnit cross-version
 * compatibility with Moodle 4.5 (PHPUnit 9) and 5.x (PHPUnit 10/11).
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \local_plugwatch\local\new_plugin_scanner
 */

namespace local_plugwatch\local;

use advanced_testcase;
use local_plugwatch\api\plugindirectory;

/**
 * Tests for the new_plugin_scanner class.
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \local_plugwatch\local\new_plugin_scanner
 */
final class new_plugin_scanner_test extends advanced_testcase {
    /**
     * Reset DB and plugin cache before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled', 1, 'local_plugwatch');
        $this->inject_pluglist(null);
    }

    /**
     * Injects a fake pluglist into plugindirectory's static in-memory cache via reflection.
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
     * @return array
     */
    private function make_api_entry(string $component, int $timelastreleased = 1700000000): array {
        return [
            'component'        => $component,
            'name'             => $component,
            'timelastreleased' => $timelastreleased,
        ];
    }

    /**
     * Opts a user in and sets their frequency/lastdigest preferences directly.
     *
     * @param int $userid Target user.
     * @param int $lastdigest Timestamp to use as their last-digest cursor.
     * @param int $frequency Frequency in seconds.
     */
    private function opt_in_user(int $userid, int $lastdigest, int $frequency = update_checker::FREQ_WEEKLY): void {
        // The new_plugins_digest message provider requires local/plugwatch:use, or
        // message_send() silently refuses to queue the message for this user.
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('local/plugwatch:use', CAP_ALLOW, $roleid, \context_system::instance()->id);
        role_assign($roleid, $userid, \context_system::instance()->id);

        set_user_preference('local_plugwatch_notifynewplugins', 1, $userid);
        set_user_preference('local_plugwatch_lastdigest', $lastdigest, $userid);
        set_user_preference('local_plugwatch_frequency', $frequency, $userid);
    }

    /**
     * The first-ever scan (empty log) records everything as a silent baseline and notifies nobody.
     *
     * @covers ::execute
     */
    public function test_first_run_is_silent_baseline(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        // Even a user who is already "due" for a digest must not be flooded by the baseline.
        $this->opt_in_user((int) $user->id, time() - update_checker::FREQ_WEEKLY - 10);

        $this->inject_pluglist([
            'block_xp'   => $this->make_api_entry('block_xp'),
            'mod_game'   => $this->make_api_entry('mod_game'),
        ]);

        $sink = $this->redirectMessages();
        new_plugin_scanner::execute();
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(0, $messages, 'The global baseline run must not send any digest.');
        $this->assertSame(2, $DB->count_records('local_plugwatch_newplugins'));
    }

    /**
     * After the baseline is established, a genuinely new component triggers a digest for a due, opted-in user.
     *
     * @covers ::execute
     */
    public function test_new_component_after_baseline_notifies_due_user(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->opt_in_user((int) $user->id, time() - update_checker::FREQ_WEEKLY - 10);

        // Simulate a prior baseline: block_xp already known.
        $DB->insert_record('local_plugwatch_newplugins', (object) [
            'component'        => 'block_xp',
            'name'             => 'XP',
            'timelastreleased' => 1700000000,
            'timediscovered'   => time() - (2 * update_checker::FREQ_WEEKLY),
        ]);

        // The mod_game component is genuinely new this run.
        $this->inject_pluglist([
            'block_xp' => $this->make_api_entry('block_xp'),
            'mod_game' => $this->make_api_entry('mod_game'),
        ]);

        $sink = $this->redirectMessages();
        new_plugin_scanner::execute();
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $messages, 'Exactly one digest must be sent for the newly discovered plugin.');
        $this->assertSame('local_plugwatch', $messages[0]->component);
        $this->assertSame('new_plugins_digest', $messages[0]->eventtype);
        $this->assertStringContainsString('mod_game', $messages[0]->fullmessage);

        $this->assertTrue($DB->record_exists('local_plugwatch_newplugins', ['component' => 'mod_game']));

        $lastdigest = (int) get_user_preferences('local_plugwatch_lastdigest', 0, $user->id);
        $this->assertGreaterThan(time() - 10, $lastdigest, 'lastdigest must be bumped to now after sending.');
    }

    /**
     * Several new components in the same run are consolidated into a single message.
     *
     * @covers ::execute
     */
    public function test_multiple_new_components_produce_one_consolidated_message(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->opt_in_user((int) $user->id, time() - update_checker::FREQ_WEEKLY - 10);

        $DB->insert_record('local_plugwatch_newplugins', (object) [
            'component'        => 'block_xp',
            'name'             => 'XP',
            'timelastreleased' => 1700000000,
            'timediscovered'   => time() - (2 * update_checker::FREQ_WEEKLY),
        ]);

        $this->inject_pluglist([
            'block_xp'  => $this->make_api_entry('block_xp'),
            'mod_game'  => $this->make_api_entry('mod_game'),
            'mod_forum' => $this->make_api_entry('mod_forum'),
        ]);

        $sink = $this->redirectMessages();
        new_plugin_scanner::execute();
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $messages, 'Two new plugins in the same run must produce a single digest.');
        $this->assertStringContainsString('mod_game', $messages[0]->fullmessage);
        $this->assertStringContainsString('mod_forum', $messages[0]->fullmessage);
    }

    /**
     * A user whose frequency interval has not elapsed yet is skipped, even with new plugins pending.
     *
     * @covers ::execute
     */
    public function test_frequency_skips_notification_if_notified_recently(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        // Notified 2 hours ago, weekly frequency: not due yet.
        $this->opt_in_user((int) $user->id, time() - (2 * 3600));

        $DB->insert_record('local_plugwatch_newplugins', (object) [
            'component'        => 'block_xp',
            'name'             => 'XP',
            'timelastreleased' => 1700000000,
            'timediscovered'   => time() - (2 * update_checker::FREQ_WEEKLY),
        ]);

        $this->inject_pluglist([
            'block_xp' => $this->make_api_entry('block_xp'),
            'mod_game' => $this->make_api_entry('mod_game'),
        ]);

        $sink = $this->redirectMessages();
        new_plugin_scanner::execute();
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(0, $messages, 'Notification must be skipped when the frequency interval has not elapsed.');
    }

    /**
     * A user who never opted in is never notified, regardless of new plugins.
     *
     * @covers ::execute
     */
    public function test_user_not_opted_in_is_never_notified(): void {
        global $DB;

        $this->getDataGenerator()->create_user();

        $DB->insert_record('local_plugwatch_newplugins', (object) [
            'component'        => 'block_xp',
            'name'             => 'XP',
            'timelastreleased' => 1700000000,
            'timediscovered'   => time() - (2 * update_checker::FREQ_WEEKLY),
        ]);

        $this->inject_pluglist([
            'block_xp' => $this->make_api_entry('block_xp'),
            'mod_game' => $this->make_api_entry('mod_game'),
        ]);

        $sink = $this->redirectMessages();
        new_plugin_scanner::execute();
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(0, $messages, 'A user who never opted in must never receive the digest.');
    }

    /**
     * When the plugin is disabled via settings, execute() does nothing.
     *
     * @covers ::execute
     */
    public function test_disabled_plugin_skips_all_processing(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->opt_in_user((int) $user->id, time() - update_checker::FREQ_WEEKLY - 10);

        set_config('enabled', 0, 'local_plugwatch');

        $this->inject_pluglist([
            'block_xp' => $this->make_api_entry('block_xp'),
        ]);

        $sink = $this->redirectMessages();
        new_plugin_scanner::execute();
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(0, $messages);
        $this->assertSame(0, $DB->count_records('local_plugwatch_newplugins'));
    }
}
