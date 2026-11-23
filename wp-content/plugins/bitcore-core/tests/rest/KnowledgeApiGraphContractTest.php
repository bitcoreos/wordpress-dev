<?php
/**
 * Standalone contract tests for BITCORE\Rest\Knowledge_Api graph endpoints.
 *
 * Run with:
 *   php tests/rest/KnowledgeApiGraphContractTest.php
 *
 * These tests intentionally avoid live WordPress, network, database, and remote
 * services. They assert the page-facing API contract used by
 * wp-content/themes/bitcore-theme/page-knowledge.php:
 *   GET /wp-json/bitcore/v1/knowledge/nodes -> { nodes: [...] }
 *   GET /wp-json/bitcore/v1/knowledge/edges -> { edges: [...] }
 *   POST /wp-json/bitcore/v1/context/search -> { items: [...] }
 *   POST /wp-json/bitcore/v1/context -> { success: true, context: {...} }
 */

declare(strict_types=1);

define('ABSPATH', __DIR__);
define('BITCORE_PATH', dirname(__DIR__, 2) . '/');

$GLOBALS['bitcore_knowledge_test_state'] = [];

final class WP_Error {
    private string $code;
    private string $message;
    private array $data;

    public function __construct(string $code = '', string $message = '', array $data = []) {
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }

    public function get_error_code(): string { return $this->code; }
    public function get_error_message(): string { return $this->message; }
    public function get_error_data(): array { return $this->data; }
}

final class WP_REST_Response {
    private mixed $data;
    private int $status;

    public function __construct(mixed $data = null, int $status = 200) {
        $this->data = $data;
        $this->status = $status;
    }

    public function get_data(): mixed { return $this->data; }
    public function get_status(): int { return $this->status; }
}

final class WP_REST_Request implements ArrayAccess {
    private array $params;

    public function __construct(array $params = []) {
        $this->params = $params;
    }

    public function get_param(string $key): mixed { return $this->params[$key] ?? null; }
    public function get_json_params(): array { return $this->params; }
    public function offsetExists(mixed $offset): bool { return array_key_exists((string) $offset, $this->params); }
    public function offsetGet(mixed $offset): mixed { return $this->params[(string) $offset] ?? null; }
    public function offsetSet(mixed $offset, mixed $value): void { $this->params[(string) $offset] = $value; }
    public function offsetUnset(mixed $offset): void { unset($this->params[(string) $offset]); }
}

final class KnowledgeApiGraphWpdbStub {
    public string $prefix = 'wp_';
    public int $insert_id = 0;
    public function prepare(string $query, mixed ...$args): string { return vsprintf(str_replace(['%d', '%s'], ['%d', "'%s'"], $query), $args); }
    public function get_var(string $query): int { return 0; }
    public function get_results(string $query, string $output = 'ARRAY_A'): array { return []; }
    public function get_row(string $query, string $output = 'ARRAY_A'): ?array { return null; }
    public function insert(string $table, array $data, array $format): int|false { $this->insert_id = 1; return 1; }
    public function update(string $table, array $data, array $where, array $format = [], array $where_format = []): int|false { return 1; }
    public function delete(string $table, array $where, array $where_format = []): int|false { return 1; }
}

final class KnowledgeGraphBithubConnectorStub {
    public function get_latest_topics(int $limit = 10): array {
        return [
            ['id' => 101, 'title' => 'Prompt Alpha', 'excerpt' => 'Prompt from Bithub', 'tags' => ['prompt', 'bithub'], 'updated_at' => '2026-05-01T00:00:00Z'],
        ];
    }

    public function get_artifacts(): array {
        return [
            ['id' => 202, 'title' => 'Artifact Beta', 'description' => 'Artifact from Bithub', 'tags' => ['artifact']],
        ];
    }

    public function search(string $query, int $limit = 10): array {
        return [
            ['id' => 303, 'title' => 'Prompt Search Match', 'excerpt' => 'Bithub search result', 'tags' => ['prompt', $query], 'type' => 'prompt'],
        ];
    }
}

final class KnowledgeGraphBitwikiConnectorStub {
    public function get_index(): array {
        return [
            ['title' => 'Skill Gamma', 'description' => 'Skill from BitWiki', 'tags' => ['skill'], 'url' => 'https://bitwiki.org/wiki/Skill_Gamma'],
        ];
    }

