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
 * Tests for the AI summarizer facade.
 *
 * Uses doc-comment annotations (not PHP attributes) for PHPUnit cross-version
 * compatibility with Moodle 4.5 (PHPUnit 9) and 5.x (PHPUnit 10/11).
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \local_plugwatch\ai\summarizer
 */

namespace local_plugwatch\ai;

use advanced_testcase;
use ReflectionMethod;

/**
 * Tests for the summarizer class.
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \local_plugwatch\ai\summarizer
 */
final class summarizer_test extends advanced_testcase {
    /**
     * Reset the database before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Invokes the private sanitize_summary() helper via reflection.
     *
     * @param string $text The raw text to sanitize.
     * @return string
     */
    private function sanitize(string $text): string {
        $method = new ReflectionMethod(summarizer::class, 'sanitize_summary');
        $method->setAccessible(true);

        return $method->invoke(null, $text);
    }

    /**
     * A short, already-clean summary is returned unchanged (after trimming).
     *
     * @covers ::sanitize_summary
     */
    public function test_sanitize_summary_trims_short_text(): void {
        $this->assertSame('Fixes a score-reset bug.', $this->sanitize('  Fixes a score-reset bug.  '));
    }

    /**
     * A blank (whitespace-only) summary is normalised to an empty string.
     *
     * @covers ::sanitize_summary
     */
    public function test_sanitize_summary_returns_empty_for_blank_input(): void {
        $this->assertSame('', $this->sanitize("   \n\t  "));
    }

    /**
     * A summary longer than the maximum is truncated with an ellipsis.
     *
     * @covers ::sanitize_summary
     */
    public function test_sanitize_summary_truncates_overly_long_text(): void {
        $long = str_repeat('a', 2500);

        $result = $this->sanitize($long);

        $this->assertSame(2001, \core_text::strlen($result));
        $this->assertStringEndsWith('…', $result);
    }

    /**
     * With neither local_aihub nor a configured core_ai provider, the fallback string is returned.
     *
     * @covers ::summarize_release_notes
     */
    public function test_no_ai_provider_returns_fallback_string(): void {
        $user = $this->getDataGenerator()->create_user();

        $summary = summarizer::summarize_release_notes(
            'XP block',
            'v2.5.1',
            'Some release notes.',
            'en',
            $user->id
        );

        $this->assertSame(get_string('noaisummary', 'local_plugwatch'), $summary);
    }
}
