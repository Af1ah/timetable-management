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
require_once($CFG->libdir . '/adminlib.php');

use local_timetable_management\manager;

$semester = optional_param('semester', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

admin_externalpage_setup('local_timetable_management_timetable');

$context = context_system::instance();
require_capability('local/timetable_management:manage', $context);

$baseurl = new moodle_url('/local/timetable_management/college_master_timetable.php');
$PAGE->set_url($baseurl, ['semester' => $semester]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('collegemastertimetable', 'local_timetable_management'));
$PAGE->set_heading(get_string('collegemastertimetable', 'local_timetable_management'));
$PAGE->set_pagelayout('admin');
$PAGE->set_other_editing_capability('local/timetable_management:manage');

$activatesemesters = manager::get_active_semesters();
$config = manager::get_global_timetable_config();
$workingdays = $config->workingdays;
$timetabledepartments = manager::get_timetable_departments();

if ($semester && !isset($activatesemesters[$semester])) {
    throw new moodle_exception('invalidsemester', 'local_timetable_management');
}

if (!$semester && $action === 'saveexcludeddepartments' && confirm_sesskey()) {
    $selecteddepartments = manager::clean_nested_param($_POST['departments'] ?? [], PARAM_INT);
    $alldepartmentids = array_map(static fn(object $department): int => (int) $department->id, $timetabledepartments);

    foreach (array_keys($activatesemesters) as $activesemester) {
        $selectedforsemester = $selecteddepartments[$activesemester] ?? [];
        $selectedforsemester = is_array($selectedforsemester)
            ? array_values(array_unique(array_map('intval', $selectedforsemester)))
            : [];
        $excludedforsemester = array_values(array_diff($alldepartmentids, $selectedforsemester));
        manager::set_excluded_departments((int) $activesemester, $excludedforsemester);
    }

    redirect(
        $baseurl,
        get_string('mastertimetablesaved', 'local_timetable_management'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

if ($semester && $action === 'savemastertable' && confirm_sesskey()) {
    // Course type keys include digits (for example, minor1 and minor2).
    $types = manager::clean_nested_param($_POST['coursetype'] ?? [], PARAM_ALPHANUMEXT);
    $excludeddepartments = manager::clean_nested_param($_POST['excludeddepartments'] ?? [], PARAM_INT);
    $entries = [];

    foreach ($workingdays as $dayoffset => $unuseddaylabel) {
        $weekday = $dayoffset + 1;
        for ($sessionindex = 1; $sessionindex <= $config->sessioncount; $sessionindex++) {
            $entries[] = [
                'weekday' => $weekday,
                'sessionindex' => $sessionindex,
                'coursetype' => $types[$weekday][$sessionindex] ?? '',
            ];
        }
    }

    manager::save_master_timetable_slots($semester, $entries);
    manager::set_excluded_departments($semester, is_array($excludeddepartments) ? $excludeddepartments : []);
    redirect(
        new moodle_url($baseurl, ['semester' => $semester]),
        get_string('mastertimetablesaved', 'local_timetable_management'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$isediting = $PAGE->user_is_editing();
$masterslots = $semester ? manager::get_master_timetable_slots_for_semester($semester) : [];
$coursetooltips = $semester ? manager::get_master_course_tooltips_by_semester($semester) : [];
$excludeddepartments = $semester ? manager::get_excluded_department_records($semester) : [];

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('collegemastertimetable', 'local_timetable_management'));

$output = $PAGE->get_renderer('local_timetable_management');
echo $output->render(new \local_timetable_management\output\college_master_page(
    $activatesemesters,
    $config,
    $baseurl,
    $semester,
    $masterslots,
    $coursetooltips,
    $isediting,
    $excludeddepartments,
    $timetabledepartments
));
echo $OUTPUT->footer();