    public function search(string $query, int $limit = 10): array {
        return [
            ['pageid' => 404, 'title' => 'Skill Search Match', 'snippet' => 'BitWiki search result', 'tags' => ['skill', $query]],
        ];
    }
}

function bitcore_knowledge_graph_test_reset(): void {
    global $wpdb;
    $wpdb = new KnowledgeApiGraphWpdbStub();
    $GLOBALS['bitcore_knowledge_test_state'] = [
        'routes' => [],
        'current_user_id' => 42,
        'can_manage_options' => false,
    ];
    $GLOBALS['bitcore'] = (object) [
        'connector_bithub' => new KnowledgeGraphBithubConnectorStub(),
        'connector_bitwiki' => new KnowledgeGraphBitwikiConnectorStub(),
    ];
}

function register_rest_route(string $namespace, string $route, array $args): void {
    $GLOBALS['bitcore_knowledge_test_state']['routes'][$namespace . $route] = $args;
}
function rest_ensure_response(mixed $data): WP_REST_Response {
    return $data instanceof WP_REST_Response ? $data : new WP_REST_Response($data);
}
function is_user_logged_in(): bool { return $GLOBALS['bitcore_knowledge_test_state']['current_user_id'] > 0; }
function current_user_can(string $capability): bool { return $capability === 'manage_options' && $GLOBALS['bitcore_knowledge_test_state']['can_manage_options']; }
function get_current_user_id(): int { return $GLOBALS['bitcore_knowledge_test_state']['current_user_id']; }
function sanitize_text_field(mixed $value): string { return trim(strip_tags((string) $value)); }
function sanitize_textarea_field(mixed $value): string { return trim(strip_tags((string) $value)); }
function wp_kses_post(mixed $value): string { return (string) $value; }
function is_wp_error(mixed $value): bool { return $value instanceof WP_Error; }
function current_time(string $type): string { return '2026-05-26 00:00:00'; }

require_once BITCORE_PATH . 'rest/class-knowledge-api.php';

final class KnowledgeApiGraphContractStandaloneTest {
    private int $passed = 0;
    private int $failed = 0;

    public function run(): void {
        $this->test_constructor_registers_page_facing_graph_routes();
        $this->test_nodes_endpoint_returns_bithub_backed_card_shape();
        $this->test_edges_endpoint_returns_bitwiki_backed_card_shape();
        $this->test_context_routes_are_registered_and_callable();
        $this->test_context_search_merges_bithub_and_bitwiki_results();
        $this->test_context_create_returns_private_context_record();
        $this->test_create_item_returns_created_status_and_private_record();
        $this->test_core_bootstrap_loads_knowledge_api();
        $this->report();
    }

    private function test_constructor_registers_page_facing_graph_routes(): void {
        bitcore_knowledge_graph_test_reset();
        new \BITCORE\Rest\Knowledge_Api();
        $routes = $GLOBALS['bitcore_knowledge_test_state']['routes'];

        $this->assert_true(isset($routes['bitcore/v1/knowledge']), 'crud_route_still_registered');
        $this->assert_true(isset($routes['bitcore/v1/knowledge/nodes']), 'nodes_route_registered');
        $this->assert_true(isset($routes['bitcore/v1/knowledge/edges']), 'edges_route_registered');
        $this->assert_same('GET', $routes['bitcore/v1/knowledge/nodes']['methods'] ?? null, 'nodes_route_uses_get');
        $this->assert_same('GET', $routes['bitcore/v1/knowledge/edges']['methods'] ?? null, 'edges_route_uses_get');
        $this->assert_true(is_callable($routes['bitcore/v1/knowledge/nodes']['callback'] ?? null), 'nodes_callback_callable');
        $this->assert_true(is_callable($routes['bitcore/v1/knowledge/edges']['callback'] ?? null), 'edges_callback_callable');
    }

