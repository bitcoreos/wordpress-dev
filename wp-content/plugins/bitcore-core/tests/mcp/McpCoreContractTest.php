<?php
declare(strict_types=1);

/**
 * Standalone MCP core contract tests.
 *
 * Guards against false positives by testing the real BITCORE_MCP_Core class,
 * connector dispatch behavior, and the real connector method surface used by
 * MCP resource callbacks.
 */

if (!defined('ABSPATH')) { define('ABSPATH', __DIR__ . '/'); }

if (!class_exists('BITCORE_Core')) {
    class BITCORE_Core {
        public function __call($name, $arguments) { return null; }
    }
}

$GLOBALS['bitcore_mcp_test_state'] = [
    'current_user_id' => 42,
    'options' => [
        'bitcore_bithub_api_url' => 'https://hub.example.test',
        'bitcore_bitwiki_api_url' => 'https://wiki.example.test/api.php',
        'bitcore_n8n_webhook_url' => 'https://n8n.example.test/webhook',
        'bitcore_n8n_api_key' => 'test-key',
    ],
    'http_get_calls' => [],
    'http_post_calls' => [],
    'http_body' => '{}',
];

function wp_parse_args(mixed $args, array $defaults = []): array {
    return array_merge($defaults, is_array($args) ? $args : []);
}
function wp_json_encode(mixed $data, int $flags = 0): string|false {
    return json_encode($data, $flags);
}
function is_wp_error(mixed $value): bool {
    return $value instanceof WP_Error;
}
function get_current_user_id(): int {
    return $GLOBALS['bitcore_mcp_test_state']['current_user_id'];
}
function wp_generate_password(int $length = 12, bool $special_chars = true): string {
    return str_repeat('a', $length);
}
function get_option(string $key, mixed $default = false): mixed {
    return $GLOBALS['bitcore_mcp_test_state']['options'][$key] ?? $default;
}
function absint(mixed $value): int { return max(0, abs((int) $value)); }
function sanitize_title(mixed $value): string {
    $value = strtolower(strip_tags((string) $value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-');
}
function sanitize_key(mixed $value): string {
    $value = strtolower((string) $value);
    return preg_replace('/[^a-z0-9_\-]/', '', $value) ?? '';
}
function add_query_arg(array $args, string $url): string {
    return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($args);
}
function wp_remote_get(string $url, array $args = []): array {
    $GLOBALS['bitcore_mcp_test_state']['http_get_calls'][] = [$url, $args];
    return ['body' => $GLOBALS['bitcore_mcp_test_state']['http_body']];
}
function wp_remote_post(string $url, array $args = []): array {
    $GLOBALS['bitcore_mcp_test_state']['http_post_calls'][] = [$url, $args];
    return ['body' => '{"ok":true}'];
}
function wp_remote_retrieve_body(array $response): string { return (string) ($response['body'] ?? ''); }
function wp_remote_retrieve_response_code(array $response): int { return (int) ($response['response']['code'] ?? 200); }
function wp_rand(int $min, int $max): int { return $min; }
if (!defined('BITCORE_TIMEOUT')) { define('BITCORE_TIMEOUT', 30); }
function add_action(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void {}

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

require_once dirname(__DIR__, 2) . '/classes/services/abstract-connector.php';
require_once dirname(__DIR__, 2) . '/mcp/core.php';
require_once dirname(__DIR__, 2) . '/classes/services/connector-bithub.php';
require_once dirname(__DIR__, 2) . '/classes/services/connector-bitwiki.php';
require_once dirname(__DIR__, 2) . '/classes/services/connector-n8n.php';

final class McpFakeCore {
    public McpFakeBithub $connector_bithub;
    public McpFakeBitwiki $connector_bitwiki;
    public McpFakeN8n $connector_n8n;
    public function __construct() {
        $this->connector_bithub = new McpFakeBithub();
        $this->connector_bitwiki = new McpFakeBitwiki();
        $this->connector_n8n = new McpFakeN8n();
    }
}
final class McpFakeBithub {
    public array $calls = [];
    public function get_latest_topics(int $limit = 10): array { $this->calls[] = ['get_latest_topics', $limit]; return ['topics' => $limit]; }
    public function get_artifacts(): array { $this->calls[] = ['get_artifacts']; return ['artifacts' => true]; }
    public function get_topic(int|string $topic_id): array { $this->calls[] = ['get_topic', $topic_id]; return ['topic_id' => (string) $topic_id]; }
    public function get_artifact(int|string $artifact_id): array { $this->calls[] = ['get_artifact', $artifact_id]; return ['artifact_id' => (string) $artifact_id]; }
}
final class McpFakeBitwiki {
    public array $calls = [];
    public function search(string $query, int $limit = 10): array { $this->calls[] = ['search', $query, $limit]; return ['query' => $query, 'limit' => $limit]; }
    public function get_index(): array { $this->calls[] = ['get_index']; return ['index' => true]; }
    public function get_article(string $path): array { $this->calls[] = ['get_article', $path]; return ['path' => $path]; }
}
final class McpFakeN8n {
    public array $calls = [];
    public function send_intent(string $intent, string $scope = 'quick', int $user_id = 0): array { $this->calls[] = ['send_intent', $intent, $scope, $user_id]; return ['intent' => $intent, 'scope' => $scope, 'user_id' => $user_id]; }
    public function get_queue_status(): array { $this->calls[] = ['get_queue_status']; return ['queue' => 'ok']; }
    public function dispatch_to_swarm(string $task, array $agents = []): array { $this->calls[] = ['dispatch_to_swarm', $task, $agents]; return ['task' => $task, 'agents' => $agents]; }
    public function get_agent_status(int|string $agent_id): array { $this->calls[] = ['get_agent_status', $agent_id]; return ['agent_id' => (string) $agent_id]; }
    public function get_swarm_status(): array { $this->calls[] = ['get_swarm_status']; return ['swarm' => 'ok']; }
}

final class McpCoreContractStandaloneTest {
    private int $passed = 0;
    private int $failed = 0;

    public function run(): int {
        $this->test_core_tools_are_listed_with_required_schema();
        $this->test_tool_call_applies_defaults_and_dispatches_to_connector();
        $this->test_tool_call_rejects_missing_required_parameter();
        $this->test_bithub_resource_id_dispatches_to_topic_not_topic_list();
        $this->test_artifact_resource_id_dispatches_to_single_artifact();
        $this->test_swarm_resource_id_dispatches_to_agent_status();
        $this->test_real_connectors_implement_methods_mcp_core_calls();
        $this->test_real_bithub_get_topic_uses_discourse_topic_endpoint();
        $this->test_real_bithub_get_artifact_numeric_reuses_topic_endpoint();
        $this->test_real_bitwiki_get_index_uses_allpages_query();
        $this->test_real_bitwiki_get_article_delegates_to_parse_query();
        $this->test_real_n8n_agent_and_swarm_status_payloads();
        $this->report();
        return $this->failed === 0 ? 0 : 1;
    }

    private function test_core_tools_are_listed_with_required_schema(): void {
        $mcp = new BITCORE_MCP_Core(new McpFakeCore());
        $tools = $mcp->list_tools();
        $names = array_column($tools, 'name');
        $this->assert_true(in_array('search_bitwiki', $names, true), 'lists_search_bitwiki_tool');
        $this->assert_true(in_array('dispatch_to_swarm', $names, true), 'lists_dispatch_to_swarm_tool');
        $search = $this->find_by_name($tools, 'search_bitwiki');
        $this->assert_same(['query'], $search['inputSchema']['required'] ?? null, 'search_bitwiki_requires_query');
        $this->assert_same('integer', $search['inputSchema']['properties']['limit']['type'] ?? null, 'search_bitwiki_limit_is_integer');
    }

    private function test_tool_call_applies_defaults_and_dispatches_to_connector(): void {
        $core = new McpFakeCore();
        $mcp = new BITCORE_MCP_Core($core);
        $result = $mcp->call_tool('search_bitwiki', ['query' => 'ASHI']);
        $this->assert_same([['search', 'ASHI', 5]], $core->connector_bitwiki->calls, 'search_tool_dispatches_with_default_limit');
        $this->assert_same(false, $result['isError'] ?? null, 'search_tool_result_not_error');
        $this->assert_contains('ASHI', $result['content'][0]['text'] ?? '', 'search_tool_formats_result_text');
    }

    private function test_tool_call_rejects_missing_required_parameter(): void {
        $mcp = new BITCORE_MCP_Core(new McpFakeCore());
        $result = $mcp->call_tool('search_bitwiki', []);
        $this->assert_true($result instanceof WP_Error, 'missing_required_returns_wp_error');
        $this->assert_same('missing_parameter', $result->get_error_code(), 'missing_required_error_code');
    }

    private function test_bithub_resource_id_dispatches_to_topic_not_topic_list(): void {
        $core = new McpFakeCore();
        $mcp = new BITCORE_MCP_Core($core);
        $mcp->read_resource('bitcore://bithub/123');
        $this->assert_same([['get_topic', 123]], $core->connector_bithub->calls, 'bithub_numeric_resource_calls_get_topic');
    }

    private function test_artifact_resource_id_dispatches_to_single_artifact(): void {
        $core = new McpFakeCore();
        $mcp = new BITCORE_MCP_Core($core);
        $mcp->read_resource('bitcore://artifacts/research-7');
        $this->assert_same([['get_artifact', 'research-7']], $core->connector_bithub->calls, 'artifact_resource_calls_get_artifact');
    }

    private function test_swarm_resource_id_dispatches_to_agent_status(): void {
        $core = new McpFakeCore();
        $mcp = new BITCORE_MCP_Core($core);
        $mcp->read_resource('bitcore://swarm/aria');
        $this->assert_same([['get_agent_status', 'aria']], $core->connector_n8n->calls, 'swarm_resource_calls_get_agent_status');
    }

    private function test_real_connectors_implement_methods_mcp_core_calls(): void {
        $expectations = [
            'BITCORE_Services_Connector_BIThub' => ['get_latest_topics', 'get_artifacts', 'get_topic', 'get_artifact'],
            'BITCORE_Services_Connector_BITwiki' => ['search', 'get_index', 'get_article'],
            'BITCORE_Services_Connector_N8N' => ['send_intent', 'get_queue_status', 'dispatch_to_swarm', 'get_agent_status', 'get_swarm_status'],
        ];
        foreach ($expectations as $class => $methods) {
            $this->assert_true(class_exists($class), $class . '_exists');
            foreach ($methods as $method) {
                $this->assert_true(method_exists($class, $method), $class . '_implements_' . $method);
            }
        }
    }

    private function test_real_bithub_get_topic_uses_discourse_topic_endpoint(): void {
        $this->reset_http_state('{"id":321,"title":"Topic"}');
        $connector = new BITCORE_Services_Connector_BIThub(new BITCORE_Core());
        $result = $connector->get_topic(321);
        $call = $GLOBALS['bitcore_mcp_test_state']['http_get_calls'][0] ?? [];
        $this->assert_same('https://hub.example.test/t/321.json', $call[0] ?? null, 'real_bithub_get_topic_url');
        $this->assert_same(321, $result['id'] ?? null, 'real_bithub_get_topic_decodes_body');
    }

    private function test_real_bithub_get_artifact_numeric_reuses_topic_endpoint(): void {
        $this->reset_http_state('{"id":77,"title":"Artifact"}');
        $connector = new BITCORE_Services_Connector_BIThub(new BITCORE_Core());
        $result = $connector->get_artifact(77);
        $call = $GLOBALS['bitcore_mcp_test_state']['http_get_calls'][0] ?? [];
        $this->assert_same('https://hub.example.test/t/77.json', $call[0] ?? null, 'real_bithub_get_artifact_numeric_url');
        $this->assert_same('Artifact', $result['title'] ?? null, 'real_bithub_get_artifact_numeric_decodes_body');
    }

    private function test_real_bitwiki_get_index_uses_allpages_query(): void {
        $this->reset_http_state('{"query":{"allpages":[{"title":"Main"}]}}');
        $connector = new BITCORE_Services_Connector_BITwiki(new BITCORE_Core());
        $result = $connector->get_index();
        $url = $GLOBALS['bitcore_mcp_test_state']['http_get_calls'][0][0] ?? '';
        $this->assert_contains('list=allpages', $url, 'real_bitwiki_get_index_allpages_query');
        $this->assert_same('Main', $result[0]['title'] ?? null, 'real_bitwiki_get_index_decodes_allpages');
    }

    private function test_real_bitwiki_get_article_delegates_to_parse_query(): void {
        $this->reset_http_state('{"parse":{"title":"ASHI Plan"}}');
        $connector = new BITCORE_Services_Connector_BITwiki(new BITCORE_Core());
        $result = $connector->get_article('ASHI_Plan');
        $url = urldecode($GLOBALS['bitcore_mcp_test_state']['http_get_calls'][0][0] ?? '');
        $this->assert_contains('action=parse', $url, 'real_bitwiki_get_article_parse_query');
        $this->assert_contains('page=ASHI Plan', $url, 'real_bitwiki_get_article_title_normalized');
        $this->assert_same('ASHI Plan', $result['title'] ?? null, 'real_bitwiki_get_article_decodes_parse');
    }

    private function test_real_n8n_agent_and_swarm_status_payloads(): void {
        $this->reset_http_state('{}');
        $connector = new BITCORE_Services_Connector_N8N(new BITCORE_Core());
        $connector->get_agent_status('Aria<script>');
        $connector->get_swarm_status();
        $first = json_decode($GLOBALS['bitcore_mcp_test_state']['http_post_calls'][0][1]['body'] ?? '{}', true);
        $second = json_decode($GLOBALS['bitcore_mcp_test_state']['http_post_calls'][1][1]['body'] ?? '{}', true);
        $this->assert_same('agent_status', $first['action'] ?? null, 'real_n8n_agent_status_action');
        $this->assert_same('ariascript', $first['agent_id'] ?? null, 'real_n8n_agent_status_sanitizes_agent_id');
        $this->assert_same('swarm_status', $second['action'] ?? null, 'real_n8n_swarm_status_action');
    }

    private function reset_http_state(string $body): void {
        $GLOBALS['bitcore_mcp_test_state']['http_get_calls'] = [];
        $GLOBALS['bitcore_mcp_test_state']['http_post_calls'] = [];
        $GLOBALS['bitcore_mcp_test_state']['http_body'] = $body;
    }

    private function find_by_name(array $rows, string $name): array {
        foreach ($rows as $row) {
            if (($row['name'] ?? null) === $name) { return $row; }
        }
        return [];
    }
    private function assert_contains(string $needle, string $haystack, string $name): void { $this->assert_true(str_contains($haystack, $needle), $name); }
    private function assert_true(bool $condition, string $name): void { $condition ? $this->pass($name) : $this->fail($name); }
    private function assert_same(mixed $expected, mixed $actual, string $name): void {
        $expected === $actual ? $this->pass($name) : $this->fail($name . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
    private function pass(string $name): void { $this->passed++; echo "PASS {$name}\n"; }
    private function fail(string $name): void { $this->failed++; echo "FAIL {$name}\n"; }
    private function report(): void {
        echo "\nAssertions passed: {$this->passed}\n";
        echo "Assertions failed: {$this->failed}\n";
    }
}

exit((new McpCoreContractStandaloneTest())->run());
