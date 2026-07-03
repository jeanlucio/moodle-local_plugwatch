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
 * Library functions for local_plugwatch.
 *
 * This file exists solely to host the navigation callback discovered by core
 * via get_plugins_with_function(). All other logic lives in autoloaded classes
 * under classes/.
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Adds a link to the Plugin Monitor preferences page in the user settings navigation.
 *
 * Core discovers this callback exclusively via get_plugins_with_function('extend_navigation_user_settings', 'lib.php').
 * The link is only shown to users with the local/plugwatch:use capability.
 *
 * @param navigation_node $navigation The navigation node to extend.
 * @param stdClass $user The user object (not necessarily $USER).
 * @param context_user $usercontext The user context.
 * @param stdClass $course The current course.
 * @param context_course $coursecontext The course context.
 * @return void
 */
function local_plugwatch_extend_navigation_user_settings(
    navigation_node $navigation,
    stdClass $user,
    context_user $usercontext,
    stdClass $course,
    context_course $coursecontext
): void {
    global $USER;

    // Only show for the current user (not admin viewing another user's profile).
    if ($user->id !== $USER->id) {
        return;
    }

    if (!has_capability('local/plugwatch:use', context_system::instance())) {
        return;
    }

    $url = new moodle_url('/local/plugwatch/preferences.php');
    $navigation->add(
        get_string('pluginname', 'local_plugwatch'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'local_plugwatch_preferences',
        new pix_icon('i/settings', '')
    );
}
