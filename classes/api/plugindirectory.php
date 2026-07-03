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
 * Plugin Directory API client.
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_plugwatch\api;

use curl;

/**
 * Client for the Moodle Plugin Directory API.
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

    /** @var string The API endpoint for the full plugin list. */
    private const PLUGLIST_URL = 'https://download.moodle.org/api/1.3/pluglist.php';

    /** @var array|null In-memory cache of the plugin list. */
    private static ?array $pluglist = null;

    /**
     * Fetches the full plugin list from the Plugin Directory API.
     *
     * Caches the result in memory for the duration of the request so that
     * multiple lookups during a task execution do not trigger multiple HTTP requests.
     *
     * @param bool $force If true, ignores the in-memory cache and fetches again.
     * @return array The associative array of plugins, keyed by component name.
     */
    public static function get_pluglist(bool $force = false): array {
        global $CFG;

        if (self::$pluglist !== null && !$force) {
            return self::$pluglist;
        }

        require_once($CFG->libdir . '/filelib.php');
        $curl = new curl();
        
        // Use a reasonable timeout (10 seconds) for fetching the ~3MB JSON.
        $options = [
            'CURLOPT_TIMEOUT' => 10,
            'CURLOPT_CONNECTTIMEOUT' => 5,
        ];
        
        $response = $curl->get(self::PLUGLIST_URL, null, $options);
        $info = $curl->get_info();

        if (empty($info['http_code']) || $info['http_code'] >= 400 || empty($response)) {
            // API is down or returned an error. Return an empty list so the task
            // doesn't crash, it will just not find any updates this time.
            self::$pluglist = [];
            return self::$pluglist;
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($decoded['plugins'])) {
            self::$pluglist = [];
            return self::$pluglist;
        }

        $mapped = [];
        foreach ($decoded['plugins'] as $plugin) {
            if (!empty($plugin['component'])) {
                $mapped[$plugin['component']] = $plugin;
            }
        }

        self::$pluglist = $mapped;
        return self::$pluglist;
    }

    /**
     * Looks up a specific plugin by its component name.
     *
     * @param string $component The Frankenstyle component name (e.g., 'block_xp').
     * @return array|null The plugin data array, or null if not found.
     */
    public static function get_plugin_info(string $component): ?array {
        $pluglist = self::get_pluglist();
        return $pluglist[$component] ?? null;
    }
}
