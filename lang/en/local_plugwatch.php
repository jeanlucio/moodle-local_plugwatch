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
 * English language strings for local_plugwatch.
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// phpcs:disable moodle.Files.LineLength

defined('MOODLE_INTERNAL') || die();

$string['addplugin'] = 'Add plugin';
$string['capability_use'] = 'Use Plugin Monitor';
$string['capability_use_help'] = 'Allows the user to manage a personal list of Moodle plugins to monitor and receive update notifications in their own language.';
$string['errorlimitreached'] = 'You have reached the maximum of {$a} watched plugins.';
$string['errorpluginnotfound'] = 'Plugin not found in the Plugin Directory.';
$string['frequency'] = 'Notification frequency';
$string['frequency_daily'] = 'Daily';
$string['frequency_monthly'] = 'Monthly';
$string['frequency_weekly'] = 'Weekly';
$string['githubtoken'] = 'GitHub API token (optional)';
$string['githubtoken_help'] = 'Personal access token to increase GitHub API rate limit from 60 to 5000 requests/hour. Leave blank to use unauthenticated access.';
$string['lastchecked'] = 'Last checked';
$string['lastnotified'] = 'Last notified';
$string['maxplugins'] = 'Maximum plugins per user';
$string['maxplugins_help'] = 'Maximum number of plugins each user can add to their watch list. Default: 30.';
$string['messageprovider_plugin_updated'] = 'Plugin update available';
$string['noaisummary'] = 'No AI summary available.';
$string['nopluginswatched'] = 'You are not watching any plugins yet.';
$string['notification_body'] = 'New version {$a->release} of {$a->name} ({$a->component}) is available. {$a->summary} View in Plugin Directory: {$a->link}';
$string['notification_subject'] = 'Plugin update: {$a->name} {$a->release}';
$string['plugin'] = 'Plugin';
$string['pluginname'] = 'Plugin Monitor';
$string['pluginsearch'] = 'Search plugins';
$string['pluginsearch_placeholder'] = 'Plugin name or component (e.g. block_xp)';
$string['preferences_heading'] = 'Plugin Monitor — Preferences';
$string['privacy:metadata:github_api'] = 'Plugin component and repository information is sent to the GitHub API to retrieve release notes. No personal user data is transmitted.';
$string['privacy:metadata:local_plugwatch_items'] = 'Stores the list of plugins the user chose to monitor.';
$string['privacy:metadata:local_plugwatch_items:component'] = 'The Frankenstyle component name of the watched plugin.';
$string['privacy:metadata:local_plugwatch_items:timecreated'] = 'The time the plugin was added to the watch list.';
$string['privacy:metadata:local_plugwatch_items:userid'] = 'The ID of the user who added the plugin.';
$string['privacy:metadata:local_plugwatch_state'] = 'Stores the last known version and notification timestamp for each watched plugin.';
$string['privacy:metadata:local_plugwatch_state:component'] = 'The Frankenstyle component name of the watched plugin.';
$string['privacy:metadata:local_plugwatch_state:releasename'] = 'The last known release string of the plugin.';
$string['privacy:metadata:local_plugwatch_state:timechecked'] = 'The last time the plugin version was checked.';
$string['privacy:metadata:local_plugwatch_state:timelastnotified'] = 'The last time a notification was sent about this plugin.';
$string['privacy:metadata:local_plugwatch_state:timelastreleased'] = 'The timestamp of the last known release.';
$string['privacy:metadata:local_plugwatch_state:userid'] = 'The ID of the user who is watching the plugin.';
$string['privacy:metadata:moodle_plugin_directory'] = 'Plugin component names are sent to the Moodle Plugin Directory API to retrieve version metadata. No personal user data is transmitted.';
$string['privacy_items_purpose'] = 'Stores the list of plugins the user chose to monitor.';
$string['privacy_state_purpose'] = 'Stores the last known version and notification timestamp for each watched plugin.';
$string['removeplugin'] = 'Remove';
$string['searchnoresults'] = 'No plugins found matching your search.';
$string['settings_enabled'] = 'Enable Plugin Watch';
$string['settings_enabled_help'] = 'When disabled, no checks are performed and no notifications are sent.';
$string['task_check_updates'] = 'Check plugin updates';
$string['watchedplugins'] = 'Watched plugins';
$string['watchedplugins_count'] = 'Watching {$a->current} of {$a->max} plugins';
$string['releasenotes'] = 'Release notes';
