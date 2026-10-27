# local_metrics

Métricas Académicas para Moodle (compatible con Moodle 4.5 y Moodle 5.0+).

Plugin local satélite para Moodle que extiende el plugin oficial [tool_monitoring](https://moodle.org/plugins/tool_monitoring) mediante la **Moodle Hooks API** (	ool_monitoring\hook\metric_collection).

Este plugin implementa y expone 38 métricas académicas de alto rendimiento optimizadas para bases de datos de gran escala (probado con PostgreSQL 16 y MariaDB con más de 1.6M de registros).

## Requisitos

- Moodle 4.5 o superior (incluyendo Moodle 5.0+).
- PHP 8.1 - 8.3+.
- Plugin 	ool_monitoring instalado en dmin/tool/monitoring/.

## Métricas Disponibles

Las 38 métricas son recolectadas automáticamente por el endpoint de Prometheus de 	ool_monitoring con el prefijo local_metrics_:

### Actividad y Usuarios
- local_metrics_active_students
- local_metrics_active_teachers
- local_metrics_student_logins
- local_metrics_new_users
- local_metrics_inactive_users
- local_metrics_users_never_logged_in
- local_metrics_last_activity_age
- local_metrics_activity_by_day
- local_metrics_activity_by_hour
- local_metrics_activity_by_week
- local_metrics_activity_events
- local_metrics_activity_events_by_type
- local_metrics_module_views

### Tareas (Assignments)
- local_metrics_assignments_by_dimension
- local_metrics_assignments_pending_grading
- local_metrics_assignment_submissions_by_timing
- local_metrics_average_grading_delay
- local_metrics_expected_submissions_by_dimension
- local_metrics_submissions_missing

### Cuestionarios (Quizzes)
- local_metrics_quizzes_by_dimension
- local_metrics_quiz_attempting_students
- local_metrics_quiz_attempts_by_state
- local_metrics_quiz_average_grade
- local_metrics_quiz_grade_results
- local_metrics_average_quiz_duration
- local_metrics_students_without_quiz_attempt

### Cursos y Matrículas
- local_metrics_courses_active
- local_metrics_courses_by_dimension
- local_metrics_courses_without_students
- local_metrics_courses_without_teachers
- local_metrics_enrolments_by_method
- local_metrics_enrolments_by_status
- local_metrics_enrolments_total
- local_metrics_role_enrolments
- local_metrics_students_enrolled
- local_metrics_students_per_course
- local_metrics_students_multiple_courses
- local_metrics_unique_course_users

## Instalación

1. Clona o copia este repositorio dentro del directorio local/metrics de tu instalación de Moodle:
   `ash
   git clone https://github.com/Ennero/moodle-local_metrics.git local/metrics
   `
2. Asegúrate de que el plugin 	ool_monitoring esté instalado en dmin/tool/monitoring.
3. Completa la instalación desde la CLI de Moodle:
   `ash
   php admin/cli/upgrade.php --non-interactive
   `
4. Las métricas serán detectadas automáticamente por 	ool_monitoring.

## Licencia

GNU General Public License v3.0 o posterior (GPL-3.0-or-later).
