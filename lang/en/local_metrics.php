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
 * English language strings for local_metrics.
 *
 * @package    local_metrics
 * @copyright  2026 UEDi - Facultad de Ingeniería USAC
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Métricas Académicas UEDi';

// Academic metrics (UEDi - USAC)
$string['metric:window_config:windowdays'] = 'Activity window (days)';
$string['metric:window_config:windowdays_help'] =
    'A user counts as "active" if their last access falls within this many days.';
$string['metric:windows_config:windowdays'] = 'Inactivity windows (days, comma separated)';
$string['metric:windows_config:windowdays_help'] =
    'One metric series is produced per value, e.g. "7,15,30" yields time_window="7d", "15d" and "30d".';
$string['metric:active_teachers_desc'] =
    'HU3-T3. Distinct teachers (editingteacher/teacher role in a course) whose last access is within the configured window, by school.';
$string['metric:active_students_desc'] =
    'HU3-T3. Distinct students (student role in a course) whose last access is within the configured window, by school.';
$string['metric:new_users_desc'] =
    'HU3-T3. Real user accounts (id > 2, not deleted) created within the configured window.';
$string['metric:users_never_logged_in_desc'] =
    'HU3-T3. Real, non-suspended accounts with no access record at all (lastaccess = lastlogin = currentlogin = 0).';
$string['metric:inactive_users_desc'] =
    'HU3-T3. Real, non-suspended accounts that have logged in at least once but whose last access is older than each configured window (time_window label: 7d, 15d, 30d, ...).';
$string['metric:students_enrolled_desc'] =
    'HU3-T4. Distinct users holding the student role in at least one course, by school. sum() over schools = unique students site-wide.';
$string['metric:enrolments_total_desc'] =
    'HU3-T4. Total user_enrolments rows (any role/method) by school / year / period / course.';
$string['metric:role_enrolments_desc'] =
    'HU3-T4. Course-level role assignments (userid, course pairs) by role shortname and school / year / period / course (covers student_enrolments and teacher_enrolments).';
$string['metric:enrolments_by_method_desc'] =
    'HU3-T4. user_enrolments rows grouped by enrolment method (manual, self, cohort, ...).';
$string['metric:enrolments_by_status_desc'] =
    'HU3-T4. user_enrolments rows by status: active (status 0) vs suspended (status 1).';
$string['metric:students_multiple_courses_desc'] =
    'HU3-T4. Number of users holding the student role in more than one course.';
$string['metric:courses_by_dimension_desc'] =
    'HU3-T5. Courses by school / year / period / course and visibility. Covers courses_total, courses_visible, courses_hidden, courses_by_school and courses_by_period from the catalogue, segmented by institutional dimension (the native tool_monitoring_courses metric already covers the unsegmented, site-wide level).';
$string['metric:courses_active_desc'] =
    'HU3-T5. Courses with at least one active enrolment (status = 0) or at least one log event within the configured window (default 30 days), by school / year / period / course.';
$string['metric:courses_without_students_desc'] =
    'HU3-T5. Courses with no user holding the student role, by school / year / period / course.';
$string['metric:courses_without_teachers_desc'] =
    'HU3-T5. Courses with no user holding the editingteacher/teacher role, by school / year / period / course.';
$string['metric:students_per_course_desc'] =
    'HU3-T5. Number of students enrolled in each course, by school / year / period / course (every course, including 0). Covers max_students_course and min_students_course from the catalogue: resolve them in PromQL with max() / min().';
$string['metric:module_views_desc'] =
    'HU3-T6. Course/module view events within the configured window (default 7 days), by type (course, resource, forum, quiz, assignment) and school / year / period. Covers course_accesses, course_views, resource_views, forum_views, quiz_views and assignment_views from the catalogue (course_accesses and course_views are the same event, course_viewed).';
$string['metric:unique_course_users_desc'] =
    'HU3-T6. Distinct users with at least one course-participation event (edulevel = 2: viewing/submitting content, not administrative enrolment) within the configured window (default 7 days), by school / year / period / course.';
$string['metric:student_logins_desc'] =
    'HU3-T6. Successful logins (user_loggedin) by users holding the student role, within the configured window (default 7 days). Site-wide, not segmented.';
$string['metric:activity_events_desc'] =
    'HU3-T6. Total Moodle log events within the configured window (default 1 day), any type. Windowed (not a cumulative all-time counter) so the query can always use the timecreated index regardless of how large the log grows.';
$string['metric:activity_events_by_type_desc'] =
    'HU3-T6. Same as activity_events, grouped by eventname (label event_name). ~38 distinct event names site-wide, safe cardinality. Also split by school / year / period of the course where the event happened; events outside any course (login, dashboard) are the series without those labels.';
