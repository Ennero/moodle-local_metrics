<?php
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Métrica: usuarios que nunca iniciaron sesión.
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
 * HU3-T3: `users_never_logged_in`.
 *
 * Cuentas reales (`id > 2`, no borradas, no suspendidas) sin ningún registro
 * de acceso: `lastaccess = 0` y `lastlogin = 0` y `currentlogin = 0`.
 */
class users_never_logged_in extends metric {

    #[\Override]
    public function get_type(): metric_type {
        return metric_type::GAUGE;
    }

    #[\Override]
    public function calculate(metric_config|null $config = null): metric_value {
        global $DB;
        $count = $DB->count_records_select(
            'user',
            'id > 2 AND deleted = 0 AND suspended = 0 AND lastaccess = 0 AND lastlogin = 0 AND currentlogin = 0'
        );
        return new metric_value($count);
    }
}