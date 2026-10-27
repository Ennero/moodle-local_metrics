<?php
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Métrica: cursos sin docentes asignados, por escuela/año/período/curso.
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
 * HU3-T5: `courses_without_teachers`.
 *
 * Cursos sin ningún usuario con rol `editingteacher`/`teacher` asignado en su
 * contexto. Serie por `school`/`year`/`period`, incluyendo las combinaciones
 * en 0.
 */
class courses_without_teachers extends metric {

    #[\Override]
    public function get_type(): metric_type {
        return metric_type::GAUGE;
    }

    #[\Override]
    public function calculate(metric_config|null $config = null): iterable {
        $withteachers = queries::courseids_with_role(['editingteacher', 'teacher']);
        $bycourse = [];
        foreach (queries::course_dimension_map() as $courseid => $row) {
            $bycourse[$courseid] = isset($withteachers[$courseid]) ? 0 : 1;
        }
        yield from queries::course_values($bycourse, queries::course_dimension_map());
    }
}