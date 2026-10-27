<?php
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Métrica: matrículas por método de inscripción.
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
 * HU3-T4: `enrolments_by_method`.
 *
 * `COUNT(*)` de `user_enrolments` agrupado por el método del plugin de
 * inscripción (`manual`, `self`, `cohort`, `guest`, ...). Serie por `method`.
 */
class enrolments_by_method extends metric {

    #[\Override]
    public function get_type(): metric_type {
        return metric_type::GAUGE;
    }

    #[\Override]
    public function calculate(metric_config|null $config = null): iterable {
        foreach (queries::enrolments_by_method() as $method => $count) {
            yield new metric_value($count, ['method' => $method]);
        }
    }
}