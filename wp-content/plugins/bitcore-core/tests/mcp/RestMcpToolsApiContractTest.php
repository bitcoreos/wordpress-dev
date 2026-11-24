<?php
/**
 * Restmcptoolsapicontracttest.
 *
 * @package    Bitcore\Tests
 * @since      2026.11.23
 * @version    1.0.0
 * @author     coraline
 */

declare(strict_types=1);

/**
 * Standalone REST MCP tools API contract tests.
 *
 * Guards against false positives by loading the real REST API class and
 * asserting that MCP calls delegate to a fake core MCP object instead of
 * returning hardcoded placeholder text.
 */

if (!defined('ABSPATH')) { define('ABSPATH', __DIR__ . '/'); }

$GLOBALS['bitcore_rest_mcp_test_state'] = [
    'actions' => [],
    'routes' => [],
    'current_user_can' => true,
    'logged_in' => true,
];

function add_action(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void {
    $GLOBALS['bitcore_rest_mcp_test_state']['actions'][] = [$hook, $priority, $accepted_args];
}
function register_rest_route(string $namespace, string $route, array $args): void {
    $GLOBALS['bitcore_rest_mcp_test_state']['routes'][] = [$namespace, $route, $args['methods'] ?? ''];
}
function rest_ensure_response(mixed $value): mixed { return $value; }
function sanitize_text_field(mixed $value): string { return trim(strip_tags((string) $value)); }
function sanitize_key(mixed $value): string {
    $value = strtolower((string) $value);
    return preg_replace('/[^a-z0-9_\-]/', '', $value) ?? '';
}
function esc_url_raw(mixed $value): string { return filter_var((string) $value, FILTER_SANITIZE_URL); }
function wp_kses_post(mixed $value): string { return (string) $value; }
function __(string $text, string $domain = ''): string { return $text; }
function current_user_can(string $capability): bool { return (bool) $GLOBALS['bitcore_rest_mcp_test_state']['current_user_can']; }
function is_user_logged_in(): bool { return (bool) $GLOBALS['bitcore_rest_mcp_test_state']['logged_in']; }
function wp_insert_post(array $post_data, bool $wp_error = false): int { return 123; }
function get_permalink(int $post_id): string { return 'https://ashi.example.test/?p=' . $post_id; }
function current_time(string $type): string { return '2026-05-26 11:00:00'; }
function get_option(string $key, mixed $default = false): mixed { return $default; }
function update_option(string $key, mixed $value): bool { return true; }
function is_wp_error(mixed $value): bool { return $value instanceof WP_Error; }

if (!class_exists('WP_Error')) {
    class WP_Error {
        public string $code;
        public string $message;
        public array $data;
        public function __construct(string $code = '', string $message = '', array $data = []) {
            $this->code = $code;
            $this->message = $message;
            $this->data = $data;
        }
        public function get_error_code(): string { return $this->code; }
        public function get_error_message(): string { return $this->message; }
        public function get_error_data(): array { return $this->data; }
    }
}

if (!class_exists('WP_Query')) {
    class WP_Query {
        public array $posts = [];
        public int $found_posts = 0;
        public function __construct(array $args = []) {}
    }
}

final class RestMcpFakeRequest {
    public function __construct(private array $params) {}
    public function get_json_params(): array { return $this->params; }
}

final class RestMcpFakeMcpCore {
    public array $calls = [];
    public function list_tools(): array {
        return [
            [
                'name' => 'search_bitwiki',
                'description' => 'Search BITwiki knowledge base',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['query' => ['type' => 'string']],
                    'required' => ['query'],
                ],
            ],
            [
                'name' => 'stream_chat',
                'description' => 'Chat through MCP core',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['message' => ['type' => 'string']],
                    'required' => ['message'],
                ],
            ],
        ];
    }
    public function call_tool(string $name, array $args = [], bool $stream = false): array|WP_Error {
        $this->calls[] = [$name, $args, $stream];
        if ($name === 'missing_tool') {
            return new WP_Error('tool_not_found', 'Tool not found: missing_tool', ['status' => 404]);
        }
        return [
            'content' => [[
                'type' => 'string',
                'text' => 'delegated:' . $name . ':' . json_encode($args),
            ]],
            'isError' => false,
        ];
    }
}

final class RestMcpFakeBitcore {
    public RestMcpFakeMcpCore $mcp;
    public function __construct() { $this->mcp = new RestMcpFakeMcpCore(); }
}

require_once dirname(__DIR__, 2) . '/rest/mcp-tools-api.php';

final class RestMcpToolsApiContractStandaloneTest {
    private int $passed = 0;
    private int $failed = 0;

