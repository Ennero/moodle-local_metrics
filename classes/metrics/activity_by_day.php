<?php
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Métrica: eventos por día calendario, dentro de la ventana configurada.
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
 * HU3-T6: `activity_by_day`.
 *
 * Eventos agrupados por día calendario (`YYYY-MM-DD`), dentro de la ventana
 * configurada (por defecto 14 días), en hora local del sitio. Labels `date`
 * (ordenable) y `date_label` (legible: `dom 27 sep`). La cardinalidad crece con
 * el tamaño de la ventana, no con el sitio (14 valores con la config. por
 * defecto) — subir mucho la ventana sí infla la cardinalidad, documentado en
 * el README del plugin.
 *
 * Desglosada además por `school`/`year`/`period` del curso donde ocurrió el
 * evento, para poder filtrarla en Grafana. Los eventos que no son de un
 * curso (inicio de sesión, tablero) van en la serie sin esas etiquetas.
 * No se desglosa por curso: 24 horas × ~7.200 cursos serían demasiadas series.
 */
class activity_by_day extends metric_with_config {

    /** @var string[] Días (índice 1 = lunes, formato ISO `N`). */
    private const DIAS = [1 => 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb', 'dom'];

    /** @var string[] Meses abreviados en español (índice 1-12). */
    private const MESES = [1 => 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    #[\Override]
    public function get_type(): metric_type {
        return metric_type::GAUGE;
    }

    #[\Override]
    public function get_default_config(): metric_config {
        return new window_config(14);
    }

    #[\Override]
    public function calculate(metric_config|null $config = null): iterable {
        /** @var window_config $config */
        $since = time() - $config->seconds();
        foreach (queries::activity_event_counts_by_day($since) as $date => $bydim) {
            $d = new \DateTimeImmutable($date);
            $label = self::DIAS[(int) $d->format('N')] . ' ' . $d->format('j') . ' ' . self::MESES[(int) $d->format('n')];
            foreach ($bydim as $dimkey => $n) {
                yield new metric_value($n, ['date' => $date, 'date_label' => $label] + queries::dimension_labels_of_key($dimkey));
            }
        }
    }
}