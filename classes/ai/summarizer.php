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
 * AI Summarizer facade.
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_plugwatch\ai;

use core\di;
use core_ai\aiactions\generate_text;

/**
 * Facade that chains AI providers to summarize plugin release notes.
 *
 * Chain order:
 * 1. local_aihub (if installed and configured)
 * 2. core_ai (if any provider is configured)
 * 3. Text fallback (no AI summary)
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class summarizer {
    /** @var int Maximum accepted length, in characters, for an AI-generated summary. */
    private const MAX_SUMMARY_LENGTH = 2000;

    /**
     * Generates a short summary of the release notes in the requested language.
     *
     * @param string $pluginname Display name of the plugin (e.g. 'XP block').
     * @param string $version The release version string (e.g. 'v2.5.1').
     * @param string $releasenotes Raw release notes from GitHub.
     * @param string $lang The language code to output the summary (e.g. 'pt_br').
     * @param int $userid The user ID requesting the summary (for local_aihub personal keys).
     * @return string The generated summary, or a fallback message if no AI is available.
     */
    public static function summarize_release_notes(
        string $pluginname,
        string $version,
        string $releasenotes,
        string $lang,
        int $userid
    ): string {
        $system = "You are a technical assistant for Moodle administrators. Summarize the following " .
            "plugin release notes in 2-3 sentences. Focus on what changed (new features, bug fixes). " .
            "Do not include greetings. Respond in the language identified by the Moodle code " .
            "'{$lang}' (e.g., 'pt_br' = Brazilian Portuguese, 'en' = English, 'es' = Spanish). " .
            "If unsure of the language code, use English.";
        $prompt = "Plugin: {$pluginname}\nVersion: {$version}\n\nRelease notes:\n" . substr($releasenotes, 0, 4000);

        // 1. Try local_aihub first.
        // Caught broadly: a provider outage (network, quota, misconfiguration) must fall
        // through to the next provider in the chain, never break the whole update check.
        if (class_exists('\local_aihub\ai')) {
            try {
                $result = \local_aihub\ai::generate_text(
                    $system,
                    $prompt,
                    false,
                    'local_plugwatch',
                    "Summary for {$pluginname} {$version}",
                    $userid
                );
                if (!empty($result['success']) && !empty($result['data'])) {
                    $summary = self::sanitize_summary((string) $result['data']);
                    if ($summary !== '') {
                        return $summary;
                    }
                }
            } catch (\Throwable $e) {
                debugging('local_plugwatch: local_aihub summary generation failed: ' . $e->getMessage(), DEBUG_NORMAL);
            }
        }

        // 2. Try core_ai subsystem.
        // Requires Moodle 4.5+ (ensured by version.php).
        try {
            $manager = di::get(\core_ai\manager::class);
            $action = new generate_text(
                contextid: \context_system::instance()->id,
                userid: $userid,
                prompttext: $system . "\n\n" . $prompt
            );
            $response = $manager->process_action($action);
            if ($response && $response->get_success() && $response->get_response_data()) {
                $data = $response->get_response_data();
                if (!empty($data['generatedcontent'])) {
                    $summary = self::sanitize_summary((string) $data['generatedcontent']);
                    if ($summary !== '') {
                        return $summary;
                    }
                }
            }
        } catch (\Throwable $e) {
            debugging('local_plugwatch: core_ai summary generation failed: ' . $e->getMessage(), DEBUG_NORMAL);
        }

        // 3. Fallback (no AI available).
        return get_string('noaisummary', 'local_plugwatch');
    }

    /**
     * Validates and normalises an AI-generated summary before it is used.
     *
     * AI output is untrusted input: it must never be assumed non-empty or
     * bounded in size. Truncates overly long responses instead of discarding
     * them outright, since a truncated summary is still useful to the reader.
     *
     * @param string $text Raw text returned by an AI provider.
     * @return string The trimmed, length-capped summary, or '' if it was empty.
     */
    private static function sanitize_summary(string $text): string {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        if (\core_text::strlen($text) > self::MAX_SUMMARY_LENGTH) {
            $text = \core_text::substr($text, 0, self::MAX_SUMMARY_LENGTH) . '…';
        }

        return $text;
    }
}
