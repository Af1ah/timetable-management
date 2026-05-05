<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade script for local_timetable_management.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_timetable_management_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026050400) {
        $table = new xmldb_table('local_timetable_cfg');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('departmentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('sessioncount', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '5');
            $table->add_field('daystartmins', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL, null, '570');
            $table->add_field('sessionlength', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '60');
            $table->add_field('breakafter', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '3');
            $table->add_field('breaklength', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '60');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('departmentid', XMLDB_KEY_FOREIGN_UNIQUE, ['departmentid'], 'course_categories', ['id']);

            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_timetable_slot');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('departmentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('semcategoryid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('weekday', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('sessionindex', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('departmentid', XMLDB_KEY_FOREIGN, ['departmentid'], 'course_categories', ['id']);
            $table->add_key('semcategoryid', XMLDB_KEY_FOREIGN, ['semcategoryid'], 'course_categories', ['id']);
            $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
            $table->add_index('slotuniq', XMLDB_INDEX_UNIQUE, ['departmentid', 'semcategoryid', 'weekday', 'sessionindex']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026050400, 'local', 'timetable_management');
    }

    if ($oldversion < 2026050401) {
        $table = new xmldb_table('local_timetable_cfg');
        $field = new xmldb_field('workingdays', XMLDB_TYPE_TEXT, null, null, null, null, null, 'breaklength');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('sectiontimes', XMLDB_TYPE_TEXT, null, null, null, null, null, 'workingdays');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026050401, 'local', 'timetable_management');
    }

    if ($oldversion < 2026050402) {
        $table = new xmldb_table('local_timetable_globalcfg');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('sessioncount', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '5');
            $table->add_field('daystartmins', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL, null, '570');
            $table->add_field('sessionlength', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '60');
            $table->add_field('breakafter', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '3');
            $table->add_field('breaklength', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '60');
            $table->add_field('workingdays', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('sectiontimes', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $dbman->create_table($table);
        }

        if (!$DB->record_exists('local_timetable_globalcfg', [])) {
            $source = $DB->get_record('local_timetable_cfg', [], '*', IGNORE_MULTIPLE);
            $now = time();
            $record = (object) [
                'sessioncount' => $source ? $source->sessioncount : 5,
                'daystartmins' => $source ? $source->daystartmins : 570,
                'sessionlength' => $source ? $source->sessionlength : 60,
                'breakafter' => $source ? $source->breakafter : 3,
                'breaklength' => $source ? $source->breaklength : 60,
                'workingdays' => $source ? $source->workingdays : null,
                'sectiontimes' => $source ? $source->sectiontimes : null,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $DB->insert_record('local_timetable_globalcfg', $record);
        }

        upgrade_plugin_savepoint(true, 2026050402, 'local', 'timetable_management');
    }

    if ($oldversion < 2026050403) {
        $table = new xmldb_table('local_timetable_master_slot');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('semester', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, null);
            $table->add_field('weekday', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('sessionindex', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('coursetype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('masterslotuniq', XMLDB_INDEX_UNIQUE, ['semester', 'weekday', 'sessionindex']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026050403, 'local', 'timetable_management');
    }

    if ($oldversion < 2026050405) {
        $table = new xmldb_table('local_timetable_master_excl');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('semester', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, null);
            $table->add_field('departmentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('departmentid', XMLDB_KEY_FOREIGN, ['departmentid'], 'course_categories', ['id']);
            $table->add_index('semesterdeptuniq', XMLDB_INDEX_UNIQUE, ['semester', 'departmentid']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026050405, 'local', 'timetable_management');
    }

    return true;
}
