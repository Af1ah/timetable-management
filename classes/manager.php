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

namespace local_semester_management;

/**
 * Manager class for semester management operations.
 *
 * @package    local_semester_management
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {

    /** @var int Total number of semesters */
    const TOTAL_SEMESTERS = 8;

    /**
     * Get semester name.
     *
     * @param int $semester
     * @return string
     */
    public static function get_semester_name(int $semester): string {
        return get_string('semestern', 'local_semester_management', $semester);
    }

    /**
     * Check if semester is valid (1-8).
     *
     * @param int $semester
     * @return bool
     */
    public static function is_valid_semester(int $semester): bool {
        return $semester >= 1 && $semester <= self::TOTAL_SEMESTERS;
    }

    /**
     * Get semester status.
     *
     * @param int $semester
     * @return object|null
     */
    public static function get_semester_status(int $semester): ?object {
        global $DB;

        $status = $DB->get_record('local_semester_status', ['semester' => $semester]);
        if (!$status) {
            // Create default status if missing.
            $status = new \stdClass();
            $status->semester = $semester;
            $status->enabled = 1;
            $status->timemodified = time();
            $status->id = $DB->insert_record('local_semester_status', $status);
        }
        return $status;
    }

    /**
     * Get all semester statuses.
     *
     * @return array
     */
    public static function get_all_semester_statuses(): array {
        $statuses = [];
        for ($i = 1; $i <= self::TOTAL_SEMESTERS; $i++) {
            $statuses[$i] = self::get_semester_status($i);
        }
        return $statuses;
    }

    /**
     * Check if semester is enabled.
     *
     * @param int $semester
     * @return bool
     */
    public static function is_semester_enabled(int $semester): bool {
        $status = self::get_semester_status($semester);
        return $status && $status->enabled == 1;
    }

    /**
     * Enable a semester.
     *
     * @param int $semester
     * @return bool
     */
    public static function enable_semester(int $semester): bool {
        global $DB;

        if (!self::is_valid_semester($semester)) {
            return false;
        }

        $status = self::get_semester_status($semester);
        $status->enabled = 1;
        $status->timemodified = time();
        $DB->update_record('local_semester_status', $status);

        // Show all courses in this semester.
        self::update_courses_visibility($semester, 1);

        return true;
    }

    /**
     * Disable a semester.
     *
     * @param int $semester
     * @return bool
     */
    public static function disable_semester(int $semester): bool {
        global $DB;

        if (!self::is_valid_semester($semester)) {
            return false;
        }

        $status = self::get_semester_status($semester);
        $status->enabled = 0;
        $status->timemodified = time();
        $DB->update_record('local_semester_status', $status);

        // Hide all courses in this semester.
        self::update_courses_visibility($semester, 0);

        return true;
    }

    /**
     * Update visibility of all courses in a semester.
     *
     * @param int $semester
     * @param int $visible 1=visible, 0=hidden
     */
    public static function update_courses_visibility(int $semester, int $visible): void {
        global $DB;

        $sql = "SELECT c.id
                  FROM {course} c
                  JOIN {local_semester_course} sc ON sc.courseid = c.id
                 WHERE sc.semester = :semester";
        $courses = $DB->get_records_sql($sql, ['semester' => $semester]);

        foreach ($courses as $course) {
            $DB->set_field('course', 'visible', $visible, ['id' => $course->id]);
            // Rebuild course cache.
            rebuild_course_cache($course->id, true);
        }
    }

    /**
     * Get courses in a semester.
     *
     * @param int $semester
     * @return array
     */
    public static function get_semester_courses(int $semester): array {
        global $DB;

        $sql = "SELECT c.id, c.fullname, c.shortname, c.visible, c.category
                  FROM {course} c
                  JOIN {local_semester_course} sc ON sc.courseid = c.id
                 WHERE sc.semester = :semester
              ORDER BY c.fullname ASC";
        return $DB->get_records_sql($sql, ['semester' => $semester]);
    }

    /**
     * Count courses in a semester.
     *
     * @param int $semester
     * @return int
     */
    public static function count_semester_courses(int $semester): int {
        global $DB;
        return $DB->count_records('local_semester_course', ['semester' => $semester]);
    }

    /**
     * Get course semester assignment.
     *
     * @param int $courseid
     * @return object|false
     */
    public static function get_course_semester(int $courseid) {
        global $DB;
        return $DB->get_record('local_semester_course', ['courseid' => $courseid]);
    }

    /**
     * Set course semester assignment.
     *
     * @param int $courseid
     * @param int $semester
     * @return bool
     */
    public static function set_course_semester(int $courseid, int $semester): bool {
        global $DB;

        if (!self::is_valid_semester($semester)) {
            return false;
        }

        $existing = self::get_course_semester($courseid);

        if ($existing) {
            $existing->semester = $semester;
            $existing->timemodified = time();
            $DB->update_record('local_semester_course', $existing);
        } else {
            $record = new \stdClass();
            $record->courseid = $courseid;
            $record->semester = $semester;
            $record->timecreated = time();
            $record->timemodified = time();
            $DB->insert_record('local_semester_course', $record);
        }

        // Update course visibility based on semester status.
        $semesterstatus = self::get_semester_status($semester);
        $DB->set_field('course', 'visible', $semesterstatus->enabled, ['id' => $courseid]);
        rebuild_course_cache($courseid, true);

        return true;
    }

    /**
     * Remove course from semester.
     *
     * @param int $courseid
     * @return bool
     */
    public static function remove_course_semester(int $courseid): bool {
        global $DB;

        $result = $DB->delete_records('local_semester_course', ['courseid' => $courseid]);

        // Restore course visibility.
        $DB->set_field('course', 'visible', 1, ['id' => $courseid]);
        rebuild_course_cache($courseid, true);

        return $result;
    }

    /**
     * Get available courses (not assigned to any semester).
     *
     * @return array
     */
    public static function get_available_courses(): array {
        global $DB;

        $sql = "SELECT c.id, c.fullname, c.shortname, c.category
                  FROM {course} c
                 WHERE c.id != :siteid
                   AND c.id NOT IN (SELECT courseid FROM {local_semester_course})
              ORDER BY c.fullname ASC";
        return $DB->get_records_sql($sql, ['siteid' => SITEID]);
    }

    /**
     * Get semester options for dropdown.
     *
     * @param bool $includeempty
     * @return array
     */
    public static function get_semester_options(bool $includeempty = true): array {
        $options = [];
        if ($includeempty) {
            $options[''] = get_string('nosemester', 'local_semester_management');
        }
        for ($i = 1; $i <= self::TOTAL_SEMESTERS; $i++) {
            $options[$i] = self::get_semester_name($i);
        }
        return $options;
    }

    /**
     * Check if course is visible based on semester.
     *
     * @param int $courseid
     * @return bool
     */
    public static function is_course_visible_by_semester(int $courseid): bool {
        $assignment = self::get_course_semester($courseid);
        if (!$assignment) {
            return true; // No semester assigned, course visibility not controlled.
        }
        return self::is_semester_enabled($assignment->semester);
    }
}
