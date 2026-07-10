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
 * Pre-uninstallation hook for local_plugwatch.
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Cleans up user_preferences rows that core does not remove automatically.
 *
 * Tables declared in db/install.xml, admin settings and capabilities are all
 * already dropped by core's uninstall_plugin(); only the local_plugwatch_*
 * user preferences need explicit cleanup here.
 *
 * @return bool
 */
function xmldb_local_plugwatch_uninstall(): bool {
    global $DB;

    $DB->delete_records_select(
        'user_preferences',
        $DB->sql_like('name', ':p'),
        ['p' => 'local_plugwatch_%']
    );

    return true;
}
