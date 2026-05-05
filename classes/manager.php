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

namespace local_timetable_management;

/**
 * Manager class for semester management operations.
 *
 * @package    local_timetable_management
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {

    /** @var int Total number of semesters */
    const TOTAL_SEMESTERS = 8;

    /** @var int Default period count */
    const DEFAULT_SESSION_COUNT = 5;

    /** @var int Default day start time in minutes */
    const DEFAULT_DAY_START_MINS = 570;

    /** @var int Default period duration in minutes */
    const DEFAULT_SESSION_LENGTH = 60;

    /** @var int Default break placement */
    const DEFAULT_BREAK_AFTER = 3;

    /** @var int Default break duration in minutes */
    const DEFAULT_BREAK_LENGTH = 60;

    /** @var string[] Default working day labels */
    const DEFAULT_WORKING_DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

    /**
     * Get semester name.
     *
     * @param int $semester
     * @return string
     */
    public static function get_semester_name(int $semester): string {
        return get_string('semestern', 'local_timetable_management', $semester);
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
     * Return all category IDs that belong to any *-semN category tree.
     * Used to determine which courses are already in a semester.
     *
     * @return array of int category IDs
     */
    private static function get_all_sem_category_ids(): array {
        global $DB;

        $semcats = $DB->get_records_sql(
            "SELECT id, path FROM {course_categories} WHERE " . $DB->sql_like('name', ':pat', false),
            ['pat' => '%-sem%']
        );

        $ids = [];
        foreach ($semcats as $cat) {
            $ids[] = (int)$cat->id;
            $children = $DB->get_fieldset_sql(
                "SELECT id FROM {course_categories} WHERE " . $DB->sql_like('path', ':pp', false),
                ['pp' => $DB->sql_like_escape($cat->path) . '/%']
            );
            foreach ($children as $cid) {
                $ids[] = (int)$cid;
            }
        }

        return array_unique($ids);
    }

    /**
     * Update visibility of all courses in a semester by scanning *-semN categories.
     *
     * @param int $semester
     * @param int $visible 1=visible, 0=hidden
     */
    public static function update_courses_visibility(int $semester, int $visible): void {
        global $DB;

        $cats = $DB->get_records_sql(
            "SELECT id, path FROM {course_categories} WHERE " . $DB->sql_like('name', ':pat', false),
            ['pat' => '%-sem' . $semester]
        );

        foreach ($cats as $cat) {
            $pathprefix = $DB->sql_like_escape($cat->path) . '/%';
            $sql = "SELECT c.id
                      FROM {course} c
                      JOIN {course_categories} cc ON cc.id = c.category
                     WHERE c.id != :siteid
                       AND (cc.id = :catid OR " . $DB->sql_like('cc.path', ':pp', false) . ")";
            $courses = $DB->get_records_sql($sql, [
                'siteid' => SITEID,
                'catid' => $cat->id,
                'pp' => $pathprefix,
            ]);
            foreach ($courses as $course) {
                $DB->set_field('course', 'visible', $visible, ['id' => $course->id]);
                rebuild_course_cache($course->id, true);
            }
        }
    }

    /**
     * Get courses in a semester (fallback non-category mode).
     * Returns courses in all *-semN category trees.
     *
     * @param int $semester
     * @return array
     */
    public static function get_semester_courses(int $semester): array {
        global $DB;

        $cats = $DB->get_records_sql(
            "SELECT id, path FROM {course_categories} WHERE " . $DB->sql_like('name', ':pat', false),
            ['pat' => '%-sem' . $semester]
        );

        if (empty($cats)) {
            return [];
        }

        $conditions = [];
        $params = ['siteid' => SITEID];
        $i = 0;
        foreach ($cats as $cat) {
            $conditions[] = "cc.id = :catid{$i}";
            $params["catid{$i}"] = $cat->id;
            $conditions[] = $DB->sql_like('cc.path', ":pp{$i}", false);
            $params["pp{$i}"] = $DB->sql_like_escape($cat->path) . '/%';
            $i++;
        }

        $sql = "SELECT c.id, c.fullname, c.shortname, c.visible, c.category
                  FROM {course} c
                  JOIN {course_categories} cc ON cc.id = c.category
                 WHERE c.id != :siteid
                   AND (" . implode(' OR ', $conditions) . ")
              ORDER BY c.fullname ASC";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Count courses in a semester by scanning *-semN category trees.
     *
     * @param int $semester
     * @return int
     */
    public static function count_semester_courses(int $semester): int {
        global $DB;

        $cats = $DB->get_records_sql(
            "SELECT id, path FROM {course_categories} WHERE " . $DB->sql_like('name', ':pat', false),
            ['pat' => '%-sem' . $semester]
        );

        $total = 0;
        foreach ($cats as $cat) {
            $total += self::count_category_courses((int)$cat->id, $cat->path);
        }

        return $total;
    }

    /**
     * Move a course into a program-sem category and apply semester visibility.
     *
     * @param int $courseid
     * @param int $semcategoryid The *-semN category ID
     */
    public static function assign_course_to_category(int $courseid, int $semcategoryid): void {
        global $DB;

        $DB->set_field('course', 'category', $semcategoryid, ['id' => $courseid]);

        // Apply visibility matching the semester's enabled state.
        $cat = $DB->get_record('course_categories', ['id' => $semcategoryid], 'name');
        if ($cat && preg_match('/-sem(\d+)$/i', $cat->name, $m)) {
            $semester = (int)$m[1];
            if (self::is_valid_semester($semester)) {
                $status = self::get_semester_status($semester);
                $DB->set_field('course', 'visible', $status->enabled, ['id' => $courseid]);
            }
        }

        rebuild_course_cache($courseid, true);
    }

    /**
     * Move a course out of a program-sem category back to the department (parent) category.
     *
     * @param int $courseid
     * @param int $semcategoryid The *-semN category the course is being removed from
     */
    public static function unassign_course_from_category(int $courseid, int $semcategoryid): void {
        global $DB;

        $cat = $DB->get_record('course_categories', ['id' => $semcategoryid], 'parent');
        $targetid = ($cat && $cat->parent > 0) ? (int)$cat->parent : 1;

        $DB->set_field('course', 'category', $targetid, ['id' => $courseid]);
        $DB->set_field('course', 'visible', 1, ['id' => $courseid]);
        rebuild_course_cache($courseid, true);
    }

    /**
     * Get available courses — all courses NOT in any *-sem* category tree.
     *
     * @return array
     */
    public static function get_available_courses(): array {
        global $DB;

        $excludeids = self::get_all_sem_category_ids();

        if (empty($excludeids)) {
            $sql = "SELECT c.id, c.fullname, c.shortname, c.visible, cc.name AS categoryname
                      FROM {course} c
                      JOIN {course_categories} cc ON cc.id = c.category
                     WHERE c.id != :siteid
                  ORDER BY cc.name, c.fullname";
            return $DB->get_records_sql($sql, ['siteid' => SITEID]);
        }

        list($notin, $notinparams) = $DB->get_in_or_equal($excludeids, SQL_PARAMS_NAMED, 'exc', false);
        $sql = "SELECT c.id, c.fullname, c.shortname, c.visible, cc.name AS categoryname
                  FROM {course} c
                  JOIN {course_categories} cc ON cc.id = c.category
                 WHERE c.id != :siteid
                   AND c.category {$notin}
              ORDER BY cc.name, c.fullname";
        return $DB->get_records_sql($sql, array_merge(['siteid' => SITEID], $notinparams));
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
            $options[''] = get_string('nosemester', 'local_timetable_management');
        }
        for ($i = 1; $i <= self::TOTAL_SEMESTERS; $i++) {
            $options[$i] = self::get_semester_name($i);
        }
        return $options;
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
     * @return void
     */
    public static function set_course_semester(int $courseid, int $semester): void {
        global $DB;

        if (!self::is_valid_semester($semester)) {
            return;
        }

        $now = time();
        $record = $DB->get_record('local_semester_course', ['courseid' => $courseid]);

        if ($record) {
            $record->semester = $semester;
            $record->timemodified = $now;
            $DB->update_record('local_semester_course', $record);
        } else {
            $record = (object) [
                'courseid' => $courseid,
                'semester' => $semester,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $DB->insert_record('local_semester_course', $record);
        }
    }

    /**
     * Remove course semester assignment.
     *
     * @param int $courseid
     * @return void
     */
    public static function remove_course_semester(int $courseid): void {
        global $DB;

        $DB->delete_records('local_semester_course', ['courseid' => $courseid]);
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

    /**
     * Get all course categories matching '*-semN' for a given semester number.
     * Returns each category with its parent (department) name.
     *
     * @param int $semester
     * @return array
     */
    public static function get_semester_programs(int $semester): array {
        global $DB;

        $pattern = '%-sem' . $semester;
        $sql = "SELECT cc.id, cc.name, cc.parent, cc.path,
                       COALESCE(parent.name, '') AS deptname
                  FROM {course_categories} cc
             LEFT JOIN {course_categories} parent ON parent.id = cc.parent
                 WHERE " . $DB->sql_like('cc.name', ':pattern', false) . "
              ORDER BY parent.name, cc.name";

        return $DB->get_records_sql($sql, ['pattern' => $pattern]);
    }

    /**
     * Count all courses in a category and all its descendant categories.
     *
     * @param int $categoryid
     * @param string $catpath The path value from course_categories.path (e.g. '/1/5/12')
     * @return int
     */
    public static function count_category_courses(int $categoryid, string $catpath): int {
        global $DB;

        $pathprefix = $DB->sql_like_escape($catpath) . '/%';
        $sql = "SELECT COUNT(c.id)
                  FROM {course} c
                  JOIN {course_categories} cc ON cc.id = c.category
                 WHERE c.id != :siteid
                   AND (cc.id = :catid OR " . $DB->sql_like('cc.path', ':pathprefix', false) . ")";

        return (int)$DB->count_records_sql($sql, [
            'siteid' => SITEID,
            'catid' => $categoryid,
            'pathprefix' => $pathprefix,
        ]);
    }

    /**
     * Get courses physically in a category tree (assigned by category, no separate DB needed).
     *
     * @param int $semester Unused — kept for signature compatibility
     * @param int $categoryid
     * @param string $catpath
     * @return array
     */
    public static function get_semester_courses_in_category(int $semester, int $categoryid, string $catpath): array {
        global $DB;

        $pathprefix = $DB->sql_like_escape($catpath) . '/%';
        $sql = "SELECT c.id, c.fullname, c.shortname, c.visible, cc.name AS categoryname
                  FROM {course} c
                  JOIN {course_categories} cc ON cc.id = c.category
                 WHERE c.id != :siteid
                   AND (cc.id = :catid OR " . $DB->sql_like('cc.path', ':pathprefix', false) . ")
              ORDER BY cc.name, c.fullname";

        return $DB->get_records_sql($sql, [
            'siteid' => SITEID,
            'catid' => $categoryid,
            'pathprefix' => $pathprefix,
        ]);
    }

    /**
     * Get courses NOT in any *-sem* category tree (global available pool).
     * categoryid/catpath params kept for signature compatibility but unused.
     *
     * @param int $categoryid Unused
     * @param string $catpath Unused
     * @return array
     */
    public static function get_available_courses_in_category(int $categoryid, string $catpath): array {
        return self::get_available_courses();
    }

    /**
     * Get departments that contain semester program categories.
     *
     * @return array
     */
    public static function get_timetable_departments(): array {
        global $DB;

        $sql = "SELECT DISTINCT parent.id, parent.name, parent.path
                  FROM {course_categories} semcat
                  JOIN {course_categories} parent ON parent.id = semcat.parent
                 WHERE " . $DB->sql_like('semcat.name', ':pattern', false) . "
              ORDER BY parent.name";

        return $DB->get_records_sql($sql, ['pattern' => '%-sem%']);
    }

    /**
     * Get enabled semesters.
     *
     * @return array
     */
    public static function get_active_semesters(): array {
        $semesters = [];

        foreach (self::get_all_semester_statuses() as $status) {
            if (!empty($status->enabled)) {
                $semester = (int) $status->semester;
                $semesters[$semester] = (object) [
                    'semester' => $semester,
                    'name' => self::get_semester_name($semester),
                ];
            }
        }

        ksort($semesters);
        return $semesters;
    }

    /**
     * Get the admission department record that best matches a timetable department category.
     *
     * @param int $categoryid
     * @return object|null
     */
    public static function get_admission_department_for_category(int $categoryid): ?object {
        global $DB;

        $category = $DB->get_record('course_categories', ['id' => $categoryid], 'id, name');
        if (!$category) {
            return null;
        }

        $programbases = [];
        $programcategories = $DB->get_records_sql(
            "SELECT name
               FROM {course_categories}
              WHERE parent = :parent
                AND " . $DB->sql_like('name', ':pattern', false),
            ['parent' => $categoryid, 'pattern' => '%-sem%']
        );

        foreach ($programcategories as $programcategory) {
            $basename = preg_replace('/-sem\d+$/i', '', trim($programcategory->name));
            if ($basename !== '') {
                $programbases[self::normalise_matching_text($basename)] = true;
            }
        }

        $departments = $DB->get_records('local_admission_departments', ['enabled' => 1]);
        $bestmatch = null;
        $bestscore = 0;
        $categoryname = self::normalise_matching_text($category->name);

        foreach ($departments as $department) {
            $score = 0;
            if (self::normalise_matching_text($department->name) === $categoryname) {
                $score += 100;
            }

            $programs = json_decode($department->programs ?? '[]', true);
            if (is_array($programs)) {
                foreach ($programs as $program) {
                    $programname = self::normalise_matching_text((string) ($program['name'] ?? ''));
                    $shortname = self::normalise_matching_text((string) ($program['shortname'] ?? ''));
                    if ($programname !== '' && isset($programbases[$programname])) {
                        $score += 10;
                    }
                    if ($shortname !== '' && isset($programbases[$shortname])) {
                        $score += 10;
                    }
                }
            }

            if ($score > $bestscore) {
                $bestscore = $score;
                $bestmatch = $department;
            }
        }

        return $bestscore > 0 ? $bestmatch : null;
    }

    /**
     * Normalise text for fuzzy matching.
     *
     * @param string $value
     * @return string
     */
    public static function normalise_matching_text(string $value): string {
        $value = \core_text::strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '', $value);
        return (string) $value;
    }

    /**
     * Get active semester program categories for a department.
     *
     * @param int $departmentid
     * @return array
     */
    public static function get_department_active_programs(int $departmentid): array {
        global $DB;

        $sql = "SELECT id, parent, name, path
                  FROM {course_categories}
                 WHERE parent = :parent
                   AND " . $DB->sql_like('name', ':pattern', false) . "
              ORDER BY name";

        $records = $DB->get_records_sql($sql, ['parent' => $departmentid, 'pattern' => '%-sem%']);
        $programs = [];

        foreach ($records as $record) {
            $semester = self::extract_semester_from_category_name($record->name);
            if (!$semester || !self::is_semester_enabled($semester)) {
                continue;
            }

            $record->semester = $semester;
            $record->semestername = self::get_semester_name($semester);
            $programs[$record->id] = $record;
        }

        uasort($programs, function($a, $b) {
            if ($a->semester === $b->semester) {
                return strcmp($a->name, $b->name);
            }
            return $a->semester <=> $b->semester;
        });

        return $programs;
    }

    /**
     * Extract semester number from category name.
     *
     * @param string $categoryname
     * @return int
     */
    public static function extract_semester_from_category_name(string $categoryname): int {
        if (preg_match('/-sem(\d+)$/i', $categoryname, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    /**
     * Get timetable configuration for a department.
     *
     * @param int $departmentid
     * @return object
     */
    public static function get_department_timetable_config(int $departmentid): object {
        global $DB;

        $config = $DB->get_record('local_timetable_cfg', ['departmentid' => $departmentid]);
        if ($config) {
            $config->workingdays = self::decode_json_array($config->workingdays);
            $config->sectiontimes = self::normalise_section_time_grid(
                self::decode_json_array($config->sectiontimes),
                $config->workingdays,
                (int) $config->sessioncount,
                (int) $config->daystartmins,
                (int) $config->sessionlength,
                (int) $config->breakafter,
                (int) $config->breaklength
            );
            $config->workingdays = self::normalise_working_days(
                $config->workingdays,
                count($config->sectiontimes)
            );
            $config->sessioncount = self::get_section_count_from_grid($config->sectiontimes);
            return $config;
        }

        return (object) [
            'departmentid' => $departmentid,
            'sessioncount' => self::DEFAULT_SESSION_COUNT,
            'daystartmins' => self::DEFAULT_DAY_START_MINS,
            'sessionlength' => self::DEFAULT_SESSION_LENGTH,
            'breakafter' => self::DEFAULT_BREAK_AFTER,
            'breaklength' => self::DEFAULT_BREAK_LENGTH,
            'workingdays' => self::DEFAULT_WORKING_DAYS,
            'sectiontimes' => self::build_default_section_time_grid(),
        ];
    }

    /**
     * Get shared timetable configuration for all departments.
     *
     * @return object
     */
    public static function get_global_timetable_config(): object {
        global $DB;

        $config = $DB->get_record('local_timetable_globalcfg', [], '*', IGNORE_MULTIPLE);
        if ($config) {
            $config->workingdays = self::decode_json_array($config->workingdays);
            $config->sectiontimes = self::normalise_section_time_grid(
                self::decode_json_array($config->sectiontimes),
                $config->workingdays,
                (int) $config->sessioncount,
                (int) $config->daystartmins,
                (int) $config->sessionlength,
                (int) $config->breakafter,
                (int) $config->breaklength
            );
            $config->workingdays = self::normalise_working_days($config->workingdays, count($config->sectiontimes));
            $config->sessioncount = self::get_section_count_from_grid($config->sectiontimes);
            return $config;
        }

        return (object) [
            'id' => 1,
            'sessioncount' => self::DEFAULT_SESSION_COUNT,
            'daystartmins' => self::DEFAULT_DAY_START_MINS,
            'sessionlength' => self::DEFAULT_SESSION_LENGTH,
            'breakafter' => self::DEFAULT_BREAK_AFTER,
            'breaklength' => self::DEFAULT_BREAK_LENGTH,
            'workingdays' => self::DEFAULT_WORKING_DAYS,
            'sectiontimes' => self::build_default_section_time_grid(),
        ];
    }

    /**
     * Save shared timetable configuration for all departments.
     *
     * @param array $workingdays
     * @param array $sectiontimes
     * @return void
     */
    public static function save_global_timetable_config(array $workingdays, array $sectiontimes): void {
        global $DB;

        $now = time();
        $record = $DB->get_record('local_timetable_globalcfg', [], '*', IGNORE_MULTIPLE);
        $sectiontimes = self::normalise_section_time_grid($sectiontimes, $workingdays);
        $workingdays = self::normalise_working_days($workingdays, count($sectiontimes));
        $sessioncount = self::get_section_count_from_grid($sectiontimes);
        $legacy = self::derive_legacy_time_values($sectiontimes);

        $data = [
            'sessioncount' => $sessioncount,
            'daystartmins' => $legacy->daystartmins,
            'sessionlength' => $legacy->sessionlength,
            'breakafter' => $legacy->breakafter,
            'breaklength' => $legacy->breaklength,
            'workingdays' => json_encode(array_values($workingdays)),
            'sectiontimes' => json_encode(array_values($sectiontimes)),
            'timemodified' => $now,
        ];

        if ($record) {
            foreach ($data as $field => $value) {
                $record->{$field} = $value;
            }
            $DB->update_record('local_timetable_globalcfg', $record);
        } else {
            $data['timecreated'] = $now;
            $DB->insert_record('local_timetable_globalcfg', (object) $data);
        }
    }

    /**
     * Save timetable configuration for a department.
     *
     * @param int $departmentid
     * @param array $workingdays
     * @param array $sectiontimes
     * @return void
     */
    public static function save_department_timetable_config(
        int $departmentid,
        array $workingdays,
        array $sectiontimes
    ): void {
        global $DB;

        $now = time();
        $record = $DB->get_record('local_timetable_cfg', ['departmentid' => $departmentid]);
        $sectiontimes = self::normalise_section_time_grid($sectiontimes, $workingdays);
        $workingdays = self::normalise_working_days($workingdays, count($sectiontimes));
        $sessioncount = self::get_section_count_from_grid($sectiontimes);
        $legacy = self::derive_legacy_time_values($sectiontimes);

        $data = [
            'departmentid' => $departmentid,
            'sessioncount' => $sessioncount,
            'daystartmins' => $legacy->daystartmins,
            'sessionlength' => $legacy->sessionlength,
            'breakafter' => $legacy->breakafter,
            'breaklength' => $legacy->breaklength,
            'workingdays' => json_encode(array_values($workingdays)),
            'sectiontimes' => json_encode(array_values($sectiontimes)),
            'timemodified' => $now,
        ];

        if ($record) {
            foreach ($data as $field => $value) {
                $record->{$field} = $value;
            }
            $DB->update_record('local_timetable_cfg', $record);
        } else {
            $data['timecreated'] = $now;
            $DB->insert_record('local_timetable_cfg', (object) $data);
        }
    }

    /**
     * Build default timetable grid.
     *
     * @return array
     */
    public static function build_default_section_time_grid(): array {
        $grid = [];

        foreach (self::DEFAULT_WORKING_DAYS as $unused => $label) {
            $grid[] = self::build_default_day_slots();
        }

        return $grid;
    }

    /**
     * Build default slots for one day.
     *
     * @return array
     */
    public static function build_default_day_slots(): array {
        $slots = [];
        $startmins = self::DEFAULT_DAY_START_MINS;

        for ($index = 1; $index <= self::DEFAULT_SESSION_COUNT; $index++) {
            $endmins = $startmins + self::DEFAULT_SESSION_LENGTH;
            $slots[] = [
                'from' => self::format_minutes($startmins),
                'to' => self::format_minutes($endmins),
            ];

            $startmins = $endmins;
            if ($index === self::DEFAULT_BREAK_AFTER) {
                $startmins += self::DEFAULT_BREAK_LENGTH;
            }
        }

        return $slots;
    }

    /**
     * Normalise working day labels.
     *
     * @param array $workingdays
     * @param int|null $requiredcount
     * @return array
     */
    public static function normalise_working_days(array $workingdays, ?int $requiredcount = null): array {
        $labels = [];

        foreach (array_values($workingdays) as $index => $label) {
            $label = trim((string) $label);
            $labels[] = $label !== '' ? $label : get_string('workingday', 'local_timetable_management') . ' ' . ($index + 1);
        }

        if (empty($labels)) {
            $labels = self::DEFAULT_WORKING_DAYS;
        }

        if ($requiredcount !== null) {
            while (count($labels) < $requiredcount) {
                $labels[] = get_string('workingday', 'local_timetable_management') . ' ' . (count($labels) + 1);
            }
            if (count($labels) > $requiredcount) {
                $labels = array_slice($labels, 0, $requiredcount);
            }
        }

        return array_values($labels);
    }

    /**
     * Normalise section time grid.
     *
     * @param array $sectiontimes
     * @param array $workingdays
     * @param int|null $fallbacksessioncount
     * @param int|null $daystartmins
     * @param int|null $sessionlength
     * @param int|null $breakafter
     * @param int|null $breaklength
     * @return array
     */
    public static function normalise_section_time_grid(
        array $sectiontimes,
        array $workingdays = [],
        ?int $fallbacksessioncount = null,
        ?int $daystartmins = null,
        ?int $sessionlength = null,
        ?int $breakafter = null,
        ?int $breaklength = null
    ): array {
        $daycount = max(count($workingdays), count($sectiontimes), 1);
        $defaults = self::build_default_section_time_grid_from_values(
            $daycount,
            $fallbacksessioncount ?? self::DEFAULT_SESSION_COUNT,
            $daystartmins ?? self::DEFAULT_DAY_START_MINS,
            $sessionlength ?? self::DEFAULT_SESSION_LENGTH,
            $breakafter ?? self::DEFAULT_BREAK_AFTER,
            $breaklength ?? self::DEFAULT_BREAK_LENGTH
        );

        $normalised = [];
        $sectioncount = 0;
        foreach ($sectiontimes as $dayslots) {
            if (is_array($dayslots)) {
                $sectioncount = max($sectioncount, count($dayslots));
            }
        }
        $sectioncount = max($sectioncount, $fallbacksessioncount ?? 0, 1);

        for ($dayindex = 0; $dayindex < $daycount; $dayindex++) {
            $normalised[$dayindex] = [];
            for ($sectionindex = 0; $sectionindex < $sectioncount; $sectionindex++) {
                $defaultslot = $defaults[$dayindex][$sectionindex] ?? ['from' => '', 'to' => ''];
                $currentslot = $sectiontimes[$dayindex][$sectionindex] ?? [];
                $from = trim((string) ($currentslot['from'] ?? $defaultslot['from']));
                $to = trim((string) ($currentslot['to'] ?? $defaultslot['to']));
                $normalised[$dayindex][$sectionindex] = ['from' => $from, 'to' => $to];
            }
        }

        return $normalised;
    }

    /**
     * Build default grid using legacy config values.
     *
     * @param int $daycount
     * @param int $sessioncount
     * @param int $daystartmins
     * @param int $sessionlength
     * @param int $breakafter
     * @param int $breaklength
     * @return array
     */
    public static function build_default_section_time_grid_from_values(
        int $daycount,
        int $sessioncount,
        int $daystartmins,
        int $sessionlength,
        int $breakafter,
        int $breaklength
    ): array {
        $grid = [];
        for ($dayindex = 0; $dayindex < $daycount; $dayindex++) {
            $slots = [];
            $startmins = $daystartmins;
            for ($sectionindex = 1; $sectionindex <= $sessioncount; $sectionindex++) {
                $endmins = $startmins + $sessionlength;
                $slots[] = [
                    'from' => self::format_minutes($startmins),
                    'to' => self::format_minutes($endmins),
                ];
                $startmins = $endmins;
                if ($breakafter > 0 && $sectionindex === $breakafter) {
                    $startmins += $breaklength;
                }
            }
            $grid[] = $slots;
        }

        return $grid;
    }

    /**
     * Get section count from grid.
     *
     * @param array $sectiontimes
     * @return int
     */
    public static function get_section_count_from_grid(array $sectiontimes): int {
        $count = 0;
        foreach ($sectiontimes as $dayslots) {
            if (is_array($dayslots)) {
                $count = max($count, count($dayslots));
            }
        }
        return max($count, 1);
    }

    /**
     * Add an extra working day.
     *
     * @param array $workingdays
     * @param array $sectiontimes
     * @return object
     */
    public static function add_working_day(array $workingdays, array $sectiontimes): object {
        $workingdays = self::normalise_working_days($workingdays);
        $sectiontimes = self::normalise_section_time_grid($sectiontimes, $workingdays);
        $sectioncount = self::get_section_count_from_grid($sectiontimes);
        $workingdays[] = get_string('workingday', 'local_timetable_management') . ' ' . (count($workingdays) + 1);
        $sectiontimes[] = self::build_default_section_time_grid_from_values(1, $sectioncount,
            self::DEFAULT_DAY_START_MINS, self::DEFAULT_SESSION_LENGTH,
            self::DEFAULT_BREAK_AFTER, self::DEFAULT_BREAK_LENGTH)[0];

        return (object) ['workingdays' => $workingdays, 'sectiontimes' => $sectiontimes];
    }

    /**
     * Remove the last working day.
     *
     * @param array $workingdays
     * @param array $sectiontimes
     * @return object
     */
    public static function remove_working_day(array $workingdays, array $sectiontimes): object {
        $workingdays = self::normalise_working_days($workingdays);
        $sectiontimes = self::normalise_section_time_grid($sectiontimes, $workingdays);
        if (count($workingdays) > 1) {
            array_pop($workingdays);
            array_pop($sectiontimes);
        }

        return (object) ['workingdays' => array_values($workingdays), 'sectiontimes' => array_values($sectiontimes)];
    }

    /**
     * Add an extra section column.
     *
     * @param array $workingdays
     * @param array $sectiontimes
     * @return object
     */
    public static function add_section(array $workingdays, array $sectiontimes): object {
        $sectiontimes = self::normalise_section_time_grid($sectiontimes, $workingdays);
        foreach ($sectiontimes as $dayindex => $dayslots) {
            $lastslot = end($dayslots);
            $nextfrom = !empty($lastslot['to']) ? $lastslot['to'] : (!empty($lastslot['from']) ? $lastslot['from'] : '');
            $nextto = '';
            if ($nextfrom !== '') {
                $nextmins = self::parse_time_to_minutes($nextfrom);
                if ($nextmins >= 0) {
                    $nextto = self::format_minutes($nextmins + self::DEFAULT_SESSION_LENGTH);
                }
            }
            $sectiontimes[$dayindex][] = ['from' => $nextfrom, 'to' => $nextto];
        }

        return (object) ['workingdays' => self::normalise_working_days($workingdays, count($sectiontimes)), 'sectiontimes' => $sectiontimes];
    }

    /**
     * Remove the last section column.
     *
     * @param array $workingdays
     * @param array $sectiontimes
     * @return object
     */
    public static function remove_section(array $workingdays, array $sectiontimes): object {
        $sectiontimes = self::normalise_section_time_grid($sectiontimes, $workingdays);
        if (self::get_section_count_from_grid($sectiontimes) > 1) {
            foreach ($sectiontimes as $dayindex => $dayslots) {
                array_pop($sectiontimes[$dayindex]);
            }
        }

        return (object) ['workingdays' => self::normalise_working_days($workingdays, count($sectiontimes)), 'sectiontimes' => $sectiontimes];
    }

    /**
     * Format time range label.
     *
     * @param int $startmins
     * @param int $endmins
     * @return string
     */
    public static function format_time_range(int $startmins, int $endmins): string {
        return self::format_minutes($startmins) . ' - ' . self::format_minutes($endmins);
    }

    /**
     * Format minutes from midnight.
     *
     * @param int $minutes
     * @return string
     */
    public static function format_minutes(int $minutes): string {
        $hours = (int) floor($minutes / 60);
        $mins = $minutes % 60;

        return sprintf('%02d:%02d', $hours, $mins);
    }

    /**
     * Convert H:i time into minutes from midnight.
     *
     * @param string $timevalue
     * @return int
     */
    public static function parse_time_to_minutes(string $timevalue): int {
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', $timevalue, $matches)) {
            return -1;
        }

        $hours = (int) $matches[1];
        $minutes = (int) $matches[2];
        if ($hours < 0 || $hours > 23 || $minutes < 0 || $minutes > 59) {
            return -1;
        }

        return ($hours * 60) + $minutes;
    }

    /**
     * Validate section time grid.
     *
     * @param array $sectiontimes
     * @return bool
     */
    public static function validate_section_times(array $sectiontimes): bool {
        foreach ($sectiontimes as $dayslots) {
            foreach ($dayslots as $slot) {
                $from = self::parse_time_to_minutes((string) ($slot['from'] ?? ''));
                $to = self::parse_time_to_minutes((string) ($slot['to'] ?? ''));
                if ($from < 0 || $to < 0 || $to <= $from) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Get display label for a slot.
     *
     * @param array $slot
     * @return string
     */
    public static function get_slot_label(array $slot): string {
        $from = trim((string) ($slot['from'] ?? ''));
        $to = trim((string) ($slot['to'] ?? ''));
        if ($from === '' || $to === '') {
            return '';
        }

        return $from . ' - ' . $to;
    }

    /**
     * Get label for a college master timetable course type.
     *
     * @param string $coursetype
     * @return string
     */
    public static function get_master_course_type_label(string $coursetype): string {
        $map = [
            'minor1' => get_string('coursetypeminor1', 'local_timetable_management'),
            'minor2' => get_string('coursetypeminor2', 'local_timetable_management'),
            'mdc' => get_string('coursetypemdc', 'local_timetable_management'),
        ];

        return $map[$coursetype] ?? $coursetype;
    }

    /**
     * Get supported college master timetable course types.
     *
     * @return array
     */
    public static function get_master_course_type_options(): array {
        return [
            '' => get_string('selectcoursetype', 'local_timetable_management'),
            'minor1' => self::get_master_course_type_label('minor1'),
            'minor2' => self::get_master_course_type_label('minor2'),
            'mdc' => self::get_master_course_type_label('mdc'),
        ];
    }

    /**
     * Decode JSON array field.
     *
     * @param mixed $value
     * @return array
     */
    public static function decode_json_array($value): array {
        if (empty($value)) {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Derive legacy timing values from the first day's grid.
     *
     * @param array $sectiontimes
     * @return object
     */
    public static function derive_legacy_time_values(array $sectiontimes): object {
        $defaults = (object) [
            'daystartmins' => self::DEFAULT_DAY_START_MINS,
            'sessionlength' => self::DEFAULT_SESSION_LENGTH,
            'breakafter' => self::DEFAULT_BREAK_AFTER,
            'breaklength' => self::DEFAULT_BREAK_LENGTH,
        ];

        if (empty($sectiontimes[0][0]['from']) || empty($sectiontimes[0][0]['to'])) {
            return $defaults;
        }

        $firstfrom = self::parse_time_to_minutes($sectiontimes[0][0]['from']);
        $firstto = self::parse_time_to_minutes($sectiontimes[0][0]['to']);
        if ($firstfrom < 0 || $firstto <= $firstfrom) {
            return $defaults;
        }

        $sessionlength = $firstto - $firstfrom;
        $breakafter = 0;
        $breaklength = 0;

        for ($index = 1; $index < count($sectiontimes[0]); $index++) {
            $prevto = self::parse_time_to_minutes($sectiontimes[0][$index - 1]['to']);
            $currfrom = self::parse_time_to_minutes($sectiontimes[0][$index]['from']);
            if ($prevto >= 0 && $currfrom > $prevto) {
                $breakafter = $index;
                $breaklength = $currfrom - $prevto;
                break;
            }
        }

        return (object) [
            'daystartmins' => $firstfrom,
            'sessionlength' => $sessionlength,
            'breakafter' => $breakafter,
            'breaklength' => $breaklength,
        ];
    }

    /**
     * Get saved college master timetable slots.
     *
     * @param int|null $semester
     * @return array
     */
    public static function get_master_timetable_slots(?int $semester = null): array {
        global $DB;

        $conditions = [];
        if ($semester !== null) {
            $conditions['semester'] = $semester;
        }

        $records = $DB->get_records('local_timetable_master_slot', $conditions);
        $slots = [];

        foreach ($records as $record) {
            $slots[(int) $record->semester][(int) $record->weekday][(int) $record->sessionindex] = $record;
        }

        return $slots;
    }

    /**
     * Get master timetable slots indexed only by weekday and session for a semester.
     *
     * @param int $semester
     * @return array
     */
    public static function get_master_timetable_slots_for_semester(int $semester): array {
        $allslots = self::get_master_timetable_slots($semester);
        return $allslots[$semester] ?? [];
    }

    /**
     * Save college master timetable slots for a semester.
     *
     * @param int $semester
     * @param array $entries
     * @return void
     */
    public static function save_master_timetable_slots(int $semester, array $entries): void {
        global $DB;

        $existing = $DB->get_records('local_timetable_master_slot', ['semester' => $semester]);
        $existingmap = [];
        foreach ($existing as $record) {
            $key = implode(':', [(int) $record->weekday, (int) $record->sessionindex]);
            $existingmap[$key] = $record;
        }

        $validtypes = array_flip(['minor1', 'minor2', 'mdc']);
        $now = time();

        foreach ($entries as $entry) {
            $weekday = (int) ($entry['weekday'] ?? 0);
            $sessionindex = (int) ($entry['sessionindex'] ?? 0);
            $coursetype = trim((string) ($entry['coursetype'] ?? ''));
            $key = implode(':', [$weekday, $sessionindex]);

            if ($weekday < 1 || $sessionindex < 1) {
                continue;
            }

            if ($coursetype === '' || !isset($validtypes[$coursetype])) {
                if (isset($existingmap[$key])) {
                    $DB->delete_records('local_timetable_master_slot', ['id' => $existingmap[$key]->id]);
                    unset($existingmap[$key]);
                }
                continue;
            }

            if (isset($existingmap[$key])) {
                $record = $existingmap[$key];
                unset($existingmap[$key]);
                $record->coursetype = $coursetype;
                $record->timemodified = $now;
                $DB->update_record('local_timetable_master_slot', $record);
            } else {
                $record = (object) [
                    'semester' => $semester,
                    'weekday' => $weekday,
                    'sessionindex' => $sessionindex,
                    'coursetype' => $coursetype,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ];
                $DB->insert_record('local_timetable_master_slot', $record);
            }

            self::clear_department_entries_for_master_slot($semester, $weekday, $sessionindex);
        }

        foreach ($existingmap as $record) {
            $DB->delete_records('local_timetable_master_slot', ['id' => $record->id]);
        }
    }

    /**
     * Remove department timetable entries that conflict with a college master slot.
     *
     * @param int $semester
     * @param int $weekday
     * @param int $sessionindex
     * @return void
     */
    public static function clear_department_entries_for_master_slot(int $semester, int $weekday, int $sessionindex): void {
        global $DB;

        $params = [
            'weekday' => $weekday,
            'sessionindex' => $sessionindex,
            'pattern' => '%-sem' . $semester,
        ];
        $sql = "SELECT ts.id
                  FROM {local_timetable_slot} ts
                  JOIN {course_categories} cc ON cc.id = ts.semcategoryid
                  JOIN {course_categories} dept ON dept.id = cc.parent
                 WHERE ts.weekday = :weekday
                   AND ts.sessionindex = :sessionindex
                   AND " . $DB->sql_like('cc.name', ':pattern', false);

        $excludeddepartments = self::get_excluded_departments($semester);
        if (!empty($excludeddepartments)) {
            list($notinsql, $notinparams) = $DB->get_in_or_equal($excludeddepartments, SQL_PARAMS_NAMED, 'dept', false);
            $sql .= " AND dept.id {$notinsql}";
            $params = array_merge($params, $notinparams);
        }

        $ids = $DB->get_fieldset_sql($sql, $params);

        if (!empty($ids)) {
            list($insql, $params) = $DB->get_in_or_equal(array_map('intval', $ids), SQL_PARAMS_NAMED);
            $DB->delete_records_select('local_timetable_slot', 'id ' . $insql, $params);
        }
    }

    /**
     * Get departments excluded from master timetable for a semester.
     *
     * @param int $semester
     * @return array
     */
    public static function get_excluded_departments(int $semester): array {
        global $DB;
        $records = $DB->get_records('local_timetable_master_excl', ['semester' => $semester], 'departmentid ASC', 'departmentid');
        return array_map('intval', array_keys($records));
    }

    /**
     * Set excluded departments for a semester.
     *
     * @param int $semester
     * @param array $departmentids Array of department IDs to exclude
     * @return void
     */
    public static function set_excluded_departments(int $semester, array $departmentids): void {
        global $DB;

        $DB->delete_records('local_timetable_master_excl', ['semester' => $semester]);

        $departmentids = array_values(array_unique(array_filter(array_map('intval', $departmentids))));
        foreach ($departmentids as $deptid) {
            $record = new \stdClass();
            $record->semester = $semester;
            $record->departmentid = $deptid;
            $DB->insert_record('local_timetable_master_excl', $record);
        }
    }

    public static function get_master_slots_for_programs(array $programs): array {
        if (empty($programs)) {
            return [];
        }

        $masterslots = self::get_master_timetable_slots();
        $result = [];
        $excludedbysemester = [];

        foreach ($programs as $program) {
            $semester = (int) ($program->semester ?? 0);
            $departmentid = (int) ($program->parent ?? 0);
            if ($semester < 1) {
                continue;
            }

            if (!array_key_exists($semester, $excludedbysemester)) {
                $excludedbysemester[$semester] = self::get_excluded_departments($semester);
            }

            $excluded = $excludedbysemester[$semester];
            if ($departmentid > 0 && in_array($departmentid, $excluded, true)) {
                continue;
            }

            if (isset($masterslots[$semester])) {
                $result[(int) $program->id] = $masterslots[$semester];
            }
        }

        return $result;
    }

    /**
     * Get excluded department names for a semester.
     *
     * @param int $semester
     * @return array
     */
    public static function get_excluded_department_records(int $semester): array {
        global $DB;

        $sql = "SELECT cc.id, cc.name
                  FROM {local_timetable_master_excl} mex
                  JOIN {course_categories} cc ON cc.id = mex.departmentid
                 WHERE mex.semester = :semester
              ORDER BY cc.name ASC";

        return array_values($DB->get_records_sql($sql, ['semester' => $semester]));
    }

    /**
     * Get published minor and MDC courses for a semester grouped by type.
     *
     * @param int $semester
     * @return array
     */
    public static function get_master_courses_by_semester(int $semester): array {
        global $DB;

        $sql = "SELECT c.id, c.name, c.code, c.coursetype, d.name AS departmentname
                  FROM {local_mca_courses} c
             LEFT JOIN {local_admission_departments} d ON d.id = c.programid
                 WHERE c.semesterno = :semester
                   AND c.coursetype IN ('minor1', 'minor2', 'mdc')
              ORDER BY c.coursetype ASC, d.name ASC, c.name ASC";

        $records = $DB->get_records_sql($sql, ['semester' => $semester]);
        $grouped = [
            'minor1' => [],
            'minor2' => [],
            'mdc' => [],
        ];

        foreach ($records as $record) {
            $departmentname = trim((string) ($record->departmentname ?? ''));
            $label = trim($record->code . ' - ' . $record->name);
            if ($departmentname !== '') {
                $label .= ' (' . $departmentname . ')';
            }
            $grouped[$record->coursetype][] = $label;
        }

        return $grouped;
    }

    /**
     * Get master timetable tooltip text for semester course types.
     *
     * @param int $semester
     * @return array
     */
    public static function get_master_course_tooltips_by_semester(int $semester): array {
        $courses = self::get_master_courses_by_semester($semester);
        $tooltips = [];

        foreach ($courses as $coursetype => $labels) {
            $tooltips[$coursetype] = empty($labels)
                ? ''
                : get_string('availablecoursesfortype', 'local_timetable_management', implode(', ', $labels));
        }

        return $tooltips;
    }

    /**
     * Get saved timetable entries for a department.
     *
     * @param int $departmentid
     * @return array
     */
    public static function get_department_timetable_entries(int $departmentid): array {
        global $DB;

        $records = $DB->get_records('local_timetable_slot', ['departmentid' => $departmentid]);
        $entries = [];

        foreach ($records as $record) {
            $entries[$record->weekday][$record->semcategoryid][$record->sessionindex] = $record;
        }

        return $entries;
    }

    /**
     * Save timetable entries for a department.
     *
     * @param int $departmentid
     * @param array $entries
     * @return void
     */
    public static function save_department_timetable_entries(int $departmentid, array $entries): void {
        global $DB;

        $existing = $DB->get_records('local_timetable_slot', ['departmentid' => $departmentid]);
        $existingmap = [];

        foreach ($existing as $record) {
            $key = implode(':', [$record->weekday, $record->semcategoryid, $record->sessionindex]);
            $existingmap[$key] = $record;
        }

        $now = time();
        foreach ($entries as $entry) {
            $key = implode(':', [$entry['weekday'], $entry['semcategoryid'], $entry['sessionindex']]);
            $courseid = empty($entry['courseid']) ? null : (int) $entry['courseid'];

            if (isset($existingmap[$key])) {
                $record = $existingmap[$key];
                unset($existingmap[$key]);

                if ($courseid === null) {
                    $DB->delete_records('local_timetable_slot', ['id' => $record->id]);
                    continue;
                }

                $record->courseid = $courseid;
                $record->timemodified = $now;
                $DB->update_record('local_timetable_slot', $record);
                continue;
            }

            if ($courseid === null) {
                continue;
            }

            $record = (object) [
                'departmentid' => $departmentid,
                'semcategoryid' => (int) $entry['semcategoryid'],
                'weekday' => (int) $entry['weekday'],
                'sessionindex' => (int) $entry['sessionindex'],
                'courseid' => $courseid,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $DB->insert_record('local_timetable_slot', $record);
        }

        foreach ($existingmap as $record) {
            $DB->delete_records('local_timetable_slot', ['id' => $record->id]);
        }
    }

    /**
     * Generate attendance sessions from timetable entries for a date range.
     *
     * @param int $departmentid
     * @param int $startdate
     * @param int $durationdays
     * @return object
     */
    public static function sync_department_attendance_sessions(
        int $departmentid,
        int $startdate,
        int $durationdays
    ): object {
        global $CFG, $DB;

        if (!self::is_attendance_plugin_available()) {
            throw new \moodle_exception('attendancepluginmissing', 'local_timetable_management');
        }

        require_once($CFG->dirroot . '/mod/attendance/externallib.php');

        $summary = (object) [
            'coursesprocessed' => 0,
            'attendancecreated' => 0,
            'sessioncandidates' => 0,
            'sessionscreated' => 0,
            'duplicateskipped' => 0,
            'timeskipped' => 0,
            'errors' => [],
        ];

        $durationdays = max(1, $durationdays);
        $entries = self::get_department_timetable_entries($departmentid);
        if (empty($entries)) {
            return $summary;
        }

        $programs = self::get_department_active_programs($departmentid);
        if (empty($programs)) {
            return $summary;
        }

        $config = self::get_global_timetable_config();
        $schedulebycourse = self::build_attendance_schedule_by_course(
            $entries,
            $programs,
            $config->workingdays,
            $config->sectiontimes,
            $startdate,
            $durationdays
        );

        if (empty($schedulebycourse)) {
            return $summary;
        }

        foreach ($schedulebycourse as $courseid => $sessions) {
            if (empty($sessions)) {
                continue;
            }

            $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname', IGNORE_MISSING);
            if (!$course) {
                $summary->errors[] = get_string('attendancecoursemissing', 'local_timetable_management', $courseid);
                continue;
            }

            try {
                $attendance = self::get_or_create_course_attendance_instance($courseid);
                $summary->coursesprocessed++;
                if (!empty($attendance->created)) {
                    $summary->attendancecreated++;
                }

                $existingsessions = self::get_existing_attendance_session_times(
                    (int) $attendance->attendanceid,
                    array_map(static function(object $session): int {
                        return (int) $session->sessiontime;
                    }, $sessions)
                );

                foreach ($sessions as $session) {
                    $summary->sessioncandidates++;
                    if (isset($existingsessions[$session->sessiontime])) {
                        $summary->duplicateskipped++;
                        continue;
                    }

                    \mod_attendance_external::add_session(
                        (int) $attendance->attendanceid,
                        $session->description,
                        (int) $session->sessiontime,
                        (int) $session->duration,
                        0,
                        true
                    );

                    $summary->sessionscreated++;
                    $existingsessions[$session->sessiontime] = true;
                }
            } catch (\Throwable $e) {
                $a = (object) [
                    'course' => format_string($course->fullname),
                    'message' => s($e->getMessage()),
                ];
                $summary->errors[] = get_string('attendancecourseerror', 'local_timetable_management', $a);
            }
        }

        return $summary;
    }

    /**
     * Check whether the attendance activity plugin is installed.
     *
     * @return bool
     */
    public static function is_attendance_plugin_available(): bool {
        return \core_component::get_plugin_directory('mod', 'attendance') !== null;
    }

    /**
     * Build attendance sessions grouped by course.
     *
     * @param array $entries
     * @param array $programs
     * @param array $workingdays
     * @param array $sectiontimes
     * @param int $startdate
     * @param int $durationdays
     * @return array
     */
    private static function build_attendance_schedule_by_course(
        array $entries,
        array $programs,
        array $workingdays,
        array $sectiontimes,
        int $startdate,
        int $durationdays
    ): array {
        $schedule = [];
        $dates = self::get_timetable_dates_in_range($workingdays, $startdate, $durationdays);

        foreach ($dates as $dateinfo) {
            $weekday = $dateinfo['weekday'];
            $daystart = $dateinfo['midnight'];

            if (empty($entries[$weekday])) {
                continue;
            }

            foreach ($programs as $programid => $program) {
                if (empty($entries[$weekday][$programid])) {
                    continue;
                }

                foreach ($entries[$weekday][$programid] as $sessionindex => $entry) {
                    $courseid = (int) ($entry->courseid ?? 0);
                    if ($courseid <= 0) {
                        continue;
                    }

                    $slot = $sectiontimes[$weekday - 1][$sessionindex - 1] ?? [];
                    $frommins = self::parse_time_to_minutes((string) ($slot['from'] ?? ''));
                    $tomins = self::parse_time_to_minutes((string) ($slot['to'] ?? ''));
                    if ($frommins < 0 || $tomins <= $frommins) {
                        continue;
                    }

                    $sessiontime = $daystart + ($frommins * MINSECS);
                    $schedule[$courseid][$sessiontime] = (object) [
                        'sessiontime' => $sessiontime,
                        'duration' => ($tomins - $frommins) * MINSECS,
                        'description' => get_string('attendancehourlabel', 'local_timetable_management', $sessionindex),
                    ];
                }
            }
        }

        foreach ($schedule as $courseid => $sessions) {
            ksort($sessions);
            $schedule[$courseid] = array_values($sessions);
        }

        return $schedule;
    }

    /**
     * Resolve which timetable weekdays should be used in a date range.
     *
     * @param array $workingdays
     * @param int $startdate
     * @param int $durationdays
     * @return array
     */
    private static function get_timetable_dates_in_range(array $workingdays, int $startdate, int $durationdays): array {
        $dates = [];
        $startmidnight = usergetmidnight($startdate);
        $mappedweekdays = self::map_working_days_to_weekdays($workingdays);
        $hasweekdaymap = in_array(true, array_map(static function($value): bool {
            return $value !== null;
        }, $mappedweekdays), true);

        for ($offset = 0; $offset < $durationdays; $offset++) {
            $currentmidnight = $startmidnight + ($offset * DAYSECS);
            $calendarweekday = (int) userdate($currentmidnight, '%u');
            $timetableweekday = null;

            if ($hasweekdaymap) {
                $matchedindex = array_search($calendarweekday, $mappedweekdays, true);
                if ($matchedindex !== false) {
                    $timetableweekday = $matchedindex + 1;
                }
            } else if (!empty($workingdays)) {
                $timetableweekday = ($offset % count($workingdays)) + 1;
            }

            if ($timetableweekday === null) {
                continue;
            }

            $dates[] = [
                'weekday' => $timetableweekday,
                'midnight' => $currentmidnight,
            ];
        }

        return $dates;
    }

    /**
     * Map configured working day labels to ISO weekday numbers where possible.
     *
     * @param array $workingdays
     * @return array
     */
    private static function map_working_days_to_weekdays(array $workingdays): array {
        $labels = [
            'monday' => 1,
            'mon' => 1,
            'tuesday' => 2,
            'tue' => 2,
            'tues' => 2,
            'wednesday' => 3,
            'wed' => 3,
            'thursday' => 4,
            'thu' => 4,
            'thur' => 4,
            'thurdsay' => 4,
            'thurs' => 4,
            'friday' => 5,
            'fri' => 5,
            'saturday' => 6,
            'sat' => 6,
            'sunday' => 7,
            'sun' => 7,
        ];

        $mapped = [];
        foreach ($workingdays as $label) {
            $normalised = \core_text::strtolower(trim((string) $label));
            $mapped[] = $labels[$normalised] ?? null;
        }

        return $mapped;
    }

    /**
     * Get the first attendance instance in a course or create one if missing.
     *
     * @param int $courseid
     * @return object
     */
    private static function get_or_create_course_attendance_instance(int $courseid): object {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/attendance/externallib.php');

        $sql = "SELECT a.id AS attendanceid, cm.id AS cmid
                  FROM {attendance} a
                  JOIN {course_modules} cm
                    ON cm.instance = a.id
                  JOIN {modules} m
                    ON m.id = cm.module
                 WHERE a.course = :courseid
                   AND m.name = :modname
              ORDER BY cm.id ASC";
        $record = $DB->get_record_sql($sql, ['courseid' => $courseid, 'modname' => 'attendance']);
        if ($record) {
            $record->created = false;
            return $record;
        }

        $created = \mod_attendance_external::add_attendance(
            $courseid,
            get_string('modulename', 'attendance'),
            '',
            NOGROUPS
        );

        $record = $DB->get_record('attendance', ['id' => $created['attendanceid']], 'id', MUST_EXIST);
        $record->attendanceid = (int) $record->id;
        unset($record->id);
        $cm = get_coursemodule_from_instance('attendance', $record->attendanceid, $courseid, false, MUST_EXIST);
        $record->cmid = $cm->id;
        $record->created = true;

        return $record;
    }

    /**
     * Get existing attendance session timestamps.
     *
     * @param int $attendanceid
     * @param array $sessiontimes
     * @return array
     */
    private static function get_existing_attendance_session_times(int $attendanceid, array $sessiontimes): array {
        global $DB;

        if (empty($sessiontimes)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($sessiontimes, SQL_PARAMS_NAMED);
        $params['attendanceid'] = $attendanceid;
        $sql = "SELECT sessdate
                  FROM {attendance_sessions}
                 WHERE attendanceid = :attendanceid
                   AND sessdate {$insql}";

        $existing = [];
        foreach ($DB->get_fieldset_sql($sql, $params) as $sessdate) {
            $existing[(int) $sessdate] = true;
        }

        return $existing;
    }

    /**
     * Get courses within a specific semester category tree.
     *
     * @param int $categoryid
     * @param string $catpath
     * @return array
     */
    public static function get_category_tree_course_options(int $categoryid, string $catpath): array {
        $courses = self::get_semester_courses_in_category(0, $categoryid, $catpath);
        $options = [];

        foreach ($courses as $course) {
            $options[$course->id] = trim($course->shortname . ' - ' . $course->fullname);
        }

        return $options;
    }

    /**
     * Check whether a course belongs to a category tree.
     *
     * @param int $courseid
     * @param int $categoryid
     * @param string $catpath
     * @return bool
     */
    public static function is_course_in_category_tree(int $courseid, int $categoryid, string $catpath): bool {
        global $DB;

        $pathprefix = $DB->sql_like_escape($catpath) . '/%';
        $sql = "SELECT c.id
                  FROM {course} c
                  JOIN {course_categories} cc ON cc.id = c.category
                 WHERE c.id = :courseid
                   AND (cc.id = :categoryid OR " . $DB->sql_like('cc.path', ':pathprefix', false) . ")";

        return $DB->record_exists_sql($sql, [
            'courseid' => $courseid,
            'categoryid' => $categoryid,
            'pathprefix' => $pathprefix,
        ]);
    }

    /**
     * Recursively clean a nested request array using clean_param().
     *
     * Use this instead of optional_param_array() whenever the POST value is a
     * multi-dimensional array (e.g. name[day][session]).
     *
     * @param mixed  $value Raw value from $_POST (scalar or array at any depth).
     * @param string $type  A PARAM_* constant string (e.g. PARAM_INT, PARAM_ALPHA).
     * @return mixed        Cleaned value with the same structure as the input.
     */
    public static function clean_nested_param(mixed $value, string $type): mixed {
        if (is_array($value)) {
            $cleaned = [];
            foreach ($value as $key => $item) {
                $cleaned[$key] = self::clean_nested_param($item, $type);
            }
            return $cleaned;
        }
        return clean_param($value, $type);
    }
}
