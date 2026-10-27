<?php
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Métrica: matrículas con rol, por rol y escuela.
 *
 * @package    local_metrics
 * @copyright  2026 UEDi - Facultad de Ingeniería USAC
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_metrics\metrics;

use local_metrics\academic_queries as queries;
use local_metrics\config\window_config;
use local_metrics\config\windows_config;


use tool_monitoring\metric;
use tool_monitoring\metric_config;
use tool_monitoring\metric_type;
use tool_monitoring\metric_value;

defined('MOODLE_INTERNAL') || die();

/**
 * HU3-T4: `role_enrolments` (cubre `student_enrolments` y `teacher_enrolments`).
 *
 * `COUNT(*)` de asignaciones de rol (userid, curso) en contexto de curso,
 * etiquetado por `role` (shortname) y `school`. En Grafana:
 * `role="student"` -> student_enrolments; `role=~"editingteacher|teacher"`
 * -> teacher_enrolments.
 */
class role_enrolments extends metric {

    #[\Override]
    public function get_type(): metric_type {
        return metric_type::GAUGE;
    }

    #[\Override]
    public function calculate(metric_config|null $config = null): iterable {
        $bycourse = [];
        foreach (queries::role_pairs_by_course() as $row) {
            $bycourse[(int) $row->courseid][$row->roleshortname] = (int) $row->pairs;
        }
        yield from queries::course_values($bycourse, queries::course_dimension_map(), 'role', [], []);
    }
}