    public function run(): int {
        $this->test_routes_are_registered_without_manual_side_effect_requirements();
        $this->test_tool_listing_includes_core_mcp_tools();
        $this->test_core_tool_execution_delegates_to_mcp_core();
        $this->test_bitcore_chat_uses_mcp_core_not_placeholder();
        $this->test_bitcore_chat_without_core_returns_error_not_fake_success();
        $this->test_public_permission_returns_boolean_true_not_string();
        $this->report();
        return $this->failed === 0 ? 0 : 1;
    }

    private function test_routes_are_registered_without_manual_side_effect_requirements(): void {
        $api = new BITCORE_Rest_MCP_Tools_API(new RestMcpFakeBitcore());
        $api->register_routes();
        $routes = array_map(fn($r) => $r[1], $GLOBALS['bitcore_rest_mcp_test_state']['routes']);
        $this->assert_true(in_array('/mcp/tools', $routes, true), 'registers_mcp_tools_route');
        $this->assert_true(in_array('/mcp/call', $routes, true), 'registers_mcp_call_route');
    }

    private function test_tool_listing_includes_core_mcp_tools(): void {
        $api = new BITCORE_Rest_MCP_Tools_API(new RestMcpFakeBitcore());
        $response = $api->get_mcp_tools();
        $names = array_column($response['tools'] ?? [], 'name');
        $this->assert_true(in_array('bitcore_create_post', $names, true), 'lists_legacy_create_post_tool');
        $this->assert_true(in_array('search_bitwiki', $names, true), 'lists_core_search_bitwiki_tool');
        $this->assert_true(in_array('stream_chat', $names, true), 'lists_core_stream_chat_tool');
    }

    private function test_core_tool_execution_delegates_to_mcp_core(): void {
        $core = new RestMcpFakeBitcore();
        $api = new BITCORE_Rest_MCP_Tools_API($core);
        $response = $api->execute_mcp_tool(new RestMcpFakeRequest([
            'name' => 'search_bitwiki',
            'parameters' => ['query' => 'ASHI'],
        ]));
        $this->assert_same([['search_bitwiki', ['query' => 'ASHI'], false]], $core->mcp->calls, 'core_tool_delegates_to_mcp_call_tool');
        $this->assert_contains('delegated:search_bitwiki', $response['content'][0]['text'] ?? '', 'core_tool_returns_delegated_response');
    }

    private function test_bitcore_chat_uses_mcp_core_not_placeholder(): void {
        $core = new RestMcpFakeBitcore();
        $api = new BITCORE_Rest_MCP_Tools_API($core);
        $response = $api->execute_mcp_tool(new RestMcpFakeRequest([
            'name' => 'bitcore_chat',
            'parameters' => ['message' => 'hello', 'context' => 'unit-test'],
        ]));
        $this->assert_same([['stream_chat', ['message' => 'hello', 'context' => 'unit-test'], false]], $core->mcp->calls, 'bitcore_chat_delegates_to_stream_chat');
        $text = $response['content'][0]['text'] ?? '';
        $this->assert_contains('delegated:stream_chat', $text, 'bitcore_chat_returns_mcp_response');
        $this->assert_false(str_contains($text, 'Received your message'), 'bitcore_chat_does_not_return_placeholder');
    }

    private function test_bitcore_chat_without_core_returns_error_not_fake_success(): void {
        $api = new BITCORE_Rest_MCP_Tools_API(null);
        $response = $api->execute_mcp_tool(new RestMcpFakeRequest([
            'name' => 'bitcore_chat',
            'parameters' => ['message' => 'hello'],
        ]));
        $this->assert_true($response instanceof WP_Error, 'chat_without_core_returns_wp_error');
        $this->assert_same('mcp_core_unavailable', $response->get_error_code(), 'chat_without_core_error_code');
    }

    private function test_public_permission_returns_boolean_true_not_string(): void {
        $api = new BITCORE_Rest_MCP_Tools_API(new RestMcpFakeBitcore());
        $result = $api->check_permission(new RestMcpFakeRequest(['name' => 'bitcore_get_posts']));
        $this->assert_same(true, $result, 'public_tool_permission_is_boolean_true');
    }

    private function assert_true(bool $condition, string $label): void {
        if ($condition) { $this->passed++; echo "PASS {$label}\n"; return; }
        $this->failed++; echo "FAIL {$label}\n";
    }
    private function assert_false(bool $condition, string $label): void { $this->assert_true(!$condition, $label); }
    private function assert_same(mixed $expected, mixed $actual, string $label): void {
        if ($expected === $actual) { $this->passed++; echo "PASS {$label}\n"; return; }
        $this->failed++; echo "FAIL {$label} expected=" . var_export($expected, true) . ' actual=' . var_export($actual, true) . "\n";
    }
    private function assert_contains(string $needle, string $haystack, string $label): void {
        $this->assert_true(str_contains($haystack, $needle), $label);
    }
    private function report(): void {
        echo "\nAssertions passed: {$this->passed}\n";
        echo "Assertions failed: {$this->failed}\n";
    }
}

exit((new RestMcpToolsApiContractStandaloneTest())->run());
