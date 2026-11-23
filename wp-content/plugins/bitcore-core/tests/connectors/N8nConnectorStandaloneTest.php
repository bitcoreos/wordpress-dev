<?php
/**
 * Standalone tests for BITCORE_Services_Connector_N8N.
 *
 * Run with:
 *   php tests/connectors/N8nConnectorStandaloneTest.php
 *
 * These tests avoid WordPress, PHPUnit, Composer, network, and database.
 */

declare(strict_types=1);

if (!defined('BITCORE_TIMEOUT')) {
    define('BITCORE_TIMEOUT', 17);
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        public function __construct(private string $code, private string $message) {}
        public function get_error_code(): string { return $this->code; }
        public function get_error_message(): string { return $this->message; }
    }
}

$GLOBALS['bitcore_n8n_test_options'] = [
    'bitcore_n8n_webhook_url' => 'https://n8n.example.test/webhook/bitcore',
    'bitcore_n8n_api_key' => 'test-n8n-key',
];
$GLOBALS['bitcore_n8n_http_post_queue'] = [];
$GLOBALS['bitcore_n8n_http_calls'] = [];
$GLOBALS['bitcore_n8n_current_user_id'] = 42;
$GLOBALS['bitcore_n8n_rand_value'] = 4242;

if (!function_exists('get_option')) {
    function get_option(string $name, mixed $default = false): mixed {
        return $GLOBALS['bitcore_n8n_test_options'][$name] ?? $default;
    }
}
if (!function_exists('get_current_user_id')) {
    function get_current_user_id(): int { return (int) $GLOBALS['bitcore_n8n_current_user_id']; }
}
if (!function_exists('wp_rand')) {
    function wp_rand(int $min = 0, int $max = 0): int { return (int) $GLOBALS['bitcore_n8n_rand_value']; }
}
if (!function_exists('sanitize_key')) {
    function sanitize_key(string $key): string {
        $key = strtolower($key);
        return preg_replace('/[^a-z0-9_\-]/', '', $key) ?? '';
    }
}
if (!function_exists('wp_remote_post')) {
    function wp_remote_post(string $url, array $args = []): array|WP_Error {
        $GLOBALS['bitcore_n8n_http_calls'][] = ['POST', $url, $args];
        return array_shift($GLOBALS['bitcore_n8n_http_post_queue']) ?? ['response' => ['code' => 200], 'body' => '{}'];
    }
}
if (!function_exists('is_wp_error')) {
    function is_wp_error(mixed $value): bool { return $value instanceof WP_Error; }
}
if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body(array $response): string { return (string) ($response['body'] ?? ''); }
}
if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code(array $response): int { return (int) ($response['response']['code'] ?? 0); }
}

require_once dirname(__DIR__, 2) . '/classes/services/abstract-connector.php';
require_once dirname(__DIR__, 2) . '/classes/services/connector-n8n.php';

final class BitcoreN8nConnectorStandaloneTest {
    private int $passed = 0;
    private int $failed = 0;

    public function run(): int {
        $this->test_missing_webhook_returns_wp_error_without_http_call();
        $this->test_send_intent_builds_payload_with_explicit_user();
        $this->test_send_intent_uses_current_user_when_user_id_missing();
        $this->test_get_queue_status_builds_payload();
        $this->test_dispatch_to_swarm_uses_default_agents();
        $this->test_dispatch_to_swarm_preserves_custom_agents();
        $this->test_get_agent_status_sanitizes_agent_id();
        $this->test_get_swarm_status_builds_payload();
        $this->test_post_uses_configured_headers_timeout_and_json_body();
        $this->test_transport_error_is_returned_unchanged();
        $this->test_http_error_status_returns_wp_error();
        $this->test_invalid_json_returns_wp_error();
        $this->test_valid_json_array_is_returned();
        $this->report();
        return $this->failed === 0 ? 0 : 1;
    }

    private function test_missing_webhook_returns_wp_error_without_http_call(): void {
        $this->reset_http();
        $GLOBALS['bitcore_n8n_test_options']['bitcore_n8n_webhook_url'] = '';
        $result = $this->connector()->get_queue_status();
        $this->assert_true($result instanceof WP_Error, 'missing_webhook_returns_wp_error');
        $this->assert_same('no_webhook', $result instanceof WP_Error ? $result->get_error_code() : null, 'missing_webhook_error_code');
        $this->assert_same([], $GLOBALS['bitcore_n8n_http_calls'], 'missing_webhook_no_http_call');
        $GLOBALS['bitcore_n8n_test_options']['bitcore_n8n_webhook_url'] = 'https://n8n.example.test/webhook/bitcore';
    }

