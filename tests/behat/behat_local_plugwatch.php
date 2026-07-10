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
 * Step definitions related to local_plugwatch.
 *
 * @package     local_plugwatch
 * @category    test
 * @copyright   2026 Jean Lúcio
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Step definitions related to local_plugwatch.
 *
 * @package     local_plugwatch
 * @copyright   2026 Jean Lúcio
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_plugwatch extends behat_base {
    /**
     * Seeds a watch list entry (item + silent baseline state) directly, bypassing the UI.
     *
     * @Given /^"(?P<username_string>(?:[^"]|\\")*)" is watching the plugin "(?P<component_string>(?:[^"]|\\")*)"$/
     * @param string $username Existing username to add the watch item for.
     * @param string $component Frankenstyle component name (e.g. block_xp).
     */
    public function user_is_watching_the_plugin(string $username, string $component): void {
        global $DB;

        $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);
        \local_plugwatch\local\watchlist_manager::add_plugin((int) $user->id, $component, time(), 'v1.0.0');
    }
}
