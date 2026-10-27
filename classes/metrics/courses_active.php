<?php
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Métrica: cursos con actividad, por escuela/año/período/curso.
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
 * HU3-T5: `courses_active`.
 *
 * Un curso cuenta como activo si tiene al menos una matrícula activa
 * (`user_enrolments.status = 0`) **o** al menos un evento en
 * `logstore_standard_log` dentro de la ventana configurada (por defecto 30
 * días). Serie por `school`/`year`/`period`.
 *
 * Nota sobre los datos actuales: el poblador sintético matricula activamente
 * a todos los estudiantes desde el día del poblado y hay ~630k eventos de log
 * recientes, así que con los datos de hoy esta métrica da prácticamente lo
 * mismo que `courses_total` (todos los cursos están "activos"). Es correcto,
 * no un bug — la métrica sólo empieza a diferenciar cuando algunos cursos
 * queden sin matrícula activa ni actividad reciente.
 */
class courses_active extends metric_with_config {

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
        /** @var window_config $config */
        $since = time() - $config->seconds();
        $enrolled = queries::active_enrolled_courseids();
        $recent = queries::courseids_with_recent_activity($since);
        $bycourse = [];
        foreach (queries::course_dimension_map() as $courseid => $row) {
            $bycourse[$courseid] = (isset($enrolled[$courseid]) || isset($recent[$courseid])) ? 1 : 0;
        }
        yield from queries::course_values($bycourse, queries::course_dimension_map());
    }
}