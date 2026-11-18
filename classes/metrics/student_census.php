<?php
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Métrica: censo institucional de estudiantes únicos reales y cuentas del sitio.
 *
 * @package    local_metrics
 * @copyright  2026 UEDi - Facultad de Ingeniería USAC
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_metrics\metrics;

use local_metrics\academic_queries as queries;
use tool_monitoring\metric;
use tool_monitoring\metric_config;
use tool_monitoring\metric_type;
use tool_monitoring\metric_value;

defined('MOODLE_INTERNAL') || die();

/**
 * Censo institucional y población estudiantil real de Moodle.
 *
 * Proporciona el conteo de estudiantes únicos sin duplicidades entre escuelas:
 * - unique_students_history: Total real de personas con rol estudiante en la historia (10,927)
 * - site_users: Total de cuentas en la plataforma sumando docentes, admin y estudiantes (15,518)
 * - enrolled_current_year: Estudiantes únicos matriculados en el ciclo 2026 (8,126)
 * - enrolled_current_period: Estudiantes únicos en el período actual (2S 2026) (6,834)
 * - active_30d: Estudiantes únicos con acceso en los últimos 30 días (4,550)
 */
class student_census extends metric {

    #[\Override]
    public function get_type(): metric_type {
        return metric_type::GAUGE;
    }

    #[\Override]
    public function calculate(metric_config|null $config = null): iterable {
        foreach (queries::student_census_data() as $type => $count) {
            yield new metric_value((int) $count, ['census_type' => $type]);
        }
    }
}
