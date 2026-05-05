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

require_once(__DIR__ . '/../../config.php');

use local_timetable_management\manager;

/**
 * Build timetable matrix rows for rendering and export.
 *
 * @param array $workingdays
 * @param array $programs
 * @param array $entries
 * @param array $courseoptions
 * @param array $sectiontimes
 * @return array
 */
function local_timetable_management_build_matrix(
    array $workingdays,
    array $programs,
    array $entries,
    array $courseoptions,
    array $sectiontimes,
    array $masterslots = [],
    array $mastertooltips = []
): array {
    $rows = [];

    foreach ($workingdays as $dayoffset => $daylabel) {
        $weekday = $dayoffset + 1;
        foreach ($programs as $program) {
            $cells = [];
            for ($sessionindex = 1; $sessionindex <= count($sectiontimes[$dayoffset] ?? []); $sessionindex++) {
                $masterrecord = $masterslots[$program->id][$weekday][$sessionindex] ?? null;
                $reservedtype = $masterrecord ? (string) $masterrecord->coursetype : '';
                $saved = $entries[$weekday][$program->id][$sessionindex] ?? null;
                $courseid = $saved ? (int) $saved->courseid : 0;
                $reservedlabel = $reservedtype !== '' ? manager::get_master_course_type_label($reservedtype) : '';
                $reservedtooltip = $reservedtype !== '' ? ($mastertooltips[(int) $program->semester][$reservedtype] ?? '') : '';
                $cells[] = [
                    'sessionindex' => $sessionindex,
                    'time' => manager::get_slot_label($sectiontimes[$dayoffset][$sessionindex - 1] ?? []),
                    'courseid' => $reservedtype !== '' ? 0 : $courseid,
                    'reserved' => $reservedtype !== '',
                    'reservedtype' => $reservedtype,
                    'reservedlabel' => $reservedlabel,
                    'reservedtooltip' => $reservedtooltip,
                    'courselabel' => $reservedtype !== ''
                        ? $reservedlabel
                        : ($courseid && isset($courseoptions[$program->id][$courseid])
                        ? $courseoptions[$program->id][$courseid]
                        : ''),
                    'courseshortname' => $reservedtype !== ''
                        ? $reservedlabel
                        : ($courseid && isset($courseoptions[$program->id][$courseid])
                        ? trim(explode(' - ', $courseoptions[$program->id][$courseid], 2)[0])
                        : ''),
                    'coursetitle' => $reservedtype !== ''
                        ? $reservedtooltip
                        : ($courseid && isset($courseoptions[$program->id][$courseid])
                        ? $courseoptions[$program->id][$courseid]
                        : ''),
                ];
            }

            $rows[] = [
                'weekday' => $weekday,
                'daylabel' => $daylabel,
                'programid' => (int) $program->id,
                'programname' => !empty($program->semestername) ? $program->semestername : format_string($program->name),
                'cells' => $cells,
            ];
        }
    }

    return $rows;
}

/**
 * Output CSV export and exit.
 *
 * @param string $departmentname
 * @param array $matrix
 * @return void
 */
function local_timetable_management_output_csv(string $departmentname, array $matrix): void {
    $filename = clean_filename($departmentname . '_timetable_' . date('Ymd')) . '.csv';

    while (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, [get_string('timetableexporttitle', 'local_timetable_management', $departmentname)], ',', '"', '\\');
    fputcsv($out, [get_string('timecreated', 'moodle'), userdate(time(), '%d %b %Y %H:%M')], ',', '"', '\\');
    fputcsv($out, [], ',', '"', '\\');

    $header = [get_string('weekday', 'local_timetable_management'), get_string('programsemester', 'local_timetable_management')];
    if (!empty($matrix[0]['cells'])) {
        foreach ($matrix[0]['cells'] as $cell) {
            $header[] = get_string('section', 'local_timetable_management', $cell['sessionindex']) .
                ($cell['time'] !== '' ? ' (' . $cell['time'] . ')' : '');
        }
    }
    fputcsv($out, $header, ',', '"', '\\');

    foreach ($matrix as $row) {
        $line = [$row['daylabel'], $row['programname']];
        foreach ($row['cells'] as $cell) {
            $line[] = $cell['courselabel'] !== '' ? $cell['courselabel'] : get_string('nocourseassigned', 'local_timetable_management');
        }
        fputcsv($out, $line, ',', '"', '\\');
    }

    fclose($out);
    exit;
}

