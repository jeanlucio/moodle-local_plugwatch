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
 * Tests for the get_watchlist web service.
 *
 * Uses doc-comment annotations (not PHP attributes) for PHPUnit cross-version
 * compatibility with Moodle 4.5 (PHPUnit 9) and 5.x (PHPUnit 10/11).
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \local_plugwatch\external\get_watchlist
 */

namespace local_plugwatch\external;

use advanced_testcase;
use local_plugwatch\local\watchlist_manager;
use required_capability_exception;

/**
 * Tests for the get_watchlist class.
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \local_plugwatch\external\get_watchlist
 */
final class get_watchlist_test extends advanced_testcase {
    /**
     * Reset the database before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
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
        get_watchlist::execute();
    }

    /**
     * A user with no watched plugins gets an empty list.
     *
     * @covers ::execute
     */
    public function test_execute_returns_empty_array_for_user_without_plugins(): void {
        $user = $this->create_user_with_capability();
        $this->setUser($user);

        $this->assertSame([], get_watchlist::execute());
    }

    /**
     * The watch list is returned with all the expected fields, in component order.
     *
     * @covers ::execute
     */
    public function test_execute_returns_watch_list_data(): void {
        $user = $this->create_user_with_capability();
        $this->setUser($user);

        watchlist_manager::add_plugin($user->id, 'block_xp', 1700000000, 'v2.5.1');
        watchlist_manager::add_plugin($user->id, 'mod_game', 1700000001, 'v1.0.0');

        $result = get_watchlist::execute();

        $this->assertCount(2, $result);
        $this->assertSame('block_xp', $result[0]['component']);
        $this->assertSame('v2.5.1', $result[0]['releasename']);
        $this->assertSame(1700000000, $result[0]['timelastreleased']);
        $this->assertSame(0, $result[0]['timelastnotified']);
        $this->assertSame('mod_game', $result[1]['component']);
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
}
