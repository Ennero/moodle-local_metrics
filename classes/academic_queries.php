<?php
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Consultas compartidas por las métricas de matrícula (HU3-T4), cursos
 * (HU3-T5) y actividad/accesos (HU3-T6).
 *
 * Varias métricas se derivan del mismo escaneo de `user_enrolments`
 * (~190k filas) o de `logstore_standard_log` (cientos de miles de filas y
 * creciendo). Para no repetir ese escaneo por cada métrica en un mismo
 * scrape, los resultados se cachean estáticamente durante el request.
 *
 * Las dimensiones son escuela/año/período (ancestros de nivel 1/2/3 de la
 * categoría del curso, resueltos en SQL) y, en las métricas de nivel curso,
 * el nombre completo del curso (label `course`), para que los dashboards
 * puedan filtrar por curso. Para acotar la cardinalidad (~7.200 cursos) las
 * series por curso sólo se emiten con valor distinto de 0; el relleno en 0
 * se hace por escuela/año/período, sin label `course` (ver
 * {@see self::dimension_label_sets()}). Nunca se usa el `courseid` como
 * label: el nombre es lo que un usuario reconoce en un desplegable.
 *
 * Todas las consultas sobre `logstore_standard_log` (T6) van acotadas por
 * `timecreated >= :since` para poder usar el índice existente sobre esa
 * columna (`mdl_logsstanlog_tim_ix`). Medido con `EXPLAIN`: sin esa cota el
 * optimizador de MariaDB hace `type=ALL` (recorre toda la tabla); con una
 * ventana que realmente acota los datos (p. ej. últimos 7 días) pasa a
 * `type=range` y el tiempo cae de ~1-3s a <2ms. Ver el documento de
 * viabilidad de T6 para los números completos.
 *
 * Nota: se usan recordsets (no `get_records_sql`) porque las filas agregadas
 * no tienen una primera columna única y `get_records_sql` descarta filas con
 * clave repetida.
 *
 * @package    local_metrics
 * @copyright  2026 UEDi - Facultad de Ingeniería USAC
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_metrics;

use tool_monitoring\metric_value;

defined('MOODLE_INTERNAL') || die();

/**
 * Consultas cacheadas por request.
 */
class academic_queries {

    /**
     * Expresion SQL para extraer el id de nivel N de la categoria de un curso.
     * Compatible con PostgreSQL y MySQL/MariaDB.
     */
    private static function level_id_expr(int $level): string {
        global $DB;
        if ($DB->get_dbfamily() === 'postgres') {
            return "NULLIF(split_part(cc.path, '/', {$level}), '')::integer";
        }
        return "CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(cc.path, '/', {$level}), '/', -1) AS UNSIGNED)";
    }

    /** @var array<string, mixed> Cache por request. */
    private static array $cache = [];

    /**
     * Limpia la cache interna (tests).
     */
    public static function reset_cache(): void {
        self::$cache = [];
    }

    /**
     * JOINs que resuelven school/year/period desde la categoría del curso `c`.
     *
     * @return string
     */
    private static function dimension_joins(): string {
        $l1 = self::level_id_expr(2);
        $l2 = self::level_id_expr(3);
        $l3 = self::level_id_expr(4);
        $l4 = self::level_id_expr(5);
        return "JOIN {course_categories} cc ON cc.id = c.category
                JOIN {course_categories} l1
                     ON l1.id = {$l1}
                LEFT JOIN {course_categories} l2
                     ON l2.id = {$l2}
                     AND cc.depth >= 2
                LEFT JOIN {course_categories} l3
                     ON l3.id = {$l3}
                     AND cc.depth >= 3
                LEFT JOIN {course_categories} l4
                     ON l4.id = {$l4}
                     AND cc.depth >= 4";
    }

    /**
     * Expresiones SQL de `yearname` y `periodname` (usar junto a {@see self::dimension_joins()}).
     *
     * El año es el primer ancestro de nivel 2 o 3 cuyo nombre es un año
     * (4 dígitos) y el período la categoría inmediatamente debajo. Casi todas
     * las escuelas son escuela/año/período, pero algunas (p. ej. el Centro de
     * Investigaciones) tienen un nivel intermedio (escuela/unidad/año/período)
     * o unidades sin año: asumir "nivel 2 = año" metía nombres de unidades
     * en el label `year` y años en `period`. Sin año reconocible, ambos quedan vacíos.
     *
     * @return array{0: string, 1: string} [yearexpr, periodexpr]
     */
    private static function dimension_exprs(): array {
        global $DB;
        if ($DB->get_dbfamily() === 'postgres') {
            $isyear2 = "l2.name ~ '^[0-9]{4}$'";
            $isyear3 = "l3.name ~ '^[0-9]{4}$'";
        } else {
            $isyear2 = "l2.name REGEXP '^[0-9]{4}$'";
            $isyear3 = "l3.name REGEXP '^[0-9]{4}$'";
        }
        return [
            "CASE WHEN {$isyear2} THEN l2.name WHEN {$isyear3} THEN l3.name ELSE '' END",
            "CASE WHEN {$isyear2} THEN COALESCE(l3.name, '') WHEN {$isyear3} THEN COALESCE(l4.name, '') ELSE '' END",
        ];
    }

    /**
     * Etiquetas school/year/period/course de una fila de
     * {@see self::course_dimension_map()}, {@see self::quiz_dimension_map()}
     * o {@see self::assign_dimension_map()}.
     *
     * @param \stdClass $row
     * @return array<string, string>
     */
    public static function course_labels(\stdClass $row): array {
        return [
            'school' => $row->school,
            'year'   => $row->yearname,
            'period' => $row->periodname,
            'course' => $row->coursename,
        ];
    }

    /**
     * Etiquetas school/year/period de cada combinación que existe hoy, sin
     * `course`. Sirven para rellenar en 0 una métrica por curso que sólo
     * emite valores distintos de 0: sumadas no cambian nada y un panel de
     * Grafana filtrado por escuela/año/período muestra "0" en vez de
     * "sin datos".
     *
     * @return array<int, array<string, string>>
     */
    public static function dimension_label_sets(): array {
        $out = [];
        foreach (self::dimension_keys() as $key) {
            [$school, $year, $period] = explode("\x1f", $key);
            $out[] = ['school' => $school, 'year' => $year, 'period' => $period];
        }
        return $out;
    }

    /**
     * Rellenos en 0 ({@see self::dimension_label_sets()}) para las
     * combinaciones de `$map` (quiz o tarea) — así una métrica de T7/T8 no
     * genera series de escuelas sin cuestionarios/tareas.
     *
     * @param array<int, \stdClass> $map
     * @return array<int, array<string, string>>
     */
    public static function dimension_label_sets_of(array $map): array {
        $out = [];
        foreach ($map as $row) {
            $out[implode("\x1f", [$row->school, $row->yearname, $row->periodname])] =
                ['school' => $row->school, 'year' => $row->yearname, 'period' => $row->periodname];
        }
        return array_values($out);
    }

