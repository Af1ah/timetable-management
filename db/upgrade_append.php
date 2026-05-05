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
