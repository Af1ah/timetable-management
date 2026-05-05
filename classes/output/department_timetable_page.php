<?php

namespace local_timetable_management\output;

defined('MOODLE_INTERNAL') || die();

use moodle_url;
use renderable;
use renderer_base;
use templatable;

class department_timetable_page implements renderable, templatable {
    public function __construct(
        private readonly array $matrix,
        private readonly int $sessioncount,
        private readonly array $sectiontimes,
        private readonly array $programs,
        private readonly array $courseoptions,
        private readonly bool $isediting,
        private readonly bool $hasmasterslots,
        private readonly bool $showdepartmentwarning,
        private readonly moodle_url $backurl,
        private readonly moodle_url $baseurl
    ) {
    }

    public function export_for_template(renderer_base $output): array {
        $headers = [];
        for ($sessionindex = 0; $sessionindex < $this->sessioncount; $sessionindex++) {
            $label = get_string('section', 'local_timetable_management', $sessionindex + 1);
            $time = \local_timetable_management\manager::get_slot_label($this->sectiontimes[0][$sessionindex] ?? []);
            $headers[] = [
                'labelhtml' => $time !== '' ? $label . '<br><span class="small text-muted">' . s($time) . '</span>' : $label,
            ];
        }

        $programcount = max(count($this->programs), 1);
        $rows = [];
        foreach ($this->matrix as $rowindex => $row) {
            $cells = [];
            foreach ($row['cells'] as $cell) {
                $cells[] = [
                    'reserved' => !empty($cell['reserved']),
                    'reservedlabel' => $cell['reservedlabel'],
                    'reservedtooltip' => $cell['reservedtooltip'],
                    'courseshortname' => $cell['courseshortname'],
                    'coursetitle' => $cell['coursetitle'],
                    'inputvalue' => $cell['courselabel'],
                    'hiddenvalue' => $cell['courseid'],
                    'hiddenname' => 'courseid[' . $row['weekday'] . '][' . $row['programid'] . '][' . $cell['sessionindex'] . ']',
                    'hiddenid' => 'courseid-' . $row['weekday'] . '-' . $row['programid'] . '-' . $cell['sessionindex'],
                    'listid' => 'course-options-' . $row['programid'],
                ];
            }

            $rows[] = [
                'showdaylabel' => $rowindex % $programcount === 0,
                'dayrowspan' => $programcount,
                'daylabel' => $row['daylabel'],
                'programname' => $row['programname'],
                'weekday' => $row['weekday'],
                'programid' => $row['programid'],
                'cells' => $cells,
            ];
        }

        $datalists = [];
        if ($this->isediting) {
            foreach ($this->courseoptions as $programid => $options) {
                $datalistoptions = [[
                    'value' => get_string('nocourseoption', 'local_timetable_management'),
                    'courseid' => '',
                ]];
                foreach ($options as $courseid => $label) {
                    $datalistoptions[] = [
                        'value' => $label,
                        'courseid' => (int) $courseid,
                    ];
                }

                $datalists[] = [
                    'id' => 'course-options-' . $programid,
                    'options' => $datalistoptions,
                ];
            }
        }

        return [
            'backurl' => $this->backurl->out(false),
            'csvurl' => (new moodle_url($this->baseurl, ['download' => 'csv']))->out(false),
            'pdfurl' => (new moodle_url($this->baseurl, ['download' => 'pdf']))->out(false),
            'showdepartmentwarning' => $this->showdepartmentwarning,
            'showmasterinfo' => $this->hasmasterslots,
            'hasrows' => !empty($rows),
            'isediting' => $this->isediting,
            'formaction' => $this->baseurl->out(false),
            'sesskey' => sesskey(),
            'headers' => $headers,
            'rows' => $rows,
            'datalists' => $datalists,
        ];
    }
}
