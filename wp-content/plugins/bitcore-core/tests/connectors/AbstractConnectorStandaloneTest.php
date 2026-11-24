<?php
/**
 * Standalone tests for BITCORE_Abstract_Connector.
 *
 * Run with:
 *   php tests/connectors/AbstractConnectorStandaloneTest.php
 *
 * These tests avoid WordPress, PHPUnit, Composer, network, and database.
 * @package    Bitcore\Tests
 * @since      2026.11.23
 * @version    1.0.0
 * @author     coraline
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

if (!class_exists('BITCORE_Core')) {
    class BITCORE_Core {}
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        public function __construct(private string $code, private string $message) {}
        public function get_error_code(): string { return $this->code; }
        public function get_error_message(): string { return $this->message; }
    }
}

$GLOBALS['bitcore_test_http_response'] = null;
$GLOBALS['bitcore_test_last_request'] = null;

if (!function_exists('trailingslashit')) {
    function trailingslashit(string $value): string { return rtrim($value, '/') . '/'; }
}
if (!function_exists('wp_json_encode')) {
    function wp_json_encode(mixed $value): string|false { return json_encode($value); }
}
if (!function_exists('current_time')) {
    function current_time(string $type): string { return '2026-01-01 00:00:00'; }
}
if (!function_exists('wp_remote_request')) {
    function wp_remote_request(string $url, array $args): array|WP_Error {
        $GLOBALS['bitcore_test_last_request'] = ['url' => $url, 'args' => $args];
        return $GLOBALS['bitcore_test_http_response'];
    }
}
if (!function_exists('is_wp_error')) {
    function is_wp_error(mixed $value): bool { return $value instanceof WP_Error; }
}
if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code(array $response): int { return (int) ($response['response']['code'] ?? 0); }
}
if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body(array $response): string { return (string) ($response['body'] ?? ''); }
}

require_once dirname(__DIR__, 2) . '/classes/services/abstract-connector.php';

final class BitcoreAbstractConnectorStandaloneTest {
    private int $passed = 0;
    private int $failed = 0;

    public function run(): int {
        $this->test_concrete_connector_extends_base_and_names_itself();
        $this->test_is_configured_requires_url_and_key();
        $this->test_last_error_and_request_log_start_empty();
        $this->test_default_headers_include_json_and_authorization_when_key_exists();
        $this->test_default_headers_omit_authorization_without_key();
        $this->test_request_builds_url_method_headers_and_json_body();
        $this->test_request_success_decodes_json_body();
        $this->test_request_success_returns_raw_for_non_json_body();
        $this->test_transport_error_sets_last_error_and_logs_error();
        $this->test_http_error_sets_last_error_and_returns_wp_error();
        $this->test_http_error_is_logged_as_error();
        $this->test_clear_request_log_empties_log();
        $this->test_request_log_keeps_only_last_100_entries();
        $this->report();
        return $this->failed === 0 ? 0 : 1;
    }

    private function test_concrete_connector_extends_base_and_names_itself(): void {
        $connector = $this->connector();
        $this->assert_true($connector instanceof BITCORE_Abstract_Connector, 'connector_extends_abstract_base');
        $this->assert_same('test_connector', $connector->get_name(), 'connector_returns_name');
    }

    private function test_is_configured_requires_url_and_key(): void {
        $this->assert_true($this->connector('https://api.example.com', 'key')->is_configured(), 'configured_with_url_and_key');
        $this->assert_false($this->connector('https://api.example.com', null)->is_configured(), 'not_configured_without_key');
        $this->assert_false($this->connector('', 'key')->is_configured(), 'not_configured_without_url');
    }

    private function test_last_error_and_request_log_start_empty(): void {
        $connector = $this->connector();
        $this->assert_same('', $connector->get_last_error(), 'last_error_empty_initially');
        $this->assert_same([], $connector->get_request_log(), 'request_log_empty_initially');
    }

    private function test_default_headers_include_json_and_authorization_when_key_exists(): void {
        $headers = $this->headers($this->connector('https://api.example.com', 'secret'));
        $this->assert_same('application/json', $headers['Content-Type'] ?? null, 'default_content_type_json');
        $this->assert_same('application/json', $headers['Accept'] ?? null, 'default_accept_json');
        $this->assert_same('Bearer secret', $headers['Authorization'] ?? null, 'default_authorization_bearer');
    }

    private function test_default_headers_omit_authorization_without_key(): void {
        $headers = $this->headers($this->connector('https://api.example.com', null));
        $this->assert_false(array_key_exists('Authorization', $headers), 'authorization_omitted_without_key');
    }

    private function test_request_builds_url_method_headers_and_json_body(): void {
        $connector = $this->connector('https://api.example.com/root', 'secret');
        $GLOBALS['bitcore_test_http_response'] = ['response' => ['code' => 200], 'body' => '{"ok":true}'];

        $result = $connector->public_request('/v1/items', 'POST', ['a' => 1], ['X-Test' => 'yes']);
        $request = $GLOBALS['bitcore_test_last_request'];

        $this->assert_same(['ok' => true], $result, 'request_returns_decoded_body');
        $this->assert_same('https://api.example.com/root/v1/items', $request['url'], 'request_builds_trailing_url');
        $this->assert_same('POST', $request['args']['method'], 'request_sets_method');
        $this->assert_same(30, $request['args']['timeout'], 'request_sets_default_timeout');
        $this->assert_same('Bearer secret', $request['args']['headers']['Authorization'] ?? null, 'request_merges_default_headers');
        $this->assert_same('yes', $request['args']['headers']['X-Test'] ?? null, 'request_merges_custom_headers');
        $this->assert_same('{"a":1}', $request['args']['body'] ?? null, 'request_json_encodes_body');
    }

    private function test_request_success_decodes_json_body(): void {
        $connector = $this->connector();
        $GLOBALS['bitcore_test_http_response'] = ['response' => ['code' => 200], 'body' => '{"value":7}'];
        $this->assert_same(['value' => 7], $connector->public_request('ok'), 'request_decodes_json_success');
    }

    private function test_request_success_returns_raw_for_non_json_body(): void {
        $connector = $this->connector();
        $GLOBALS['bitcore_test_http_response'] = ['response' => ['code' => 200], 'body' => 'plain text'];
        $this->assert_same(['raw' => 'plain text'], $connector->public_request('raw'), 'request_returns_raw_non_json');
    }

    private function test_transport_error_sets_last_error_and_logs_error(): void {
        $connector = $this->connector();
        $GLOBALS['bitcore_test_http_response'] = new WP_Error('transport_down', 'Transport failed');

        $result = $connector->public_request('fail');
        $log = $connector->get_request_log();

        $this->assert_true($result instanceof WP_Error, 'transport_error_returns_wp_error');
        $this->assert_same('Transport failed', $connector->get_last_error(), 'transport_error_sets_last_error');
        $this->assert_same(true, $log[0]['error'] ?? null, 'transport_error_logged_as_error');
    }

    private function test_http_error_sets_last_error_and_returns_wp_error(): void {
        $connector = $this->connector();
        $GLOBALS['bitcore_test_http_response'] = ['response' => ['code' => 500], 'body' => 'server exploded'];

        $result = $connector->public_request('server-error');

        $this->assert_true($result instanceof WP_Error, 'http_error_returns_wp_error');
        $this->assert_same('http_error', $result->get_error_code(), 'http_error_code_precise');
        $this->assert_same('HTTP 500: server exploded', $connector->get_last_error(), 'http_error_sets_last_error');
    }

    private function test_http_error_is_logged_as_error(): void {
        $connector = $this->connector();
        $GLOBALS['bitcore_test_http_response'] = ['response' => ['code' => 404], 'body' => 'missing'];

        $connector->public_request('missing');
        $log = $connector->get_request_log();

        $this->assert_same(true, $log[0]['error'] ?? null, 'http_error_logged_as_error');
    }

    private function test_clear_request_log_empties_log(): void {
        $connector = $this->connector();
        $GLOBALS['bitcore_test_http_response'] = ['response' => ['code' => 200], 'body' => '{}'];
        $connector->public_request('one');
        $this->assert_same(1, count($connector->get_request_log()), 'clear_setup_log_has_one_entry');
        $connector->clear_request_log();
        $this->assert_same([], $connector->get_request_log(), 'clear_request_log_removes_entries');
    }

    private function test_request_log_keeps_only_last_100_entries(): void {
        $connector = $this->connector();
        $GLOBALS['bitcore_test_http_response'] = ['response' => ['code' => 200], 'body' => '{}'];
        for ($i = 1; $i <= 105; $i++) {
            $connector->public_request('entry-' . $i);
        }
        $log = $connector->get_request_log();
        $this->assert_same(100, count($log), 'request_log_capped_at_100');
        $this->assert_same('entry-6', $log[0]['endpoint'] ?? null, 'request_log_drops_oldest_entries');
        $this->assert_same('entry-105', $log[99]['endpoint'] ?? null, 'request_log_keeps_latest_entry');
    }

    private function connector(string $api_url = 'https://api.test.com', ?string $api_key = 'test-key'): BITCORE_Abstract_Connector {
        return new class($api_url, $api_key) extends BITCORE_Abstract_Connector {
            public function __construct(private string $test_url, private ?string $test_key) {
                $this->api_url = $test_url;
                $this->api_key = $test_key;
            }
            protected function get_api_key(): ?string { return $this->test_key; }
            protected function get_api_url(): string { return $this->test_url; }
            public function get_name(): string { return 'test_connector'; }
            public function public_request(string $endpoint, string $method = 'GET', array $body = [], array $headers = []): array|WP_Error {
                return $this->request($endpoint, $method, $body, $headers);
            }
            public function public_headers(): array { return $this->get_default_headers(); }
        };
    }

    private function headers(BITCORE_Abstract_Connector $connector): array {
        return $connector->public_headers();
    }

    private function assert_true(bool $condition, string $name): void { $condition ? $this->pass($name) : $this->fail($name); }
    private function assert_false(bool $condition, string $name): void { (!$condition) ? $this->pass($name) : $this->fail($name); }
    private function assert_same(mixed $expected, mixed $actual, string $name): void {
        if ($expected === $actual) { $this->pass($name); return; }
        $this->fail($name . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
    private function pass(string $name): void { $this->passed++; echo "PASS {$name}\n"; }
    private function fail(string $name): void { $this->failed++; echo "FAIL {$name}\n"; }
    private function report(): void {
        echo "\nAssertions passed: {$this->passed}\n";
        echo "Assertions failed: {$this->failed}\n";
    }
}

$test = new BitcoreAbstractConnectorStandaloneTest();
exit($test->run());
