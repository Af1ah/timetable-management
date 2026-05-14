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

namespace local_timetable_management\task;

/**
 * Ad hoc task: generate attendance sessions for a department from the timetable.
 *
 * Custom data expected:
 *   departmentid  int  - course_categories.id for the department
 *   startdate     int  - Unix timestamp of the first day to process
 *   durationdays  int  - How many calendar days to cover
 *
 * @package    local_timetable_management
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_attendance_sessions extends \core\task\adhoc_task {

    public function get_name(): string {
        return get_string('task_syncattendancesessions', 'local_timetable_management');
    }

    public function execute(): void {
        global $DB;

        $data = $this->get_custom_data();
        $departmentid = (int) ($data->departmentid ?? 0);
        $startdate    = (int) ($data->startdate    ?? 0);
        $durationdays = max(1, (int) ($data->durationdays ?? 1));

        if ($departmentid <= 0) {
            mtrace('sync_attendance_sessions: invalid departmentid, aborting.');
            return;
        }

        $dept = $DB->get_record('course_categories', ['id' => $departmentid], 'id, name');
        $deptname = $dept ? format_string($dept->name) : "Department {$departmentid}";

        mtrace(sprintf(
            'Attendance sync starting: dept="%s", from=%s, days=%d',
            $deptname,
            userdate($startdate, get_string('strftimedatefullshort')),
            $durationdays
        ));

        try {
            $summary = \local_timetable_management\manager::sync_department_attendance_sessions(
                $departmentid,
                $startdate,
                $durationdays
            );
        } catch (\Throwable $e) {
            $summary = (object) [
                'coursesprocessed'  => 0,
                'attendancecreated' => 0,
                'sessioncandidates' => 0,
                'sessionscreated'   => 0,
                'duplicateskipped'  => 0,
                'timeskipped'       => 0,
                'errors'            => [$e->getMessage()],
            ];
        }

        $tried   = (int) $summary->sessioncandidates;
        $created = (int) $summary->sessionscreated;
        $skipped = (int) $summary->duplicateskipped;
        $failed  = count($summary->errors);

        mtrace(sprintf(
            'Attendance sync done: dept="%s" tried=%d created=%d skipped=%d failed=%d',
            $deptname,
            $tried,
            $created,
            $skipped,
            $failed
        ));

        self::notify_admins($deptname, $startdate, $durationdays, $summary);
    }

    /**
     * Send a notification message to every site admin with the sync statistics.
     *
     * @param string $deptname  Human-readable department name.
     * @param int    $startdate Unix timestamp of the sync start date.
     * @param int    $durationdays Number of calendar days covered.
     * @param object $summary   Result object from sync_department_attendance_sessions().
     */
    private static function notify_admins(
        string $deptname,
        int $startdate,
        int $durationdays,
        object $summary
    ): void {
        $tried   = (int) $summary->sessioncandidates;
        $created = (int) $summary->sessionscreated;
        $skipped = (int) $summary->duplicateskipped;
        $failed  = count($summary->errors);
        $haserrors = !empty($summary->errors);

        $subject = $haserrors
            ? get_string('task_syncnotify_failsubject', 'local_timetable_management', $deptname)
            : get_string('task_syncnotify_subject',     'local_timetable_management', $deptname);

        $a = (object) [
            'department' => $deptname,
            'startdate'  => userdate($startdate, get_string('strftimedatefullshort')),
            'days'       => $durationdays,
            'tried'      => $tried,
            'created'    => $created,
            'skipped'    => $skipped,
            'failed'     => $failed,
        ];
        $body = get_string('task_syncnotify_body', 'local_timetable_management', $a);

        if ($haserrors) {
            $body .= "\n\n" . get_string('task_syncnotify_errors', 'local_timetable_management') . "\n"
                   . implode("\n", array_map('strip_tags', $summary->errors));
        }

        $noreply = \core_user::get_noreply_user();

        foreach (get_admins() as $admin) {
            $msg = new \core\message\message();
            $msg->component         = 'local_timetable_management';
            $msg->name              = 'attendancesync';
            $msg->userfrom          = $noreply;
            $msg->userto            = $admin;
            $msg->subject           = $subject;
            $msg->fullmessage       = $body;
            $msg->fullmessageformat = FORMAT_PLAIN;
            $msg->fullmessagehtml   = nl2br(s($body));
            $msg->smallmessage      = $subject;
            $msg->notification      = 1;
            message_send($msg);
        }
    }
}
