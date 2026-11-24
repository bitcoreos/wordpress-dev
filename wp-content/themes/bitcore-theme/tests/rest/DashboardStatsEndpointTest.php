<?php
/**
 * Dashboardstatsendpointtest.
 *
 * @package    Bitcore\Theme
 * @since      2026.11.23
 * @version    1.0.0
 * @author     coraline
 */

declare(strict_types=1);

define('ABSPATH', '/tmp/wordpress/');

$GLOBALS['bitcore_theme_stats_test'] = [
    'routes' => [],
    'taxonomy_exists' => true,
    'term_counts' => [],
    'health_snapshot' => ['active_agents' => 0],
];

function add_action(string $hook, mixed $callback, int $priority = 10, int $accepted_args = 1): void {}
function add_shortcode(string $tag, mixed $callback): void {}
function add_theme_support(string $feature, mixed ...$args): void {}
function add_editor_style(mixed $stylesheet = 'editor-style.css'): void {}
function register_block_pattern_category(string $category_name, array $category_properties): bool { return true; }
function register_sidebar(array $args = []): string { return $args['id'] ?? 'sidebar'; }
function wp_register_script(string $handle, string $src = '', array $deps = [], string|bool|null $ver = false, bool|array $args = false): bool { return true; }
function wp_enqueue_script(string $handle, string $src = '', array $deps = [], string|bool|null $ver = false, bool|array $args = false): bool { return true; }
function wp_enqueue_style(string $handle, string $src = '', array $deps = [], string|bool|null $ver = false, string $media = 'all'): bool { return true; }
function wp_localize_script(string $handle, string $object_name, array $l10n): bool { return true; }
function wp_get_theme(): object { return new class { public function get(string $key): string { return '2.0.0'; } }; }
function get_template_directory_uri(): string { return 'https://example.test/theme'; }
function get_template_directory(): string { return dirname(__DIR__, 1); }
function file_exists_stub(string $path): bool { return false; }
function shortcode_atts(array $pairs, array $atts, string $shortcode = ''): array { return array_merge($pairs, $atts); }
function admin_url(string $path = ''): string { return 'https://example.test/wp-admin/' . $path; }
function wp_create_nonce(string|int $action = -1): string { return 'nonce'; }
function rest_url(string $path = ''): string { return 'https://example.test/wp-json/' . $path; }
function get_post_field(string $field, int $post_id): string { return 'dashboard'; }
function get_queried_object_id(): int { return 1; }
function __(string $text, string $domain = 'default'): string { return $text; }
function esc_html_e(string $text, string $domain = 'default'): void { echo $text; }
function esc_attr_e(string $text, string $domain = 'default'): void { echo $text; }
function esc_attr(mixed $text): string { return (string) $text; }
function register_rest_route(string $namespace, string $route, array $args): bool {
    $GLOBALS['bitcore_theme_stats_test']['routes'][] = [$namespace, $route, $args];
    return true;
}
function __return_true(): bool { return true; }
function taxonomy_exists(string $taxonomy): bool { return (bool) $GLOBALS['bitcore_theme_stats_test']['taxonomy_exists']; }
function is_wp_error(mixed $thing): bool { return false; }
function sanitize_key(string $key): string { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', $key)); }
function current_time(string $type): string { return '2026-05-24 02:20:00'; }
function get_terms(array $args): array {
    $slugs = $args['slug'] ?? [];
    if (is_string($slugs)) {
        $slugs = [$slugs];
    }
    $terms = [];
    foreach ($slugs as $slug) {
        if (array_key_exists($slug, $GLOBALS['bitcore_theme_stats_test']['term_counts'])) {
            $terms[] = (object) [
                'slug' => $slug,
                'count' => $GLOBALS['bitcore_theme_stats_test']['term_counts'][$slug],
            ];
        }
    }
    return $terms;
}

class WP_REST_Response {
    public mixed $data;
    public int $status;
    public function __construct(mixed $data = null, int $status = 200) {
        $this->data = $data;
        $this->status = $status;
    }
    public function get_data(): mixed { return $this->data; }
    public function get_status(): int { return $this->status; }
}

class BITCORE_Health_Snapshot {
    public static function get_latest(bool $refresh = false): mixed {
        return $GLOBALS['bitcore_theme_stats_test']['health_snapshot'];
    }
}

require_once dirname(__DIR__, 2) . '/functions.php';

final class DashboardStatsEndpointTest {
    private int $passed = 0;
    private int $failed = 0;
    private string $source;

    public function __construct() {
        $this->source = file_get_contents(dirname(__DIR__, 2) . '/functions.php');
    }

    public function run(): int {
        $this->test_stats_use_real_task_status_terms_and_health_snapshot();
        $this->test_stats_fallback_to_zero_when_sources_missing();
        $this->test_stats_route_registration_contract();
        $this->test_stats_endpoint_source_has_no_rand_family_calls();
        echo "\nAssertions passed: {$this->passed}\n";
        echo "Assertions failed: {$this->failed}\n";
        return $this->failed === 0 ? 0 : 1;
    }

    private function reset_state(): void {
        $GLOBALS['bitcore_theme_stats_test']['routes'] = [];
        $GLOBALS['bitcore_theme_stats_test']['taxonomy_exists'] = true;
        $GLOBALS['bitcore_theme_stats_test']['term_counts'] = [];
        $GLOBALS['bitcore_theme_stats_test']['health_snapshot'] = ['active_agents' => 0];
    }

    private function test_stats_use_real_task_status_terms_and_health_snapshot(): void {
        $this->reset_state();
        $GLOBALS['bitcore_theme_stats_test']['term_counts'] = [
            'doing' => 26,
            'review' => 5,
            'done' => 4,
            'inbox' => 1,
        ];
        $GLOBALS['bitcore_theme_stats_test']['health_snapshot'] = ['active_agents' => 12];

        $response = bitcore_rest_get_stats();
        $data = $response->get_data();

        $this->assert_same(200, $response->get_status(), 'stats_response_status_200');
        $this->assert_same(31, $data['active_tasks'] ?? null, 'stats_active_tasks_from_active_terms');
        $this->assert_same(4, $data['completed'] ?? null, 'stats_completed_from_done_terms');
        $this->assert_same(1, $data['pending'] ?? null, 'stats_pending_from_pending_terms');
        $this->assert_same(12, $data['agents_online'] ?? null, 'stats_agents_online_from_health_snapshot');
        $this->assert_same('real_counts', $data['source'] ?? null, 'stats_source_marks_real_counts');
    }

    private function test_stats_fallback_to_zero_when_sources_missing(): void {
        $this->reset_state();
        $GLOBALS['bitcore_theme_stats_test']['taxonomy_exists'] = false;
        $GLOBALS['bitcore_theme_stats_test']['health_snapshot'] = null;

        $data = bitcore_rest_get_stats()->get_data();

        $this->assert_same(0, $data['active_tasks'] ?? null, 'stats_active_tasks_zero_fallback');
        $this->assert_same(0, $data['completed'] ?? null, 'stats_completed_zero_fallback');
        $this->assert_same(0, $data['pending'] ?? null, 'stats_pending_zero_fallback');
        $this->assert_same(0, $data['agents_online'] ?? null, 'stats_agents_online_zero_fallback');
    }

    private function test_stats_route_registration_contract(): void {
        $this->reset_state();
        bitcore_register_rest_routes();
        $route = $GLOBALS['bitcore_theme_stats_test']['routes'][0] ?? null;
        $this->assert_same('bitcore/v1', $route[0] ?? null, 'stats_route_namespace');
        $this->assert_same('/stats', $route[1] ?? null, 'stats_route_path');
        $this->assert_same('GET', $route[2]['methods'] ?? null, 'stats_route_method');
        $this->assert_same('bitcore_rest_get_stats', $route[2]['callback'] ?? null, 'stats_route_callback');
        $this->assert_same('__return_true', $route[2]['permission_callback'] ?? null, 'stats_route_permission_contract');
    }

    private function test_stats_endpoint_source_has_no_rand_family_calls(): void {
        $stats_body = $this->extract_function_body($this->source, 'bitcore_rest_get_stats');
        $this->assert_not_contains('rand(', $stats_body, 'stats_source_no_rand');
        $this->assert_not_contains('mt_rand(', $stats_body, 'stats_source_no_mt_rand');
        $this->assert_not_contains('wp_rand(', $stats_body, 'stats_source_no_wp_rand');
    }

    private function extract_function_body(string $source, string $function_name): string {
        $offset = strpos($source, 'function ' . $function_name);
        if ($offset === false) {
            return '';
        }
        $next = strpos($source, "\nfunction ", $offset + 1);
        if ($next === false) {
            $next = strlen($source);
        }
        return substr($source, $offset, $next - $offset);
    }

    private function assert_same(mixed $expected, mixed $actual, string $name): void {
        $this->assert_true($expected === $actual, $name . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true));
    }

    private function assert_not_contains(string $needle, string $haystack, string $name): void {
        $this->assert_true(strpos($haystack, $needle) === false, $name);
    }

    private function assert_true(bool $condition, string $name): void {
        if ($condition) {
            $this->passed++;
            echo "PASS {$name}\n";
            return;
        }
        $this->failed++;
        echo "FAIL {$name}\n";
    }
}

$test = new DashboardStatsEndpointTest();
exit($test->run());
