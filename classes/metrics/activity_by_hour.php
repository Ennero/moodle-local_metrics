<?php
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Métrica: eventos por hora del día, dentro de la ventana configurada.
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
 * HU3-T6: `activity_by_hour`.
 *
 * Distribución de eventos por hora del día (0-23, hora local del sitio),
 * dentro de la ventana configurada (por defecto 7 días). Labels `hour`
 * (`"00"`-`"23"`, ordenable) y `hour_label` (`"08:00"`), 24 valores.
 *
 * Se calcula con `GROUP BY HOUR(...)` en SQL en vez de dejar que Prometheus
 * lo derive de una serie temporal: un patrón "qué hora del día es más
 * concurrida" cruza muchos días, y ni `rate()` ni `increase()` dan eso
 * directamente sobre una serie de conteos por scrape. El costo es
 * insignificante gracias al índice de `timecreated` (medido: <2ms sobre 7
 * días de datos, ver documento de viabilidad).
 *
 * Desglosada además por `school`/`year`/`period` del curso donde ocurrió el
 * evento, para poder filtrarla en Grafana. Los eventos que no son de un
 * curso (inicio de sesión, tablero) van en la serie sin esas etiquetas.
 * No se desglosa por curso: 24 horas × ~7.200 cursos serían demasiadas series.
 */
class activity_by_hour extends metric_with_config {

    #[\Override]
    public function get_type(): metric_type {
        return metric_type::GAUGE;
    }

    #[\Override]
    public function get_default_config(): metric_config {
        return new window_config(7);
    }

    #[\Override]
    public function calculate(metric_config|null $config = null): iterable {
        /** @var window_config $config */
        $since = time() - $config->seconds();
        foreach (queries::activity_event_counts_by_hour($since) as $hour => $bydim) {
            foreach ($bydim as $dimkey => $n) {
                yield new metric_value($n, ['hour' => sprintf('%02d', $hour), 'hour_label' => sprintf('%02d:00', $hour)]
                    + queries::dimension_labels_of_key($dimkey));
            }
        }
    }
}