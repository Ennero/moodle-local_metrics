<?php
namespace local_metrics\metrics;

use local_metrics\academic_queries as queries;
use local_metrics\config\window_config;
use local_metrics\config\windows_config;


use tool_monitoring\metric;
use tool_monitoring\metric_config;
use tool_monitoring\metric_type;

defined('MOODLE_INTERNAL') || die();

class submissions_missing extends metric {
    #[\Override]
    public function get_type(): metric_type {
        return metric_type::GAUGE;
    }

    #[\Override]
    public function calculate(metric_config|null $config = null): iterable {
        $studentcounts = queries::student_counts_by_course();
        $validcounts = queries::assign_valid_submission_counts();
        $sql = "SELECT a.course AS courseid, a.id AS assignid FROM {assign} a WHERE a.course <> 1";
        $assigns = queries::fetch_all_helper($sql);
        $bycourse = [];
        foreach ($assigns as $row) {
            $cid = (int) $row->courseid;
            $aid = (int) $row->assignid;
            $expected = $studentcounts[$cid] ?? 0;
            $valid = $validcounts[$aid] ?? 0;
            $bycourse[$cid] = ($bycourse[$cid] ?? 0) + max(0, $expected - $valid);
        }
        yield from queries::course_values($bycourse, queries::course_dimension_map(), null, [],
            queries::assign_dimension_label_sets());
    }
}