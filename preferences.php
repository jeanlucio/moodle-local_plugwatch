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
 * Entry point for the preferences page.
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/plugwatch:use', $context);

$url = new moodle_url('/local/plugwatch/preferences.php');
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('preferences_heading', 'local_plugwatch'));
$PAGE->set_heading(get_string('preferences_heading', 'local_plugwatch'));

// Handle form submission for frequency and new-plugins digest opt-in.
if ($action = optional_param('action', '', PARAM_ALPHANUM)) {
    require_sesskey();
    if ($action === 'setfrequency') {
        $frequency = required_param('frequency', PARAM_INT);
        set_user_preference('local_plugwatch_frequency', $frequency, $USER);

        $notifynewplugins = optional_param('notifynewplugins', 0, PARAM_BOOL);
        $wasenabled = (bool) get_user_preferences('local_plugwatch_notifynewplugins', false, $USER);
        if ($notifynewplugins) {
            if (!$wasenabled) {
                // Silent baseline: opting in now must not flood the user with
                // every plugin published in the directory since forever.
                set_user_preference('local_plugwatch_lastdigest', time(), $USER);
            }
            set_user_preference('local_plugwatch_notifynewplugins', 1, $USER);
        } else {
            unset_user_preference('local_plugwatch_notifynewplugins', $USER);
        }

        redirect($url, get_string('changessaved', 'core'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

echo $OUTPUT->header();

$renderable = new \local_plugwatch\output\preferences_page((int) $USER->id);
$renderer = $PAGE->get_renderer('local_plugwatch');
echo $renderer->render($renderable);

$PAGE->requires->js_call_amd('local_plugwatch/preferences', 'init', ['.path-local-plugwatch']);

echo $OUTPUT->footer();
