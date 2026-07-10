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
 * Tests for lang string keys that Moodle core derives automatically.
 *
 * Uses doc-comment annotations (not PHP attributes) for PHPUnit cross-version
 * compatibility with Moodle 4.5 (PHPUnit 9) and 5.x (PHPUnit 10/11).
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_plugwatch;

use advanced_testcase;

/**
 * Some Moodle-defined entities (capabilities, message providers) resolve
 * their display name from a reserved lang key format that diverges from the
 * plugin's own free-form string naming (e.g. "plugwatch:use", not
 * "capability_use"; "messageprovider:plugin_updated", not
 * "messageprovider_plugin_updated"). Nothing in PHPCS, moodlecheck or
 * PHPStan catches a mismatch — it only ever surfaces on the specific core
 * screen that renders that entity (Define roles, notification preferences).
 * These tests read the declarations directly from db/access.php and
 * db/messages.php so a future capability or provider is covered
 * automatically, without needing its name hardcoded here.
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class lang_test extends advanced_testcase {
    /**
     * Reset the database before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Every capability declared in db/access.php must have its display
     * string under the exact key Moodle derives from the capability name
     * (get_capability_string() in accesslib.php), not a custom key.
     */
    public function test_capability_strings_resolve(): void {
        global $CFG;

        $capabilities = [];
        require($CFG->dirroot . '/local/plugwatch/db/access.php');

        $this->assertNotEmpty($capabilities, 'db/access.php must declare at least one capability.');

        foreach (array_keys($capabilities) as $capabilityname) {
            // Mirrors get_capability_string()'s own parsing in accesslib.php:
            // 'local/plugwatch:use' splits into type='local', name='plugwatch', capname='use',
            // and the string key it looks up is "{$name}:{$capname}", e.g. 'plugwatch:use'.
            [, $name, $capname] = preg_split('|[/:]|', $capabilityname);
            $stringkey = "{$name}:{$capname}";
            $this->assertTrue(
                get_string_manager()->string_exists($stringkey, 'local_plugwatch'),
                "Capability '{$capabilityname}' must have a lang string under the key '{$stringkey}'."
            );
        }
    }

    /**
     * Every message provider declared in db/messages.php must have its
     * display name under the reserved messageprovider:<name> key (colon,
     * not underscore), or /message/notificationpreferences.php breaks for
     * every user as soon as they open the page.
     */
    public function test_message_provider_strings_resolve(): void {
        global $CFG;

        $messageproviders = [];
        require($CFG->dirroot . '/local/plugwatch/db/messages.php');

        $this->assertNotEmpty($messageproviders, 'db/messages.php must declare at least one message provider.');

        foreach (array_keys($messageproviders) as $providername) {
            $this->assertTrue(
                get_string_manager()->string_exists("messageprovider:{$providername}", 'local_plugwatch'),
                "Message provider '{$providername}' must have a lang string under the key 'messageprovider:{$providername}'."
            );
        }
    }
}
