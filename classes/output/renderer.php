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

namespace local_timetable_management\output;

defined('MOODLE_INTERNAL') || die();

use plugin_renderer_base;

class renderer extends plugin_renderer_base {
    public function render_manage_page(manage_page $page): string {
        return $this->render_from_template('local_timetable_management/manage_page', $page->export_for_template($this));
    }

    public function render_programs_page(programs_page $page): string {
        return $this->render_from_template('local_timetable_management/programs_page', $page->export_for_template($this));
    }

    public function render_courses_page(courses_page $page): string {
        return $this->render_from_template('local_timetable_management/courses_page', $page->export_for_template($this));
    }

    public function render_timetable_page(timetable_page $page): string {
        return $this->render_from_template('local_timetable_management/timetable_page', $page->export_for_template($this));
    }

    public function render_college_master_page(college_master_page $page): string {
        return $this->render_from_template('local_timetable_management/college_master_page', $page->export_for_template($this));
    }

    public function render_department_timetable_page(department_timetable_page $page): string {
        return $this->render_from_template('local_timetable_management/department_timetable_page', $page->export_for_template($this));
    }

}
