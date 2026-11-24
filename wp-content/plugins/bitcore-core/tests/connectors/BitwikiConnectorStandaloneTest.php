<?php
/**
 * Standalone tests for BITCORE_Services_Connector_BITwiki.
 *
 * Run with:
 *   php tests/connectors/BitwikiConnectorStandaloneTest.php
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

$GLOBALS['bitcore_bitwiki_test_options'] = [
    'bitcore_bitwiki_api_url' => 'https://wiki.example.test/api.php',
];
$GLOBALS['bitcore_bitwiki_http_get_queue'] = [];
$GLOBALS['bitcore_bitwiki_http_calls'] = [];

if (!function_exists('get_option')) {
    function get_option(string $name, mixed $default = false): mixed {
        return $GLOBALS['bitcore_bitwiki_test_options'][$name] ?? $default;
    }
}
if (!function_exists('add_query_arg')) {
    function add_query_arg(array $args, string $url): string {
        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($args);
    }
}
if (!function_exists('wp_remote_get')) {
    function wp_remote_get(string $url, array $args = []): array|WP_Error {
        $GLOBALS['bitcore_bitwiki_http_calls'][] = ['GET', $url, $args];
        return array_shift($GLOBALS['bitcore_bitwiki_http_get_queue']) ?? ['response' => ['code' => 200], 'body' => '{}'];
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

require_once dirname(__DIR__, 2) . '/classes/services/connector-bitwiki.php';

final class BitcoreBitwikiConnectorStandaloneTest {
    private int $passed = 0;
    private int $failed = 0;

    public function run(): int {
        $this->test_search_builds_mediawiki_search_query_and_decodes_results();
        $this->test_search_returns_empty_on_transport_error();
        $this->test_search_returns_empty_on_malformed_shape();
        $this->test_search_rejects_http_error_status();
        $this->test_get_index_builds_allpages_query_and_decodes_pages();
        $this->test_get_index_returns_empty_on_transport_error();
        $this->test_get_index_rejects_http_error_status();
        $this->test_get_article_normalizes_path_and_delegates_to_parse_query();
        $this->test_get_article_rejects_empty_path_without_http_call();
        $this->test_get_page_returns_null_on_transport_error();
        $this->test_get_page_returns_null_on_malformed_shape();
        $this->test_get_page_rejects_http_error_status();
        $this->test_recent_changes_builds_recentchanges_query_and_decodes_rows();
        $this->test_recent_changes_returns_empty_on_transport_error();
        $this->test_recent_changes_rejects_http_error_status();
        $this->report();
        return $this->failed === 0 ? 0 : 1;
    }

    private function test_search_builds_mediawiki_search_query_and_decodes_results(): void {
        $this->reset_http();
        $GLOBALS['bitcore_bitwiki_http_get_queue'][] = $this->json_response([
            'query' => ['search' => [['title' => 'ASHI'], ['title' => 'BITCORE']]],
        ]);
        $result = $this->connector()->search('ASHI swarm', 7);
        $call = $GLOBALS['bitcore_bitwiki_http_calls'][0] ?? null;
        $query = $this->query_args((string) ($call[1] ?? ''));

        $this->assert_same([['title' => 'ASHI'], ['title' => 'BITCORE']], $result, 'search_decodes_results');
        $this->assert_same('GET', $call[0] ?? null, 'search_uses_get');
        $this->assert_same('https://wiki.example.test/api.php', strtok((string) ($call[1] ?? ''), '?'), 'search_base_url');
        $this->assert_same('query', $query['action'] ?? null, 'search_action_query');
        $this->assert_same('search', $query['list'] ?? null, 'search_list_search');
        $this->assert_same('ASHI swarm', $query['srsearch'] ?? null, 'search_query_value');
        $this->assert_same('7', $query['srlimit'] ?? null, 'search_limit_value');
        $this->assert_same('json', $query['format'] ?? null, 'search_format_json');
        $this->assert_same(30, $call[2]['timeout'] ?? null, 'search_timeout');
    }

    private function test_search_returns_empty_on_transport_error(): void {
        $this->reset_http();
        $GLOBALS['bitcore_bitwiki_http_get_queue'][] = new WP_Error('down', 'network down');
        $this->assert_same([], $this->connector()->search('ASHI'), 'search_empty_on_transport_error');
    }

    private function test_search_returns_empty_on_malformed_shape(): void {
        $this->reset_http();
        $GLOBALS['bitcore_bitwiki_http_get_queue'][] = $this->json_response(['unexpected' => true]);
        $this->assert_same([], $this->connector()->search('ASHI'), 'search_empty_on_malformed_shape');
    }

    private function test_search_rejects_http_error_status(): void {
        $this->reset_http();
        $GLOBALS['bitcore_bitwiki_http_get_queue'][] = $this->json_response(['query' => ['search' => [['title' => 'Do not trust']]]], 500);
        $this->assert_same([], $this->connector()->search('ASHI'), 'search_rejects_http_error_status');
    }

    private function test_get_index_builds_allpages_query_and_decodes_pages(): void {
        $this->reset_http();
        $GLOBALS['bitcore_bitwiki_http_get_queue'][] = $this->json_response(['query' => ['allpages' => [['title' => 'Main']]]]);
        $result = $this->connector()->get_index();
        $query = $this->query_args((string) ($GLOBALS['bitcore_bitwiki_http_calls'][0][1] ?? ''));

        $this->assert_same([['title' => 'Main']], $result, 'index_decodes_allpages');
        $this->assert_same('query', $query['action'] ?? null, 'index_action_query');
        $this->assert_same('allpages', $query['list'] ?? null, 'index_list_allpages');
        $this->assert_same('0', $query['apnamespace'] ?? null, 'index_namespace_main');
        $this->assert_same('50', $query['aplimit'] ?? null, 'index_limit_50');
    }

    private function test_get_index_returns_empty_on_transport_error(): void {
        $this->reset_http();
        $GLOBALS['bitcore_bitwiki_http_get_queue'][] = new WP_Error('down', 'network down');
        $this->assert_same([], $this->connector()->get_index(), 'index_empty_on_transport_error');
    }

    private function test_get_index_rejects_http_error_status(): void {
        $this->reset_http();
        $GLOBALS['bitcore_bitwiki_http_get_queue'][] = $this->json_response(['query' => ['allpages' => [['title' => 'Bad']]]], 503);
        $this->assert_same([], $this->connector()->get_index(), 'index_rejects_http_error_status');
    }

    private function test_get_article_normalizes_path_and_delegates_to_parse_query(): void {
        $this->reset_http();
        $GLOBALS['bitcore_bitwiki_http_get_queue'][] = $this->json_response(['parse' => ['title' => 'ASHI Plan', 'text' => ['*' => '<p>ok</p>']]]);
        $result = $this->connector()->get_article('/ASHI_Plan/');
        $query = $this->query_args((string) ($GLOBALS['bitcore_bitwiki_http_calls'][0][1] ?? ''));

        $this->assert_same('ASHI Plan', $result['title'] ?? null, 'article_decodes_parse_title');
        $this->assert_same('parse', $query['action'] ?? null, 'article_action_parse');
        $this->assert_same('ASHI Plan', $query['page'] ?? null, 'article_path_normalized');
        $this->assert_same('text', $query['prop'] ?? null, 'article_prop_text');
    }

    private function test_get_article_rejects_empty_path_without_http_call(): void {
        $this->reset_http();
        $this->assert_same(null, $this->connector()->get_article('///'), 'article_empty_path_null');
        $this->assert_same([], $GLOBALS['bitcore_bitwiki_http_calls'], 'article_empty_path_no_http');
    }

    private function test_get_page_returns_null_on_transport_error(): void {
        $this->reset_http();
        $GLOBALS['bitcore_bitwiki_http_get_queue'][] = new WP_Error('down', 'network down');
        $this->assert_same(null, $this->connector()->get_page('ASHI'), 'page_null_on_transport_error');
    }

    private function test_get_page_returns_null_on_malformed_shape(): void {
        $this->reset_http();
        $GLOBALS['bitcore_bitwiki_http_get_queue'][] = $this->json_response(['unexpected' => true]);
        $this->assert_same(null, $this->connector()->get_page('ASHI'), 'page_null_on_malformed_shape');
    }

    private function test_get_page_rejects_http_error_status(): void {
        $this->reset_http();
        $GLOBALS['bitcore_bitwiki_http_get_queue'][] = $this->json_response(['parse' => ['title' => 'Do not trust']], 404);
        $this->assert_same(null, $this->connector()->get_page('Missing'), 'page_rejects_http_error_status');
    }

    private function test_recent_changes_builds_recentchanges_query_and_decodes_rows(): void {
        $this->reset_http();
        $GLOBALS['bitcore_bitwiki_http_get_queue'][] = $this->json_response(['query' => ['recentchanges' => [['title' => 'Updated']]]]);
        $result = $this->connector()->get_recent_changes(3);
        $query = $this->query_args((string) ($GLOBALS['bitcore_bitwiki_http_calls'][0][1] ?? ''));

        $this->assert_same([['title' => 'Updated']], $result, 'recent_changes_decodes_rows');
        $this->assert_same('query', $query['action'] ?? null, 'recent_changes_action_query');
        $this->assert_same('recentchanges', $query['list'] ?? null, 'recent_changes_list');
        $this->assert_same('3', $query['rclimit'] ?? null, 'recent_changes_limit');
    }

    private function test_recent_changes_returns_empty_on_transport_error(): void {
        $this->reset_http();
        $GLOBALS['bitcore_bitwiki_http_get_queue'][] = new WP_Error('down', 'network down');
        $this->assert_same([], $this->connector()->get_recent_changes(), 'recent_changes_empty_on_transport_error');
    }

    private function test_recent_changes_rejects_http_error_status(): void {
        $this->reset_http();
        $GLOBALS['bitcore_bitwiki_http_get_queue'][] = $this->json_response(['query' => ['recentchanges' => [['title' => 'Bad']]]], 500);
        $this->assert_same([], $this->connector()->get_recent_changes(), 'recent_changes_rejects_http_error_status');
    }

    private function connector(): BITCORE_Services_Connector_BITwiki {
        return new BITCORE_Services_Connector_BITwiki(new stdClass());
    }

    private function json_response(array $payload, int $code = 200): array {
        return ['response' => ['code' => $code], 'body' => json_encode($payload)];
    }

    private function query_args(string $url): array {
        $query = parse_url($url, PHP_URL_QUERY) ?: '';
        parse_str($query, $args);
        return $args;
    }

    private function reset_http(): void {
        $GLOBALS['bitcore_bitwiki_http_get_queue'] = [];
        $GLOBALS['bitcore_bitwiki_http_calls'] = [];
    }

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

$test = new BitcoreBitwikiConnectorStandaloneTest();
exit($test->run());