    /**
     * Valores de una métrica por curso, listos para `yield from`.
     *
     * Emite una serie por curso (etiquetas {@see self::course_labels()}) sólo
     * si el valor es distinto de 0, y después los rellenos en 0 sin `course`
     * (`$fillsets`, por defecto {@see self::dimension_label_sets()}). Los
     * cursos que no están en `$map` (curso sitio, huérfanos) se ignoran.
     *
     * @param array<int, int|float|array<string, int|float>> $bycourse courseid => valor,
     *        o courseid => [valor de `$extralabel` => valor] si hay `$extralabel`
     * @param array<int, \stdClass> $map courseid => fila de {@see self::course_dimension_map()}
     * @param string|null $extralabel label adicional (p. ej. `status`, `type`)
     * @param string[] $extravalues valores de `$extralabel` a rellenar en 0
     * @param array<int, array<string, string>>|null $fillsets
     * @return \Generator<metric_value>
     */
    public static function course_values(array $bycourse, array $map, ?string $extralabel = null,
            array $extravalues = [], ?array $fillsets = null): \Generator {
        foreach ($bycourse as $courseid => $value) {
            if (!isset($map[$courseid])) {
                continue;
            }
            $labels = self::course_labels($map[$courseid]);
            if ($extralabel === null) {
                if ($value != 0) {
                    yield new metric_value($value, $labels);
                }
                continue;
            }
            foreach ($value as $extra => $n) {
                if ($n != 0) {
                    yield new metric_value($n, $labels + [$extralabel => (string) $extra]);
                }
            }
        }
        foreach ($fillsets ?? self::dimension_label_sets() as $labels) {
            if ($extralabel === null) {
                yield new metric_value(0, $labels);
                continue;
            }
            foreach ($extravalues as $extra) {
                yield new metric_value(0, $labels + [$extralabel => $extra]);
            }
        }
    }

    /**
     * Matrículas (`user_enrolments`) por curso.
     *
     * @return array<int, \stdClass> courseid => {enrolments_total, active, suspended}
     */
    public static function enrolments_by_course(): array {
        if (!isset(self::$cache[__FUNCTION__])) {
            $sql = "SELECT e.courseid,
                           COUNT(*) AS enrolments_total,
                           SUM(CASE WHEN ue.status = 0 THEN 1 ELSE 0 END) AS active,
                           SUM(CASE WHEN ue.status = 1 THEN 1 ELSE 0 END) AS suspended
                      FROM {user_enrolments} ue
                      JOIN {enrol} e ON e.id = ue.enrolid
                     GROUP BY e.courseid";
            $out = [];
            foreach (self::fetch_all($sql) as $row) {
                $out[(int) $row->courseid] = $row;
            }
            self::$cache[__FUNCTION__] = $out;
        }
        return self::$cache[__FUNCTION__];
    }

    /**
     * Asignaciones de rol en contexto de curso, por rol y curso
     * (= "matrículas con rol X" en ese curso).
     *
     * @return array<int, \stdClass> filas con: roleshortname, courseid, pairs
     */
    public static function role_pairs_by_course(): array {
        if (!isset(self::$cache[__FUNCTION__])) {
            $sql = "SELECT r.shortname AS roleshortname, ctx.instanceid AS courseid, COUNT(*) AS pairs
                      FROM {role_assignments} ra
                      JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
                      JOIN {role} r ON r.id = ra.roleid
                     GROUP BY r.shortname, ctx.instanceid";
            self::$cache[__FUNCTION__] = self::fetch_all($sql);
        }
        return self::$cache[__FUNCTION__];
    }

    /**
     * Matrículas agrupadas por método de inscripción (manual, self, cohort...).
     *
     * @return array<string, int> método => cantidad
     */
    public static function enrolments_by_method(): array {
        global $DB;
        if (!isset(self::$cache[__FUNCTION__])) {
            $sql = "SELECT e.enrol AS method, COUNT(*) AS n
                      FROM {user_enrolments} ue
                      JOIN {enrol} e ON e.id = ue.enrolid
                     GROUP BY e.enrol";
            $out = [];
            foreach (self::fetch_all($sql) as $r) {
                $out[$r->method] = (int) $r->n;
            }
            self::$cache[__FUNCTION__] = $out;
        }
        return self::$cache[__FUNCTION__];
    }

    /**
     * Asignaciones de rol en contexto de curso, por rol y escuela.
     *
     * `pairs` = nº de asignaciones (userid, curso) → "matrículas con rol X".
     * `users` = usuarios distintos con ese rol en esa escuela.
     *
     * @return array<int, \stdClass> filas con: roleshortname, school, pairs, users
     */
    public static function role_assignments_by_school(): array {
        global $DB;
        if (!isset(self::$cache[__FUNCTION__])) {
            $l1 = self::level_id_expr(2);
            $sql = "SELECT r.shortname AS roleshortname, l1.name AS school,
                           COUNT(*) AS pairs,
                           COUNT(DISTINCT ra.userid) AS users
                      FROM {role_assignments} ra
                      JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
                      JOIN {role} r ON r.id = ra.roleid
                      JOIN {course} c ON c.id = ctx.instanceid
                      JOIN {course_categories} cc ON cc.id = c.category
                      JOIN {course_categories} l1
                           ON l1.id = {$l1}
                     GROUP BY r.shortname, l1.id, l1.name";
            self::$cache[__FUNCTION__] = self::fetch_all($sql);
        }
        return self::$cache[__FUNCTION__];
    }

