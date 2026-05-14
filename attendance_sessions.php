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
use local_timetable_management\task\sync_attendance_sessions;

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
    $weeksahead = max(0, (int) ($data->weeksahead ?? 0));

    // Compute the window for the immediate task: today → this Saturday (inclusive).
    $todaymidnight   = usergetmidnight(time());
    $thissatmidnight = manager::get_next_saturday();
    $daystilsat      = (int) round(($thissatmidnight - $todaymidnight) / DAYSECS);
    $firstduration   = max(1, $daystilsat + 1);

    // Task 0 — runs on the next cron tick, covers the current week up to this Saturday.
    $task = new sync_attendance_sessions();
    $task->set_custom_data([
        'departmentid' => $departmentid,
        'startdate'    => $todaymidnight,
        'durationdays' => $firstduration,
    ]);
    \core\task\manager::queue_adhoc_task($task, true);

    // Tasks 1..N — each scheduled to execute on its own Saturday morning, covering that
    // Saturday + the 6 following days (a clean, non-overlapping 7-day window per week).
    $nextsatmidnight = $thissatmidnight + WEEKSECS;
    for ($week = 1; $week <= $weeksahead; $week++) {
        $satstart = $nextsatmidnight + (($week - 1) * WEEKSECS);
        $weektask = new sync_attendance_sessions();
        $weektask->set_custom_data([
            'departmentid' => $departmentid,
            'startdate'    => $satstart,
            'durationdays' => 7,
        ]);
        $weektask->set_next_run_time($satstart); // cron will not run this before its Saturday.
        \core\task\manager::queue_adhoc_task($weektask, true);
    }

    $taskcount = 1 + $weeksahead;
    redirect(
        $backurl,
        get_string('attendancetasksqueued', 'local_timetable_management', $taskcount),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('generateattendancesessionsfor', 'local_timetable_management',
    format_string($department->name)));
$mform->display();
echo $OUTPUT->footer();
