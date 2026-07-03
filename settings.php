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
 * Admin settings for local_plugwatch.
 *
 * No DB queries at include time. Option lists that require DB data must use
 * lazy-loading admin_setting_* classes.
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_plugwatch',
        get_string('pluginname', 'local_plugwatch')
    );

    $ADMIN->add('localplugins', $settings);

    // Enable / disable the plugin entirely.
    $settings->add(new admin_setting_configcheckbox(
        'local_plugwatch/enabled',
        get_string('settings_enabled', 'local_plugwatch'),
        get_string('settings_enabled_help', 'local_plugwatch'),
        1
    ));

    // Maximum plugins per user (default 30).
    $settings->add(new admin_setting_configtext(
        'local_plugwatch/maxplugins',
        get_string('maxplugins', 'local_plugwatch'),
        get_string('maxplugins_help', 'local_plugwatch'),
        30,
        PARAM_INT
    ));

    // Optional GitHub personal access token.
    $settings->add(new admin_setting_configpasswordunmask(
        'local_plugwatch/githubtoken',
        get_string('githubtoken', 'local_plugwatch'),
        get_string('githubtoken_help', 'local_plugwatch'),
        ''
    ));
}
