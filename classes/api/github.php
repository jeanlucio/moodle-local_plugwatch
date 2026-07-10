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
 * Tries the GitHub Releases API first. When a repository has no releases
 * published (only tags, or a changelog file convention instead), falls back
 * to reading CHANGES.md, then CHANGELOG.md, via the GitHub Contents API.
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class github {
    /** @var string[] Changelog file names to try, in order, when there is no GitHub release. */
    private const CHANGELOG_FILENAMES = ['CHANGES.md', 'CHANGELOG.md'];

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
        $headers = self::build_headers();

        $notes = self::get_release_notes_from_api($owner, $repo, $headers);
        if ($notes !== null) {
            return $notes;
        }

        foreach (self::CHANGELOG_FILENAMES as $filename) {
            $content = self::get_file_contents($owner, $repo, $filename, $headers);
            if ($content !== null) {
                return self::extract_top_section($content);
            }
        }

        return null;
    }

    /**
     * Builds the HTTP headers shared by every GitHub API request, including
     * the optional admin-configured token to raise the rate limit.
     *
     * @return string[] Headers ready for CURLOPT_HTTPHEADER.
     */
    private static function build_headers(): array {
        $headers = [
            'Accept: application/vnd.github.v3+json',
            'User-Agent: Moodle-local_plugwatch',
        ];

        $token = get_config('local_plugwatch', 'githubtoken');
        if (!empty($token)) {
            $headers[] = 'Authorization: token ' . $token;
        }

        return $headers;
    }

    /**
     * Fetches the body of the latest published release via the Releases API.
     *
     * @param string $owner Repository owner (user or organisation).
     * @param string $repo Repository name.
     * @param string[] $headers HTTP headers built by build_headers().
     * @return string|null The release body, or null if there is no release or on error.
     */
    private static function get_release_notes_from_api(string $owner, string $repo, array $headers): ?string {
        $curl = new curl();
        $options = [
            'CURLOPT_TIMEOUT' => 5,
            'CURLOPT_CONNECTTIMEOUT' => 3,
            'CURLOPT_HTTPHEADER' => $headers,
        ];

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

    /**
     * Fetches and decodes a file's contents from the repository via the Contents API.
     *
     * @param string $owner Repository owner (user or organisation).
     * @param string $repo Repository name.
     * @param string $path Path of the file within the repository (e.g. 'CHANGES.md').
     * @param string[] $headers HTTP headers built by build_headers().
     * @return string|null The decoded file contents, or null if not found or on error.
     */
    private static function get_file_contents(string $owner, string $repo, string $path, array $headers): ?string {
        $curl = new curl();
        $options = [
            'CURLOPT_TIMEOUT' => 5,
            'CURLOPT_CONNECTTIMEOUT' => 3,
            'CURLOPT_HTTPHEADER' => $headers,
        ];

        $url = "https://api.github.com/repos/{$owner}/{$repo}/contents/{$path}";
        $response = $curl->get($url, null, $options);
        $info = $curl->get_info();

        if (empty($info['http_code']) || $info['http_code'] !== 200 || empty($response)) {
            return null;
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($decoded['content'])) {
            return null;
        }

        if (($decoded['encoding'] ?? '') !== 'base64') {
            return null;
        }

        $content = base64_decode($decoded['content']);
        if ($content === false || $content === '') {
            return null;
        }

        return $content;
    }

    /**
     * Extracts only the most recent version's section from a changelog file.
     *
     * Looks for Markdown heading lines (e.g. '## [2.5.1] - 2026-01-01') and
     * returns everything from the first version heading up to (but not
     * including) the second one. Headings are grouped by level first and the
     * most frequently repeated level is treated as "the version heading
     * level" — this avoids mistaking a lone top-level title (e.g.
     * '# Changelog' followed by '## x.y.z' entries) for the first version
     * section. Files with zero or one heading are returned in full, since
     * there is nothing to trim.
     *
     * @param string $content Raw changelog file contents.
     * @return string The top section of the changelog, trimmed.
     */
    private static function extract_top_section(string $content): string {
        $lines = preg_split('/\r\n|\r|\n/', $content);

        $headingsbylevel = [];
        foreach ($lines as $index => $line) {
            if (preg_match('/^(#{1,6})\s/', $line, $matches)) {
                $headingsbylevel[strlen($matches[1])][] = $index;
            }
        }

        if (empty($headingsbylevel)) {
            return trim($content);
        }

        $bestlevel = null;
        $bestcount = 0;
        foreach ($headingsbylevel as $level => $indexes) {
            if (count($indexes) > $bestcount) {
                $bestcount = count($indexes);
                $bestlevel = $level;
            }
        }

        $headingindexes = $headingsbylevel[$bestlevel];
        $start = $headingindexes[0];
        $end = $headingindexes[1] ?? count($lines);

        return trim(implode("\n", array_slice($lines, $start, $end - $start)));
    }
}
