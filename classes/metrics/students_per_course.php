<?php
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Métrica: estudiantes por curso.
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
 * HU3-T5: `students_per_course` (cubre `max_students_course` y
 * `min_students_course` del catálogo).
 *
 * Una serie por curso con su cantidad de estudiantes, etiquetada con
 * escuela/año/período/curso. El máximo y el mínimo se calculan en Grafana
 * (`max()`/`min()`) sobre lo que deje el filtro, así el mismo dato sirve
 * para cualquier combinación de escuela, año, período o curso. Antes se
 * reducía a `stat="max"|"min"` en PHP por escuela/año/período, pero eso no
 * se podía filtrar por curso.
 */
class students_per_course extends metric {

    #[\Override]
    public function get_type(): metric_type {
        return metric_type::GAUGE;
    }

    #[\Override]
    public function calculate(metric_config|null $config = null): iterable {
        // Todas las series, incluso en 0: el mínimo por escuela/año/período
        // (min() en Grafana) necesita ver los cursos vacíos.
        $studentcounts = queries::student_counts_by_course();
        foreach (queries::course_dimension_map() as $courseid => $row) {
            yield new metric_value($studentcounts[$courseid] ?? 0, queries::course_labels($row));
        }
    }
}