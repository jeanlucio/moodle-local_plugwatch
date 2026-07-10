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
 * Scheduled task to check for plugin updates.
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_plugwatch\task;

use core\task\scheduled_task;
use local_plugwatch\local\new_plugin_scanner;
use local_plugwatch\local\update_checker;

/**
 * Scheduled task to check for plugin updates.
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class check_updates extends scheduled_task {
    /**
     * Get a descriptive name for this task.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_check_updates', 'local_plugwatch');
    }

    /**
     * Execute the task.
     *
     * Runs the personal watchlist check and the new-plugins digest scan in the
     * same execution: both call plugindirectory::get_pluglist() internally,
     * which is memoised for the duration of the request, so this does not
     * trigger a second HTTP call to the Plugin Directory API.
     *
     * @return void
     */
    public function execute(): void {
        update_checker::execute();
        new_plugin_scanner::execute();
    }
}