/**
 * Output PDF export and exit.
 *
 * @param string $departmentname
 * @param array $matrix
 * @return void
 */
function local_timetable_management_output_pdf(string $departmentname, array $matrix): void {
    global $CFG;

    require_once($CFG->libdir . '/pdflib.php');

    $pdf = new pdf('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Moodle');
    $pdf->SetAuthor(fullname($GLOBALS['USER']));
    $pdf->SetTitle(get_string('timetableexporttitle', 'local_timetable_management', $departmentname));
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(true, 10);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 9);

    $html = '<h2 style="text-align:center;">' .
        s(get_string('timetableexporttitle', 'local_timetable_management', $departmentname)) .
        '</h2>';
    $html .= '<p style="text-align:right;">' . s(userdate(time(), '%d %b %Y %H:%M')) . '</p>';
    $html .= '<table border="1" cellpadding="4" cellspacing="0" style="width:100%; font-size:9pt;">';
    $html .= '<thead><tr>';
    $html .= '<th><strong>' . s(get_string('weekday', 'local_timetable_management')) . '</strong></th>';
    $html .= '<th><strong>' . s(get_string('programsemester', 'local_timetable_management')) . '</strong></th>';
    if (!empty($matrix[0]['cells'])) {
        foreach ($matrix[0]['cells'] as $cell) {
            $label = get_string('section', 'local_timetable_management', $cell['sessionindex']);
            if ($cell['time'] !== '') {
                $label .= '<br/>' . s($cell['time']);
            }
            $html .= '<th><strong>' . $label . '</strong></th>';
        }
    }
    $html .= '</tr></thead><tbody>';

    $rowsbyday = [];
    foreach ($matrix as $row) {
        $rowsbyday[$row['daylabel']][] = $row;
    }

    foreach ($rowsbyday as $daylabel => $dayrows) {
        $rowspan = count($dayrows);
        foreach ($dayrows as $index => $row) {
            $html .= '<tr>';
            if ($index === 0) {
                $html .= '<td rowspan="' . $rowspan . '">' . s($daylabel) . '</td>';
            }
            $html .= '<td>' . s($row['programname']) . '</td>';
            foreach ($row['cells'] as $cell) {
                $html .= '<td>' . s($cell['courselabel'] !== '' ? $cell['courselabel'] : get_string('nocourseassigned', 'local_timetable_management')) . '</td>';
            }
            $html .= '</tr>';
        }
    }

    $html .= '</tbody></table>';
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output(clean_filename($departmentname . '_timetable_' . date('Ymd')) . '.pdf', 'D');
    exit;
}

/**
 * Resolve the timetable department category for the current HOD.
 *
 * @param int $userid
 * @return int
 */
function local_timetable_management_resolve_hod_departmentid(int $userid): int {
    global $DB;

    $hoddepartments = $DB->get_records('local_admission_departments', [
        'hodid' => $userid,
        'enabled' => 1,
    ]);

    if (empty($hoddepartments)) {
        return 0;
    }

    $departmentnames = [];
    foreach ($hoddepartments as $hoddepartment) {
        $name = manager::normalise_matching_text((string) ($hoddepartment->name ?? ''));
        if ($name !== '') {
            $departmentnames[$name] = true;
        }
    }

    $timetabledepartments = manager::get_timetable_departments();
    foreach ($timetabledepartments as $department) {
        $departmentname = manager::normalise_matching_text((string) $department->name);
        if ($departmentname !== '' && isset($departmentnames[$departmentname])) {
            return (int) $department->id;
        }
    }

    return 0;
}

