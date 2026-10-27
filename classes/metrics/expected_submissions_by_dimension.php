<?php
namespace local_metrics\metrics;

use local_metrics\academic_queries as queries;
use local_metrics\config\window_config;
use local_metrics\config\windows_config;


use tool_monitoring\metric;
use tool_monitoring\metric_config;
use tool_monitoring\metric_type;

defined('MOODLE_INTERNAL') || die();

class expected_submissions_by_dimension extends metric {
    #[\Override]
    public function get_type(): metric_type {
        return metric_type::GAUGE;
    }

    #[\Override]
    public function calculate(metric_config|null $config = null): iterable {
        $studentcounts = queries::student_counts_by_course();
        $assigncounts = queries::assign_counts_by_course();
        $bycourse = [];
        foreach ($assigncounts as $courseid => $n) {
            $bycourse[$courseid] = ($studentcounts[$courseid] ?? 0) * $n;
        }
        yield from queries::course_values($bycourse, queries::course_dimension_map(), null, [],
            queries::assign_dimension_label_sets());
    }
}