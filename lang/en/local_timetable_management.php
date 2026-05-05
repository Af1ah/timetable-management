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

/**
 * Language strings for local_timetable_management.
 *
 * @package    local_timetable_management
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Semester Management';
$string['managesemesters'] = 'Manage Semesters';
$string['timetable'] = 'Time Table';
$string['collegemastertimetable'] = 'College Master Time Table';
$string['collegemastertimetablefor'] = 'College Master Time Table for {$a}';
$string['timetablefor'] = 'Time Table for {$a}';
$string['settings'] = 'Settings';
$string['edit'] = 'Edit';
$string['view'] = 'View';
$string['downloadcsv'] = 'Download CSV';
$string['downloadpdf'] = 'Download PDF';
$string['timetablesaved'] = 'Time table saved';
$string['timetablesettingssaved'] = 'Time table settings saved';
$string['timetable_management:manage'] = 'Manage semesters and course assignments';

// Semesters.
$string['semester'] = 'Semester';
$string['semester1'] = 'Semester 1';
$string['semester2'] = 'Semester 2';
$string['semester3'] = 'Semester 3';
$string['semester4'] = 'Semester 4';
$string['semester5'] = 'Semester 5';
$string['semester6'] = 'Semester 6';
$string['semester7'] = 'Semester 7';
$string['semester8'] = 'Semester 8';
$string['semestern'] = 'Semester {$a}';

// Status.
$string['enabled'] = 'Enabled';
$string['disabled'] = 'Disabled';
$string['status'] = 'Status';
$string['enable'] = 'Enable';
$string['disable'] = 'Disable';
$string['semesterenabled'] = 'Semester {$a} enabled';
$string['semesterdisabled'] = 'Semester {$a} disabled - all courses are now hidden';

// Courses.
$string['courses'] = 'Courses';
$string['coursecount'] = '{$a} course(s)';
$string['nocourses'] = 'No courses assigned';
$string['viewcourses'] = 'View Courses';
$string['assigncourses'] = 'Assign Courses';
$string['coursesinsemester'] = 'Courses in Semester {$a}';
$string['assigncoursestosemester'] = 'Assign Courses to Semester {$a}';
$string['availablecourses'] = 'Available Courses';
$string['assignedcourses'] = 'Assigned Courses';
$string['assign'] = 'Assign';
$string['unassign'] = 'Remove';
$string['courseassigned'] = 'Course assigned to semester';
$string['courseunassigned'] = 'Course removed from semester';
$string['courseshidden'] = 'Courses in this semester are hidden because the semester is disabled';

// Course form.
$string['selectsemester'] = 'Semester';
$string['selectsemester_help'] = 'Select the semester for this course';
$string['nosemester'] = 'No semester';
$string['semesterrequired'] = 'You must select a semester';

// Actions.
$string['actions'] = 'Actions';
$string['back'] = 'Back';
$string['manage'] = 'Manage';

// Programs page.
$string['program'] = 'Program';
$string['department'] = 'Department';
$string['programsinsemester'] = 'Programs in {$a}';
$string['noprograms'] = 'No programs found for this semester. Publish courses from the Minor & MDC Allotment plugin first.';
$string['coursesincategory'] = 'Courses in {$a->program} ({$a->semester})';
$string['departments'] = 'Departments';
$string['departmenttimetable'] = 'Department timetable';
$string['notimetabledepartments'] = 'No departments with semester programs were found.';
$string['activesections'] = 'Active semesters';
$string['activesemesters'] = 'Active semesters';
$string['weekday'] = 'Day';
$string['daymonday'] = 'Monday';
$string['daytuesday'] = 'Tuesday';
$string['daywednesday'] = 'Wednesday';
$string['daythursday'] = 'Thursday';
$string['dayfriday'] = 'Friday';
$string['timesettings'] = 'Time settings';
$string['daysettings'] = 'Working day settings';
$string['sessioncount'] = 'Total class sessions';
$string['sessionstarttime'] = 'Day start time';
$string['sessionduration'] = 'Each period duration (minutes)';
$string['breakafter'] = 'Gap after period';
$string['breakduration'] = 'Gap duration (minutes)';
$string['savetimesettings'] = 'Save time settings';
$string['savetimetable'] = 'Save time table';
$string['savechanges'] = 'Save changes';
$string['workingday'] = 'Working day';
$string['section'] = 'Session {$a}';
$string['fromtime'] = 'From';
$string['totime'] = 'To';
$string['addworkingday'] = 'Add working day';
$string['removeworkingday'] = 'Remove working day';
$string['addsection'] = 'Add session';
$string['removesection'] = 'Remove session';
$string['timetableview'] = 'Time table';
$string['nocourseoption'] = 'Select a course';
$string['noprogramsindepartment'] = 'No active semester programs found for this department.';
$string['programsemester'] = 'Semester';
$string['searchcourse'] = 'Search course';
$string['nocourseassigned'] = 'No course assigned';
$string['departmentnotmapped'] = 'This department is not linked to an admission department.';
$string['timetableexporttitle'] = 'Time Table - {$a}';
$string['mastertimetablesaved'] = 'College master time table saved';
$string['nomastercoursetypes'] = 'Minor and MDC course data is not available.';
$string['nomastertimetablesemesters'] = 'No active semesters are available for the college master time table.';
$string['reservedformastertimetable'] = 'Reserved for {$a}';
$string['reservedslot'] = 'Reserved slot';
$string['availablecoursesfortype'] = 'Available courses: {$a}';
$string['coursetypeminor1'] = 'Minor 1';
$string['coursetypeminor2'] = 'Minor 2';
$string['coursetypemdc'] = 'MDC';
$string['excludeddepartmentslabel'] = 'Excluded departments';
$string['excludeddepartmentshelp'] = 'Remove departments here while edit mode is on, then save the master time table to keep those departments under their own timetable.';
$string['selectcoursetype'] = 'Select type';
$string['semestercoursesummary'] = '{$a->semestername}: {$a->coursenames}';
$string['mastertimedepartmenthelp'] = 'Reserved slots from the college master time table are locked here. HoDs cannot edit those cells.';
$string['removedepartment'] = 'Remove department';
$string['invaliddepartment'] = 'Invalid department';
$string['invalidtimevalue'] = 'Invalid time settings';

// Errors.
$string['invalidsemester'] = 'Invalid semester';
$string['invalidcourse'] = 'Invalid course';
