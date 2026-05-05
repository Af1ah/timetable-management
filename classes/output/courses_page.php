<?php

namespace local_timetable_management\output;

defined('MOODLE_INTERNAL') || die();

use moodle_url;
use renderable;
use renderer_base;
use templatable;

class courses_page implements renderable, templatable {
    public function __construct(
        private readonly moodle_url $baseurl,
        private readonly moodle_url $backurl,
        private readonly array $availablecourses,
        private readonly array $assignedcourses,
        private readonly bool $semesterdisabled
    ) {
    }

    public function export_for_template(renderer_base $output): array {
        return [
            'backurl' => $this->backurl->out(false),
            'formaction' => $this->baseurl->out(false),
            'sesskey' => sesskey(),
            'semesterdisabled' => $this->semesterdisabled,
            'availablecourses' => $this->export_courses($this->availablecourses),
            'assignedcourses' => $this->export_courses($this->assignedcourses),
            'searchlabel' => get_string('search'),
        ];
    }

    private function export_courses(array $courses): array {
        $items = [];
        foreach ($courses as $course) {
            $label = format_string($course->shortname) . ' - ' . format_string($course->fullname);
            if (!empty($course->categoryname)) {
                $label .= ' [' . format_string($course->categoryname) . ']';
            }

            $items[] = [
                'id' => (int) $course->id,
                'label' => $label,
                'shortname' => \core_text::strtolower((string) $course->shortname),
                'fullname' => \core_text::strtolower((string) $course->fullname),
            ];
        }

        return $items;
    }
}
