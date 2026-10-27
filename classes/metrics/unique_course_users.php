<?php
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Métrica: usuarios distintos con actividad de curso, por escuela/año/período/curso.
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
 * HU3-T6: `unique_course_users`.
 *
 * Usuarios distintos con al menos un evento "de participación"
 * (`edulevel = 2`: la propia clasificación de Moodle para acciones de
 * alumno/docente sobre contenido de curso — ver, entregar, responder — que
 * excluye altas administrativas como matricular o asignar rol) dentro de la
 * ventana configurada, agregado por escuela/año/período.
 *
 * Una serie por curso con sus usuarios distintos. Al sumar varios cursos
 * (filtro por escuela/año/período) una persona activa en dos cursos cuenta
 * dos veces: el total es de **participaciones** (persona-curso), no de
 * personas. Para personas distintas por escuela ver `active_students` /
 * `active_teachers`.
 */
class unique_course_users extends metric_with_config {

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
        $bycourse = [];
        foreach (queries::participating_course_user_pairs($since) as $pair) {
            $courseid = (int) $pair->courseid;
            $bycourse[$courseid] = ($bycourse[$courseid] ?? 0) + 1;
        }
        yield from queries::course_values($bycourse, queries::course_dimension_map());
    }
}