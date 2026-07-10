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
 * New plugins digest scanner.
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_plugwatch\local;

use local_plugwatch\api\plugindirectory;

/**
 * Detects newly published plugins in the directory and e-mails a consolidated
 * digest to every user who opted in, at their configured frequency.
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class new_plugin_scanner {
    /** @var string Name of the user preference storing the opt-in flag. */
    private const PREF_NOTIFY = 'local_plugwatch_notifynewplugins';

    /** @var string Name of the user preference storing the last digest timestamp. */
    private const PREF_LASTDIGEST = 'local_plugwatch_lastdigest';

    /**
     * Executes the new-plugins scan and, unless this run performed the initial
     * global baseline, sends digests to every eligible user who is due one.
     *
     * @return void
     */
    public static function execute(): void {
        if (!get_config('local_plugwatch', 'enabled')) {
            return;
        }

        $pluglist = plugindirectory::get_pluglist();
        if (empty($pluglist)) {
            return;
        }

        $wasbaseline = self::record_new_plugins($pluglist);
        if ($wasbaseline) {
            // First-ever scan: the whole directory was just recorded as "already
            // known", so nobody should be notified about any of it.
            return;
        }

        self::send_digests();
    }

    /**
     * Inserts a row for every component in the pluglist not yet present in the log.
     *
     * @param array $pluglist Plugin data keyed by component, as returned by plugindirectory::get_pluglist().
     * @return bool True when the log was empty before this call (the global silent baseline).
     */
    private static function record_new_plugins(array $pluglist): bool {
        global $DB;

        $existingcomponents = $DB->get_records_menu('local_plugwatch_newplugins', null, '', 'component, id');
        $wasbaseline = empty($existingcomponents);
        $now = time();

        foreach ($pluglist as $component => $apidata) {
            if (array_key_exists($component, $existingcomponents)) {
                continue;
            }

            $DB->insert_record('local_plugwatch_newplugins', (object) [
                'component'        => $component,
                'name'             => (string) ($apidata['name'] ?? $component),
                'timelastreleased' => (int) ($apidata['timelastreleased'] ?? 0),
                'timediscovered'   => $now,
            ]);
        }

        return $wasbaseline;
    }

    /**
     * Sends the consolidated digest to every opted-in user who is due one.
     *
     * Loads the eligible users and the candidate new-plugin rows in two bulk
     * queries, then decides per user (in memory) whether they are due and what
     * to include, instead of issuing one query per user.
     *
     * @return void
     */
    private static function send_digests(): void {
        global $DB;

        $sql = "SELECT u.id AS userid, u.lang,
                       preflastdigest.value AS lastdigest,
                       preffreq.value AS frequency
                  FROM {user} u
                  JOIN {user_preferences} prefnotify ON prefnotify.userid = u.id
                       AND prefnotify.name = :notifyname AND prefnotify.value = '1'
             LEFT JOIN {user_preferences} preflastdigest ON preflastdigest.userid = u.id
                       AND preflastdigest.name = :lastdigestname
             LEFT JOIN {user_preferences} preffreq ON preffreq.userid = u.id
                       AND preffreq.name = :freqname
                 WHERE u.deleted = 0 AND u.suspended = 0";

        $users = $DB->get_records_sql($sql, [
            'notifyname'     => self::PREF_NOTIFY,
            'lastdigestname' => self::PREF_LASTDIGEST,
            'freqname'       => 'local_plugwatch_frequency',
        ]);

        if (empty($users)) {
            return;
        }

        $now = time();
        $mindigest = $now;
        foreach ($users as $user) {
            $lastdigest = $user->lastdigest !== null ? (int) $user->lastdigest : $now;
            $mindigest = min($mindigest, $lastdigest);
        }

        $candidates = $DB->get_records_select(
            'local_plugwatch_newplugins',
            'timediscovered > :mindigest',
            ['mindigest' => $mindigest],
            'timediscovered ASC'
        );
        if (empty($candidates)) {
            return;
        }

        $dueuserids = [];
        $perusernewplugins = [];
        foreach ($users as $user) {
            $lastdigest = $user->lastdigest !== null ? (int) $user->lastdigest : $now;
            $freq = $user->frequency !== null ? (int) $user->frequency : update_checker::FREQ_WEEKLY;

            if (($now - $lastdigest) < $freq) {
                continue;
            }

            $userplugins = array_values(array_filter(
                $candidates,
                fn ($plugin): bool => (int) $plugin->timediscovered > $lastdigest
            ));
            if (empty($userplugins)) {
                continue;
            }

            $dueuserids[] = (int) $user->userid;
            $perusernewplugins[(int) $user->userid] = $userplugins;
        }

        if (empty($dueuserids)) {
            return;
        }

        $fullusers = $DB->get_records_list('user', 'id', $dueuserids);

        foreach ($dueuserids as $userid) {
            $user = $fullusers[$userid] ?? null;
            if (!$user) {
                continue;
            }

            self::send_digest_to_user($user, $perusernewplugins[$userid]);
            set_user_preference(self::PREF_LASTDIGEST, $now, $userid);
        }
    }

    /**
     * Sends a single consolidated digest message to one user.
     *
     * @param \stdClass $user The already-loaded recipient user record.
     * @param array $newplugins List of local_plugwatch_newplugins rows to include.
     * @return void
     */
    private static function send_digest_to_user(\stdClass $user, array $newplugins): void {
        global $OUTPUT;

        $count = count($newplugins);
        $plugindata = array_map(static fn ($plugin): array => [
            'name'         => $plugin->name,
            'component'    => $plugin->component,
            'directoryurl' => 'https://moodle.org/plugins/' . $plugin->component,
        ], $newplugins);

        $subject = get_string('newplugins_digest_subject', 'local_plugwatch', $count);
        $introtext = get_string('newplugins_digest_intro', 'local_plugwatch', $count);

        $fullmessagehtml = $OUTPUT->render_from_template('local_plugwatch/new_plugins_digest', [
            'count'     => $count,
            'introtext' => $introtext,
            'plugins'   => $plugindata,
        ]);

        $fullmessagelines = array_map(
            static fn (array $plugin): string => "- {$plugin['name']} ({$plugin['component']}): {$plugin['directoryurl']}",
            $plugindata
        );
        $fullmessage = $introtext . "\n\n" . implode("\n", $fullmessagelines);

        $message = new \core\message\message();
        $message->component         = 'local_plugwatch';
        $message->name              = 'new_plugins_digest';
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
