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
 * GitHub API client.
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_plugwatch\api;

use curl;

/**
 * Client for the GitHub API to fetch release notes.
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class github {
    /**
     * Attempts to fetch the latest release notes from a GitHub repository URL.
     *
     * @param string $repositoryurl The URL of the repository (e.g. https://github.com/moodle/moodle)
     * @return string|null The release notes body, or null if not found or on error.
     */
    public static function get_latest_release_notes(string $repositoryurl): ?string {
        global $CFG;

        // Extract owner and repo from URL.
        if (!preg_match('#^https?://github\.com/([^/]+)/([^/]+)#i', $repositoryurl, $matches)) {
            return null;
        }

        $owner = $matches[1];
        // Remove .git suffix if present.
        $repo = preg_replace('/\.git$/i', '', $matches[2]);

        require_once($CFG->libdir . '/filelib.php');
        $curl = new curl();
        $options = [
            'CURLOPT_TIMEOUT' => 5,
            'CURLOPT_CONNECTTIMEOUT' => 3,
            'CURLOPT_HTTPHEADER' => [
                'Accept: application/vnd.github.v3+json',
                'User-Agent: Moodle-local_plugwatch',
            ],
        ];

        // If admin configured a token, use it to avoid strict rate limits.
        $token = get_config('local_plugwatch', 'githubtoken');
        if (!empty($token)) {
            $options['CURLOPT_HTTPHEADER'][] = 'Authorization: token ' . $token;
        }

        $url = "https://api.github.com/repos/{$owner}/{$repo}/releases/latest";
        $response = $curl->get($url, null, $options);
        $info = $curl->get_info();

        if (empty($info['http_code']) || $info['http_code'] !== 200 || empty($response)) {
            return null;
        }

        $decoded = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE && !empty($decoded['body'])) {
            return $decoded['body'];
        }

        return null;
    }
}