    private function test_send_intent_builds_payload_with_explicit_user(): void {
        $this->reset_http();
        $GLOBALS['bitcore_n8n_http_post_queue'][] = $this->json_response(['ok' => true]);
        $result = $this->connector()->send_intent('Research ASHI', 'deep', 9);
        $payload = $this->payload(0);

        $this->assert_same(['ok' => true], $result, 'send_intent_decodes_result');
        $this->assert_same('Research ASHI', $payload['intent'] ?? null, 'send_intent_payload_intent');
        $this->assert_same('deep', $payload['scope'] ?? null, 'send_intent_payload_scope');
        $this->assert_same(9, $payload['user_id'] ?? null, 'send_intent_explicit_user_id');
        $this->assert_same('wordpress', $payload['source'] ?? null, 'send_intent_source_wordpress');
        $this->assert_has_correlation_id($payload, 'send_intent_correlation_id');
        $this->assert_timestamp($payload['timestamp'] ?? null, 'send_intent_timestamp');
    }

    private function test_send_intent_uses_current_user_when_user_id_missing(): void {
        $this->reset_http();
        $GLOBALS['bitcore_n8n_current_user_id'] = 77;
        $GLOBALS['bitcore_n8n_http_post_queue'][] = $this->json_response(['ok' => true]);
        $this->connector()->send_intent('Chat', 'quick', 0);
        $payload = $this->payload(0);
        $this->assert_same(77, $payload['user_id'] ?? null, 'send_intent_fallback_current_user');
        $GLOBALS['bitcore_n8n_current_user_id'] = 42;
    }

    private function test_get_queue_status_builds_payload(): void {
        $this->reset_http();
        $GLOBALS['bitcore_n8n_http_post_queue'][] = $this->json_response(['queue' => 'ok']);
        $result = $this->connector()->get_queue_status();
        $payload = $this->payload(0);
        $this->assert_same(['queue' => 'ok'], $result, 'queue_status_decodes_result');
        $this->assert_same('queue_status', $payload['action'] ?? null, 'queue_status_action');
        $this->assert_timestamp($payload['timestamp'] ?? null, 'queue_status_timestamp');
    }

    private function test_dispatch_to_swarm_uses_default_agents(): void {
        $this->reset_http();
        $GLOBALS['bitcore_n8n_http_post_queue'][] = $this->json_response(['dispatched' => true]);
        $this->connector()->dispatch_to_swarm('Build bridge');
        $payload = $this->payload(0);
        $this->assert_same('dispatch', $payload['action'] ?? null, 'dispatch_action');
        $this->assert_same('Build bridge', $payload['task'] ?? null, 'dispatch_task');
        $this->assert_same(['coraline', 'hyperion'], $payload['agents'] ?? null, 'dispatch_default_agents');
        $this->assert_has_correlation_id($payload, 'dispatch_correlation_id');
    }

    private function test_dispatch_to_swarm_preserves_custom_agents(): void {
        $this->reset_http();
        $GLOBALS['bitcore_n8n_http_post_queue'][] = $this->json_response(['dispatched' => true]);
        $this->connector()->dispatch_to_swarm('Build bridge', ['aria', 'forge']);
        $payload = $this->payload(0);
        $this->assert_same(['aria', 'forge'], $payload['agents'] ?? null, 'dispatch_custom_agents');
    }

    private function test_get_agent_status_sanitizes_agent_id(): void {
        $this->reset_http();
        $GLOBALS['bitcore_n8n_http_post_queue'][] = $this->json_response(['agent' => 'ok']);
        $this->connector()->get_agent_status('Aria<script>');
        $payload = $this->payload(0);
        $this->assert_same('agent_status', $payload['action'] ?? null, 'agent_status_action');
        $this->assert_same('ariascript', $payload['agent_id'] ?? null, 'agent_status_sanitizes_agent_id');
    }

    private function test_get_swarm_status_builds_payload(): void {
        $this->reset_http();
        $GLOBALS['bitcore_n8n_http_post_queue'][] = $this->json_response(['swarm' => 'ok']);
        $this->connector()->get_swarm_status();
        $payload = $this->payload(0);
        $this->assert_same('swarm_status', $payload['action'] ?? null, 'swarm_status_action');
        $this->assert_timestamp($payload['timestamp'] ?? null, 'swarm_status_timestamp');
    }

