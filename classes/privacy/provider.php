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
 * Privacy Subsystem implementation for local_plugwatch.
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_plugwatch\privacy;

use context;
use context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\plugin\provider as plugin_provider;
use core_privacy\local\request\transform;
use core_privacy\local\request\user_preference_provider;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for local_plugwatch.
 *
 * All personal data lives at CONTEXT_SYSTEM: the user's plugin watch list
 * (local_plugwatch_items), the per-plugin notification state
 * (local_plugwatch_state), and the local_plugwatch_frequency user preference.
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    core_userlist_provider,
    plugin_provider,
    user_preference_provider {
    /**
     * Describes what personal data this plugin stores and where it is sent externally.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection The updated collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_plugwatch_items',
            [
                'userid'      => 'privacy:metadata:local_plugwatch_items:userid',
                'component'   => 'privacy:metadata:local_plugwatch_items:component',
                'timecreated' => 'privacy:metadata:local_plugwatch_items:timecreated',
            ],
            'privacy:metadata:local_plugwatch_items'
        );

        $collection->add_database_table(
            'local_plugwatch_state',
            [
                'userid'           => 'privacy:metadata:local_plugwatch_state:userid',
                'component'        => 'privacy:metadata:local_plugwatch_state:component',
                'releasename'      => 'privacy:metadata:local_plugwatch_state:releasename',
                'timelastreleased' => 'privacy:metadata:local_plugwatch_state:timelastreleased',
                'timelastnotified' => 'privacy:metadata:local_plugwatch_state:timelastnotified',
                'timechecked'      => 'privacy:metadata:local_plugwatch_state:timechecked',
            ],
            'privacy:metadata:local_plugwatch_state'
        );

        $collection->add_user_preference(
            'local_plugwatch_frequency',
            'privacy:metadata:preference:local_plugwatch_frequency'
        );

        $collection->add_external_location_link(
            'moodle_plugin_directory',
            ['component' => 'privacy:metadata:local_plugwatch_items:component'],
            'privacy:metadata:moodle_plugin_directory'
        );

        $collection->add_external_location_link(
            'github_api',
            ['component' => 'privacy:metadata:local_plugwatch_items:component'],
            'privacy:metadata:github_api'
        );

        $collection->add_external_location_link(
            'ai_provider',
            ['releasenotes' => 'privacy:metadata:ai_provider:releasenotes'],
            'privacy:metadata:ai_provider'
        );

        return $collection;
    }

    /**
     * Returns the list of contexts that contain personal data for the given user.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the contexts with user data.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                 WHERE ctx.contextlevel = :contextlevel
                   AND (
                       EXISTS (SELECT 1 FROM {local_plugwatch_items} i WHERE i.userid = :userid1)
                    OR EXISTS (SELECT 1 FROM {local_plugwatch_state} s WHERE s.userid = :userid2)
                   )";

        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_SYSTEM,
            'userid1'      => $userid,
            'userid2'      => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Adds the userids of all users with data in the given context to the userlist.
     *
     * @param userlist $userlist The userlist to add the users to.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof context_system) {
            return;
        }

        $sql = "SELECT userid FROM {local_plugwatch_items}
                 UNION
                SELECT userid FROM {local_plugwatch_state}";
        $userlist->add_from_sql('userid', $sql, []);
    }

    /**
     * Exports all personal data for the approved contexts for the given user.
     *
     * @param approved_contextlist $contextlist The approved contexts to export data for.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $hassystemcontext = false;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_system) {
                $hassystemcontext = true;
                break;
            }
        }
        if (!$hassystemcontext) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        $systemcontext = context_system::instance();

        $items = $DB->get_records('local_plugwatch_items', ['userid' => $userid], 'component ASC');
        $itemsdata = [];
        foreach ($items as $item) {
            $itemsdata[] = (object) [
                'component'   => $item->component,
                'timecreated' => transform::datetime($item->timecreated),
            ];
        }
        if (!empty($itemsdata)) {
            writer::with_context($systemcontext)->export_data(
                [get_string('privacy:metadata:local_plugwatch_items', 'local_plugwatch')],
                (object) ['items' => $itemsdata]
            );
        }

        $states = $DB->get_records('local_plugwatch_state', ['userid' => $userid], 'component ASC');
        $statesdata = [];
        foreach ($states as $state) {
            $statesdata[] = (object) [
                'component'        => $state->component,
                'releasename'      => $state->releasename,
                'timelastreleased' => $state->timelastreleased ? transform::datetime($state->timelastreleased) : null,
                'timelastnotified' => $state->timelastnotified ? transform::datetime($state->timelastnotified) : null,
                'timechecked'      => $state->timechecked ? transform::datetime($state->timechecked) : null,
            ];
        }
        if (!empty($statesdata)) {
            writer::with_context($systemcontext)->export_data(
                [get_string('privacy:metadata:local_plugwatch_state', 'local_plugwatch')],
                (object) ['state' => $statesdata]
            );
        }
    }

    /**
     * Deletes all personal data for all users in the given context.
     *
     * @param context $context The specific context to delete data for.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if (!$context instanceof context_system) {
            return;
        }

        $DB->delete_records('local_plugwatch_items');
        $DB->delete_records('local_plugwatch_state');
    }

    /**
     * Deletes all personal data for the approved contexts for the given user.
     *
     * @param approved_contextlist $contextlist The approved contexts to delete data for.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_system) {
                $DB->delete_records('local_plugwatch_items', ['userid' => $userid]);
                $DB->delete_records('local_plugwatch_state', ['userid' => $userid]);
            }
        }
    }

    /**
     * Deletes personal data for the given users in the given context.
     *
     * @param approved_userlist $userlist The approved context and userids to delete data for.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof context_system) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('local_plugwatch_items', "userid {$insql}", $inparams);
        $DB->delete_records_select('local_plugwatch_state', "userid {$insql}", $inparams);
    }

    /**
     * Exports the user preferences related to local_plugwatch.
     *
     * @param int $userid The user whose preferences should be exported.
     * @return void
     */
    public static function export_user_preferences(int $userid): void {
        $frequency = get_user_preferences('local_plugwatch_frequency', null, $userid);
        if ($frequency !== null) {
            writer::export_user_preference(
                'local_plugwatch',
                'local_plugwatch_frequency',
                $frequency,
                get_string('privacy:metadata:preference:local_plugwatch_frequency', 'local_plugwatch')
            );
        }
    }
}
