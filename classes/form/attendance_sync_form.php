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

namespace local_timetable_management\form;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

use local_timetable_management\manager;

/**
 * Form for scheduling attendance session generation.
 *
 * On submit this creates:
 *   - One immediate ad hoc task covering today → this Saturday.
 *   - One ad hoc task per extra week, each scheduled to run on its Saturday.
 *
 * @package    local_timetable_management
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attendance_sync_form extends \moodleform {

    public function definition(): void {
        $mform = $this->_form;

        $thissatmidnight = manager::get_next_saturday();
        $todaymidnight   = usergetmidnight(time());
        $daystilsat      = (int) round(($thissatmidnight - $todaymidnight) / DAYSECS);
        $firstduration   = max(1, $daystilsat + 1); // today through Saturday inclusive.

        $mform->addElement('header', 'attendanceheader',
            get_string('generateattendancesessions', 'local_timetable_management'));

        // Explain what the immediate task will do.
        $immediatedesc = get_string(
            'attendanceimmediatetask',
            'local_timetable_management',
            (object) [
                'date'  => userdate($thissatmidnight, get_string('strftimedatefullshort')),
                'days'  => $firstduration,
            ]
        );
        $mform->addElement('static', 'immediateinfo', '', $immediatedesc);

        // Repeat: queue one additional task per extra Saturday.
        $weekoptions = [0 => get_string('attendanceweeksahead_none', 'local_timetable_management')];
        for ($i = 1; $i <= 12; $i++) {
            $weekoptions[$i] = get_string('attendanceweeksahead_n', 'local_timetable_management', $i);
        }
        $mform->addElement('select', 'weeksahead',
            get_string('attendanceweeksahead', 'local_timetable_management'),
            $weekoptions);
        $mform->setDefault('weeksahead', 0);
        $mform->addHelpButton('weeksahead', 'attendanceweeksahead', 'local_timetable_management');

        $this->add_action_buttons(true, get_string('generateattendancesessions', 'local_timetable_management'));
    }

    public function validation($data, $files): array {
        return parent::validation($data, $files);
    }
}
