<?php
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Métrica: matrículas activas vs suspendidas.
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
 * HU3-T4: `enrolments_by_status` (cubre `active_enrolments` y
 * `suspended_enrolments`).
 *
 * `COUNT(*)` de `user_enrolments` por estado. Serie por `status`
 * (`active` = `ue.status 0`, `suspended` = `ue.status 1`).
 */
class enrolments_by_status extends metric {

    #[\Override]
    public function get_type(): metric_type {
        return metric_type::GAUGE;
    }

    #[\Override]
    public function calculate(metric_config|null $config = null): iterable {
        $map = queries::course_dimension_map();
        $active = 0;
        $suspended = 0;
        foreach (queries::enrolments_by_course() as $courseid => $row) {
            if (!isset($map[$courseid])) {
                continue;
            }
            $active += (int) $row->active;
            $suspended += (int) $row->suspended;
        }
        yield new metric_value($active, ['status' => 'active']);
        yield new metric_value($suspended, ['status' => 'suspended']);
    }
}