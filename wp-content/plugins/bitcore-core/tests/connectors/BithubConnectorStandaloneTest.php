<?php
/**
 * Standalone tests for BITCORE_Services_Connector_BIThub.
 *
 * Run with:
 *   php tests/connectors/BithubConnectorStandaloneTest.php
 *
 * These tests avoid WordPress, PHPUnit, Composer, network, and database.
 * @package    Bitcore\Tests
 * @since      2026.11.23
 * @version    1.0.0
 * @author     coraline
 */

declare(strict_types=1);

if (!class_exists('WP_Error')) {
    class WP_Error {
        public function __construct(private string $code, private string $message) {}
        public function get_error_code(): string { return $this->code; }
        public function get_error_message(): string { return $this->message; }
    }
}

$GLOBALS['bitcore_test_options'] = [
    'bitcore_bithub_api_url' => 'https://hub.example.test',
    'bitcore_bithub_api_key' => 'secret-key',
];
$GLOBALS['bitcore_test_http_get_queue'] = [];
$GLOBALS['bitcore_test_http_post_queue'] = [];
$GLOBALS['bitcore_test_http_calls'] = [];

if (!function_exists('get_option')) {
    function get_option(string $name, mixed $default = false): mixed {
        return $GLOBALS['bitcore_test_options'][$name] ?? $default;
    }
}
if (!function_exists('wp_remote_get')) {
    function wp_remote_get(string $url, array $args = []): array|WP_Error {
        $GLOBALS['bitcore_test_http_calls'][] = ['GET', $url, $args];
        return array_shift($GLOBALS['bitcore_test_http_get_queue']) ?? ['response' => ['code' => 200], 'body' => '{}'];
    }
}
if (!function_exists('wp_remote_post')) {
    function wp_remote_post(string $url, array $args = []): array|WP_Error {
        $GLOBALS['bitcore_test_http_calls'][] = ['POST', $url, $args];
        return array_shift($GLOBALS['bitcore_test_http_post_queue']) ?? ['response' => ['code' => 200], 'body' => '{}'];
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
if (!function_exists('absint')) {
    function absint(mixed $value): int { return abs((int) $value); }
}
if (!function_exists('sanitize_title')) {
    function sanitize_title(mixed $value): string {
        $value = strtolower(strip_tags((string) $value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        return trim($value, '-');
    }
}

require_once dirname(__DIR__, 2) . '/classes/services/connector-bithub.php';

final class BitcoreBithubConnectorStandaloneTest {
    private int $passed = 0;
    private int $failed = 0;

    public function run(): int {
        $this->test_latest_topics_uses_configured_url_headers_timeout_and_limit();
        $this->test_latest_topics_returns_empty_on_transport_error();
        $this->test_latest_topics_returns_empty_on_malformed_shape();
        $this->test_latest_topics_rejects_http_error_status();
        $this->test_categories_uses_categories_endpoint_and_decodes_categories();
        $this->test_artifacts_uses_artifacts_category_endpoint_and_decodes_topics();
        $this->test_get_topic_rejects_non_numeric_and_zero_ids_without_http_call();
        $this->test_get_topic_uses_discourse_topic_endpoint_and_decodes_array();
        $this->test_get_topic_returns_empty_for_non_json_body();
        $this->test_get_topic_rejects_http_error_status();
        $this->test_get_artifact_numeric_reuses_get_topic();
        $this->test_get_artifact_slug_matches_slug_title_and_fancy_title();
        $this->test_get_artifact_rejects_empty_slug_without_http_call();
        $this->test_get_artifact_returns_empty_when_no_match();
        $this->test_create_topic_posts_to_posts_endpoint_with_expected_payload();
        $this->test_create_topic_returns_wp_error_on_transport_failure();
        $this->test_create_topic_decodes_success_response();
        $this->test_create_topic_rejects_http_error_status();
        $this->report();
        return $this->failed === 0 ? 0 : 1;
    }

    private function test_latest_topics_uses_configured_url_headers_timeout_and_limit(): void {
        $this->reset_http();
        $GLOBALS['bitcore_test_http_get_queue'][] = $this->json_response([
            'topic_list' => ['topics' => [ ['id' => 1], ['id' => 2], ['id' => 3] ]],
        ]);

        $result = $this->connector()->get_latest_topics(2);
        $call = $GLOBALS['bitcore_test_http_calls'][0] ?? null;

        $this->assert_same([['id' => 1], ['id' => 2]], $result, 'latest_topics_applies_limit');
        $this->assert_same('GET', $call[0] ?? null, 'latest_topics_uses_get');
        $this->assert_same('https://hub.example.test/latest.json', $call[1] ?? null, 'latest_topics_url');
        $this->assert_same(30, $call[2]['timeout'] ?? null, 'latest_topics_timeout');
        $this->assert_same('application/json', $call[2]['headers']['Accept'] ?? null, 'latest_topics_accept_header');
        $this->assert_same('secret-key', $call[2]['headers']['Api-Key'] ?? null, 'latest_topics_api_key_header');
    }

    private function test_latest_topics_returns_empty_on_transport_error(): void {
        $this->reset_http();
        $GLOBALS['bitcore_test_http_get_queue'][] = new WP_Error('down', 'network down');
        $this->assert_same([], $this->connector()->get_latest_topics(), 'latest_topics_empty_on_transport_error');
    }

    private function test_latest_topics_returns_empty_on_malformed_shape(): void {
        $this->reset_http();
        $GLOBALS['bitcore_test_http_get_queue'][] = $this->json_response(['unexpected' => true]);
        $this->assert_same([], $this->connector()->get_latest_topics(), 'latest_topics_empty_on_malformed_shape');
    }

    private function test_latest_topics_rejects_http_error_status(): void {
        $this->reset_http();
        $GLOBALS['bitcore_test_http_get_queue'][] = $this->json_response(['topic_list' => ['topics' => [['id' => 9]]]], 500);
        $this->assert_same([], $this->connector()->get_latest_topics(), 'latest_topics_rejects_http_error_status');
    }

    private function test_categories_uses_categories_endpoint_and_decodes_categories(): void {
        $this->reset_http();
        $GLOBALS['bitcore_test_http_get_queue'][] = $this->json_response(['category_list' => ['categories' => [['id' => 10]]]]);
        $result = $this->connector()->get_categories();
        $this->assert_same([['id' => 10]], $result, 'categories_decodes_category_list');
        $this->assert_same('https://hub.example.test/categories.json', $GLOBALS['bitcore_test_http_calls'][0][1] ?? null, 'categories_url');
    }

    private function test_artifacts_uses_artifacts_category_endpoint_and_decodes_topics(): void {
        $this->reset_http();
        $GLOBALS['bitcore_test_http_get_queue'][] = $this->json_response(['topic_list' => ['topics' => [['slug' => 'alpha']]]]);
        $result = $this->connector()->get_artifacts();
        $this->assert_same([['slug' => 'alpha']], $result, 'artifacts_decodes_topics');
        $this->assert_same('https://hub.example.test/c/artifacts/6.json', $GLOBALS['bitcore_test_http_calls'][0][1] ?? null, 'artifacts_url');
    }

    private function test_get_topic_rejects_non_numeric_and_zero_ids_without_http_call(): void {
        $this->reset_http();
        $connector = $this->connector();
        $this->assert_same([], $connector->get_topic('abc'), 'get_topic_rejects_non_numeric');
        $this->assert_same([], $connector->get_topic(0), 'get_topic_rejects_zero');
        $this->assert_same([], $GLOBALS['bitcore_test_http_calls'], 'get_topic_invalid_ids_make_no_http_call');
    }

    private function test_get_topic_uses_discourse_topic_endpoint_and_decodes_array(): void {
        $this->reset_http();
        $GLOBALS['bitcore_test_http_get_queue'][] = $this->json_response(['id' => 42, 'title' => 'Topic']);
        $result = $this->connector()->get_topic(42);
        $this->assert_same(['id' => 42, 'title' => 'Topic'], $result, 'get_topic_decodes_topic');
        $this->assert_same('https://hub.example.test/t/42.json', $GLOBALS['bitcore_test_http_calls'][0][1] ?? null, 'get_topic_url');
    }

    private function test_get_topic_returns_empty_for_non_json_body(): void {
        $this->reset_http();
        $GLOBALS['bitcore_test_http_get_queue'][] = ['response' => ['code' => 200], 'body' => 'not json'];
        $this->assert_same([], $this->connector()->get_topic(11), 'get_topic_empty_for_non_json_body');
    }

    private function test_get_topic_rejects_http_error_status(): void {
        $this->reset_http();
        $GLOBALS['bitcore_test_http_get_queue'][] = $this->json_response(['id' => 500, 'title' => 'Should not trust'], 500);
        $this->assert_same([], $this->connector()->get_topic(500), 'get_topic_rejects_http_error_status');
    }

    private function test_get_artifact_numeric_reuses_get_topic(): void {
        $this->reset_http();
        $GLOBALS['bitcore_test_http_get_queue'][] = $this->json_response(['id' => 77, 'title' => 'Artifact']);
        $result = $this->connector()->get_artifact(77);
        $this->assert_same(77, $result['id'] ?? null, 'artifact_numeric_returns_topic');
        $this->assert_same('https://hub.example.test/t/77.json', $GLOBALS['bitcore_test_http_calls'][0][1] ?? null, 'artifact_numeric_topic_url');
    }

    private function test_get_artifact_slug_matches_slug_title_and_fancy_title(): void {
        $cases = [
            ['research-alpha', [['slug' => 'research-alpha', 'id' => 1]], 1, 'artifact_matches_slug'],
            ['Research Beta', [['title' => 'Research Beta', 'id' => 2]], 2, 'artifact_matches_title'],
            ['Research Gamma', [['fancy_title' => 'Research Gamma', 'id' => 3]], 3, 'artifact_matches_fancy_title'],
        ];
        foreach ($cases as [$needle, $topics, $id, $name]) {
            $this->reset_http();
            $GLOBALS['bitcore_test_http_get_queue'][] = $this->json_response(['topic_list' => ['topics' => $topics]]);
            $result = $this->connector()->get_artifact($needle);
            $this->assert_same($id, $result['id'] ?? null, $name);
        }
    }

    private function test_get_artifact_rejects_empty_slug_without_http_call(): void {
        $this->reset_http();
        $this->assert_same([], $this->connector()->get_artifact(' <b></b> '), 'artifact_empty_slug_rejected');
        $this->assert_same([], $GLOBALS['bitcore_test_http_calls'], 'artifact_empty_slug_no_http');
    }

    private function test_get_artifact_returns_empty_when_no_match(): void {
        $this->reset_http();
        $GLOBALS['bitcore_test_http_get_queue'][] = $this->json_response(['topic_list' => ['topics' => [['slug' => 'other']]]]);
        $this->assert_same([], $this->connector()->get_artifact('missing'), 'artifact_no_match_empty');
    }

    private function test_create_topic_posts_to_posts_endpoint_with_expected_payload(): void {
        $this->reset_http();
        $GLOBALS['bitcore_test_http_post_queue'][] = $this->json_response(['id' => 123]);
        $result = $this->connector()->create_topic('Title', 'Body', 9);
        $call = $GLOBALS['bitcore_test_http_calls'][0] ?? null;
        $this->assert_same(['id' => 123], $result, 'create_topic_decodes_result');
        $this->assert_same('POST', $call[0] ?? null, 'create_topic_uses_post');
        $this->assert_same('https://hub.example.test/posts.json', $call[1] ?? null, 'create_topic_url');
        $this->assert_same(['title' => 'Title', 'raw' => 'Body', 'category' => 9], $call[2]['body'] ?? null, 'create_topic_payload');
        $this->assert_same('secret-key', $call[2]['headers']['Api-Key'] ?? null, 'create_topic_api_key_header');
    }

    private function test_create_topic_returns_wp_error_on_transport_failure(): void {
        $this->reset_http();
        $GLOBALS['bitcore_test_http_post_queue'][] = new WP_Error('post_down', 'post failed');
        $result = $this->connector()->create_topic('Title', 'Body');
        $this->assert_true($result instanceof WP_Error, 'create_topic_transport_error_passthrough');
    }

    private function test_create_topic_decodes_success_response(): void {
        $this->reset_http();
        $GLOBALS['bitcore_test_http_post_queue'][] = $this->json_response(['topic_id' => 99]);
        $this->assert_same(['topic_id' => 99], $this->connector()->create_topic('T', 'C'), 'create_topic_decodes_success');
    }

    private function test_create_topic_rejects_http_error_status(): void {
        $this->reset_http();
        $GLOBALS['bitcore_test_http_post_queue'][] = $this->json_response(['id' => 501], 500);
        $result = $this->connector()->create_topic('Bad', 'Body');
        $this->assert_true($result instanceof WP_Error, 'create_topic_rejects_http_error_status');
    }

    private function connector(): BITCORE_Services_Connector_BIThub {
        return new BITCORE_Services_Connector_BIThub(new stdClass());
    }

    private function json_response(array $payload, int $code = 200): array {
        return ['response' => ['code' => $code], 'body' => json_encode($payload)];
    }

    private function reset_http(): void {
        $GLOBALS['bitcore_test_http_get_queue'] = [];
        $GLOBALS['bitcore_test_http_post_queue'] = [];
        $GLOBALS['bitcore_test_http_calls'] = [];
    }

    private function assert_true(bool $condition, string $name): void { $condition ? $this->pass($name) : $this->fail($name); }
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

$test = new BitcoreBithubConnectorStandaloneTest();
exit($test->run());
