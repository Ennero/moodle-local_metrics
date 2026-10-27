<?php
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Métrica: usuarios inactivos por ventana temporal.
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
 * HU3-T3: `inactive_users` (cubre `inactive_users_7d/15d/30d` del catálogo).
 *
 * Usuarios reales que **ya iniciaron sesión alguna vez** (`lastaccess > 0`)
 * pero cuyo último acceso cae fuera de la ventana. Una serie por ventana,
 * etiquetada `time_window` (`7d`, `15d`, `30d`, ...).
 *
 * Ver {@see windows_config} para la justificación de implementarla una sola vez.
 */
class inactive_users extends metric_with_config {

    #[\Override]
    public function get_type(): metric_type {
        return metric_type::GAUGE;
    }

    #[\Override]
    public function get_default_config(): metric_config {
        return new windows_config('7,15,30');
    }

    #[\Override]
    public function calculate(metric_config|null $config = null): iterable {
        global $DB;
        /** @var windows_config $config */
        $now = time();
        foreach ($config->days() as $days) {
            $threshold = $now - ($days * DAYSECS);
            $count = $DB->count_records_select(
                'user',
                'id > 2 AND deleted = 0 AND suspended = 0 AND lastaccess > 0 AND lastaccess < :threshold',
                ['threshold' => $threshold]
            );
            yield new metric_value($count, ['time_window' => "{$days}d"]);
        }
    }
}