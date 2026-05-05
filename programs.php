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
 * Programs page — lists all programs (categories matching *-semN) for a semester.
 *
 * @package    local_timetable_management
 * @copyright  2026 Af1ah
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_timetable_management\manager;

$semester = required_param('semester', PARAM_INT);

if (!manager::is_valid_semester($semester)) {
    throw new moodle_exception('invalidsemester', 'local_timetable_management');
}

admin_externalpage_setup('local_timetable_management');

$context = context_system::instance();
require_capability('local/timetable_management:manage', $context);

$PAGE->set_url(new moodle_url('/local/timetable_management/programs.php', ['semester' => $semester]));

$semestername = manager::get_semester_name($semester);
$manageurl = new moodle_url('/local/timetable_management/manage.php');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('programsinsemester', 'local_timetable_management', $semestername));

$programs = manager::get_semester_programs($semester);

$output = $PAGE->get_renderer('local_timetable_management');
echo $output->render(new \local_timetable_management\output\programs_page($semester, $programs, $manageurl));
echo $OUTPUT->footer();
