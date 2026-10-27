<?php
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Métrica: total de eventos Moodle registrados en la ventana configurada.
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
 * HU3-T6: `activity_events`.
 *
 * `COUNT(*)` de `logstore_standard_log` dentro de la ventana configurada
 * (por defecto 1 día). Incluye **cualquier** tipo de evento, no sólo vistas:
 * altas administrativas (matricular, asignar rol) cuentan igual que un
 * acceso real. Ver la nota de datos en el documento de viabilidad — hoy la
 * mayoría del log es ruido de aprovisionamiento sintético, no uso orgánico.
 *
 * Deliberadamente acotada por ventana (no es un contador acumulado de todo
 * el histórico): así la consulta siempre puede usar el índice de
 * `timecreated` sin importar cuánto crezca la tabla con el tiempo. Se evaluó
 * exponer un contador (`COUNTER`) de todo el histórico y dejar que PromQL
 * hiciera `increase()`/`rate()`, pero eso implica escanear toda la tabla en
 * cada scrape — exactamente lo que la tarea pide evitar a medida que el log
 * crece. Con la ventana acotada, `activity_by_hour`/`_day`/`_week` se
 * calculan aparte con la misma técnica (ver esas clases) en vez de derivarse
 * de ésta por PromQL.
 */
class activity_events extends metric_with_config {

    #[\Override]
    public function get_type(): metric_type {
        return metric_type::GAUGE;
    }

    #[\Override]
    public function get_default_config(): metric_config {
        return new window_config(1);
    }

    #[\Override]
    public function calculate(metric_config|null $config = null): iterable {
        /** @var window_config $config */
        $since = time() - $config->seconds();
        yield new metric_value(queries::activity_event_count($since));
    }
}