$departmentid = optional_param('departmentid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$download = optional_param('download', '', PARAM_ALPHA);

require_login();

$context = context_system::instance();
$canmanage = has_capability('local/timetable_management:manage', $context);

if (!$departmentid) {
    $hoddepartmentid = local_timetable_management_resolve_hod_departmentid((int) $USER->id);
    if ($hoddepartmentid) {
        $departmentid = $hoddepartmentid;
    }
    if (!$departmentid && $canmanage) {
        redirect(new moodle_url('/local/timetable_management/timetable.php'));
    }
    if (!$departmentid) {
        throw new required_capability_exception($context, 'local/timetable_management:manage', 'nopermissions', '');
    }
}

$department = $DB->get_record('course_categories', ['id' => $departmentid], '*', MUST_EXIST);
$admissiondepartment = manager::get_admission_department_for_category($departmentid);
$ishod = $admissiondepartment
    && !empty($admissiondepartment->enabled)
    && (int) ($admissiondepartment->hodid ?? 0) === (int) $USER->id;

if (!$canmanage && !$ishod) {
    throw new required_capability_exception($context, 'local/timetable_management:manage', 'nopermissions', '');
}

$canedit = $canmanage || $ishod;
$baseurl = new moodle_url('/local/timetable_management/department_timetable.php', ['departmentid' => $departmentid]);
$PAGE->set_url($baseurl);
$PAGE->set_context($context);
$PAGE->set_title(get_string('timetablefor', 'local_timetable_management', format_string($department->name)));
$PAGE->set_heading(format_string($department->name));
$PAGE->set_pagelayout('standard');
$PAGE->set_other_editing_capability(['local/timetable_management:manage', 'local/admission:hoddepartment']);

$programs = manager::get_department_active_programs($departmentid);
$config = manager::get_global_timetable_config();
$workingdays = $config->workingdays;
$sectiontimes = $config->sectiontimes;
$masterslots = manager::get_master_slots_for_programs($programs);
$mastertooltips = [];
foreach ($programs as $program) {
    if (!isset($mastertooltips[(int) $program->semester])) {
        $mastertooltips[(int) $program->semester] = manager::get_master_course_tooltips_by_semester((int) $program->semester);
    }
}

if ($action === 'savetable' && $canedit && confirm_sesskey()) {
    $courseids = manager::clean_nested_param($_POST['courseid'] ?? [], PARAM_INT);
    $entries = [];

    foreach ($workingdays as $dayoffset => $daylabel) {
        $weekday = $dayoffset + 1;
        foreach ($programs as $program) {
            $programid = (int) $program->id;
            if (empty($courseids[$weekday][$programid]) || !is_array($courseids[$weekday][$programid])) {
                continue;
            }

            foreach ($courseids[$weekday][$programid] as $sessionindex => $courseid) {
                if (!empty($masterslots[$programid][$weekday][$sessionindex])) {
                    continue;
                }

                $courseid = (int) $courseid;
                if ($courseid > 0 && !manager::is_course_in_category_tree($courseid, $programid, $program->path)) {
                    $courseid = 0;
                }

                $entries[] = [
                    'weekday' => $weekday,
                    'semcategoryid' => $programid,
                    'sessionindex' => (int) $sessionindex,
                    'courseid' => $courseid,
                ];
            }
        }
    }

    manager::save_department_timetable_entries($departmentid, $entries);
    redirect($baseurl,
        get_string('timetablesaved', 'local_timetable_management'),
        null, \core\output\notification::NOTIFY_SUCCESS);
}

$entries = manager::get_department_timetable_entries($departmentid);
$backurl = new moodle_url('/local/timetable_management/timetable.php');

$courseoptions = [];
foreach ($programs as $program) {
    $courseoptions[$program->id] = manager::get_category_tree_course_options((int) $program->id, $program->path);
}

$matrix = local_timetable_management_build_matrix(
    $workingdays,
    $programs,
    $entries,
    $courseoptions,
    $sectiontimes,
    $masterslots,
    $mastertooltips
);

if ($download === 'csv') {
    local_timetable_management_output_csv(format_string($department->name), $matrix);
}
if ($download === 'pdf') {
    local_timetable_management_output_pdf(format_string($department->name), $matrix);
}

$isediting = $PAGE->user_is_editing() && $canedit;

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('timetablefor', 'local_timetable_management', format_string($department->name)));

$output = $PAGE->get_renderer('local_timetable_management');
echo $output->render(new \local_timetable_management\output\department_timetable_page(
    $matrix,
    $config->sessioncount,
    $sectiontimes,
    $programs,
    $courseoptions,
    $isediting,
    !empty($masterslots),
    !$admissiondepartment && !$canmanage,
    $backurl,
    $baseurl,
    new moodle_url('/local/timetable_management/attendance_sessions.php', ['departmentid' => $departmentid])
));
echo $OUTPUT->footer();
