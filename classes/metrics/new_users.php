<?php
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Métrica: cuentas creadas dentro de una ventana temporal.
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
 * HU3-T3: `new_users`.
 *
 * Cuentas de usuario reales (`id > 2`, no borradas) con `timecreated` dentro
 * de la ventana configurada (por defecto 30 días).
 */
class new_users extends metric_with_config {

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
        global $DB;
        /** @var window_config $config */
        $since = time() - $config->seconds();
        $count = $DB->count_records_select(
            'user',
            'id > 2 AND deleted = 0 AND timecreated >= :since',
            ['since' => $since]
        );
        yield new metric_value($count, ['window_days' => (string) $config->windowdays]);
    }
}