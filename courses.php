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
 *
 * @package    local_semester_management
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_semester_management\manager;

$semester = required_param('semester', PARAM_INT);
$add = optional_param_array('addselect', [], PARAM_INT);
$remove = optional_param_array('removeselect', [], PARAM_INT);

if (!manager::is_valid_semester($semester)) {
    throw new moodle_exception('invalidsemester', 'local_semester_management');
}

admin_externalpage_setup('local_semester_management');

$context = context_system::instance();
require_capability('local/semester_management:manage', $context);

$baseurl = new moodle_url('/local/semester_management/courses.php', ['semester' => $semester]);
$manageurl = new moodle_url('/local/semester_management/manage.php');
$PAGE->set_url($baseurl);

// Handle add action.
if (!empty($add) && confirm_sesskey()) {
    foreach ($add as $courseid) {
        manager::set_course_semester($courseid, $semester);
    }
    redirect($baseurl, get_string('courseassigned', 'local_semester_management'),
        null, \core\output\notification::NOTIFY_SUCCESS);
}

// Handle remove action.
if (!empty($remove) && confirm_sesskey()) {
    foreach ($remove as $courseid) {
        manager::remove_course_semester($courseid);
    }
    redirect($baseurl, get_string('courseunassigned', 'local_semester_management'),
        null, \core\output\notification::NOTIFY_SUCCESS);
}

// Get course data.
$assignedcourses = manager::get_semester_courses($semester);
$availablecourses = manager::get_available_courses();

// Display page.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('coursesinsemester', 'local_semester_management', $semester));

// Back button.
echo html_writer::div(
    html_writer::link($manageurl, get_string('back', 'local_semester_management'), ['class' => 'btn btn-secondary mb-3'])
);

// Show warning if semester is disabled.
$semesterstatus = manager::get_semester_status($semester);
if (!$semesterstatus->enabled) {
    echo $OUTPUT->notification(get_string('courseshidden', 'local_semester_management'), 'warning');
}

// Dual listbox form.
echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $baseurl->out(false),
    'id' => 'assignform',
    'class' => 'mform',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

echo html_writer::start_div('row');

// Available courses column.
echo html_writer::start_div('col-md-5');
echo html_writer::tag('h4', get_string('availablecourses', 'local_semester_management'));

// Search box for available.
echo html_writer::start_div('mb-2');
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'id' => 'search-available',
    'class' => 'form-control',
    'placeholder' => get_string('search'),
]);
echo html_writer::end_div();

// Available listbox.
echo html_writer::start_tag('select', [
    'name' => 'addselect[]',
    'id' => 'addselect',
    'multiple' => 'multiple',
    'size' => '15',
    'class' => 'form-control',
    'style' => 'width: 100%; height: 350px;',
]);

foreach ($availablecourses as $course) {
    $label = format_string($course->shortname) . ' - ' . format_string($course->fullname);
    echo html_writer::tag('option', $label, [
        'value' => $course->id,
        'data-shortname' => strtolower($course->shortname),
        'data-fullname' => strtolower($course->fullname),
    ]);
}

echo html_writer::end_tag('select');
echo html_writer::end_div();

// Buttons column.
echo html_writer::start_div('col-md-2 d-flex flex-column justify-content-center align-items-center');
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'name' => 'add',
    'value' => get_string('add') . ' →',
    'class' => 'btn btn-success mb-2',
    'style' => 'width: 100px;',
]);
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'name' => 'remove',
    'value' => '← ' . get_string('remove'),
    'class' => 'btn btn-danger',
    'style' => 'width: 100px;',
]);
echo html_writer::end_div();

// Assigned courses column.
echo html_writer::start_div('col-md-5');
echo html_writer::tag('h4', get_string('assignedcourses', 'local_semester_management'));

// Search box for assigned.
echo html_writer::start_div('mb-2');
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'id' => 'search-assigned',
    'class' => 'form-control',
    'placeholder' => get_string('search'),
]);
echo html_writer::end_div();

// Assigned listbox.
echo html_writer::start_tag('select', [
    'name' => 'removeselect[]',
    'id' => 'removeselect',
    'multiple' => 'multiple',
    'size' => '15',
    'class' => 'form-control',
    'style' => 'width: 100%; height: 350px;',
]);

foreach ($assignedcourses as $course) {
    $label = format_string($course->shortname) . ' - ' . format_string($course->fullname);
    echo html_writer::tag('option', $label, [
        'value' => $course->id,
        'data-shortname' => strtolower($course->shortname),
        'data-fullname' => strtolower($course->fullname),
    ]);
}

echo html_writer::end_tag('select');
echo html_writer::end_div();

echo html_writer::end_div(); // row
echo html_writer::end_tag('form');

// JavaScript for search filtering.
$js = <<<'JS'
document.addEventListener('DOMContentLoaded', function() {
    function filterSelect(searchInput, selectBox) {
        var filter = searchInput.value.toLowerCase();
        var options = selectBox.getElementsByTagName('option');

        for (var i = 0; i < options.length; i++) {
            var shortname = options[i].getAttribute('data-shortname') || '';
            var fullname = options[i].getAttribute('data-fullname') || '';
            var text = options[i].text.toLowerCase();

            if (shortname.indexOf(filter) > -1 || fullname.indexOf(filter) > -1 || text.indexOf(filter) > -1) {
                options[i].style.display = '';
            } else {
                options[i].style.display = 'none';
            }
        }
    }

    var searchAvailable = document.getElementById('search-available');
    var addSelect = document.getElementById('addselect');
    var searchAssigned = document.getElementById('search-assigned');
    var removeSelect = document.getElementById('removeselect');

    searchAvailable.addEventListener('keyup', function() {
        filterSelect(searchAvailable, addSelect);
    });

    searchAssigned.addEventListener('keyup', function() {
        filterSelect(searchAssigned, removeSelect);
    });
});
JS;

echo html_writer::script($js);

echo $OUTPUT->footer();
