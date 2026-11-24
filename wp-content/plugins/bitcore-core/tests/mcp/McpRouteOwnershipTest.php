<?php
/**
 * Mcprouteownershiptest.
 *
 * @package    Bitcore\Tests
 * @since      2026.11.23
 * @version    1.0.0
 * @author     coraline
 */

declare(strict_types=1);

/**
 * Standalone route ownership tests for MCP REST endpoints.
 *
 * Guards against duplicate route registration by requiring:
 * - mcp-tools-api.php has no file-load side-effect hook.
 * - BITCORE_Rest_MCP_Tools_API constructor has no hook side effect.
 * - register_routes() explicitly registers exactly two MCP routes.
 * - generic BITCORE_Rest_API no longer registers /mcp/* routes.
 */

$GLOBALS['bitcore_route_ownership_state'] = [
    'actions' => [],
    'routes' => [],
];

function add_action(string $hook, mixed $callback, int $priority = 10, int $accepted_args = 1): void {
    $name = 'callable';
    if (is_string($callback)) {
        $name = $callback;
    } elseif (is_array($callback)) {
        $target = is_object($callback[0]) ? get_class($callback[0]) : (string) $callback[0];
        $name = $target . '::' . $callback[1];
    }
    $GLOBALS['bitcore_route_ownership_state']['actions'][] = [$hook, $name, $priority, $accepted_args];
}
function register_rest_route(string $namespace, string $route, array $args): void {
    $GLOBALS['bitcore_route_ownership_state']['routes'][] = [$namespace, $route, $args['methods'] ?? ''];
}
function __(string $text, string $domain = ''): string { return $text; }
function rest_ensure_response(mixed $value): mixed { return $value; }
function sanitize_text_field(mixed $value): string { return trim(strip_tags((string) $value)); }
function sanitize_key(mixed $value): string { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $value) ?? ''); }
function esc_url_raw(mixed $value): string { return (string) $value; }
function wp_kses_post(mixed $value): string { return (string) $value; }
function current_user_can(string $capability): bool { return true; }
function is_user_logged_in(): bool { return true; }
function wp_insert_post(array $post_data, bool $wp_error = false): int { return 1; }
function is_wp_error(mixed $value): bool { return $value instanceof WP_Error; }
function get_permalink(int $post_id): string { return 'https://ashi.example.test/?p=' . $post_id; }
function get_option(string $key, mixed $default = false): mixed { return $default; }
function update_option(string $key, mixed $value): bool { return true; }
function current_time(string $type): string { return '2026-05-26 11:05:00'; }
function get_current_user_id(): int { return 1; }

if (!class_exists('WP_Error')) {
    class WP_Error {
        public function __construct(public string $code = '', public string $message = '', public array $data = []) {}
        public function get_error_code(): string { return $this->code; }
    }
}
if (!class_exists('WP_Query')) {
    class WP_Query {
        public array $posts = [];
        public int $found_posts = 0;
        public function __construct(array $args = []) {}
    }
}

final class McpRouteOwnershipTest {
    private int $passed = 0;
    private int $failed = 0;

    public function run(): int {
        $this->test_dedicated_mcp_api_is_explicit_registration_only();
        $this->test_generic_rest_api_does_not_register_mcp_routes();
        $this->report();
        return $this->failed === 0 ? 0 : 1;
    }

    private function test_dedicated_mcp_api_is_explicit_registration_only(): void {
        $before = count($GLOBALS['bitcore_route_ownership_state']['actions']);
        require_once dirname(__DIR__, 2) . '/rest/mcp-tools-api.php';
        $after_require = count($GLOBALS['bitcore_route_ownership_state']['actions']);
        $this->assert_same($before, $after_require, 'mcp_tools_api_require_has_no_action_side_effect');

        $api = new BITCORE_Rest_MCP_Tools_API(null);
        $after_construct = count($GLOBALS['bitcore_route_ownership_state']['actions']);
        $this->assert_same($after_require, $after_construct, 'mcp_tools_api_constructor_has_no_action_side_effect');

        $GLOBALS['bitcore_route_ownership_state']['routes'] = [];
        $api->register_routes();
        $this->assert_same(
            [
                ['bitcore/v1', '/mcp/tools', 'GET'],
                ['bitcore/v1', '/mcp/call', 'POST'],
            ],
            $GLOBALS['bitcore_route_ownership_state']['routes'],
            'mcp_tools_api_explicit_registers_two_routes_once'
        );
    }

    private function test_generic_rest_api_does_not_register_mcp_routes(): void {
        require_once dirname(__DIR__, 2) . '/rest/api.php';
        $GLOBALS['bitcore_route_ownership_state']['routes'] = [];
        $core = new stdClass();
        $core->connector_n8n = new stdClass();
        $core->connector_bithub = new stdClass();
        $core->connector_bitwiki = new stdClass();
        $core->mcp = null;
        $api = new BITCORE_Rest_API($core);
        $api->register_routes();
        $routes = array_map(fn($route) => $route[1], $GLOBALS['bitcore_route_ownership_state']['routes']);
        $this->assert_false(in_array('/mcp/tools', $routes, true), 'generic_rest_api_does_not_register_mcp_tools');
        $this->assert_false(in_array('/mcp/call', $routes, true), 'generic_rest_api_does_not_register_mcp_call');
        $this->assert_true(in_array('/chat', $routes, true), 'generic_rest_api_keeps_chat_route');
    }

    private function assert_true(bool $condition, string $label): void {
        if ($condition) { $this->passed++; echo "PASS {$label}\n"; return; }
        $this->failed++; echo "FAIL {$label}\n";
    }
    private function assert_false(bool $condition, string $label): void { $this->assert_true(!$condition, $label); }
    private function assert_same(mixed $expected, mixed $actual, string $label): void {
        if ($expected === $actual) { $this->passed++; echo "PASS {$label}\n"; return; }
        $this->failed++;
        echo "FAIL {$label} expected=" . var_export($expected, true) . ' actual=' . var_export($actual, true) . "\n";
    }
    private function report(): void {
        echo "\nAssertions passed: {$this->passed}\n";
        echo "Assertions failed: {$this->failed}\n";
    }
}

exit((new McpRouteOwnershipTest())->run());
