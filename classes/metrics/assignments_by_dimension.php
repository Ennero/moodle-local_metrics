<?php
namespace local_metrics\metrics;

use local_metrics\academic_queries as queries;
use local_metrics\config\window_config;
use local_metrics\config\windows_config;


use tool_monitoring\metric;
use tool_monitoring\metric_config;
use tool_monitoring\metric_type;

defined('MOODLE_INTERNAL') || die();

class assignments_by_dimension extends metric {
    private const STATUSES = ['upcoming', 'open', 'closed'];

    #[\Override]
    public function get_type(): metric_type {
        return metric_type::GAUGE;
    }

    #[\Override]
    public function calculate(metric_config|null $config = null): iterable {
        $bycourse = queries::assign_status_counts();
        yield from queries::course_values($bycourse, queries::course_dimension_map(), 'status', self::STATUSES,
            queries::assign_dimension_label_sets());
    }
}