    private function test_nodes_endpoint_returns_bithub_backed_card_shape(): void {
        bitcore_knowledge_graph_test_reset();
        $api = new \BITCORE\Rest\Knowledge_Api();
        if (!method_exists($api, 'get_nodes')) {
            $this->assert_true(false, 'get_nodes_method_exists');
            return;
        }
        $this->assert_true(true, 'get_nodes_method_exists');

        $response = $api->get_nodes(new WP_REST_Request(['limit' => 10]));
        $this->assert_true($response instanceof WP_REST_Response, 'nodes_returns_rest_response');
        $this->assert_same(200, $response->get_status(), 'nodes_status_200');

        $data = $response->get_data();
        $this->assert_true(is_array($data), 'nodes_data_array');
        $this->assert_true(isset($data['nodes']) && is_array($data['nodes']), 'nodes_key_array');
        $this->assert_true(count($data['nodes']) >= 2, 'nodes_include_topics_and_artifacts');

        $first = $data['nodes'][0];
        $this->assert_same('bithub', $first['source'] ?? null, 'node_source_bithub');
        $this->assert_true(isset($first['id']) && $first['id'] !== '', 'node_has_id');
        $this->assert_true(isset($first['title']) && $first['title'] !== '', 'node_has_title');
        $this->assert_true(array_key_exists('description', $first), 'node_has_description_key');
        $this->assert_true(isset($first['tags']) && is_array($first['tags']), 'node_tags_array');
        $this->assert_true(isset($first['type']) && in_array($first['type'], ['prompt', 'artifact', 'topic'], true), 'node_type_known');
    }

    private function test_edges_endpoint_returns_bitwiki_backed_card_shape(): void {
        bitcore_knowledge_graph_test_reset();
        $api = new \BITCORE\Rest\Knowledge_Api();
        if (!method_exists($api, 'get_edges')) {
            $this->assert_true(false, 'get_edges_method_exists');
            return;
        }
        $this->assert_true(true, 'get_edges_method_exists');

        $response = $api->get_edges(new WP_REST_Request(['limit' => 10]));
        $this->assert_true($response instanceof WP_REST_Response, 'edges_returns_rest_response');
        $this->assert_same(200, $response->get_status(), 'edges_status_200');

        $data = $response->get_data();
        $this->assert_true(is_array($data), 'edges_data_array');
        $this->assert_true(isset($data['edges']) && is_array($data['edges']), 'edges_key_array');
        $this->assert_true(count($data['edges']) >= 1, 'edges_include_bitwiki_items');

        $first = $data['edges'][0];
        $this->assert_same('bitwiki', $first['source'] ?? null, 'edge_source_bitwiki');
        $this->assert_true(isset($first['id']) && $first['id'] !== '', 'edge_has_id');
        $this->assert_true(isset($first['title']) && $first['title'] !== '', 'edge_has_title');
        $this->assert_true(array_key_exists('description', $first), 'edge_has_description_key');
        $this->assert_true(isset($first['tags']) && is_array($first['tags']), 'edge_tags_array');
        $this->assert_same('skill', $first['type'] ?? null, 'edge_type_skill');
    }


    private function test_context_routes_are_registered_and_callable(): void {
        bitcore_knowledge_graph_test_reset();
        new \BITCORE\Rest\Knowledge_Api();
        $routes = $GLOBALS['bitcore_knowledge_test_state']['routes'];

        $this->assert_true(isset($routes['bitcore/v1/context/search']), 'context_search_route_registered');
        $this->assert_true(isset($routes['bitcore/v1/context']), 'context_create_route_registered');
        $this->assert_same('POST', $routes['bitcore/v1/context/search']['methods'] ?? null, 'context_search_route_uses_post');
        $this->assert_same('POST', $routes['bitcore/v1/context']['methods'] ?? null, 'context_create_route_uses_post');
        $this->assert_true(is_callable($routes['bitcore/v1/context/search']['callback'] ?? null), 'context_search_callback_callable');
        $this->assert_true(is_callable($routes['bitcore/v1/context']['callback'] ?? null), 'context_create_callback_callable');
    }

