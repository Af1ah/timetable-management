<?php

namespace local_timetable_management\output;

defined('MOODLE_INTERNAL') || die();

use local_timetable_management\manager;
use moodle_url;
use renderable;
use renderer_base;
use templatable;

class college_master_page implements renderable, templatable {
    public function __construct(
        private readonly array $activesemesters,
        private readonly object $config,
        private readonly moodle_url $baseurl,
        private readonly int $semester = 0,
        private readonly array $masterslots = [],
        private readonly array $coursetooltips = [],
        private readonly bool $isediting = false,
        private readonly array $excludeddepartments = [],
        private readonly array $timetabledepartments = []
    ) {
    }

    public function export_for_template(renderer_base $output): array {
        $showform = $this->semester > 0 && isset($this->activesemesters[$this->semester]);

        if ($showform) {
            return $this->export_form_view();
        }

        return $this->export_list_view();
    }

    private function export_list_view(): array {
        $semesterrows = [];
        foreach ($this->activesemesters as $activesemester) {
            $excludeddepartments = manager::get_excluded_department_records((int) $activesemester->semester);
            $departmentoptions = [];

            foreach ($this->timetabledepartments as $department) {
                $isexcluded = false;
                foreach ($excludeddepartments as $excludeddepartment) {
                    if ((int) $excludeddepartment->id === (int) $department->id) {
                        $isexcluded = true;
                        break;
                    }
                }

                $departmentoptions[] = [
                    'id' => (int) $department->id,
                    'name' => format_string($department->name),
                    'checked' => !$isexcluded,
                ];
            }

            $semesterrows[] = [
                'semesterid' => (int) $activesemester->semester,
                'name' => $activesemester->name,
                'hasexcludeddepartments' => !empty($excludeddepartments),
                'excludeddepartments' => array_map(static function(object $department): array {
                    return [
                        'name' => format_string($department->name),
                    ];
                }, $excludeddepartments),
                'departmentoptions' => $departmentoptions,
                'manageurl' => (new moodle_url($this->baseurl, ['semester' => $activesemester->semester]))->out(false),
            ];
        }

        return [
            'islist' => true,
            'backurl' => (new moodle_url('/local/timetable_management/timetable.php'))->out(false),
            'hassemesters' => !empty($semesterrows),
            'semesters' => $semesterrows,
            'isediting' => $this->isediting,
            'listformaction' => $this->baseurl->out(false),
            'sesskey' => sesskey(),
        ];
    }

    private function export_form_view(): array {
        $headers = [];
        for ($sessionindex = 0; $sessionindex < $this->config->sessioncount; $sessionindex++) {
            $label = get_string('section', 'local_timetable_management', $sessionindex + 1);
            $time = manager::get_slot_label($this->config->sectiontimes[0][$sessionindex] ?? []);
            $headers[] = [
                'labelhtml' => $time !== '' ? $label . '<br><span class="small text-muted">' . s($time) . '</span>' : $label,
            ];
        }

        $rows = [];
        foreach ($this->config->workingdays as $dayoffset => $daylabel) {
            $weekday = $dayoffset + 1;
            $cells = [];
            for ($sessionindex = 1; $sessionindex <= $this->config->sessioncount; $sessionindex++) {
                $savedtype = $this->masterslots[$weekday][$sessionindex]->coursetype ?? '';
                $options = [];
                foreach (manager::get_master_course_type_options() as $value => $label) {
                    $options[] = [
                        'value' => $value,
                        'label' => $label,
                        'selected' => (string) $savedtype === (string) $value,
                    ];
                }

                $cells[] = [
                    'selectname' => 'coursetype[' . $weekday . '][' . $sessionindex . ']',
                    'title' => $savedtype !== '' ? ($this->coursetooltips[$savedtype] ?? '') : '',
                    'options' => $options,
                ];
            }
            $rows[] = ['daylabel' => $daylabel, 'cells' => $cells];
        }

        $tooltipitems = [];
        foreach ($this->coursetooltips as $coursetype => $tooltip) {
            if ($tooltip === '') {
                continue;
            }
            $tooltipitems[] = [
                'typename' => manager::get_master_course_type_label($coursetype),
                'tooltip' => $tooltip,
            ];
        }

        return [
            'isform' => true,
            'backurl' => $this->baseurl->out(false),
            'selectedsemesterheading' => get_string(
                'collegemastertimetablefor',
                'local_timetable_management',
                $this->activesemesters[$this->semester]->name
            ),
            'formaction' => (new moodle_url($this->baseurl, ['semester' => $this->semester]))->out(false),
            'sesskey' => sesskey(),
            'headers' => $headers,
            'rows' => $rows,
            'hascourseinfo' => !empty($tooltipitems),
            'courseinfoitems' => $tooltipitems,
            'isediting' => $this->isediting,
            'hasexcludeddepartments' => !empty($this->excludeddepartments),
            'excludeddepartments' => array_map(static function(object $department): array {
                return [
                    'id' => (int) $department->id,
                    'name' => format_string($department->name),
                ];
            }, $this->excludeddepartments),
        ];
    }
}
