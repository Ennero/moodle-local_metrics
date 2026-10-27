<?php
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Métrica: docentes con actividad reciente, por escuela.
 *
 * @package    local_metrics
 * @copyright  2026 UEDi - Facultad de Ingeniería USAC
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_metrics\metrics;

use local_metrics\academic_queries as queries;
use local_metrics\config\window_config;
use local_metrics\config\windows_config;



use tool_monitoring\metric_config;
use tool_monitoring\metric_type;
use tool_monitoring\metric_value;
use tool_monitoring\metric_with_config;

defined('MOODLE_INTERNAL') || die();

/**
 * HU3-T3: `active_teachers`.
 *
 * Docentes (roles `editingteacher` / `teacher` en contexto de curso) con
 * `lastaccess` dentro de la ventana configurada. Serie por `school`.
 */
class active_teachers extends metric_with_config {

    #[\Override]
    public function get_type(): metric_type {
        return metric_type::GAUGE;
    }

    #[\Override]
    public function get_default_config(): metric_config {
        return new window_config(30);
    }

    #[\Override]
    public function calculate(metric_config|null $config = null): iterable {
        /** @var window_config $config */
        $since = time() - $config->seconds();
        foreach (queries::active_users_by_school(['editingteacher', 'teacher'], $since) as $school => $count) {
            yield new metric_value($count, ['school' => $school]);
        }
    }
}