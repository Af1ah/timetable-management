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
require_once($CFG->libdir . '/adminlib.php');

use local_timetable_management\manager;

$action = optional_param('action', '', PARAM_ALPHA);
$view = optional_param('view', 'table', PARAM_ALPHA);

admin_externalpage_setup('local_timetable_management_timetable');

$context = context_system::instance();
require_capability('local/timetable_management:manage', $context);

$baseurl = new moodle_url('/local/timetable_management/timetable.php');
$PAGE->set_url($baseurl, ['view' => $view]);

$config = manager::get_global_timetable_config();
$workingdays = $config->workingdays;
$sectiontimes = $config->sectiontimes;

if ($action === 'saveconfig' && confirm_sesskey()) {
    $workingdays = optional_param_array('daylabel', $workingdays, PARAM_TEXT);
    $fromvalues = manager::clean_nested_param($_POST['cellfrom'] ?? [], PARAM_TEXT);
    $tovalues = manager::clean_nested_param($_POST['cellto'] ?? [], PARAM_TEXT);
    $gridaction = optional_param('gridaction', '', PARAM_ALPHA);

    $sectiontimes = [];
    foreach ($fromvalues as $dayindex => $daysections) {
        foreach ((array) $daysections as $sectionindex => $fromvalue) {
            $sectiontimes[(int) $dayindex][(int) $sectionindex] = [
                'from' => trim((string) $fromvalue),
                'to' => trim((string) ($tovalues[$dayindex][$sectionindex] ?? '')),
            ];
        }
    }

    $sectiontimes = manager::normalise_section_time_grid($sectiontimes, $workingdays);
    $workingdays = manager::normalise_working_days($workingdays, count($sectiontimes));

    if ($gridaction === 'addday') {
        $updated = manager::add_working_day($workingdays, $sectiontimes);
        $workingdays = $updated->workingdays;
        $sectiontimes = $updated->sectiontimes;
        $view = 'settings';
    } else if ($gridaction === 'removeday') {
        $updated = manager::remove_working_day($workingdays, $sectiontimes);
        $workingdays = $updated->workingdays;
        $sectiontimes = $updated->sectiontimes;
        $view = 'settings';
    } else if ($gridaction === 'addsection') {
        $updated = manager::add_section($workingdays, $sectiontimes);
        $workingdays = $updated->workingdays;
        $sectiontimes = $updated->sectiontimes;
        $view = 'settings';
    } else if ($gridaction === 'removesection') {
        $updated = manager::remove_section($workingdays, $sectiontimes);
        $workingdays = $updated->workingdays;
        $sectiontimes = $updated->sectiontimes;
        $view = 'settings';
    } else {
        if (!manager::validate_section_times($sectiontimes)) {
            throw new moodle_exception('invalidtimevalue', 'local_timetable_management');
        }

        manager::save_global_timetable_config($workingdays, $sectiontimes);
        redirect(
            new moodle_url($baseurl, ['view' => 'settings']),
            get_string('timetablesettingssaved', 'local_timetable_management'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

$config->workingdays = $workingdays;
$config->sectiontimes = $sectiontimes;
$config->sessioncount = manager::get_section_count_from_grid($sectiontimes);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('timetable', 'local_timetable_management'));

$departments = $view !== 'settings' ? manager::get_timetable_departments() : [];

$output = $PAGE->get_renderer('local_timetable_management');
echo $output->render(new \local_timetable_management\output\timetable_page($view, $config, $baseurl, $departments));
echo $OUTPUT->footer();
