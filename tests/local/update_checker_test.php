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
 * Tests for the update checker engine.
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_plugwatch\local;

use advanced_testcase;

/**
 * Tests for the update checker engine.
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_plugwatch\local\update_checker
 */
class update_checker_test extends advanced_testcase {

    /**
     * Test the API version extraction method.
     */
    public function test_get_latest_version_from_api() {
        $this->resetAfterTest();

        // Use reflection to test the private method.
        $method = new \ReflectionMethod(update_checker::class, 'get_latest_version_from_api');
        $method->setAccessible(true);

        $apidata = [
            'versions' => [
                ['timecreated' => 1000, 'release' => '1.0'],
                ['timecreated' => 3000, 'release' => '3.0'],
                ['timecreated' => 2000, 'release' => '2.0'],
            ]
        ];

        $latest = $method->invoke(null, $apidata);
        $this->assertEquals('3.0', $latest['release']);
    }
}
