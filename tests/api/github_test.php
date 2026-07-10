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
 * Tests for the GitHub API client.
 *
 * Only the pure, deterministic logic (header building and changelog section
 * extraction) is exercised here — the HTTP-calling methods are not covered,
 * since local_plugwatch\api\github has no injectable HTTP client to mock
 * without a larger refactor.
 *
 * Uses doc-comment annotations (not PHP attributes) for PHPUnit cross-version
 * compatibility with Moodle 4.5 (PHPUnit 9) and 5.x (PHPUnit 10/11).
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \local_plugwatch\api\github
 */

namespace local_plugwatch\api;

use advanced_testcase;
use ReflectionMethod;

/**
 * Tests for the github class.
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \local_plugwatch\api\github
 */
final class github_test extends advanced_testcase {
    /**
     * Reset the database before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Invokes a private static method via reflection.
     *
     * @param string $method Method name to invoke.
     * @param array $args Positional arguments to pass.
     * @return mixed
     */
    private function invoke_private(string $method, array $args) {
        $reflection = new ReflectionMethod(github::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs(null, $args);
    }

    /**
     * Without a configured token, the headers must not include an Authorization line.
     *
     * @covers ::build_headers
     */
    public function test_build_headers_without_token(): void {
        unset_config('githubtoken', 'local_plugwatch');

        $headers = $this->invoke_private('build_headers', []);

        $this->assertContains('Accept: application/vnd.github.v3+json', $headers);
        foreach ($headers as $header) {
            $this->assertStringNotContainsString('Authorization:', $header);
        }
    }

    /**
     * With a configured token, the headers must include the Authorization line.
     *
     * @covers ::build_headers
     */
    public function test_build_headers_with_token(): void {
        set_config('githubtoken', 'abc123', 'local_plugwatch');

        $headers = $this->invoke_private('build_headers', []);

        $this->assertContains('Authorization: token abc123', $headers);
    }

    /**
     * With two version headings, only the first section is extracted.
     *
     * @covers ::extract_top_section
     */
    public function test_extract_top_section_with_two_headings(): void {
        $content = "## 2.6.0 - 2026-01-01\nAdds a leaderboard widget.\n\n" .
            "## 2.5.1 - 2025-06-01\nFixes a score-reset bug.\n";

        $result = $this->invoke_private('extract_top_section', [$content]);

        $this->assertStringContainsString('Adds a leaderboard widget.', $result);
        $this->assertStringNotContainsString('Fixes a score-reset bug.', $result);
    }

    /**
     * A lone top-level title (e.g. '# Changelog') must not be mistaken for a
     * version heading — the section boundaries must follow the repeated
     * '##' version-heading level instead.
     *
     * @covers ::extract_top_section
     */
    public function test_extract_top_section_ignores_lone_title_heading(): void {
        $content = "# Changelog\n\n## 2.6.0 - 2026-01-01\nAdds a leaderboard widget.\n\n" .
            "## 2.5.1 - 2025-06-01\nFixes a score-reset bug.\n";

        $result = $this->invoke_private('extract_top_section', [$content]);

        $this->assertStringNotContainsString('# Changelog', $result);
        $this->assertStringContainsString('Adds a leaderboard widget.', $result);
        $this->assertStringNotContainsString('Fixes a score-reset bug.', $result);
    }

    /**
     * With a single heading, everything from that heading onward is returned.
     *
     * @covers ::extract_top_section
     */
    public function test_extract_top_section_with_one_heading(): void {
        $content = "Some preamble text.\n\n## 1.0.0 - 2026-01-01\nInitial release.\n";

        $result = $this->invoke_private('extract_top_section', [$content]);

        $this->assertStringNotContainsString('Some preamble text.', $result);
        $this->assertStringContainsString('Initial release.', $result);
    }

    /**
     * With no heading at all, the whole trimmed content is returned.
     *
     * @covers ::extract_top_section
     */
    public function test_extract_top_section_with_no_heading(): void {
        $content = "  Just plain release notes, no markdown headings.  ";

        $result = $this->invoke_private('extract_top_section', [$content]);

        $this->assertSame('Just plain release notes, no markdown headings.', $result);
    }
}
