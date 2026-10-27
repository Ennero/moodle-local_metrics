<?php
namespace local_metrics\metrics;

use local_metrics\academic_queries as queries;
use local_metrics\config\window_config;
use local_metrics\config\windows_config;


use tool_monitoring\metric;
use tool_monitoring\metric_config;
use tool_monitoring\metric_type;

defined('MOODLE_INTERNAL') || die();

class students_without_quiz_attempt extends metric {
    #[\Override]
    public function get_type(): metric_type {
        return metric_type::GAUGE;
    }

    #[\Override]
    public function calculate(metric_config|null $config = null): iterable {
        $attemptedbycourse = queries::quiz_attempting_student_counts();
        $studentcounts = queries::student_counts_by_course();
        $quizcounts = queries::quiz_open_counts();
        $bycourse = [];
        foreach (array_keys($quizcounts) as $courseid) {
            $attempted = $attemptedbycourse[$courseid] ?? 0;
            $bycourse[$courseid] = max(0, ($studentcounts[$courseid] ?? 0) - $attempted);
        }
        yield from queries::course_values($bycourse, queries::course_dimension_map(), null, [],
            queries::quiz_dimension_label_sets());
    }
}