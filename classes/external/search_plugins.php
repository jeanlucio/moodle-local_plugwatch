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
 * Web service: search_plugins.
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
use local_plugwatch\api\plugindirectory;

/**
 * Searches for plugins in the Moodle Plugin Directory.
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_plugins extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'query' => new external_value(PARAM_TEXT, 'Search query (plugin name or component).'),
        ]);
    }

    /**
     * Searches for plugins in the directory.
     *
     * @param string $query The search query.
     * @return array List of matching plugins.
     */
    public static function execute(string $query): array {
        ['query' => $query] = self::validate_parameters(
            self::execute_parameters(),
            ['query' => $query]
        );

        self::validate_context(\context_system::instance());
        require_capability('local/plugwatch:use', \context_system::instance());

        $query = \core_text::strtolower(trim($query));
        if (empty($query)) {
            return [];
        }

        $pluglist = plugindirectory::get_pluglist();
        $results = [];

        foreach ($pluglist as $plugin) {
            $name = \core_text::strtolower($plugin['name'] ?? '');
            $component = \core_text::strtolower($plugin['component'] ?? '');

            if (str_contains($name, $query) || str_contains($component, $query)) {
                $results[] = [
                    'name' => $plugin['name'] ?? $plugin['component'],
                    'component' => $plugin['component'],
                ];
                if (count($results) >= 15) {
                    break;
                }
            }
        }

        return $results;
    }

    /**
     * Describes the return value.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'name' => new external_value(PARAM_TEXT, 'The human readable name of the plugin.'),
                'component' => new external_value(PARAM_COMPONENT, 'The Frankenstyle component name.'),
            ])
        );
    }
}
