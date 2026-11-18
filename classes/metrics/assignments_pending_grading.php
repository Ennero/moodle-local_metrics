<?php
namespace local_metrics\metrics;

use local_metrics\academic_queries as queries;
use local_metrics\config\window_config;
use local_metrics\config\windows_config;


use tool_monitoring\metric;
use tool_monitoring\metric_config;
use tool_monitoring\metric_type;
use tool_monitoring\metric_value;

defined('MOODLE_INTERNAL') || die();

class assignments_pending_grading extends metric {
    #[\Override]
    public function get_type(): metric_type {
        return metric_type::GAUGE;
    }

    #[\Override]
    public function calculate(metric_config|null $config = null): iterable {
        $bycourse = queries::assign_pending_grading_counts();
        $teachers = queries::course_teachers_map();
        $map = queries::course_dimension_map();
        foreach ($bycourse as $courseid => $value) {
            if (!isset($map[$courseid])) {
                continue;
            }
            if ($value != 0) {
                $labels = queries::course_labels($map[$courseid]);
                $labels['teacher'] = $teachers[$courseid] ?? 'Sin docente';
                yield new metric_value($value, $labels);
            }
        }
        foreach (queries::assign_dimension_label_sets() as $labels) {
            yield new metric_value(0, $labels);
        }
    }
}