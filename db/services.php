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
 * Web service definitions for local_plugwatch.
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_plugwatch_get_watchlist' => [
        'classname'     => 'local_plugwatch\external\get_watchlist',
        'methodname'    => 'execute',
        'description'   => 'Returns the list of plugins the authenticated user is watching.',
        'type'          => 'read',
        'ajax'          => true,
        'loginrequired' => true,
    ],
    'local_plugwatch_add_plugin' => [
        'classname'     => 'local_plugwatch\external\add_plugin',
        'methodname'    => 'execute',
        'description'   => 'Adds a plugin to the authenticated user\'s watch list.',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],
    'local_plugwatch_remove_plugin' => [
        'classname'     => 'local_plugwatch\external\remove_plugin',
        'methodname'    => 'execute',
        'description'   => 'Removes a plugin from the authenticated user\'s watch list.',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],
    'local_plugwatch_search_plugins' => [
        'classname'     => 'local_plugwatch\external\search_plugins',
        'methodname'    => 'execute',
        'description'   => 'Searches for plugins in the Moodle Plugin Directory.',
        'type'          => 'read',
        'ajax'          => true,
        'loginrequired' => true,
    ],
];
