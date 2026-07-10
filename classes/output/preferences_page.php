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
 * Output renderable for the preferences page.
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_plugwatch\output;

use renderable;
use templatable;
use renderer_base;
use local_plugwatch\local\watchlist_manager;

/**
 * Preferences page renderable.
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class preferences_page implements renderable, templatable {
    /** @var int User ID */
    private int $userid;

    /** @var int Selected frequency */
    private int $frequency;

    /**
     * Constructor.
     *
     * @param int $userid The user ID.
     * @param int|null $frequency The selected frequency (optional, fallbacks to user preference).
     */
    public function __construct(int $userid, ?int $frequency = null) {
        $this->userid = $userid;
        if ($frequency !== null) {
            $this->frequency = $frequency;
        } else {
            $this->frequency = (int) get_user_preferences('local_plugwatch_frequency', 604800, $userid);
        }
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output The renderer.
     * @return array Context array.
     */
    public function export_for_template(renderer_base $output): array {
        global $CFG;

        $watchlist = watchlist_manager::get_watchlist($this->userid);
        $maxplugins = (int) get_config('local_plugwatch', 'maxplugins');

        $plugins = [];
        foreach ($watchlist as $item) {
            $plugins[] = [
                'component' => $item->component,
                'releasename' => $item->releasename ?? '',
                'timelastnotified' => $item->timelastnotified ? userdate($item->timelastnotified) : '',
                'timechecked' => $item->timechecked ? userdate($item->timechecked) : '',
            ];
        }

        $frequencies = [
            86400 => get_string('frequency_daily', 'local_plugwatch'),
            604800 => get_string('frequency_weekly', 'local_plugwatch'),
            2592000 => get_string('frequency_monthly', 'local_plugwatch'),
        ];

        $frequencyoptions = [];
        foreach ($frequencies as $val => $label) {
            $frequencyoptions[] = [
                'value' => $val,
                'label' => $label,
                'selected' => ($this->frequency === $val),
            ];
        }

        return [
            'sesskey' => sesskey(),
            'maxplugins' => $maxplugins,
            'currentcount' => count($plugins),
            'frequency' => $this->frequency,
            'frequencyoptions' => $frequencyoptions,
            'hasplugins' => !empty($plugins),
            'plugins' => $plugins,
            'notificationprefsurl' => new \moodle_url('/message/notificationpreferences.php'),
        ];
    }
}
