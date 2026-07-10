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
 * Upgrade steps for local_plugwatch.
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Performs plugin database upgrades.
 *
 * @param int $oldversion Previously installed plugin version.
 * @return bool
 */
function xmldb_local_plugwatch_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026070301) {
        $table = new xmldb_table('local_plugwatch_state');
        $field = new xmldb_field('release', XMLDB_TYPE_CHAR, '100', null, null);

        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'releasename');
        }

        upgrade_plugin_savepoint(true, 2026070301, 'local', 'plugwatch');
    }

    if ($oldversion < 2026071000) {
        $table = new xmldb_table('local_plugwatch_newplugins');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('component', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
            $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
            $table->add_field('timelastreleased', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timediscovered', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

            $table->add_index('uq_component', XMLDB_INDEX_UNIQUE, ['component']);
            $table->add_index('ix_timediscovered', XMLDB_INDEX_NOTUNIQUE, ['timediscovered']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026071000, 'local', 'plugwatch');
    }

    return true;
}
