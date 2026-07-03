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
 * Test data generator for local_plugwatch.
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_plugwatch\tests;

use local_plugwatch\local\watchlist_manager;

/**
 * Data generator helpers for local_plugwatch PHPUnit tests.
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plugwatch_generator {
    /**
     * Creates a watch list item and its baseline state record for the given user.
     *
     * @param int $userid User ID.
     * @param string $component Frankenstyle component (default: 'block_xp').
     * @param int $timelastreleased Baseline timestamp (default: current time).
     * @param string $release Baseline release string (default: 'v1.0.0').
     * @return void
     */
    public static function create_watch_item(
        int $userid,
        string $component = 'block_xp',
        int $timelastreleased = 0,
        string $release = 'v1.0.0'
    ): void {
        if ($timelastreleased === 0) {
            $timelastreleased = time();
        }
        watchlist_manager::add_plugin($userid, $component, $timelastreleased, $release);
    }

    /**
     * Directly inserts a state record bypassing add_plugin (for testing update_state paths).
     *
     * @param int $userid User ID.
     * @param string $component Frankenstyle component.
     * @param int $timelastreleased Timestamp.
     * @param string $release Release string.
     * @param int $timelastnotified Last notification timestamp.
     * @return int ID of the inserted record.
     */
    public static function create_state(
        int $userid,
        string $component,
        int $timelastreleased,
        string $release = 'v1.0.0',
        int $timelastnotified = 0
    ): int {
        global $DB;

        $now = time();
        return $DB->insert_record('local_plugwatch_state', (object) [
            'userid'           => $userid,
            'component'        => $component,
            'timelastreleased' => $timelastreleased,
            'releasename'      => $release,
            'timelastnotified' => $timelastnotified,
            'timechecked'      => $now,
            'timemodified'     => $now,
        ]);
    }
}