$string['metric:activity_by_hour_desc'] =
    'HU3-T6. Log events within the configured window (default 7 days), grouped by hour of day (label hour, 00-23). Also split by school / year / period of the course where the event happened; events outside any course (login, dashboard) are the series without those labels.';
$string['metric:activity_by_day_desc'] =
    'HU3-T6. Log events within the configured window (default 14 days), grouped by calendar day (label date). Also split by school / year / period of the course where the event happened; events outside any course (login, dashboard) are the series without those labels.';
$string['metric:activity_by_week_desc'] =
    'HU3-T6. Log events within the configured window (default 90 days), grouped by ISO week (label week). Also split by school / year / period of the course where the event happened; events outside any course (login, dashboard) are the series without those labels.';
$string['metric:last_activity_age_desc'] =
    'HU3-T6. Real, non-suspended accounts partitioned into fixed last-access ranges (range label: 0-1d, 1-7d, 7-30d, 30d+, nunca). Never exposed per student.';
$string['metric:quizzes_by_dimension_desc'] =
    'HU3-T8. Quiz activities by school / year / period / course and whether they are currently open per their timeopen/timeclose window (open label: true, false). Covers quizzes_total and quizzes_open from the catalogue.';
$string['metric:quiz_attempts_by_state_desc'] =
    'HU3-T8. Quiz attempts (excluding teacher previews) by native Moodle state (state label: inprogress, overdue, finished, abandoned) and school / year / period. Covers quiz_attempts, quiz_attempts_finished and quiz_attempts_abandoned from the catalogue. Not a duplicate of the native tool_monitoring_quiz_attempts_in_progress metric, which only covers in-progress attempts within a configurable deadline/idle window, site-wide.';
$string['metric:quiz_attempting_students_desc'] =
    'HU3-T8. Distinct students with at least one quiz attempt, by school / year / period / course. Base metric for the quiz_attempts_per_student ratio from the catalogue (resolved in PromQL against quiz_attempts_by_state).';
$string['metric:students_without_quiz_attempt_desc'] =
    'HU3-T8. Real students enrolled in a course that has at least one quiz, minus students with an attempt in that course, summed per course (not deduplicated site-wide) and aggregated by school / year / period / course.';
$string['metric:average_quiz_duration_desc'] =
    'HU3-T8. Average duration in seconds (timefinish - timestart) of finished quiz attempts, by school / year / period / course. Not zero-filled: no finished attempts means no series, not a misleading "0 seconds". Per course, as two series: stat="sum" and stat="count" (average = sum / count, so any set of courses can be averaged correctly).';
$string['metric:quiz_average_grade_desc'] =
    'HU3-T8. Average quiz grade, normalised to a 0-100 percentage of each quiz\'s configured maximum grade before averaging, by school / year / period / course. Quizzes with no maximum grade configured are excluded. Not zero-filled. Per course, as two series: stat="sum" and stat="count" (average = sum / count, so any set of courses can be averaged correctly).';
$string['metric:quiz_grade_results_desc'] =
    'HU3-T8. Quiz grades classified as passed or failed (result label), by school / year / period / course. Pass threshold: the activity\'s configured gradepass if set, otherwise 60% of the quiz\'s maximum grade as a documented default. Base for the quiz_pass_rate / quiz_failure_rate ratios from the catalogue (resolved in PromQL).';
$string['metric:assignments_by_dimension_desc'] =
    'HU3-T7. Assignment activities by school / year / period / course and submission-window status (status label: upcoming, open, closed), based on allowsubmissionsfromdate/duedate. Covers assignments_total, assignments_open and assignments_closed from the catalogue.';
$string['metric:assignment_submissions_by_timing_desc'] =
    'HU3-T7. Real submissions (status = submitted, not the draft placeholder rows Moodle creates for draft-enabled assignments) by on-time/late (timing label) and school / year / period, against duedate. Covers submissions_total, submissions_on_time and submissions_late from the catalogue.';
$string['metric:expected_submissions_by_dimension_desc'] =
    'HU3-T7. Expected submissions (enrolled students per course x assignments in that course, summed) by school / year / period / course. Base for the submission_rate and missing_submission_rate ratios from the catalogue (resolved in PromQL).';
$string['metric:submissions_missing_desc'] =
    'HU3-T7. Expected submissions minus valid (status = submitted) submissions, per assignment, summed per course (not deduplicated site-wide) and aggregated by school / year / period / course.';
$string['metric:assignments_pending_grading_desc'] =
    'HU3-T7. Submitted assignments (status = submitted) with no matching row in assign_grades yet, by school / year / period / course.';
$string['metric:average_grading_delay_desc'] =
    'HU3-T7. Average delay in seconds between a submission and its grade, by school / year / period / course. Not zero-filled: no graded submissions means no series, not a misleading "0 seconds". Per course, as two series: stat="sum" and stat="count" (average = sum / count, so any set of courses can be averaged correctly).';