    private function test_post_uses_configured_headers_timeout_and_json_body(): void {
        $this->reset_http();
        $GLOBALS['bitcore_n8n_http_post_queue'][] = $this->json_response(['ok' => true]);
        $this->connector()->get_queue_status();
        $call = $GLOBALS['bitcore_n8n_http_calls'][0] ?? null;
        $this->assert_same('POST', $call[0] ?? null, 'post_uses_post');
        $this->assert_same('https://n8n.example.test/webhook/bitcore', $call[1] ?? null, 'post_uses_webhook_url');
        $this->assert_same('application/json', $call[2]['headers']['Content-Type'] ?? null, 'post_content_type_json');
        $this->assert_same('test-n8n-key', $call[2]['headers']['X-Bitcore-Key'] ?? null, 'post_x_bitcore_key');
        $this->assert_same(BITCORE_TIMEOUT, $call[2]['timeout'] ?? null, 'post_timeout_constant');
        $this->assert_true(is_array(json_decode((string) ($call[2]['body'] ?? ''), true)), 'post_body_valid_json');
    }

    private function test_transport_error_is_returned_unchanged(): void {
        $this->reset_http();
        $error = new WP_Error('transport_down', 'network down');
        $GLOBALS['bitcore_n8n_http_post_queue'][] = $error;
        $result = $this->connector()->get_queue_status();
        $this->assert_true($result === $error, 'transport_error_passthrough');
    }

    private function test_http_error_status_returns_wp_error(): void {
        $this->reset_http();
        $GLOBALS['bitcore_n8n_http_post_queue'][] = $this->json_response(['ok' => true], 500);
        $result = $this->connector()->get_queue_status();
        $this->assert_true($result instanceof WP_Error, 'http_error_returns_wp_error');
        $this->assert_same('n8n_http_error', $result instanceof WP_Error ? $result->get_error_code() : null, 'http_error_code');
    }

    private function test_invalid_json_returns_wp_error(): void {
        $this->reset_http();
        $GLOBALS['bitcore_n8n_http_post_queue'][] = ['response' => ['code' => 200], 'body' => '{not-json'];
        $result = $this->connector()->get_queue_status();
        $this->assert_true($result instanceof WP_Error, 'invalid_json_returns_wp_error');
        $this->assert_same('n8n_invalid_json', $result instanceof WP_Error ? $result->get_error_code() : null, 'invalid_json_error_code');
    }

    private function test_valid_json_array_is_returned(): void {
        $this->reset_http();
        $GLOBALS['bitcore_n8n_http_post_queue'][] = $this->json_response(['nested' => ['ok' => true]]);
        $this->assert_same(['nested' => ['ok' => true]], $this->connector()->get_queue_status(), 'valid_json_array_returned');
    }

    private function connector(): BITCORE_Services_Connector_N8N {
        return new BITCORE_Services_Connector_N8N(new stdClass());
    }

    private function json_response(array $payload, int $code = 200): array {
        return ['response' => ['code' => $code], 'body' => json_encode($payload)];
    }

    private function payload(int $index): array {
        $body = $GLOBALS['bitcore_n8n_http_calls'][$index][2]['body'] ?? '{}';
        $decoded = json_decode((string) $body, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function reset_http(): void {
        $GLOBALS['bitcore_n8n_http_post_queue'] = [];
        $GLOBALS['bitcore_n8n_http_calls'] = [];
        $GLOBALS['bitcore_n8n_test_options']['bitcore_n8n_webhook_url'] = 'https://n8n.example.test/webhook/bitcore';
        $GLOBALS['bitcore_n8n_test_options']['bitcore_n8n_api_key'] = 'test-n8n-key';
        $GLOBALS['bitcore_n8n_current_user_id'] = 42;
    }

    private function assert_timestamp(mixed $value, string $name): void {
        $this->assert_true(is_int($value) && $value > 0, $name);
    }

    private function assert_has_correlation_id(array $payload, string $name): void {
        $value = $payload['correlation_id'] ?? '';
        $this->assert_true(is_string($value) && str_starts_with($value, 'bitcore_') && str_ends_with($value, '_4242'), $name);
    }

    private function assert_same(mixed $expected, mixed $actual, string $name): void {
        if ($expected === $actual) { $this->pass($name); return; }
        $this->fail($name . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }

    private function assert_true(bool $condition, string $name): void {
        if ($condition) { $this->pass($name); return; }
        $this->fail($name . ': condition was false');
    }

    private function pass(string $name): void { $this->passed++; echo "PASS {$name}\n"; }
    private function fail(string $name): void { $this->failed++; echo "FAIL {$name}\n"; }
    private function report(): void {
        echo "\nAssertions passed: {$this->passed}\n";
        echo "Assertions failed: {$this->failed}\n";
    }
}

$test = new BitcoreN8nConnectorStandaloneTest();
exit($test->run());
