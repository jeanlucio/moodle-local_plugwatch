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
 * Web service: remove_plugin.
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_plugwatch\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_plugwatch\local\watchlist_manager;

/**
 * Removes a plugin from the authenticated user's watch list.
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class remove_plugin extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'component' => new external_value(PARAM_COMPONENT, 'Frankenstyle component name to remove from the watch list.'),
        ]);
    }

    /**
     * Removes the plugin from the current user's watch list.
     *
     * @param string $component Frankenstyle component name.
     * @return array
     */
    public static function execute(string $component): array {
        global $USER;

        ['component' => $component] = self::validate_parameters(
            self::execute_parameters(),
            ['component' => $component]
        );

        self::validate_context(\context_system::instance());
        require_capability('local/plugwatch:use', \context_system::instance());

        try {
            watchlist_manager::remove_plugin((int) $USER->id, $component);
        } catch (\moodle_exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }

        return ['success' => true, 'message' => ''];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the plugin was removed successfully.'),
            'message' => new external_value(PARAM_TEXT, 'Error message if success is false, empty otherwise.'),
        ]);
    }
}
