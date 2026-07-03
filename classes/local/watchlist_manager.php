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
 * Watch list CRUD manager for local_plugwatch.
 *
 * Handles adding, removing and retrieving user plugin watch list entries,
 * and initialises the silent baseline state record on first add.
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_plugwatch\local;

/**
 * Manages the personal plugin watch list for each user.
 *
 * All public methods are static to keep the API simple for task and
 * web-service callers. The default per-user limit is read from the
 * plugin config setting local_plugwatch/maxplugins (default 30).
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class watchlist_manager {
    /** @var string Table that stores the user's watch list entries. */
    private const TABLE_ITEMS = 'local_plugwatch_items';

    /** @var string Table that stores the version/notification state per entry. */
    private const TABLE_STATE = 'local_plugwatch_state';

    /** @var int Default maximum number of plugins a user may watch. */
    private const DEFAULT_LIMIT = 30;

    /**
     * Returns the configured maximum number of plugins per user.
     *
     * Reads from the admin setting local_plugwatch/maxplugins.
     * Falls back to DEFAULT_LIMIT when the setting is absent or zero.
     *
     * @return int
     */
    public static function get_limit(): int {
        $configured = (int) get_config('local_plugwatch', 'maxplugins');
        return $configured > 0 ? $configured : self::DEFAULT_LIMIT;
    }

    /**
     * Returns the number of plugins the user is currently watching.
     *
     * @param int $userid Target user ID.
     * @return int
     */
    public static function count_plugins(int $userid): int {
        global $DB;
        return (int) $DB->count_records(self::TABLE_ITEMS, ['userid' => $userid]);
    }

    /**
     * Returns true when the user is already watching the given component.
     *
     * @param int $userid Target user ID.
     * @param string $component Frankenstyle component name (e.g. block_xp).
     * @return bool
     */
    public static function has_plugin(int $userid, string $component): bool {
        global $DB;
        return $DB->record_exists(self::TABLE_ITEMS, ['userid' => $userid, 'component' => $component]);
    }

    /**
     * Adds a plugin to the user's watch list.
     *
     * Creates both the item record and the silent baseline state record so
     * that the first update check does not trigger a notification for the
     * version that was already available at the time of adding.
     *
     * Throws moodle_exception when the user has already reached the limit.
     * Does nothing (idempotent) when the plugin is already in the list.
     *
     * @param int $userid Target user ID.
     * @param string $component Frankenstyle component name (e.g. block_xp).
     * @param int $timelastreleased Current timelastreleased from the Plugin Directory (baseline).
     * @param string $release Human-readable release string used as baseline (e.g. "v2.5.1").
     * @return void
     * @throws \moodle_exception When the per-user plugin limit has been reached.
     */
    public static function add_plugin(
        int $userid,
        string $component,
        int $timelastreleased = 0,
        string $release = ''
    ): void {
        global $DB;

        if (self::has_plugin($userid, $component)) {
            return;
        }

        $limit = self::get_limit();
        if (self::count_plugins($userid) >= $limit) {
            throw new \moodle_exception('errorlimitreached', 'local_plugwatch', '', $limit);
        }

        $now = time();

        $item = (object) [
            'userid'       => $userid,
            'component'    => $component,
            'timecreated'  => $now,
            'timemodified' => $now,
        ];
        $DB->insert_record(self::TABLE_ITEMS, $item);

        // Insert the baseline state record so the first task run does not
        // re-notify the version that was already present when the user added the plugin.
        $state = (object) [
            'userid'           => $userid,
            'component'        => $component,
            'timelastreleased' => $timelastreleased,
            'release'          => $release !== '' ? $release : null,
            'timelastnotified' => 0,
            'timechecked'      => 0,
            'timemodified'     => $now,
        ];
        $DB->insert_record(self::TABLE_STATE, $state);
    }

    /**
     * Removes a plugin from the user's watch list and deletes its state record.
     *
     * Does nothing when the plugin is not in the list.
     *
     * @param int $userid Target user ID.
     * @param string $component Frankenstyle component name (e.g. block_xp).
     * @return void
     */
    public static function remove_plugin(int $userid, string $component): void {
        global $DB;
        $DB->delete_records(self::TABLE_ITEMS, ['userid' => $userid, 'component' => $component]);
        $DB->delete_records(self::TABLE_STATE, ['userid' => $userid, 'component' => $component]);
    }

    /**
     * Returns the user's watch list joined with the current state for each entry.
     *
     * Each element is a plain object with fields: id, userid, component,
     * timecreated, timelastreleased, release, timelastnotified, timechecked.
     * Fields from the state table are null when no state row exists yet.
     *
     * @param int $userid Target user ID.
     * @return array Array of stdClass rows, ordered by component ascending.
     */
    public static function get_watchlist(int $userid): array {
        global $DB;

        $sql = "SELECT i.id, i.userid, i.component, i.timecreated,
                       s.timelastreleased, s.release, s.timelastnotified, s.timechecked
                  FROM {local_plugwatch_items} i
             LEFT JOIN {local_plugwatch_state} s ON s.userid = i.userid AND s.component = i.component
                 WHERE i.userid = :userid
              ORDER BY i.component ASC";

        return array_values($DB->get_records_sql($sql, ['userid' => $userid]));
    }

    /**
     * Updates the state record for a user-plugin pair after a successful check.
     *
     * Creates the state row when it does not yet exist (defensive fallback;
     * add_plugin should always create it, but this protects against orphaned items).
     *
     * @param int $userid Target user ID.
     * @param string $component Frankenstyle component name.
     * @param int $timelastreleased New timelastreleased from the Plugin Directory.
     * @param string $release New human-readable release string.
     * @param int $timelastnotified Timestamp of the notification just sent (0 = no notification sent).
     * @return void
     */
    public static function update_state(
        int $userid,
        string $component,
        int $timelastreleased,
        string $release,
        int $timelastnotified = 0
    ): void {
        global $DB;

        $now = time();
        $existing = $DB->get_record(
            self::TABLE_STATE,
            ['userid' => $userid, 'component' => $component]
        );

        if ($existing) {
            $existing->timelastreleased = $timelastreleased;
            $existing->release          = $release !== '' ? $release : null;
            $existing->timechecked      = $now;
            $existing->timemodified     = $now;
            if ($timelastnotified > 0) {
                $existing->timelastnotified = $timelastnotified;
            }
            $DB->update_record(self::TABLE_STATE, $existing);
        } else {
            $state = (object) [
                'userid'           => $userid,
                'component'        => $component,
                'timelastreleased' => $timelastreleased,
                'release'          => $release !== '' ? $release : null,
                'timelastnotified' => $timelastnotified,
                'timechecked'      => $now,
                'timemodified'     => $now,
            ];
            $DB->insert_record(self::TABLE_STATE, $state);
        }
    }
}
