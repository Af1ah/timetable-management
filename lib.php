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
 * Library functions for local_timetable_management.
 *
 * @package    local_timetable_management
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extend course edit form with semester selection.
 *
 * @param MoodleQuickForm $mform
 * @param stdClass $course
 * @param mixed $context
 */
function local_timetable_management_course_edit_form_definition(MoodleQuickForm $mform, $course, $context) {
    $options = \local_timetable_management\manager::get_semester_options(true);

    $mform->addElement('header', 'local_semester_header', get_string('semester', 'local_timetable_management'));

    $mform->addElement('select', 'local_semester', get_string('selectsemester', 'local_timetable_management'), $options);
    $mform->addHelpButton('local_semester', 'selectsemester', 'local_timetable_management');

    // Set default value for existing course.
    if (!empty($course->id)) {
        $assignment = \local_timetable_management\manager::get_course_semester($course->id);
        if ($assignment) {
            $mform->setDefault('local_semester', $assignment->semester);
        }
    }
}

/**
 * Process course edit form data.
 *
 * @param stdClass $course
 * @param stdClass $data
 */
function local_timetable_management_course_edit_form_data(stdClass $course, stdClass $data) {
    if (isset($data->local_semester)) {
        if (!empty($data->local_semester)) {
            \local_timetable_management\manager::set_course_semester($course->id, (int)$data->local_semester);
        } else {
            // Remove semester assignment if "No semester" selected.
            \local_timetable_management\manager::remove_course_semester($course->id);
        }
    }
}
