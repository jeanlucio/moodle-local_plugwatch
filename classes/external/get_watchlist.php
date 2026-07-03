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
 * Web service: get_watchlist.
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_plugwatch\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_plugwatch\local\watchlist_manager;

/**
 * Returns the authenticated user's watch list, joined with current state data.
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_watchlist extends external_api {
    /**
     * Describes the parameters — this WS takes no input.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Returns the watch list for the current user.
     *
     * @return array
     */
    public static function execute(): array {
        global $USER;

        self::validate_context(\context_system::instance());
        require_capability('local/plugwatch:use', \context_system::instance());

        $rows = watchlist_manager::get_watchlist((int) $USER->id);
        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'component'        => $row->component,
                'timecreated'      => (int) $row->timecreated,
                'timelastreleased' => (int) ($row->timelastreleased ?? 0),
                'releasename'      => (string) ($row->releasename ?? ''),
                'timelastnotified' => (int) ($row->timelastnotified ?? 0),
                'timechecked'      => (int) ($row->timechecked ?? 0),
            ];
        }
        return $result;
    }

    /**
     * Describes the return value.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'component'        => new external_value(PARAM_COMPONENT, 'Frankenstyle component name.'),
                'timecreated'      => new external_value(PARAM_INT, 'Unix timestamp when the plugin was added.'),
                'timelastreleased' => new external_value(PARAM_INT, 'Unix timestamp of the last known release.'),
                'releasename'      => new external_value(PARAM_TEXT, 'Last known release string.'),
                'timelastnotified' => new external_value(PARAM_INT, 'Unix timestamp of the last notification.'),
                'timechecked'      => new external_value(PARAM_INT, 'Unix timestamp of the last version check.'),
            ])
        );
    }
}
