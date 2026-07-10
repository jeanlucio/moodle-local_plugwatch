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
 * Tests for the add_plugin web service.
 *
 * Uses doc-comment annotations (not PHP attributes) for PHPUnit cross-version
 * compatibility with Moodle 4.5 (PHPUnit 9) and 5.x (PHPUnit 10/11).
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \local_plugwatch\external\add_plugin
 */

namespace local_plugwatch\external;

use advanced_testcase;
use local_plugwatch\api\plugindirectory;
use local_plugwatch\local\watchlist_manager;
use required_capability_exception;

/**
 * Tests for the add_plugin class.
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \local_plugwatch\external\add_plugin
 */
final class add_plugin_test extends advanced_testcase {
    /**
     * Reset the database and the plugindirectory in-memory cache before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
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
        add_plugin::execute('block_xp');
    }

    /**
     * Adding a plugin that exists in the Plugin Directory succeeds and creates the baseline.
     *
     * @covers ::execute
     */
    public function test_execute_adds_existing_plugin(): void {
        global $DB;

        $user = $this->create_user_with_capability();
        $this->setUser($user);

        $this->inject_pluglist([
            'block_xp' => [
                'component'        => 'block_xp',
                'name'              => 'XP',
                'timelastreleased'  => 1700000000,
                'version'           => ['release' => 'v2.5.1'],
            ],
        ]);

        $result = add_plugin::execute('block_xp');

        $this->assertTrue($result['success']);
        $this->assertSame('', $result['message']);
        $this->assertTrue($DB->record_exists('local_plugwatch_items', ['userid' => $user->id, 'component' => 'block_xp']));
    }

    /**
     * Adding a plugin that does not exist in the Plugin Directory fails gracefully.
     *
     * @covers ::execute
     */
    public function test_execute_rejects_unknown_plugin(): void {
        global $DB;

        $user = $this->create_user_with_capability();
        $this->setUser($user);

        $this->inject_pluglist([]);

        $result = add_plugin::execute('block_doesnotexist');

        $this->assertFalse($result['success']);
        $this->assertSame(get_string('errorpluginnotfound', 'local_plugwatch'), $result['message']);
        $this->assertFalse($DB->record_exists('local_plugwatch_items', ['userid' => $user->id]));
    }

    /**
     * Adding a plugin beyond the per-user limit fails gracefully instead of throwing.
     *
     * @covers ::execute
     */
    public function test_execute_rejects_over_limit(): void {
        $user = $this->create_user_with_capability();
        $this->setUser($user);
        set_config('maxplugins', 1, 'local_plugwatch');

        $this->inject_pluglist([
            'block_xp' => ['component' => 'block_xp', 'name' => 'XP', 'timelastreleased' => 1700000000],
            'mod_game' => ['component' => 'mod_game', 'name' => 'Game', 'timelastreleased' => 1700000000],
        ]);

        $first = add_plugin::execute('block_xp');
        $this->assertTrue($first['success']);

        $second = add_plugin::execute('mod_game');
        $this->assertFalse($second['success']);
        $this->assertNotEmpty($second['message']);
    }
}
