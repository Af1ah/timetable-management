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
 * Manage courses in a semester using dual listbox.
 * When categoryid is provided, scope to that program category tree.
 *
 * @package    local_timetable_management
 * @copyright  2026 Af1ah
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_timetable_management\manager;

$semester   = required_param('semester', PARAM_INT);
$categoryid = optional_param('categoryid', 0, PARAM_INT);
$add        = optional_param_array('addselect', [], PARAM_INT);
$remove     = optional_param_array('removeselect', [], PARAM_INT);

if (!manager::is_valid_semester($semester)) {
    throw new moodle_exception('invalidsemester', 'local_timetable_management');
}

// Validate category when provided.
global $DB;
$category = null;
if ($categoryid) {
    $category = $DB->get_record('course_categories', ['id' => $categoryid]);
    if (!$category) {
        throw new moodle_exception('invalidcategory', 'error');
    }
}

admin_externalpage_setup('local_timetable_management');

$context = context_system::instance();
require_capability('local/timetable_management:manage', $context);

$urlparams = ['semester' => $semester];
if ($categoryid) {
    $urlparams['categoryid'] = $categoryid;
}
$baseurl = new moodle_url('/local/timetable_management/courses.php', $urlparams);
$PAGE->set_url($baseurl);

// Back button destination: programs list when inside a category, manage otherwise.
$backurl = $categoryid
    ? new moodle_url('/local/timetable_management/programs.php', ['semester' => $semester])
    : new moodle_url('/local/timetable_management/manage.php');

// Handle add action.
if (!empty($add) && confirm_sesskey()) {
    if ($category) {
        foreach ($add as $courseid) {
            manager::assign_course_to_category($courseid, $categoryid);
        }
    } else {
        foreach ($add as $courseid) {
            manager::set_course_semester($courseid, $semester);
        }
    }
    redirect($baseurl, get_string('courseassigned', 'local_timetable_management'),
        null, \core\output\notification::NOTIFY_SUCCESS);
}

// Handle remove action.
if (!empty($remove) && confirm_sesskey()) {
    if ($category) {
        foreach ($remove as $courseid) {
            manager::unassign_course_from_category($courseid, $categoryid);
        }
    } else {
        foreach ($remove as $courseid) {
            manager::remove_course_semester($courseid);
        }
    }
    redirect($baseurl, get_string('courseunassigned', 'local_timetable_management'),
        null, \core\output\notification::NOTIFY_SUCCESS);
}

// Load course data — scoped to category tree when categoryid provided.
if ($category) {
    $assignedcourses  = manager::get_semester_courses_in_category($semester, $categoryid, $category->path);
    $availablecourses = manager::get_available_courses_in_category($categoryid, $category->path);
} else {
    $assignedcourses  = manager::get_semester_courses($semester);
    $availablecourses = manager::get_available_courses();
}

// Heading.
$semestername = manager::get_semester_name($semester);
if ($category) {
    $heading = get_string('coursesincategory', 'local_timetable_management',
        (object)['semester' => $semestername, 'program' => format_string($category->name)]);
} else {
    $heading = get_string('coursesinsemester', 'local_timetable_management', $semester);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($heading);

$semesterstatus = manager::get_semester_status($semester);

$output = $PAGE->get_renderer('local_timetable_management');
echo $output->render(new \local_timetable_management\output\courses_page(
    $baseurl,
    $backurl,
    $availablecourses,
    $assignedcourses,
    !$semesterstatus->enabled
));

echo $OUTPUT->footer();
