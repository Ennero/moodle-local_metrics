<?php
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Métrica: distribución de cuentas por antigüedad del último acceso.
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
 * HU3-T6: `last_activity_age`.
 *
 * Cuentas reales no suspendidas repartidas en rangos fijos según hace cuánto
 * fue su último acceso (`user.lastaccess`): `0-1d`, `1-7d`, `7-30d`, `30d+`,
 * `nunca`. Label `range`. **No se expone por estudiante** (el catálogo lo
 * pide explícito) — sólo el agregado en 5 rangos, cardinalidad fija.
 *
 * A diferencia de `inactive_users` (HU3-T3), que da un conteo acumulativo
 * por umbral configurable (cuántos superan 7/15/30 días), acá es una
 * partición fija: cada cuenta cae en un único rango y los 5 rangos suman el
 * total de cuentas reales — pensado para un solo gráfico de torta/barras que
 * muestre "qué tan reciente es la actividad de la base de usuarios" de un
 * vistazo.
 */
class last_activity_age extends metric {

    #[\Override]
    public function get_type(): metric_type {
        return metric_type::GAUGE;
    }

    #[\Override]
    public function calculate(metric_config|null $config = null): iterable {
        foreach (queries::last_activity_age_buckets() as $range => $n) {
            yield new metric_value($n, ['range' => $range]);
        }
    }
}