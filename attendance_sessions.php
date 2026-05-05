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

require_once(__DIR__ . '/../../config.php');

use local_timetable_management\manager;
use local_timetable_management\form\attendance_sync_form;

/**
 * Build the attendance sync result message.
 *
 * @param object $summary
 * @return string
 */
function local_timetable_management_build_attendance_message(object $summary): string {
    $parts = [];

    $parts[] = get_string('attendancesummarycreated', 'local_timetable_management', (object) [
        'sessions' => (int) $summary->sessionscreated,
        'courses' => (int) $summary->coursesprocessed,
    ]);

    if (!empty($summary->attendancecreated)) {
        $parts[] = get_string('attendancesummaryinstances', 'local_timetable_management',
            (int) $summary->attendancecreated);
    }

    if (!empty($summary->duplicateskipped)) {
        $parts[] = get_string('attendancesummaryduplicates', 'local_timetable_management',
            (int) $summary->duplicateskipped);
    }

    if (!empty($summary->errors)) {
        $parts[] = get_string('attendancesummaryerrors', 'local_timetable_management', implode(' ', $summary->errors));
    }

    return implode(' ', $parts);
}

$departmentid = required_param('departmentid', PARAM_INT);

require_login();

$context = context_system::instance();
$canmanage = has_capability('local/timetable_management:manage', $context);

$department = $DB->get_record('course_categories', ['id' => $departmentid], '*', MUST_EXIST);
$admissiondepartment = manager::get_admission_department_for_category($departmentid);
$ishod = $admissiondepartment
    && !empty($admissiondepartment->enabled)
    && (int) ($admissiondepartment->hodid ?? 0) === (int) $USER->id;

if (!$canmanage && !$ishod) {
    throw new required_capability_exception($context, 'local/timetable_management:manage', 'nopermissions', '');
}

if (!manager::is_attendance_plugin_available()) {
    throw new moodle_exception('attendancepluginmissing', 'local_timetable_management');
}

$baseurl = new moodle_url('/local/timetable_management/attendance_sessions.php', ['departmentid' => $departmentid]);
$backurl = new moodle_url('/local/timetable_management/department_timetable.php', ['departmentid' => $departmentid]);

$PAGE->set_url($baseurl);
$PAGE->set_context($context);
$PAGE->set_title(get_string('generateattendancesessionsfor', 'local_timetable_management', format_string($department->name)));
$PAGE->set_heading(format_string($department->name));
$PAGE->set_pagelayout('standard');

$mform = new attendance_sync_form($baseurl);

if ($mform->is_cancelled()) {
    redirect($backurl);
}

if ($data = $mform->get_data()) {
    $summary = manager::sync_department_attendance_sessions(
        $departmentid,
        (int) $data->startdate,
        (int) $data->durationdays
    );

    $messagetype = \core\output\notification::NOTIFY_INFO;
    if (!empty($summary->errors) || ((int) $summary->sessionscreated === 0 && (int) $summary->duplicateskipped === 0)) {
        $messagetype = \core\output\notification::NOTIFY_WARNING;
    }
    if ((int) $summary->sessionscreated > 0) {
        $messagetype = \core\output\notification::NOTIFY_SUCCESS;
    }

    redirect($backurl, local_timetable_management_build_attendance_message($summary), null, $messagetype);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('generateattendancesessionsfor', 'local_timetable_management',
    format_string($department->name)));
echo $OUTPUT->notification(get_string('generateattendancesessions_help', 'local_timetable_management'), 'info');
$mform->display();
echo $OUTPUT->footer();
