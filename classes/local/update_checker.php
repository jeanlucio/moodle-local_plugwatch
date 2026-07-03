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
 * Update checker engine.
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_plugwatch\local;

use local_plugwatch\api\plugindirectory;
use local_plugwatch\api\github;
use local_plugwatch\ai\summarizer;

/**
 * Executes the core business logic of checking updates and notifying users.
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_checker {
    /** @var int Daily frequency in seconds. */
    public const FREQ_DAILY = 86400;

    /** @var int Weekly frequency in seconds (default). */
    public const FREQ_WEEKLY = 604800;

    /** @var int Monthly frequency in seconds. */
    public const FREQ_MONTHLY = 2592000;

    /**
     * Executes the update check process for all users.
     *
     * @return void
     */
    public static function execute(): void {
        global $DB;

        if (!get_config('local_plugwatch', 'enabled')) {
            return;
        }

        // Fetch the full list once.
        $pluglist = plugindirectory::get_pluglist();
        if (empty($pluglist)) {
            // API is down or empty, abort to avoid false positives or errors.
            return;
        }

        // Cache for GitHub release notes so we don't fetch the same repo multiple times.
        $notescache = [];

        // Join items, state and user to iterate.
        $sql = "SELECT i.id as itemid, i.userid, i.component,
                       s.timelastreleased as saved_timelastreleased,
                       s.releasename AS saved_release,
                       s.timelastnotified,
                       u.lang
                  FROM {local_plugwatch_items} i
                  JOIN {local_plugwatch_state} s ON s.userid = i.userid AND s.component = i.component
                  JOIN {user} u ON u.id = i.userid
                 WHERE u.deleted = 0 AND u.suspended = 0";

        $rs = $DB->get_recordset_sql($sql);

        foreach ($rs as $record) {
            $component = $record->component;
            if (!isset($pluglist[$component])) {
                continue;
            }

            $apidata = $pluglist[$component];
            $apitimelast = (int) $apidata['timelastreleased'];

            if ($apitimelast <= (int) $record->saved_timelastreleased) {
                // No update. Just touch timechecked.
                watchlist_manager::update_state(
                    (int) $record->userid,
                    $component,
                    (int) $record->saved_timelastreleased,
                    (string) $record->saved_release,
                    (int) $record->timelastnotified
                );
                continue;
            }

            // Update detected. Check frequency preference.
            $freq = (int) get_user_preferences('local_plugwatch_frequency', self::FREQ_WEEKLY, $record->userid);
            $now = time();
            if (($now - (int) $record->timelastnotified) < $freq) {
                // Too soon to notify again. Wait for the next cycle.
                // We don't update state yet, so we keep detecting it until it's time to notify.
                continue;
            }

            // Time to notify. Get latest release from the plugin directory API.
            $latestversion = self::get_latest_version_from_api($apidata);
            $release = $latestversion['release'] ?? 'Unknown';

            // Get release notes.
            $sourceurl = $apidata['source'] ?? '';
            $notes = '';
            if (!empty($sourceurl) && strpos($sourceurl, 'github.com') !== false) {
                if (!array_key_exists($sourceurl, $notescache)) {
                    $notescache[$sourceurl] = github::get_latest_release_notes($sourceurl) ?? '';
                }
                $notes = $notescache[$sourceurl];
            }

            // Summarize via AI.
            $lang = !empty($record->lang) ? $record->lang : 'en';
            $summary = summarizer::summarize_release_notes(
                $apidata['name'] ?? $component,
                $release,
                $notes,
                $lang,
                (int) $record->userid
            );

            // Send notification.
            self::send_notification((int) $record->userid, $apidata, $release, $summary);

            // Update state.
            watchlist_manager::update_state(
                (int) $record->userid,
                $component,
                $apitimelast,
                $release,
                $now
            );
        }

        $rs->close();
    }

    /**
     * Extracts the latest version object from the plugin data.
     *
     * @param array $apidata Plugin data array from the API.
     * @return array|null
     */
    private static function get_latest_version_from_api(array $apidata): ?array {
        if (empty($apidata['versions']) || !is_array($apidata['versions'])) {
            return null;
        }

        $latest = null;
        foreach ($apidata['versions'] as $v) {
            if ($latest === null || (isset($v['timecreated']) && $v['timecreated'] > $latest['timecreated'])) {
                $latest = $v;
            }
        }

        return $latest;
    }

    /**
     * Sends the notification via Message API.
     *
     * @param int $userid
     * @param array $apidata
     * @param string $release
     * @param string $summary
     * @return void
     */
    private static function send_notification(int $userid, array $apidata, string $release, string $summary): void {
        global $DB, $PAGE;

        $user = $DB->get_record('user', ['id' => $userid]);
        if (!$user) {
            return;
        }

        $pluginname = $apidata['name'] ?? $apidata['component'];
        $link = 'https://moodle.org/plugins/' . $apidata['component'];

        $a = new \stdClass();
        $a->name = $pluginname;
        $a->component = $apidata['component'];
        $a->release = $release;
        $a->summary = $summary;
        $a->link = $link;

        // Use standard capability checks just in case (cron executes as CLI).
        $subject = get_string('notification_subject', 'local_plugwatch', $a);
        // Render the mustache template for HTML body.
        global $OUTPUT;

        // We simulate the template data.
        $templatedata = [
            'name' => $pluginname,
            'component' => $apidata['component'],
            'release' => $release,
            'summaryhtml' => format_text($summary, FORMAT_HTML),
            'link' => $link,
        ];

        // For simplicity and fallback, we generate a text body too.
        $fullmessage = get_string('notification_body', 'local_plugwatch', $a);

        $fullmessagehtml = $OUTPUT->render_from_template('local_plugwatch/notification_message', $templatedata);

        $message = new \core\message\message();
        $message->component         = 'local_plugwatch';
        $message->name              = 'plugin_updated';
        $message->userfrom          = \core_user::get_noreply_user();
        $message->userto            = $user;
        $message->subject           = $subject;
        $message->fullmessage       = $fullmessage;
        $message->fullmessageformat = FORMAT_MARKDOWN;
        $message->fullmessagehtml   = $fullmessagehtml;
        $message->smallmessage      = $subject;
        $message->notification      = 1;

        message_send($message);
    }
}
