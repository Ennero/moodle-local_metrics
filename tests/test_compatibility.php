<?php
/**
 * Compatibility and Integrity Test Suite for local_metrics.
 * Validates hook definitions, class hierarchy, and integration with tool_monitoring.
 *
 * @package    local_metrics
 * @copyright  2026 UEDi - Facultad de Ingeniería USAC
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core {
    class component {
        public static function get_component_from_classname(string $class): string {
            $parts = explode('\\', ltrim($class, '\\'));
            return $parts[0] ?? 'core';
        }
    }
    class lang_string {
        public function __construct(public string $identifier, public string $component = '') {}
        public function out(): string { return $this->identifier; }
        public function __toString(): string { return $this->identifier; }
    }
}

namespace core\hook {
    interface described_hook {
        public static function get_hook_description(): string;
        public static function get_hook_tags(): array;
    }
}

namespace core\exception {
    class coding_exception extends \Exception {}
}

namespace {
    defined('MOODLE_INTERNAL') || define('MOODLE_INTERNAL', true);
    defined('DAYSECS') || define('DAYSECS', 86400);
    defined('MATURITY_STABLE') || define('MATURITY_STABLE', 200);

    // Autoloader for tool_monitoring and local_metrics
    spl_autoload_register(function (string $class): void {
        $tm_prefix = 'tool_monitoring\\';
        $lm_prefix = 'local_metrics\\';

        $tm_paths = [
            __DIR__ . '/../../moodle-tool_monitoring45',
            __DIR__ . '/../../moodle-tool_monitoring_for_4.5',
            __DIR__ . '/../../../admin/tool/monitoring',
            __DIR__ . '/../vendor/tool_monitoring',
            __DIR__ . '/../tool_monitoring',
        ];

        if (str_starts_with($class, $tm_prefix)) {
            $sub = substr($class, strlen($tm_prefix));
            $file = str_replace('\\', '/', $sub) . '.php';
            foreach ($tm_paths as $tm_root) {
                $target = "$tm_root/classes/$file";
                if (file_exists($target)) {
                    require_once $target;
                    return;
                }
            }
        }

        if (str_starts_with($class, $lm_prefix)) {
            $sub = substr($class, strlen($lm_prefix));
            $file = str_replace('\\', '/', $sub) . '.php';
            $target = __DIR__ . "/../classes/$file";
            if (file_exists($target)) {
                require_once $target;
                return;
            }
        }
    });

    echo "========================================================\n";
    echo "  moodle-local_metrics Compatibility & Integrity Tests  \n";
    echo "========================================================\n\n";

    $failures = [];
    $total_tests = 0;

    function assert_true(bool $cond, string $msg, array &$failures, int &$total_tests): void {
        $total_tests++;
        if ($cond) {
            echo "  [PASS] $msg\n";
        } else {
            echo "  [FAIL] $msg\n";
            $failures[] = $msg;
        }
    }

    // 1. Validate version.php
    echo "--- 1. Validating version.php ---\n";
    $plugin = new stdClass();
    require __DIR__ . '/../version.php';
    assert_true($plugin->component === 'local_metrics', "Plugin component is 'local_metrics'", $failures, $total_tests);
    assert_true($plugin->version > 0, "Plugin version is defined and positive ({$plugin->version})", $failures, $total_tests);
    assert_true($plugin->requires >= 2024100700, "Moodle requirement supports Moodle 4.5+ ({$plugin->requires})", $failures, $total_tests);
    assert_true(isset($plugin->dependencies['tool_monitoring']), "Declares dependency on tool_monitoring", $failures, $total_tests);

    // 2. Validate db/hooks.php
    echo "\n--- 2. Validating db/hooks.php & Metric Registration ---\n";
    $callbacks = [];
    require __DIR__ . '/../db/hooks.php';
    assert_true(!empty($callbacks), "Callbacks array is defined and not empty", $failures, $total_tests);
    assert_true(count($callbacks) === 38, "Exactly 38 academic metrics are registered in hooks (found: " . count($callbacks) . ")", $failures, $total_tests);

    $hook_class = \tool_monitoring\hook\metric_collection::class;
    assert_true(class_exists($hook_class), "Hook class tool_monitoring\\hook\\metric_collection exists", $failures, $total_tests);

    // 3. Test Metric Classes Compatibility
    echo "\n--- 3. Testing Metric Classes & Compatibility with tool_monitoring ---\n";
    $hook_instance = new \tool_monitoring\hook\metric_collection();

    $expected_metrics = [
        'active_students', 'active_teachers', 'activity_by_day', 'activity_by_hour', 'activity_by_week',
        'activity_events', 'activity_events_by_type', 'assignment_submissions_by_timing', 'assignments_by_dimension',
        'assignments_pending_grading', 'average_grading_delay', 'average_quiz_duration', 'courses_active',
        'courses_by_dimension', 'courses_without_students', 'courses_without_teachers', 'enrolments_by_method',
        'enrolments_by_status', 'enrolments_total', 'expected_submissions_by_dimension', 'inactive_users',
        'last_activity_age', 'module_views', 'new_users', 'quiz_attempting_students', 'quiz_attempts_by_state',
        'quiz_average_grade', 'quiz_grade_results', 'quizzes_by_dimension', 'role_enrolments', 'student_logins',
        'students_enrolled', 'students_multiple_courses', 'students_per_course', 'students_without_quiz_attempt',
        'submissions_missing', 'unique_course_users', 'users_never_logged_in'
    ];

    $registered_names = [];
    foreach ($callbacks as $entry) {
        $callback = $entry['callback'];
        $class_name = $callback[0];
        $method = $callback[1];

        assert_true(class_exists($class_name), "Class $class_name exists", $failures, $total_tests);
        assert_true(is_subclass_of($class_name, \tool_monitoring\metric::class), "$class_name extends tool_monitoring\\metric", $failures, $total_tests);
        assert_true(method_exists($class_name, $method), "$class_name has static method $method", $failures, $total_tests);

        // Execute collection
        $metric = $class_name::$method($hook_instance);
        $m_name = $metric->get_name();
        $registered_names[] = $m_name;

        // Verify component resolution
        assert_true($metric->get_component() === 'local_metrics', "$m_name resolves component 'local_metrics'", $failures, $total_tests);

        // Verify type is GAUGE
        assert_true($metric->get_type() === \tool_monitoring\metric_type::GAUGE, "$m_name returns metric_type::GAUGE", $failures, $total_tests);

        // Verify qualified name
        $qname = "{$metric->get_component()}_{$m_name}";
        assert_true(str_starts_with($qname, 'local_metrics_'), "$qname starts with local_metrics_", $failures, $total_tests);
    }

    assert_true(count(array_unique($registered_names)) === 38, "All 38 registered metrics have unique names", $failures, $total_tests);

    // 4. Validate Language Strings
    echo "\n--- 4. Validating Language Strings ---\n";
    $string = [];
    require __DIR__ . '/../lang/en/local_metrics.php';
    assert_true(isset($string['pluginname']), "Language string 'pluginname' is defined", $failures, $total_tests);

    foreach ($expected_metrics as $em) {
        $key = "metric:{$em}_desc";
        assert_true(!empty($string[$key]), "String '$key' exists and is non-empty", $failures, $total_tests);
    }

    echo "\n========================================================\n";
    if (empty($failures)) {
        echo "  RESULT: ALL $total_tests TESTS PASSED SUCCESSFULLY! (100%)\n";
        echo "========================================================\n";
        exit(0);
    } else {
        echo "  RESULT: " . count($failures) . " TESTS FAILED out of $total_tests!\n";
        echo "========================================================\n";
        exit(1);
    }
}