    private function test_context_search_merges_bithub_and_bitwiki_results(): void {
        bitcore_knowledge_graph_test_reset();
        $api = new \BITCORE\Rest\Knowledge_Api();
        if (!method_exists($api, 'search_context')) {
            $this->assert_true(false, 'search_context_method_exists');
            return;
        }
        $this->assert_true(true, 'search_context_method_exists');

        $response = $api->search_context(new WP_REST_Request(['query' => 'prompt', 'limit' => 10]));
        $this->assert_true($response instanceof WP_REST_Response, 'context_search_returns_rest_response');
        $this->assert_same(200, $response->get_status(), 'context_search_status_200');

        $data = $response->get_data();
        $this->assert_true(is_array($data), 'context_search_data_array');
        $this->assert_true(isset($data['items']) && is_array($data['items']), 'context_search_items_array');
        $this->assert_true(count($data['items']) >= 2, 'context_search_merges_two_sources');
        $this->assert_same('prompt', $data['query'] ?? null, 'context_search_echoes_query');
        $sources = array_values(array_unique(array_map(fn($item) => $item['source'] ?? '', $data['items'])));
        sort($sources);
        $this->assert_same(['bithub', 'bitwiki'], $sources, 'context_search_sources_bithub_and_bitwiki');
    }

    private function test_context_create_returns_private_context_record(): void {
        bitcore_knowledge_graph_test_reset();
        $api = new \BITCORE\Rest\Knowledge_Api();
        if (!method_exists($api, 'create_context')) {
            $this->assert_true(false, 'create_context_method_exists');
            return;
        }
        $this->assert_true(true, 'create_context_method_exists');

        $response = $api->create_context(new WP_REST_Request([
            'title' => 'Agent Context',
            'content' => 'Context body',
            'tags' => ['agent', 'context'],
            'session_id' => 'session-1',
        ]));
        $this->assert_true($response instanceof WP_REST_Response, 'context_create_returns_rest_response');
        $this->assert_same(201, $response->get_status(), 'context_create_status_201');
        $data = $response->get_data();
        $this->assert_true((bool) ($data['success'] ?? false), 'context_create_success_true');
        $this->assert_same('private', $data['context']['status'] ?? null, 'context_create_status_private');
        $this->assert_same('context', $data['context']['type'] ?? null, 'context_create_type_context');
    }


    private function test_create_item_returns_created_status_and_private_record(): void {
        bitcore_knowledge_graph_test_reset();
        $api = new \BITCORE\Rest\Knowledge_Api();

        $response = $api->create_item(new WP_REST_Request([
            'title' => 'Knowledge Item',
            'content' => 'Knowledge body',
            'content_type' => 'markdown',
            'tags' => ['knowledge', 'artifact'],
            'session_id' => 'session-2',
        ]));

        $this->assert_true($response instanceof WP_REST_Response, 'knowledge_create_item_returns_rest_response');
        $this->assert_same(201, $response->get_status(), 'knowledge_create_item_status_201');
        $data = $response->get_data();
        $this->assert_true((bool) ($data['success'] ?? false), 'knowledge_create_item_success_true');
        $this->assert_same('private', $data['status'] ?? null, 'knowledge_create_item_status_private');
        $this->assert_same(1, $data['id'] ?? null, 'knowledge_create_item_id_inserted');
    }

    private function test_core_bootstrap_loads_knowledge_api(): void {
        // Post-split: the knowledge-api require and instantiation live in the
        // core init trait; read it alongside the core composition file so the
        // contract still proves core bootstrap loads the Knowledge API.
        $core = (string) file_get_contents(BITCORE_PATH . 'classes/core.php')
            . (string) file_get_contents(BITCORE_PATH . 'classes/traits/trait-core-init.php');
        $this->assert_true(is_string($core), 'core_file_readable');
        $this->assert_true(str_contains($core, "rest/class-knowledge-api.php"), 'core_requires_knowledge_api');
        $this->assert_true(str_contains($core, 'new \\BITCORE\\Rest\\Knowledge_Api') || str_contains($core, 'new \BITCORE\Rest\Knowledge_Api'), 'core_instantiates_knowledge_api');
    }

    private function assert_true(bool $condition, string $label): void {
        if ($condition) { $this->passed++; return; }
        $this->failed++;
        fwrite(STDERR, "FAIL: {$label}\n");
    }

    private function assert_same(mixed $expected, mixed $actual, string $label): void {
        if ($expected === $actual) { $this->passed++; return; }
        $this->failed++;
        fwrite(STDERR, "FAIL: {$label}; expected=" . var_export($expected, true) . ' actual=' . var_export($actual, true) . "\n");
    }

    private function report(): void {
        echo "KnowledgeApiGraphContractStandaloneTest: passed={$this->passed} failed={$this->failed}\n";
        if ($this->failed > 0) { exit(1); }
    }
}

(new KnowledgeApiGraphContractStandaloneTest())->run();
