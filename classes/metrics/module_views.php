<?php
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Métrica: vistas de curso/recurso/foro/quiz/tarea, por escuela/año/período/curso.
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
 * HU3-T6: `module_views` (cubre `course_accesses`, `course_views`,
 * `resource_views`, `forum_views`, `quiz_views` y `assignment_views` del
 * catálogo — label `type`).
 *
 * `course_accesses` y `course_views` son, literalmente, la misma definición
 * en el catálogo ("eventos de acceso/visualización de curso"): las dos
 * mapean al evento `\core\event\course_viewed`, así que comparten la serie
 * `type="course"`. Las otras cuatro salen del evento nativo
 * `course_module_viewed` de cada módulo (`mod_resource`, `mod_forum`,
 * `mod_quiz`, `mod_assign`) — el mismo patrón que usa el propio reporte
 * "Actividad reciente" de Moodle para saber qué se está mirando.
 *
 * Ventana configurable (`window_config`, por defecto 7 días) para poder usar
 * el índice de `timecreated` (ver benchmark en `docs/HU3_viabilidad_metricas.md`).
 *
 * Se completa en 0 cada combinación `type` × escuela/año/período que exista
 * hoy ({@see queries::dimension_keys()}), incluso sin eventos en la ventana —
 * un panel de Grafana muestra "0" en vez de "sin datos". Con las ~164
 * combinaciones actuales son ≤ 820 series (5 tipos), lejos de un problema de
 * cardinalidad.
 */
class module_views extends metric_with_config {

    /** @var array<string, string> type => nombre completo del evento Moodle */
    private const EVENTS = [
        'course'     => '\core\event\course_viewed',
        'resource'   => '\mod_resource\event\course_module_viewed',
        'forum'      => '\mod_forum\event\course_module_viewed',
        'quiz'       => '\mod_quiz\event\course_module_viewed',
        'assignment' => '\mod_assign\event\course_module_viewed',
    ];

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
        queries::preload_event_counts_by_course(array_values(self::EVENTS), $since);
        $bycourse = [];
        foreach (self::EVENTS as $type => $eventname) {
            foreach (queries::event_counts_by_course($eventname, $since) as $courseid => $n) {
                $bycourse[$courseid][$type] = $n;
            }
        }
        yield from queries::course_values($bycourse, queries::course_dimension_map(), 'type', array_keys(self::EVENTS));
    }
}