<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_timetable_management\output;

defined('MOODLE_INTERNAL') || die();

use local_timetable_management\manager;
use moodle_url;
use renderable;
use renderer_base;
use templatable;

class manage_page implements renderable, templatable {
    public function __construct(
        private readonly array $statuses,
        private readonly moodle_url $baseurl
    ) {
    }

    public function export_for_template(renderer_base $output): array {
        $rows = [];

        for ($semester = 1; $semester <= manager::TOTAL_SEMESTERS; $semester++) {
            $status = $this->statuses[$semester];
            $coursecount = manager::count_semester_courses($semester);
            $actions = [];

            if (!empty($status->enabled)) {
                $actions[] = [
                    'url' => (new moodle_url($this->baseurl, [
                        'action' => 'disable',
                        'semester' => $semester,
                        'sesskey' => sesskey(),
                    ]))->out(false),
                    'label' => get_string('disable', 'local_timetable_management'),
                    'class' => 'btn btn-sm btn-warning',
                ];
            } else {
                $actions[] = [
                    'url' => (new moodle_url($this->baseurl, [
                        'action' => 'enable',
                        'semester' => $semester,
                        'sesskey' => sesskey(),
                    ]))->out(false),
                    'label' => get_string('enable', 'local_timetable_management'),
                    'class' => 'btn btn-sm btn-success',
                ];
            }

            $actions[] = [
                'url' => (new moodle_url('/local/timetable_management/programs.php', ['semester' => $semester]))->out(false),
                'label' => get_string('manage', 'local_timetable_management'),
                'class' => 'btn btn-sm btn-primary',
            ];

            $rows[] = [
                'semestername' => manager::get_semester_name($semester),
                'isenabled' => !empty($status->enabled),
                'coursecountlabel' => get_string('coursecount', 'local_timetable_management', $coursecount),
                'coursesurl' => (new moodle_url('/local/timetable_management/programs.php', ['semester' => $semester]))->out(false),
                'actions' => $actions,
            ];
        }

        return ['rows' => $rows];
    }
}
