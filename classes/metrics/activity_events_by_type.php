<?php
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Métrica: eventos Moodle registrados en la ventana, agrupados por tipo.
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
 * HU3-T6: `activity_events_by_type`.
 *
 * Igual que `activity_events` pero agrupada por `eventname` (label
 * `event_name`). Con los datos de hoy salen ~38 valores distintos de
 * `eventname` en todo el sitio — cardinalidad segura para Prometheus (no
 * varía con la cantidad de cursos/usuarios, es un catálogo fijo de tipos de
 * evento de Moodle).
 *
 * Desglosada además por `school`/`year`/`period` del curso donde ocurrió el
 * evento, para poder filtrarla en Grafana. Los eventos que no son de un
 * curso (inicio de sesión, tablero) van en la serie sin esas etiquetas.
 * No se desglosa por curso: 24 horas × ~7.200 cursos serían demasiadas series.
 */
class activity_events_by_type extends metric_with_config {

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
        foreach (queries::activity_event_counts_by_type($since) as $eventname => $bydim) {
            foreach ($bydim as $dimkey => $n) {
                yield new metric_value($n, ['event_name' => $eventname] + queries::dimension_labels_of_key($dimkey));
            }
        }
    }
}