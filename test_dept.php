<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
$sql = "SELECT DISTINCT parent.id, parent.name
          FROM {course_categories} semcat
          JOIN {course_categories} parent ON parent.id = semcat.parent
         WHERE " . $DB->sql_like('semcat.name', ':pattern', false) . "
      ORDER BY parent.name";
$records = $DB->get_records_sql($sql, ['pattern' => '%-sem1']);
print_r($records);
