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

/**
 * Main management page for semesters.
 *
 * @package    local_semester_management
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_semester_management\manager;

$action = optional_param('action', '', PARAM_ALPHA);
$semester = optional_param('semester', 0, PARAM_INT);

admin_externalpage_setup('local_semester_management');

$context = context_system::instance();
require_capability('local/semester_management:manage', $context);

$baseurl = new moodle_url('/local/semester_management/manage.php');
$PAGE->set_url($baseurl);

// Handle enable/disable actions.
if ($action === 'enable' && $semester && confirm_sesskey()) {
    manager::enable_semester($semester);
    redirect($baseurl, get_string('semesterenabled', 'local_semester_management', $semester),
        null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($action === 'disable' && $semester && confirm_sesskey()) {
    manager::disable_semester($semester);
    redirect($baseurl, get_string('semesterdisabled', 'local_semester_management', $semester),
        null, \core\output\notification::NOTIFY_WARNING);
}

// Display main page.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managesemesters', 'local_semester_management'));

// Build semester table.
$table = new html_table();
$table->head = [
    get_string('semester', 'local_semester_management'),
    get_string('status', 'local_semester_management'),
    get_string('courses', 'local_semester_management'),
    get_string('actions', 'local_semester_management'),
];
$table->attributes['class'] = 'generaltable';

$statuses = manager::get_all_semester_statuses();

for ($i = 1; $i <= manager::TOTAL_SEMESTERS; $i++) {
    $status = $statuses[$i];
    $coursecount = manager::count_semester_courses($i);

    // Status badge.
    if ($status->enabled) {
        $statushtml = html_writer::span(get_string('enabled', 'local_semester_management'), 'badge bg-success');
    } else {
        $statushtml = html_writer::span(get_string('disabled', 'local_semester_management'), 'badge bg-danger');
    }

    // Course count with link.
    $coursecounthtml = html_writer::link(
        new moodle_url('/local/semester_management/courses.php', ['semester' => $i]),
        get_string('coursecount', 'local_semester_management', $coursecount)
    );

    // Actions.
    $actions = [];

    // Enable/Disable toggle.
    if ($status->enabled) {
        $actions[] = html_writer::link(
            new moodle_url($baseurl, ['action' => 'disable', 'semester' => $i, 'sesskey' => sesskey()]),
            get_string('disable', 'local_semester_management'),
            ['class' => 'btn btn-sm btn-warning']
        );
    } else {
        $actions[] = html_writer::link(
            new moodle_url($baseurl, ['action' => 'enable', 'semester' => $i, 'sesskey' => sesskey()]),
            get_string('enable', 'local_semester_management'),
            ['class' => 'btn btn-sm btn-success']
        );
    }

    // View/Manage courses.
    $actions[] = html_writer::link(
        new moodle_url('/local/semester_management/courses.php', ['semester' => $i]),
        get_string('manage', 'local_semester_management'),
        ['class' => 'btn btn-sm btn-primary']
    );

    $table->data[] = [
        manager::get_semester_name($i),
        $statushtml,
        $coursecounthtml,
        implode(' ', $actions),
    ];
}

echo html_writer::table($table);

echo $OUTPUT->footer();
