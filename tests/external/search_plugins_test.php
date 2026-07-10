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
 * Tests for the search_plugins web service.
 *
 * Uses doc-comment annotations (not PHP attributes) for PHPUnit cross-version
 * compatibility with Moodle 4.5 (PHPUnit 9) and 5.x (PHPUnit 10/11).
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \local_plugwatch\external\search_plugins
 */

namespace local_plugwatch\external;

use advanced_testcase;
use local_plugwatch\api\plugindirectory;
use required_capability_exception;

/**
 * Tests for the search_plugins class.
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \local_plugwatch\external\search_plugins
 */
final class search_plugins_test extends advanced_testcase {
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
        search_plugins::execute('xp');
    }

    /**
     * An empty query returns no results without touching the Plugin Directory data.
     *
     * @covers ::execute
     */
    public function test_execute_empty_query_returns_empty_array(): void {
        $this->setUser($this->create_user_with_capability());
        $this->inject_pluglist(['block_xp' => ['component' => 'block_xp', 'name' => 'XP']]);

        $this->assertSame([], search_plugins::execute('   '));
    }

    /**
     * A query matching either the name or the component is found, case-insensitively.
     *
     * @covers ::execute
     */
    public function test_execute_matches_name_or_component(): void {
        $this->setUser($this->create_user_with_capability());
        $this->inject_pluglist([
            'block_xp'   => ['component' => 'block_xp', 'name' => 'XP — Gamification'],
            'mod_game'   => ['component' => 'mod_game', 'name' => 'Game'],
            'mod_forum'  => ['component' => 'mod_forum', 'name' => 'Forum'],
        ]);

        $bycomponent = search_plugins::execute('block_xp');
        $this->assertCount(1, $bycomponent);
        $this->assertSame('block_xp', $bycomponent[0]['component']);

        $byname = search_plugins::execute('GAMIF');
        $this->assertCount(1, $byname);
        $this->assertSame('block_xp', $byname[0]['component']);
    }

    /**
     * Results are capped at 15 matches.
     *
     * @covers ::execute
     */
    public function test_execute_caps_results_at_fifteen(): void {
        $this->setUser($this->create_user_with_capability());

        $pluglist = [];
        for ($i = 0; $i < 20; $i++) {
            $pluglist["block_fake{$i}"] = ['component' => "block_fake{$i}", 'name' => "Fake plugin {$i}"];
        }
        $this->inject_pluglist($pluglist);

        $this->assertCount(15, search_plugins::execute('fake'));
    }
}
