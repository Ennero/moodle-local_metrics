<?php
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Métrica: total de matrículas, por escuela/año/período/curso.
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
 * HU3-T4: `enrolments_total`.
 *
 * `COUNT(*)` de filas de `user_enrolments` (todas las inscripciones, cualquier
 * rol y método), agregado por `school` / `year` / `period`.
 */
class enrolments_total extends metric {

    #[\Override]
    public function get_type(): metric_type {
        return metric_type::GAUGE;
    }

    #[\Override]
    public function calculate(metric_config|null $config = null): iterable {
        yield from queries::course_values(
            array_map(fn($row) => (int) $row->enrolments_total, queries::enrolments_by_course()),
            queries::course_dimension_map()
        );
    }
}