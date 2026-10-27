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
 * Registration of academic metrics via Moodle Hooks API for tool_monitoring.
 *
 * @package    local_metrics
 * @copyright  2026 UEDi - Facultad de Ingeniería USAC
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use tool_monitoring\hook\metric_collection;
use local_metrics\metrics;

$callbacks = [
    ['hook' => metric_collection::class, 'callback' => [metrics\active_students::class, 'collect']],
    ['hook' => metric_collection::class, 'callback' => [metrics\active_teachers::class, 'collect']],
    ['hook' => metric_collection::class, 'callback' => [metrics\activity_by_day::class, 'collect']],
    ['hook' => metric_collection::class, 'callback' => [metrics\activity_by_hour::class, 'collect']],
    ['hook' => metric_collection::class, 'callback' => [metrics\activity_by_week::class, 'collect']],
    ['hook' => metric_collection::class, 'callback' => [metrics\activity_events::class, 'collect']],
    ['hook' => metric_collection::class, 'callback' => [metrics\activity_events_by_type::class, 'collect']],
    ['hook' => metric_collection::class, 'callback' => [metrics\assignment_submissions_by_timing::class, 'collect']],
    ['hook' => metric_collection::class, 'callback' => [metrics\assignments_by_dimension::class, 'collect']],
    ['hook' => metric_collection::class, 'callback' => [metrics\assignments_pending_grading::class, 'collect']],
    ['hook' => metric_collection::class, 'callback' => [metrics\average_grading_delay::class, 'collect']],
    ['hook' => metric_collection::class, 'callback' => [metrics\average_quiz_duration::class, 'collect']],
    ['hook' => metric_collection::class, 'callback' => [metrics\courses_active::class, 'collect']],
    ['hook' => metric_collection::class, 'callback' => [metrics\courses_by_dimension::class, 'collect']],
    ['hook' => metric_collection::class, 'callback' => [metrics\courses_without_students::class, 'collect']],
    ['hook' => metric_collection::class, 'callback' => [metrics\courses_without_teachers::class, 'collect']],
    ['hook' => metric_collection::class, 'callback' => [metrics\enrolments_by_method::class, 'collect']],
    ['hook' => metric_collection::class, 'callback' => [metrics\enrolments_by_status::class, 'collect']],
    ['hook' => metric_collection::class, 'callback' => [metrics\enrolments_total::class, 'collect']],
    ['hook' => metric_collection::class, 'callback' => [metrics\expected_submissions_by_dimension::class, 'collect']],
    ['hook' => metric_collection::class, 'callback' => [metrics\inactive_users::class, 'collect']],
    ['hook' => metric_collection::class, 'callback' => [metrics\last_activity_age::class, 'collect']],
    ['hook' => metric_collection::class, 'callback' => [metrics\module_views::class, 'collect']],
    ['hook' => metric_collection::class, 'callback' => [metrics\new_users::class, 'collect']],
    ['hook' => metric_collection::class, 'callback' => [metrics\quiz_attempting_students::class, 'collect']],
    ['hook' => metric_collection::class, 'callback' => [metrics\quiz_attempts_by_state::class, 'collect']],
    ['hook' => metric_collection::class, 'callback' => [metrics\quiz_average_grade::class, 'collect']],
    ['hook' => metric_collection::class, 'callback' => [metrics\quiz_grade_results::class, 'collect']],
    ['hook' => metric_collection::class, 'callback' => [metrics\quizzes_by_dimension::class, 'collect']],
    ['hook' => metric_collection::class, 'callback' => [metrics\role_enrolments::class, 'collect']],
    ['hook' => metric_collection::class, 'callback' => [metrics\student_logins::class, 'collect']],
    ['hook' => metric_collection::class, 'callback' => [metrics\students_enrolled::class, 'collect']],
    ['hook' => metric_collection::class, 'callback' => [metrics\students_multiple_courses::class, 'collect']],
    ['hook' => metric_collection::class, 'callback' => [metrics\students_per_course::class, 'collect']],
    ['hook' => metric_collection::class, 'callback' => [metrics\students_without_quiz_attempt::class, 'collect']],
    ['hook' => metric_collection::class, 'callback' => [metrics\submissions_missing::class, 'collect']],
    ['hook' => metric_collection::class, 'callback' => [metrics\unique_course_users::class, 'collect']],
    ['hook' => metric_collection::class, 'callback' => [metrics\users_never_logged_in::class, 'collect']],
];