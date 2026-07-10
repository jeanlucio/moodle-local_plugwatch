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
 * Tests for the remove_plugin web service.
 *
 * Uses doc-comment annotations (not PHP attributes) for PHPUnit cross-version
 * compatibility with Moodle 4.5 (PHPUnit 9) and 5.x (PHPUnit 10/11).
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \local_plugwatch\external\remove_plugin
 */

namespace local_plugwatch\external;

use advanced_testcase;
use local_plugwatch\local\watchlist_manager;
use required_capability_exception;

/**
 * Tests for the remove_plugin class.
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \local_plugwatch\external\remove_plugin
 */
final class remove_plugin_test extends advanced_testcase {
    /**
     * Reset the database before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Creates a user with the local/plugwatch:use capability at the system context.
     *
     * @return \stdClass
     */
    private function create_user_with_capability(): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('local/plugwatch:use', CAP_ALLOW, $roleid, \context_system::instance()->id);
        role_assign($roleid, $user->id, \context_system::instance()->id);

        return $user;
    }

    /**
     * A user without the capability is denied.
     *
     * @covers ::execute
     */
    public function test_execute_denies_user_without_capability(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(required_capability_exception::class);
        remove_plugin::execute('block_xp');
    }

    /**
     * Removing a watched plugin clears both tables and returns success.
     *
     * @covers ::execute
     */
    public function test_execute_removes_watched_plugin(): void {
        global $DB;

        $user = $this->create_user_with_capability();
        $this->setUser($user);
        watchlist_manager::add_plugin($user->id, 'block_xp', 1700000000, 'v2.5.1');

        $result = remove_plugin::execute('block_xp');

        $this->assertTrue($result['success']);
        $this->assertFalse($DB->record_exists('local_plugwatch_items', ['userid' => $user->id]));
        $this->assertFalse($DB->record_exists('local_plugwatch_state', ['userid' => $user->id]));
    }

    /**
     * Removing a plugin that was never watched is a safe no-op that still succeeds.
     *
     * @covers ::execute
     */
    public function test_execute_on_unwatched_plugin_is_idempotent(): void {
        $user = $this->create_user_with_capability();
        $this->setUser($user);

        $result = remove_plugin::execute('block_xp');

        $this->assertTrue($result['success']);
    }

    /**
     * Removing a plugin only affects the current user's own watch list.
     *
     * @covers ::execute
     */
    public function test_execute_does_not_affect_other_users(): void {
        global $DB;

        $roleid = $this->getDataGenerator()->create_role();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        assign_capability('local/plugwatch:use', CAP_ALLOW, $roleid, \context_system::instance()->id);
        role_assign($roleid, $user1->id, \context_system::instance()->id);
        role_assign($roleid, $user2->id, \context_system::instance()->id);

        watchlist_manager::add_plugin($user1->id, 'block_xp', 1700000000, 'v2.5.1');
        watchlist_manager::add_plugin($user2->id, 'block_xp', 1700000000, 'v2.5.1');

        $this->setUser($user1);
        remove_plugin::execute('block_xp');

        $this->assertFalse($DB->record_exists('local_plugwatch_items', ['userid' => $user1->id]));
        $this->assertTrue($DB->record_exists('local_plugwatch_items', ['userid' => $user2->id]));
    }
}
