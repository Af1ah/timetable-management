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
 * Admin settings for local_timetable_management.
 *
 * @package    local_timetable_management
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add(
        'courses',
        new admin_externalpage(
            'local_timetable_management',
            get_string('managesemesters', 'local_timetable_management'),
            new moodle_url('/local/timetable_management/manage.php'),
            'local/timetable_management:manage'
        )
    );

    $ADMIN->add(
        'courses',
        new admin_externalpage(
            'local_timetable_management_timetable',
            get_string('timetable', 'local_timetable_management'),
            new moodle_url('/local/timetable_management/timetable.php'),
            'local/timetable_management:manage'
        )
    );

}