    /**
     * Cantidad de estudiantes con rol de estudiante en más de un curso.
     *
     * @return int
     */
    public static function students_in_multiple_courses(): int {
        global $DB;
        if (!isset(self::$cache[__FUNCTION__])) {
            $sql = "SELECT COUNT(*) AS n FROM (
                        SELECT ra.userid
                          FROM {role_assignments} ra
                          JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
                          JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
                         GROUP BY ra.userid
                        HAVING COUNT(DISTINCT ctx.instanceid) > 1
                    ) t";
            self::$cache[__FUNCTION__] = (int) $DB->get_field_sql($sql);
        }
        return self::$cache[__FUNCTION__];
    }

    /**
     * Usuarios reales (id > 2, no borrados/suspendidos) con actividad reciente,
     * por escuela.
     *
     * @param string[] $roleshortnames roles a incluir (contexto curso)
     * @param int $sincets sólo usuarios con lastaccess >= este timestamp
     * @return array<string, int> school => cantidad de usuarios distintos
     */
    public static function active_users_by_school(array $roleshortnames, int $sincets): array {
        global $DB;
        [$rolesql, $params] = $DB->get_in_or_equal($roleshortnames, SQL_PARAMS_NAMED, 'role');
        $params['since'] = $sincets;
        $l1 = self::level_id_expr(2);
        $sql = "SELECT l1.name AS school, COUNT(DISTINCT ra.userid) AS n
                  FROM {role_assignments} ra
                  JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
                  JOIN {role} r ON r.id = ra.roleid AND r.shortname $rolesql
                  JOIN {course} c ON c.id = ctx.instanceid
                  JOIN {course_categories} cc ON cc.id = c.category
                  JOIN {course_categories} l1
                       ON l1.id = {$l1}
                  JOIN {user} u ON u.id = ra.userid
                       AND u.deleted = 0 AND u.suspended = 0
                       AND u.lastaccess > 0 AND u.lastaccess >= :since
                 GROUP BY l1.id, l1.name";
        $out = [];
        foreach (self::fetch_all($sql, $params) as $r) {
            $out[$r->school] = (int) $r->n;
        }
        return $out;
    }

    /**
     * Curso -> escuela/año/período/visibilidad (HU3-T5).
     *
     * Una fila por curso real (excluye el curso sitio, id 1). Base compartida
     * para todas las métricas de T5: se computa una sola vez por request y el
     * resto de los métodos de esta sección la reusan en PHP en vez de repetir
     * el JOIN a la jerarquía de categorías.
     *
     * @return array<int, \stdClass> courseid => {school, yearname, periodname, visible}
     */
    public static function course_dimension_map(): array {
        if (!isset(self::$cache[__FUNCTION__])) {
            $joins = self::dimension_joins();
            [$yearexpr, $periodexpr] = self::dimension_exprs();
            $sql = "SELECT c.id AS courseid,
                           l1.name AS school,
                           {$yearexpr} AS yearname,
                           {$periodexpr} AS periodname,
                           c.fullname AS coursename,
                           c.shortname AS shortname,
                           c.visible AS visible
                      FROM {course} c
                      {$joins}
                     WHERE c.id <> 1";
            $map = [];
            $seen = [];
            foreach (self::fetch_all($sql) as $row) {
                $map[(int) $row->courseid] = $row;
                $key = implode("\x1f", [$row->school, $row->yearname, $row->periodname, $row->coursename]);
                $seen[$key][] = (int) $row->courseid;
            }
            // Dos cursos con el mismo nombre en la misma escuela/año/período
            // darían series idénticas y Prometheus se quedaría con una sola:
            // a esos se les agrega el nombre corto, «NOMBRE (UEDI-123)».
            foreach ($seen as $ids) {
                if (count($ids) > 1) {
                    foreach ($ids as $id) {
                        $map[$id]->coursename .= ' (' . $map[$id]->shortname . ')';
                    }
                }
            }
            self::$cache[__FUNCTION__] = $map;
        }
        return self::$cache[__FUNCTION__];
    }

    /**
     * IDs de curso con al menos una matrícula activa (`user_enrolments.status = 0`).
     *
     * @return array<int, true> set de courseid
     */
    public static function active_enrolled_courseids(): array {
        if (!isset(self::$cache[__FUNCTION__])) {
            $sql = "SELECT DISTINCT e.courseid
                      FROM {user_enrolments} ue
                      JOIN {enrol} e ON e.id = ue.enrolid
                     WHERE ue.status = 0";
            $set = [];
            foreach (self::fetch_all($sql) as $row) {
                $set[(int) $row->courseid] = true;
            }
            self::$cache[__FUNCTION__] = $set;
        }
        return self::$cache[__FUNCTION__];
    }

    /**
     * IDs de curso con al menos un evento en `logstore_standard_log` desde `$sincets`.
     *
     * @param int $sincets timestamp desde el cual contar actividad
     * @return array<int, true> set de courseid
     */
    public static function courseids_with_recent_activity(int $sincets): array {
        $key = __FUNCTION__ . ':' . $sincets;
        if (!isset(self::$cache[$key])) {
            $sql = "SELECT DISTINCT courseid
                      FROM {logstore_standard_log}
                     WHERE courseid IS NOT NULL AND courseid <> 1 AND timecreated >= :since";
            $set = [];
            foreach (self::fetch_all($sql, ['since' => $sincets]) as $row) {
                $set[(int) $row->courseid] = true;
            }
            self::$cache[$key] = $set;
        }
        return self::$cache[$key];
    }

    /**
     * IDs de curso donde al menos un usuario tiene alguno de los roles dados
     * (contexto curso).
     *
     * @param string[] $roleshortnames
     * @return array<int, true> set de courseid
     */
    public static function courseids_with_role(array $roleshortnames): array {
        global $DB;
        $key = __FUNCTION__ . ':' . implode(',', $roleshortnames);
        if (!isset(self::$cache[$key])) {
            [$rolesql, $params] = $DB->get_in_or_equal($roleshortnames, SQL_PARAMS_NAMED, 'role');
            $sql = "SELECT DISTINCT ctx.instanceid AS courseid
                      FROM {role_assignments} ra
                      JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
                      JOIN {role} r ON r.id = ra.roleid AND r.shortname $rolesql";
            $set = [];
            foreach (self::fetch_all($sql, $params) as $row) {
                $set[(int) $row->courseid] = true;
            }
            self::$cache[$key] = $set;
        }
        return self::$cache[$key];
    }

    /**
     * Cantidad de estudiantes distintos (rol `student`, contexto curso) por curso.
     *
     * @return array<int, int> courseid => cantidad de estudiantes
     */
    public static function student_counts_by_course(): array {
        if (!isset(self::$cache[__FUNCTION__])) {
            $sql = "SELECT ctx.instanceid AS courseid, COUNT(DISTINCT ra.userid) AS n
                      FROM {role_assignments} ra
                      JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
                      JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
                     GROUP BY ctx.instanceid";
            $counts = [];
            foreach (self::fetch_all($sql) as $row) {
                $counts[(int) $row->courseid] = (int) $row->n;
            }
            self::$cache[__FUNCTION__] = $counts;
        }
        return self::$cache[__FUNCTION__];
    }

    /**
     * Combinaciones distintas de escuela/año/período que existen hoy (según
     * {@see self::course_dimension_map()}). Se usa para completar en 0 las
     * métricas de T6 que de otro modo omitirían por completo una dimensión
     * sin eventos en la ventana — un panel de Grafana muestra "0" en vez de
     * "sin datos", más claro para un usuario no técnico.
     *
     * @return string[] lista de "school\x1fyear\x1fperiod"
     */
    public static function dimension_keys(): array {
        if (!isset(self::$cache[__FUNCTION__])) {
            $keys = [];
            foreach (self::course_dimension_map() as $row) {
                $keys[implode("\x1f", [$row->school, $row->yearname, $row->periodname])] = true;
            }
            self::$cache[__FUNCTION__] = array_keys($keys);
        }
        return self::$cache[__FUNCTION__];
    }

    /**
     * Cantidad de eventos de un tipo dado en `logstore_standard_log`, por
     * curso, desde `$sincets`. Curso 1 (sitio) y `courseid` nulos se excluyen.
     *
     * @param string $eventname nombre completo del evento (p. ej. `\core\event\course_viewed`)
     * @param int $sincets
     * @return array<int, int> courseid => cantidad
     */
    public static function event_counts_by_course(string $eventname, int $sincets): array {
        $key = __FUNCTION__ . ':' . $eventname . ':' . $sincets;
        if (!isset(self::$cache[$key])) {
            $sql = "SELECT courseid, COUNT(*) AS n
                      FROM {logstore_standard_log}
                     WHERE eventname = :eventname
                       AND timecreated >= :since
                       AND courseid IS NOT NULL AND courseid <> 1
                     GROUP BY courseid";
            $counts = [];
            foreach (self::fetch_all($sql, ['eventname' => $eventname, 'since' => $sincets]) as $row) {
                $counts[(int) $row->courseid] = (int) $row->n;
            }
            self::$cache[$key] = $counts;
        }
        return self::$cache[$key];
    }

    /**
     * Precarga {@see self::event_counts_by_course()} para varios eventos con un
     * único recorrido del log (en vez de uno por evento: sin índice por
     * `eventname`, cada consulta recorre toda la ventana de tiempo).
     *
     * @param string[] $eventnames
     * @param int $sincets
     */
    public static function preload_event_counts_by_course(array $eventnames, int $sincets): void {
        global $DB;
        [$insql, $params] = $DB->get_in_or_equal($eventnames, SQL_PARAMS_NAMED, 'ev');
        $params['since'] = $sincets;
        $sql = "SELECT eventname, courseid, COUNT(*) AS n
                  FROM {logstore_standard_log}
                 WHERE eventname $insql
                   AND timecreated >= :since
                   AND courseid IS NOT NULL AND courseid <> 1
                 GROUP BY eventname, courseid";
        $counts = array_fill_keys($eventnames, []);
        foreach (self::fetch_all($sql, $params) as $row) {
            $counts[$row->eventname][(int) $row->courseid] = (int) $row->n;
        }
        foreach ($counts as $eventname => $bycourse) {
            self::$cache['event_counts_by_course:' . $eventname . ':' . $sincets] = $bycourse;
        }
    }

    /**
     * Pares (courseid, userid) distintos con un evento "de participación"
     * (`edulevel = 2`, la clasificación propia de Moodle para acciones de
     * alumno/docente sobre contenido de curso, no de administración) desde
     * `$sincets`. Base para `unique_course_users`.
     *
     * @param int $sincets
     * @return array<int, \stdClass> filas con: courseid, userid
     */
    public static function participating_course_user_pairs(int $sincets): array {
        $key = __FUNCTION__ . ':' . $sincets;
        if (!isset(self::$cache[$key])) {
            $sql = "SELECT DISTINCT courseid, userid
                      FROM {logstore_standard_log}
                     WHERE edulevel = 2
                       AND timecreated >= :since
                       AND courseid IS NOT NULL AND courseid <> 1";
            self::$cache[$key] = self::fetch_all($sql, ['since' => $sincets]);
        }
        return self::$cache[$key];
    }

    /**
     * IDs de usuario con rol `student` en algún curso (cualquier escuela).
     *
     * @return array<int, true> set de userid
     */
    public static function student_userids(): array {
        if (!isset(self::$cache[__FUNCTION__])) {
            $sql = "SELECT DISTINCT ra.userid
                      FROM {role_assignments} ra
                      JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
                      JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'";
            $set = [];
            foreach (self::fetch_all($sql) as $row) {
                $set[(int) $row->userid] = true;
            }
            self::$cache[__FUNCTION__] = $set;
        }
        return self::$cache[__FUNCTION__];
    }

    /**
     * Cantidad de logins exitosos (`\core\event\user_loggedin`) de usuarios
     * con rol `student`, desde `$sincets`. Sitewide, sin dimensión.
     *
     * @param int $sincets
     * @return int
     */
    public static function student_login_count(int $sincets): int {
        global $DB;
        $key = __FUNCTION__ . ':' . $sincets;
        if (!isset(self::$cache[$key])) {
            // EXISTS y no JOIN: con JOIN cada login se contaba una vez por cada
            // curso en el que el usuario es estudiante.
            $sql = "SELECT COUNT(*) AS n
                      FROM {logstore_standard_log} l
                     WHERE l.eventname = :eventname
                       AND l.timecreated >= :since
                       AND EXISTS (SELECT 1
                                     FROM {role_assignments} ra
                                     JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
                                     JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
                                    WHERE ra.userid = l.userid)";
            self::$cache[$key] = (int) $DB->count_records_sql($sql, [
                'eventname' => '\core\event\user_loggedin',
                'since' => $sincets,
            ]);
        }
        return self::$cache[$key];
    }

    /**
     * Cantidad total de eventos registrados desde `$sincets` (cualquier tipo).
     *
     * @param int $sincets
     * @return int
     */
    // Las métricas activity_* cuentan actividad de personas: excluyen los eventos
    // con origin = 'cli' (tareas programadas y scripts de carga masiva).
    public static function activity_event_count(int $sincets): int {
        global $DB;
        $key = __FUNCTION__ . ':' . $sincets;
        if (!isset(self::$cache[$key])) {
            self::$cache[$key] = (int) $DB->count_records_select(
                'logstore_standard_log',
                "timecreated >= :since AND origin <> 'cli'",
                ['since' => $sincets]
            );
        }
        return self::$cache[$key];
    }

    /**
     * Eventos (sin los de origen `cli`) desde `$sincets`, contados por curso y
     * por "cubeta" (`$bucketexpr`: tipo de evento, hora, día o semana), y
     * luego sumados por escuela/año/período en PHP con
     * {@see self::course_dimension_map()}. El `GROUP BY courseid, cubeta` en
     * SQL devuelve pocas filas (~15k para 7 días por hora) y evita repetir el
     * JOIN a la jerarquía de categorías sobre el log.
     *
     * Los eventos que no son de un curso (inicio de sesión, tablero, curso
     * sitio) o de un curso ya borrado quedan bajo la clave `''`.
     *
     * @param string $bucketexpr expresión SQL de la cubeta
     * @param int $sincets
     * @return array<string, array<string, int>> cubeta => ("school\x1fyear\x1fperiod" | '') => cantidad
     */
    private static function activity_by_bucket(string $bucketexpr, int $sincets): array {
        $key = __FUNCTION__ . ':' . $bucketexpr . ':' . $sincets;
        if (!isset(self::$cache[$key])) {
            $sql = "SELECT courseid, {$bucketexpr} AS b, COUNT(*) AS n
                      FROM {logstore_standard_log}
                     WHERE timecreated >= :since
                       AND origin <> 'cli'
                     GROUP BY courseid, b";
            $map = self::course_dimension_map();
            $out = [];
            foreach (self::fetch_all($sql, ['since' => $sincets]) as $row) {
                $courseid = (int) $row->courseid;
                $dimkey = '';
                if (isset($map[$courseid])) {
                    $c = $map[$courseid];
                    $dimkey = implode("\x1f", [$c->school, $c->yearname, $c->periodname]);
                }
                $bucket = (string) $row->b;
                $out[$bucket][$dimkey] = ($out[$bucket][$dimkey] ?? 0) + (int) $row->n;
            }
            self::$cache[$key] = $out;
        }
        return self::$cache[$key];
    }

    /**
     * Etiquetas school/year/period de una clave de {@see self::activity_by_bucket()}
     * (vacías para la clave `''`).
     *
     * @param string $dimkey
     * @return array<string, string>
     */
    public static function dimension_labels_of_key(string $dimkey): array {
        if ($dimkey === '') {
            return [];
        }
        [$school, $year, $period] = explode("\x1f", $dimkey);
        return ['school' => $school, 'year' => $year, 'period' => $period];
    }

    /**
     * Eventos desde `$sincets`, por `eventname` y escuela/año/período.
     *
     * @param int $sincets
     * @return array<string, array<string, int>> eventname => dimkey => cantidad
     */
    public static function activity_event_counts_by_type(int $sincets): array {
        return self::activity_by_bucket('eventname', $sincets);
    }

    /**
     * Expresión SQL con el instante de `timecreated` en la hora local del sitio
     * (zona horaria configurada en Moodle), independiente de la zona horaria
     * del servidor de base de datos. Usa el desfase actual de esa zona.
     *
     * @return string
     */
    private static function local_time_expr(): string {
        global $DB;
        $offset = (new \DateTimeZone(\core_date::get_server_timezone()))->getOffset(new \DateTime('now'));
        if ($DB->get_dbfamily() === 'postgres') {
            return "to_timestamp(timecreated + ($offset))";
        }
        return "DATE_ADD('1970-01-01 00:00:00', INTERVAL (timecreated + ($offset)) SECOND)";
    }

    /**
     * Eventos desde `$sincets`, por hora del día (0-23, hora local del sitio)
     * y escuela/año/período. Las 24 horas están siempre, con la clave `''`
     * en 0 si no hubo eventos.
     *
     * @param int $sincets
     * @return array<int, array<string, int>> hora (0-23) => dimkey => cantidad
     */
    public static function activity_event_counts_by_hour(int $sincets): array {
        global $DB;
        if ($DB->get_dbfamily() === 'postgres') {
            $hourexpr = "to_char(" . self::local_time_expr() . ", 'FMHH24')::integer";
        } else {
            $hourexpr = 'HOUR(' . self::local_time_expr() . ')';
        }
        $bybucket = self::activity_by_bucket($hourexpr, $sincets);
        $out = [];
        for ($h = 0; $h < 24; $h++) {
            $out[$h] = ($bybucket[(string) $h] ?? []) + ['' => 0];
        }
        return $out;
    }

    /**
     * Eventos desde `$sincets`, por día calendario (`YYYY-MM-DD`, hora local
     * del sitio) y escuela/año/período. Incluye todos los días del rango, con
     * la clave `''` en 0 los vacíos.
     *
     * @param int $sincets
     * @return array<string, array<string, int>> fecha => dimkey => cantidad
     */
    public static function activity_event_counts_by_day(int $sincets): array {
        global $DB;
        if ($DB->get_dbfamily() === 'postgres') {
            $dayexpr = "to_char(" . self::local_time_expr() . ", 'YYYY-MM-DD')";
        } else {
            $dayexpr = 'DATE(' . self::local_time_expr() . ')';
        }
        $bybucket = self::activity_by_bucket($dayexpr, $sincets);
        $out = [];
        $tz = \core_date::get_server_timezone_object();
        $day = (new \DateTime('@' . $sincets))->setTimezone($tz)->setTime(0, 0);
        $today = (new \DateTime('now', $tz))->format('Y-m-d');
        while (($d = $day->format('Y-m-d')) <= $today) {
            $out[$d] = ($bybucket[$d] ?? []) + ['' => 0];
            $day->modify('+1 day');
        }
        return $out;
    }

    /**
     * Eventos desde `$sincets`, por semana (lunes a domingo, hora local del
     * sitio, identificada por la fecha del lunes) y escuela/año/período.
     *
     * @param int $sincets
     * @return array<string, array<string, int>> lunes => dimkey => cantidad
     */
    public static function activity_event_counts_by_week(int $sincets): array {
        global $DB;
        if ($DB->get_dbfamily() === 'postgres') {
            $weekexpr = "to_char(date_trunc('week', " . self::local_time_expr() . "), 'YYYY-MM-DD')";
        } else {
            $local = self::local_time_expr();
            // WEEKDAY(): 0 = lunes. Restarlo lleva cada fecha al lunes de su semana.
            $weekexpr = "DATE_SUB(DATE($local), INTERVAL WEEKDAY($local) DAY)";
        }
        $out = self::activity_by_bucket($weekexpr, $sincets);
        ksort($out);
        return $out;
    }

    /**
     * Usuarios reales, no suspendidos, agrupados por antigüedad de su último
     * acceso (`user.lastaccess`) en rangos fijos y excluyentes entre sí.
     * Base para `last_activity_age` (HU3-T6).
     *
     * A diferencia de `inactive_users` (HU3-T3, que cuenta cuántos superan
     * cada umbral de forma acumulativa), esto es una partición: cada usuario
     * cae en exactamente un rango, y los rangos suman el total de cuentas
     * reales no suspendidas.
     *
     * @return array<string, int> rango => cantidad. Rangos:
     *         `0-1d`, `1-7d`, `7-30d`, `30d+`, `nunca`.
     */
    public static function last_activity_age_buckets(): array {
        if (!isset(self::$cache[__FUNCTION__])) {
            $now = time();
            $sql = "SELECT
                        CASE
                            WHEN lastaccess = 0 THEN 'nunca'
                            WHEN lastaccess >= :t1 THEN '0-1d'
                            WHEN lastaccess >= :t7 THEN '1-7d'
                            WHEN lastaccess >= :t30 THEN '7-30d'
                            ELSE '30d+'
                        END AS rango,
                        COUNT(*) AS n
                      FROM {user}
                     WHERE id > 2 AND deleted = 0 AND suspended = 0
                     GROUP BY rango";
            $params = [
                't1'  => $now - DAYSECS,
                't7'  => $now - 7 * DAYSECS,
                't30' => $now - 30 * DAYSECS,
            ];
            $out = ['0-1d' => 0, '1-7d' => 0, '7-30d' => 0, '30d+' => 0, 'nunca' => 0];
            foreach (self::fetch_all($sql, $params) as $row) {
                $out[$row->rango] = (int) $row->n;
            }
            self::$cache[__FUNCTION__] = $out;
        }
        return self::$cache[__FUNCTION__];
    }

    /**
     * Quiz -> escuela/año/período/ventana de apertura/nota máxima (HU3-T8).
     *
     * Una fila por quiz real (el curso sitio, id 1, no tiene actividades).
     * Base compartida para todas las métricas de T8: se computa una sola vez
     * por request, mismo criterio que {@see self::course_dimension_map()}.
     *
     * @return array<int, \stdClass> quizid => {courseid, school, yearname,
     *         periodname, timeopen, timeclose, maxgrade}
     */
    public static function quiz_dimension_map(): array {
        if (!isset(self::$cache[__FUNCTION__])) {
            $joins = self::dimension_joins();
            [$yearexpr, $periodexpr] = self::dimension_exprs();
            $sql = "SELECT q.id AS quizid,
                           q.course AS courseid,
                           l1.name AS school,
                           {$yearexpr} AS yearname,
                           {$periodexpr} AS periodname,
                           c.fullname AS coursename,
                           q.timeopen AS timeopen,
                           q.timeclose AS timeclose,
                           q.grade AS maxgrade
                      FROM {quiz} q
                      JOIN {course} c ON c.id = q.course
                      {$joins}
                     WHERE c.id <> 1";
            $map = [];
            foreach (self::fetch_all($sql) as $row) {
                $map[(int) $row->quizid] = $row;
            }
            self::$cache[__FUNCTION__] = $map;
        }
        return self::$cache[__FUNCTION__];
    }

    /**
     * Intentos de quiz reales, sin previsualizaciones de profesor (`preview = 1`).
     *
     * @return array<int, \stdClass> filas con: quizid, userid, state, timestart, timefinish
     */
    public static function quiz_attempts_raw(): array {
        if (!isset(self::$cache[__FUNCTION__])) {
            $sql = "SELECT quiz AS quizid, userid, state, timestart, timefinish
                      FROM {quiz_attempts}
                     WHERE preview = 0";
            self::$cache[__FUNCTION__] = self::fetch_all($sql);
        }
        return self::$cache[__FUNCTION__];
    }

    /**
     * Calificaciones finales de quiz (`quiz_grades`). Ya están en la escala de
     * `quiz.grade` (la nota máxima configurada), no en la escala cruda de
     * `sumgrades`.
     *
     * @return array<int, \stdClass> filas con: quizid, userid, grade
     */
    public static function quiz_grades_raw(): array {
        if (!isset(self::$cache[__FUNCTION__])) {
            $sql = "SELECT quiz AS quizid, userid, grade FROM {quiz_grades}";
            self::$cache[__FUNCTION__] = self::fetch_all($sql);
        }
        return self::$cache[__FUNCTION__];
    }

    /**
     * Umbral de aprobación configurado por quiz (`grade_items.gradepass`),
     * en la misma escala que `quiz.grade` / `quiz_grades.grade`. Sólo incluye
     * quizzes con un umbral realmente configurado (`gradepass > 0`) — ver la
     * nota de criterio de aprobación en `quiz_grade_results`.
     *
     * @return array<int, float> quizid => gradepass
     */
    public static function quiz_gradepass_map(): array {
        if (!isset(self::$cache[__FUNCTION__])) {
            $sql = "SELECT iteminstance AS quizid, gradepass
                      FROM {grade_items}
                     WHERE itemmodule = 'quiz' AND gradepass > 0";
            $out = [];
            foreach (self::fetch_all($sql) as $row) {
                $out[(int) $row->quizid] = (float) $row->gradepass;
            }
            self::$cache[__FUNCTION__] = $out;
        }
        return self::$cache[__FUNCTION__];
    }

    /**
     * Tarea (`assign`) -> escuela/año/período/ventana de entrega (HU3-T7).
     *
     * Una fila por tarea real. Base compartida para todas las métricas de T7,
     * mismo criterio que {@see self::quiz_dimension_map()}.
     *
     * @return array<int, \stdClass> assignid => {courseid, school, yearname,
     *         periodname, allowsubmissionsfromdate, duedate}
     */
    public static function assign_dimension_map(): array {
        if (!isset(self::$cache[__FUNCTION__])) {
            $joins = self::dimension_joins();
            [$yearexpr, $periodexpr] = self::dimension_exprs();
            $sql = "SELECT a.id AS assignid,
                           a.course AS courseid,
                           l1.name AS school,
                           {$yearexpr} AS yearname,
                           {$periodexpr} AS periodname,
                           c.fullname AS coursename,
                           a.allowsubmissionsfromdate AS allowfrom,
                           a.duedate AS duedate
                      FROM {assign} a
                      JOIN {course} c ON c.id = a.course
                      {$joins}
                     WHERE c.id <> 1";
            $map = [];
            foreach (self::fetch_all($sql) as $row) {
                $map[(int) $row->assignid] = $row;
            }
            self::$cache[__FUNCTION__] = $map;
        }
        return self::$cache[__FUNCTION__];
    }

    /**
     * Entregas de tarea, sólo el intento vigente de cada estudiante
     * (`latest = 1` — Moodle marca así la fila activa cuando
     * `maxattempts > 1`; en este ambiente todas las tareas tienen
     * `maxattempts = 1`, pero se filtra igual por corrección).
     *
     * @return array<int, \stdClass> filas con: assignid, userid, status, timecreated, timemodified
     */
    public static function assign_submissions_raw(): array {
        if (!isset(self::$cache[__FUNCTION__])) {
            $sql = "SELECT assignment AS assignid, userid, status, timecreated, timemodified
                      FROM {assign_submission}
                     WHERE latest = 1";
            self::$cache[__FUNCTION__] = self::fetch_all($sql);
        }
        return self::$cache[__FUNCTION__];
    }

    /**
     * Calificaciones de tarea (`assign_grades`). La sola presencia de una fila
     * para un `(assignid, userid)` significa que esa entrega ya fue calificada
     * — Moodle sólo inserta la fila cuando un docente pone nota.
     *
     * @return array<int, \stdClass> filas con: assignid, userid, timemodified
     */
    public static function assign_grades_raw(): array {
        if (!isset(self::$cache[__FUNCTION__])) {
            $sql = "SELECT assignment AS assignid, userid, timemodified
                      FROM {assign_grades}";
            self::$cache[__FUNCTION__] = self::fetch_all($sql);
        }
        return self::$cache[__FUNCTION__];
    }

    /**
     * Ejecuta un SQL agregado y devuelve todas las filas como lista.
     *
     * Se usa un recordset porque las filas agregadas no tienen primera columna
     * única (lo que haría que `get_records_sql` descartara filas).
     *
     * @param string $sql
     * @param array $params
     * @return array<int, \stdClass>
     */

    /**
     * Conjuntos de etiquetas para escuelas/anos/periodos que tienen al menos una tarea.
     * Consulta rapida y directa a la BD sin instanciar miles de objetos.
     *
     * @return array<int, array<string, string>>
     */
    public static function assign_dimension_label_sets(): array {
        if (!isset(self::$cache[__FUNCTION__])) {
            $joins = self::dimension_joins();
            [$yearexpr, $periodexpr] = self::dimension_exprs();
            $sql = "SELECT DISTINCT l1.name AS school, {$yearexpr} AS yearname, {$periodexpr} AS periodname
                      FROM {assign} a
                      JOIN {course} c ON c.id = a.course
                      {$joins}
                     WHERE c.id <> 1";
            $out = [];
            foreach (self::fetch_all($sql) as $row) {
                $out[] = ['school' => $row->school, 'year' => $row->yearname, 'period' => $row->periodname];
            }
            self::$cache[__FUNCTION__] = $out;
        }
        return self::$cache[__FUNCTION__];
    }

    /**
     * Conjuntos de etiquetas para escuelas/anos/periodos que tienen al menos un quiz.
     *
     * @return array<int, array<string, string>>
     */
    public static function quiz_dimension_label_sets(): array {
        if (!isset(self::$cache[__FUNCTION__])) {
            $joins = self::dimension_joins();
            [$yearexpr, $periodexpr] = self::dimension_exprs();
            $sql = "SELECT DISTINCT l1.name AS school, {$yearexpr} AS yearname, {$periodexpr} AS periodname
                      FROM {quiz} q
                      JOIN {course} c ON c.id = q.course
                      {$joins}
                     WHERE c.id <> 1";
            $out = [];
            foreach (self::fetch_all($sql) as $row) {
                $out[] = ['school' => $row->school, 'year' => $row->yearname, 'period' => $row->periodname];
            }
            self::$cache[__FUNCTION__] = $out;
        }
        return self::$cache[__FUNCTION__];
    }

    /**
     * Cantidad de tareas por curso.
     *
     * @return array<int, int>
     */
    public static function assign_counts_by_course(): array {
        if (!isset(self::$cache[__FUNCTION__])) {
            $sql = "SELECT course AS courseid, COUNT(*) AS n FROM {assign} WHERE course <> 1 GROUP BY course";
            $out = [];
            foreach (self::fetch_all($sql) as $row) {
                $out[(int) $row->courseid] = (int) $row->n;
            }
            self::$cache[__FUNCTION__] = $out;
        }
        return self::$cache[__FUNCTION__];
    }

    /**
     * Tareas clasificadas por status (upcoming, open, closed) por curso.
     *
     * @return array<int, array<string, int>>
     */
    public static function assign_status_counts(): array {
        if (!isset(self::$cache[__FUNCTION__])) {
            $now = time();
            $sql = "SELECT a.course AS courseid,
                           CASE WHEN a.allowsubmissionsfromdate > 0 AND a.allowsubmissionsfromdate > :now1 THEN 'upcoming'
                                WHEN a.duedate > 0 AND a.duedate < :now2 THEN 'closed'
                                ELSE 'open' END AS status,
                           COUNT(*) AS count_status
                      FROM {assign} a
                     WHERE a.course <> 1
                     GROUP BY a.course, 2";
            $bycourse = [];
            foreach (self::fetch_all($sql, ['now1' => $now, 'now2' => $now]) as $row) {
                $bycourse[(int) $row->courseid][$row->status] = (int) $row->count_status;
            }
            self::$cache[__FUNCTION__] = $bycourse;
        }
        return self::$cache[__FUNCTION__];
    }

    /**
     * Quizzes clasificados por si estan abiertos o cerrados por curso.
     *
     * @return array<int, array<string, int>>
     */
    public static function quiz_open_counts(): array {
        if (!isset(self::$cache[__FUNCTION__])) {
            $now = time();
            $sql = "SELECT q.course AS courseid,
                           CASE WHEN (q.timeopen = 0 OR q.timeopen <= :now1) AND (q.timeclose = 0 OR q.timeclose >= :now2) THEN 'true' ELSE 'false' END AS is_open,
                           COUNT(*) AS count_quiz
                      FROM {quiz} q
                     WHERE q.course <> 1
                     GROUP BY q.course, 2";
            $bycourse = [];
            foreach (self::fetch_all($sql, ['now1' => $now, 'now2' => $now]) as $row) {
                $bycourse[(int) $row->courseid][$row->is_open] = (int) $row->count_quiz;
            }
            self::$cache[__FUNCTION__] = $bycourse;
        }
        return self::$cache[__FUNCTION__];
    }

    /**
     * Helper publico para consultas genericas.
     *
     * @param string $sql
     * @param array $params
     * @return array
     */
    public static function fetch_all_helper(string $sql, array $params = []): array {
        return self::fetch_all($sql, $params);
    }

    /**
     * Duracion promedio de quiz agregada por curso (sum y count).
     *
     * @return array<int, array{sum: int, count: int}>
     */
    public static function quiz_duration_aggregates(): array {
        if (!isset(self::$cache[__FUNCTION__])) {
            $sql = "SELECT q.course AS courseid,
                           SUM(qa.timefinish - qa.timestart) AS sum_dur,
                           COUNT(*) AS count_dur
                      FROM {quiz_attempts} qa
                      JOIN {quiz} q ON q.id = qa.quiz
                     WHERE qa.preview = 0
                       AND qa.state = 'finished'
                       AND qa.timestart > 0
                       AND qa.timefinish > qa.timestart
                     GROUP BY q.course";
            $bycourse = [];
            foreach (self::fetch_all($sql) as $row) {
                $bycourse[(int) $row->courseid] = [
                    'sum' => (int) $row->sum_dur,
                    'count' => (int) $row->count_dur,
                ];
            }
            self::$cache[__FUNCTION__] = $bycourse;
        }
        return self::$cache[__FUNCTION__];
    }

    /**
     * Intentos de quiz agrupados por curso y estado.
     *
     * @return array<int, array<string, int>>
     */
    public static function quiz_attempts_by_state_counts(): array {
        if (!isset(self::$cache[__FUNCTION__])) {
            $sql = "SELECT q.course AS courseid, qa.state, COUNT(*) AS count_state
                      FROM {quiz_attempts} qa
                      JOIN {quiz} q ON q.id = qa.quiz
                     WHERE qa.preview = 0
                     GROUP BY q.course, qa.state";
            $bycourse = [];
            foreach (self::fetch_all($sql) as $row) {
                $bycourse[(int) $row->courseid][$row->state] = (int) $row->count_state;
            }
            self::$cache[__FUNCTION__] = $bycourse;
        }
        return self::$cache[__FUNCTION__];
    }

    /**
     * Estudiantes distintos con intento de quiz por curso.
     *
     * @return array<int, int>
     */
    public static function quiz_attempting_student_counts(): array {
        if (!isset(self::$cache[__FUNCTION__])) {
            $sql = "SELECT q.course AS courseid, COUNT(DISTINCT qa.userid) AS student_count
                      FROM {quiz_attempts} qa
                      JOIN {quiz} q ON q.id = qa.quiz
                     WHERE qa.preview = 0
                     GROUP BY q.course";
            $bycourse = [];
            foreach (self::fetch_all($sql) as $row) {
                $bycourse[(int) $row->courseid] = (int) $row->student_count;
            }
            self::$cache[__FUNCTION__] = $bycourse;
        }
        return self::$cache[__FUNCTION__];
    }

    /**
     * Promedio de notas de quiz normalizado por curso.
     *
     * @return array<int, array{sum: float, count: int}>
     */
    public static function quiz_average_grade_aggregates(): array {
        if (!isset(self::$cache[__FUNCTION__])) {
            $sql = "SELECT q.course AS courseid,
                           SUM((qg.grade / q.grade) * 100) AS sum_grade,
                           COUNT(*) AS count_grade
                      FROM {quiz_grades} qg
                      JOIN {quiz} q ON q.id = qg.quiz
                     WHERE q.grade > 0
                     GROUP BY q.course";
            $bycourse = [];
            foreach (self::fetch_all($sql) as $row) {
                $bycourse[(int) $row->courseid] = [
                    'sum' => round((float) $row->sum_grade, 2),
                    'count' => (int) $row->count_grade,
                ];
            }
            self::$cache[__FUNCTION__] = $bycourse;
        }
        return self::$cache[__FUNCTION__];
    }

    /**
     * Resultados de quiz (passed / failed) por curso.
     *
     * @return array<int, array<string, int>>
     */
    public static function quiz_grade_results_counts(): array {
        if (!isset(self::$cache[__FUNCTION__])) {
            $sql = "SELECT q.course AS courseid,
                           CASE WHEN qg.grade >= COALESCE(NULLIF(gi.gradepass, 0), q.grade * 0.6) THEN 'passed' ELSE 'failed' END AS result,
                           COUNT(*) AS count_res
                      FROM {quiz_grades} qg
                      JOIN {quiz} q ON q.id = qg.quiz
                      LEFT JOIN {grade_items} gi ON gi.itemtype = 'mod' AND gi.itemmodule = 'quiz' AND gi.iteminstance = q.id
                     WHERE q.grade > 0
                     GROUP BY q.course, 2";
            $bycourse = [];
            foreach (self::fetch_all($sql) as $row) {
                $bycourse[(int) $row->courseid][$row->result] = (int) $row->count_res;
            }
            self::$cache[__FUNCTION__] = $bycourse;
        }
        return self::$cache[__FUNCTION__];
    }

    /**
     * Entregas de tarea por timing (ontime / late) por curso.
     *
     * @return array<int, array<string, int>>
     */
    public static function assign_submission_timing_counts(): array {
        if (!isset(self::$cache[__FUNCTION__])) {
            $sql = "SELECT a.course AS courseid,
                           CASE WHEN a.duedate > 0 AND s.timemodified > a.duedate THEN 'late' ELSE 'ontime' END AS timing,
                           COUNT(*) AS count_timing
                      FROM {assign_submission} s
                      JOIN {assign} a ON a.id = s.assignment
                     WHERE s.status = 'submitted'
                     GROUP BY a.course, 2";
            $bycourse = [];
            foreach (self::fetch_all($sql) as $row) {
                $bycourse[(int) $row->courseid][$row->timing] = (int) $row->count_timing;
            }
            self::$cache[__FUNCTION__] = $bycourse;
        }
        return self::$cache[__FUNCTION__];
    }

    /**
     * Entregas de tarea pendientes de calificar por curso.
     *
     * @return array<int, int>
     */
    public static function assign_pending_grading_counts(): array {
        if (!isset(self::$cache[__FUNCTION__])) {
            global $DB;
            $sql = "SELECT a.course AS courseid, COUNT(*) AS pending_count
                      FROM {assign_submission} s
                      JOIN {assign} a ON a.id = s.assignment
                      LEFT JOIN {assign_grades} g ON g.assignment = s.assignment AND g.userid = s.userid AND g.attemptnumber = s.attemptnumber
                     WHERE s.latest = 1 AND s.status = 'submitted'
                       AND g.id IS NULL
                     GROUP BY a.course";
            $bycourse = [];
            foreach ($DB->get_records_sql($sql) as $row) {
                $bycourse[(int) $row->courseid] = (int) $row->pending_count;
            }
            self::$cache[__FUNCTION__] = $bycourse;
        }
        return self::$cache[__FUNCTION__];
    }

    /**
     * Demora promedio de calificacion de tarea por curso (sum y count).
     *
     * @return array<int, array{sum: int, count: int}>
     */
    public static function assign_grading_delay_aggregates(): array {
        if (!isset(self::$cache[__FUNCTION__])) {
            global $DB;
            $sql = "SELECT a.course AS courseid,
                           SUM(g.timemodified - s.timemodified) AS sum_delay,
                           COUNT(*) AS count_delay
                      FROM {assign_submission} s
                      JOIN {assign_grades} g ON g.assignment = s.assignment AND g.userid = s.userid AND g.attemptnumber = s.attemptnumber
                      JOIN {assign} a ON a.id = s.assignment
                     WHERE s.latest = 1 AND s.status = 'submitted'
                       AND g.timemodified >= s.timemodified
                     GROUP BY a.course";
            $bycourse = [];
            foreach ($DB->get_records_sql($sql) as $row) {
                $bycourse[(int) $row->courseid] = [
                    'sum' => (int) $row->sum_delay,
                    'count' => (int) $row->count_delay,
                ];
            }
            self::$cache[__FUNCTION__] = $bycourse;
        }
        return self::$cache[__FUNCTION__];
    }

    /**
     * Entregas validas por ID de tarea.
     *
     * @return array<int, int>
     */
    public static function assign_valid_submission_counts(): array {
        if (!isset(self::$cache[__FUNCTION__])) {
            $sql = "SELECT s.assignment AS assignid, COUNT(DISTINCT s.userid) AS valid_count
                      FROM {assign_submission} s
                     WHERE s.status = 'submitted'
                     GROUP BY s.assignment";
            $byassign = [];
            foreach (self::fetch_all($sql) as $row) {
                $byassign[(int) $row->assignid] = (int) $row->valid_count;
            }
            self::$cache[__FUNCTION__] = $byassign;
        }
        return self::$cache[__FUNCTION__];
    }

    private static function fetch_all(string $sql, array $params = []): array {
        global $DB;
        $rows = [];
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $row) {
            $rows[] = $row;
        }
        $rs->close();
        return $rows;
    }
}