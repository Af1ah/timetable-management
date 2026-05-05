<?php

namespace local_timetable_management\output;

defined('MOODLE_INTERNAL') || die();

use local_timetable_management\manager;
use moodle_url;
use renderable;
use renderer_base;
use templatable;

class timetable_page implements renderable, templatable {
    public function __construct(
        private readonly string $view,
        private readonly object $config,
        private readonly moodle_url $baseurl,
        private readonly array $departments = []
    ) {
    }

    public function export_for_template(renderer_base $output): array {
        $data = [
            'issettings' => $this->view === 'settings',
            'showtableviewbutton' => $this->view === 'settings',
            'taburl' => (new moodle_url($this->baseurl, ['view' => $this->view === 'settings' ? 'table' : 'settings']))->out(false),
            'tablabel' => $this->view === 'settings'
                ? get_string('timetableview', 'local_timetable_management')
                : get_string('settings', 'local_timetable_management'),
            'masterurl' => (new moodle_url('/local/timetable_management/college_master_timetable.php'))->out(false),
        ];

        if ($this->view === 'settings') {
            $rows = [];
            for ($dayindex = 0; $dayindex < count($this->config->workingdays); $dayindex++) {
                $cells = [];
                for ($sectionindex = 0; $sectionindex < $this->config->sessioncount; $sectionindex++) {
                    $slot = $this->config->sectiontimes[$dayindex][$sectionindex] ?? ['from' => '', 'to' => ''];
                    $cells[] = [
                        'fromname' => 'cellfrom[' . $dayindex . '][' . $sectionindex . ']',
                        'toname' => 'cellto[' . $dayindex . '][' . $sectionindex . ']',
                        'fromvalue' => $slot['from'],
                        'tovalue' => $slot['to'],
                    ];
                }

                $rows[] = [
                    'daylabelname' => 'daylabel[' . $dayindex . ']',
                    'daylabelvalue' => $this->config->workingdays[$dayindex],
                    'cells' => $cells,
                ];
            }

            $headers = [];
            for ($sectionindex = 0; $sectionindex < $this->config->sessioncount; $sectionindex++) {
                $headers[] = get_string('section', 'local_timetable_management', $sectionindex + 1);
            }

            $data += [
                'formaction' => (new moodle_url($this->baseurl, ['view' => 'settings']))->out(false),
                'sesskey' => sesskey(),
                'headers' => $headers,
                'rows' => $rows,
            ];

            return $data;
        }

        $rows = [];
        foreach ($this->departments as $department) {
            $programs = manager::get_department_active_programs((int) $department->id);
            $programnames = [];
            foreach ($programs as $program) {
                $programnames[] = format_string($program->name);
            }

            $rows[] = [
                'departmentname' => format_string($department->name),
                'programnames' => empty($programnames) ? '-' : implode(', ', $programnames),
                'manageurl' => (new moodle_url('/local/timetable_management/department_timetable.php', [
                    'departmentid' => $department->id,
                ]))->out(false),
            ];
        }

        $data += [
            'hasdepartments' => !empty($rows),
            'rows' => $rows,
        ];

        return $data;
    }
}
