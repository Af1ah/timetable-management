<?php

namespace local_timetable_management\output;

defined('MOODLE_INTERNAL') || die();

use local_timetable_management\manager;
use moodle_url;
use renderable;
use renderer_base;
use templatable;

class programs_page implements renderable, templatable {
    public function __construct(
        private readonly int $semester,
        private readonly array $programs,
        private readonly moodle_url $backurl
    ) {
    }

    public function export_for_template(renderer_base $output): array {
        $rows = [];
        foreach ($this->programs as $program) {
            $rows[] = [
                'programname' => format_string($program->name),
                'departmentname' => $program->deptname ? format_string($program->deptname) : '-',
                'coursecount' => manager::count_category_courses((int) $program->id, $program->path),
                'manageurl' => (new moodle_url('/local/timetable_management/courses.php', [
                    'semester' => $this->semester,
                    'categoryid' => $program->id,
                ]))->out(false),
            ];
        }

        return [
            'backurl' => $this->backurl->out(false),
            'hasprograms' => !empty($rows),
            'rows' => $rows,
        ];
    }
}
