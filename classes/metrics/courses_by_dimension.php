<?php
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Métrica: cursos por escuela/año/período y visibilidad.
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
 * HU3-T5: `courses_by_dimension`.
 *
 * Unifica varias filas del catálogo (`courses_total`, `courses_visible`,
 * `courses_hidden`, `courses_by_school`, `courses_by_period` segmentados por
 * dimensión institucional) en una sola métrica con labels `school`, `year`,
 * `period`, `visible`. El nivel global sin segmentar ya lo da la métrica
 * nativa `tool_monitoring_courses` por PromQL puro (`sum(...)`); esta métrica
 * sólo hace falta porque esa nativa no conoce la jerarquía de categorías.
 */
class courses_by_dimension extends metric {

    #[\Override]
    public function get_type(): metric_type {
        return metric_type::GAUGE;
    }

    #[\Override]
    public function calculate(metric_config|null $config = null): iterable {
        // Una serie por curso (valor 1): es además la lista de cursos que usan
        // los desplegables «Curso» de Grafana.
        foreach (queries::course_dimension_map() as $row) {
            yield new metric_value(1, queries::course_labels($row) + [
                'visible' => ((int) $row->visible === 1) ? 'true' : 'false',
            ]);
        }
    }
}