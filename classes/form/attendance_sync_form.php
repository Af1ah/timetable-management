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

/**
 * Form for generating attendance sessions from the timetable.
 *
 * @package    local_timetable_management
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attendance_sync_form extends \moodleform {
    /**
     * Define the form.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;

        $mform->addElement('header', 'attendanceheader',
            get_string('generateattendancesessions', 'local_timetable_management'));
        $mform->addElement('static', 'attendancehelp', '',
            get_string('generateattendancesessions_help', 'local_timetable_management'));

        $mform->addElement('date_selector', 'startdate',
            get_string('attendancestartdate', 'local_timetable_management'));
        $mform->setDefault('startdate', usergetmidnight(time()));

        $mform->addElement('text', 'durationdays',
            get_string('attendancedurationdays', 'local_timetable_management'),
            ['type' => 'number', 'min' => 1, 'step' => 1, 'size' => 5]);
        $mform->setType('durationdays', PARAM_INT);
        $mform->setDefault('durationdays', 7);
        $mform->addRule('durationdays', null, 'required', null, 'client');
        $mform->addRule('durationdays', null, 'numeric', null, 'client');

        $this->add_action_buttons(true, get_string('generateattendancesessions', 'local_timetable_management'));
    }

    /**
     * Validate submitted data.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        if ((int) ($data['durationdays'] ?? 0) < 1) {
            $errors['durationdays'] = get_string('attendancedurationdaysinvalid', 'local_timetable_management');
        }

        return $errors;
    }
}
