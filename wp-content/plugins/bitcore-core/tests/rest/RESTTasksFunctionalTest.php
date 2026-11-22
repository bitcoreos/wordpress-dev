<?php
declare(strict_types=1);

/**
 * Functional standalone test for REST_Tasks.
 * No WordPress bootstrap, DB writes, network calls, or deployment.
 */

define('ABSPATH', dirname(__DIR__, 2));

$GLOBALS['routes'] = [];
$GLOBALS['logged_in'] = false;
$GLOBALS['current_user_id'] = 42;
$GLOBALS['inserted_posts'] = [];
$GLOBALS['updated_posts'] = [];
$GLOBALS['deleted_posts'] = [];
$GLOBALS['meta_updates'] = [];
$GLOBALS['term_sets'] = [];
$GLOBALS['actions'] = [];
$GLOBALS['posts_by_id'] = [];
$GLOBALS['post_meta'] = [];
$GLOBALS['object_terms'] = [];
$GLOBALS['wp_query_posts'] = [];
$GLOBALS['wp_query_total'] = 0;
$GLOBALS['last_wp_query_args'] = [];
$GLOBALS['next_insert_result'] = 777;
$GLOBALS['next_update_result'] = 1;
$GLOBALS['next_delete_result'] = true;

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
    public function get_error_data(): array { return $this->data; }
}
class WP_REST_Server { public const CREATABLE = 'POST'; public const EDITABLE = 'PUT'; }
class WP_REST_Response {
    private mixed $data;
    public function __construct($data) { $this->data = $data; }
    public function get_data() { return $this->data; }
}
class WP_Query {
    public array $posts;
    public int $found_posts;
    public function __construct(array $args) {
        $GLOBALS['last_wp_query_args'] = $args;
        $this->posts = $GLOBALS['wp_query_posts'];
        $this->found_posts = $GLOBALS['wp_query_total'];
    }
}
class FakeRequest implements ArrayAccess {
    private array $params;
    private array $json;
    public function __construct(array $params = [], array $json = []) {
        $this->params = $params;
        $this->json = $json;
    }
    public function get_params(): array { return $this->params; }
    public function get_json_params(): array { return $this->json; }
    public function offsetExists($offset): bool { return array_key_exists($offset, $this->params); }
    public function offsetGet($offset): mixed { return $this->params[$offset] ?? null; }
    public function offsetSet($offset, $value): void { $this->params[$offset] = $value; }
    public function offsetUnset($offset): void { unset($this->params[$offset]); }
}

function register_rest_route($namespace, $route, $args): void { $GLOBALS['routes'][] = compact('namespace', 'route', 'args'); }
function rest_ensure_response($data) { return $data instanceof WP_REST_Response ? $data : new WP_REST_Response($data); }
function is_user_logged_in(): bool { return (bool) $GLOBALS['logged_in']; }
function get_current_user_id(): int { return (int) $GLOBALS['current_user_id']; }
function sanitize_text_field($value): string { return trim(strip_tags((string) $value)); }
function sanitize_textarea_field($value): string { return trim(strip_tags((string) $value)); }
function wp_unslash($value) { return $value; }
function is_wp_error($value): bool { return $value instanceof WP_Error; }
function __(string $text, string $domain = ''): string { return $text; }
function wp_insert_post(array $post) { $GLOBALS['inserted_posts'][] = $post; return $GLOBALS['next_insert_result']; }
function update_post_meta($id, $key, $value): void { $GLOBALS['meta_updates'][] = compact('id', 'key', 'value'); $GLOBALS['post_meta'][$id][$key] = $value; }
function wp_set_object_terms($id, $terms, $taxonomy): void { $GLOBALS['term_sets'][] = compact('id', 'terms', 'taxonomy'); }
function get_post($id) { return $GLOBALS['posts_by_id'][$id] ?? null; }
function wp_update_post(array $post) { $GLOBALS['updated_posts'][] = $post; return $GLOBALS['next_update_result']; }
function wp_delete_post($id, $force = false) { $GLOBALS['deleted_posts'][] = compact('id', 'force'); return $GLOBALS['next_delete_result']; }
function do_action($hook, $arg = null): void { $GLOBALS['actions'][] = compact('hook', 'arg'); }
function get_post_meta($id, $key, $single = true) { return $GLOBALS['post_meta'][$id][$key] ?? ''; }
function wp_get_object_terms($id, $taxonomy, $args = []) { return $GLOBALS['object_terms'][$id][$taxonomy] ?? []; }

require_once dirname(__DIR__, 2) . '/rest/class-rest-tasks.php';

final class RESTTasksFunctionalTest {
    private int $passed = 0;
    private int $failed = 0;
    private REST_Tasks $api;

    public function __construct() { $this->api = new REST_Tasks(); }

    public function run(): int {
        $this->test_register_routes_contract();
        $this->test_get_items_builds_canonical_agent_task_query();
        $this->test_get_items_filters_by_sanitized_task_type_without_dropping_status();
        $this->test_get_items_clamps_invalid_pagination();
        $this->test_get_item_rejects_wrong_post_type();
        $this->test_prepare_response_defaults_and_meta();
        $this->test_create_item_persists_canonical_task();
        $this->test_create_item_persists_supplied_status_and_task_type();
        $this->test_create_item_rejects_missing_title();
        $this->test_create_item_rejects_zero_insert_result();
        $this->test_update_item_persists_mutations();
        $this->test_update_item_persists_task_type_and_params();
        $this->test_update_item_rejects_blank_title();
        $this->test_update_item_rejects_failed_post_update();
        $this->test_delete_item_success_and_failure();
        $this->test_dispatch_task_sets_doing_and_fires_action();
        $this->test_permission_gates_follow_login_state();
        return $this->report();
    }

    private function reset_state(): void {
        foreach (['routes','inserted_posts','updated_posts','deleted_posts','meta_updates','term_sets','actions','posts_by_id','post_meta','object_terms','wp_query_posts','last_wp_query_args'] as $key) {
            $GLOBALS[$key] = [];
        }
        $GLOBALS['wp_query_total'] = 0;
        $GLOBALS['next_insert_result'] = 777;
        $GLOBALS['next_update_result'] = 1;
        $GLOBALS['next_delete_result'] = true;
        $GLOBALS['logged_in'] = false;
    }

    private function post(int $id, string $type = 'agent_task'): object {
        return (object) [
            'ID' => $id,
            'post_type' => $type,
            'post_title' => 'Task ' . $id,
            'post_content' => 'Description ' . $id,
            'post_author' => 42,
            'post_date' => '2026-05-26 12:00:00',
            'post_modified' => '2026-05-26 12:30:00',
        ];
    }

    private function data($response): array { return $response instanceof WP_REST_Response ? $response->get_data() : []; }

    private function test_register_routes_contract(): void {
        $this->reset_state();
        $this->api->register_routes();
        $this->assert_same(6, count($GLOBALS['routes']), 'registers_six_task_routes');
        $routes = array_map(fn($r) => $r['namespace'] . $r['route'] . ':' . $r['args']['methods'], $GLOBALS['routes']);
        foreach (['bitcore/v1/tasks:GET','bitcore/v1/tasks/(?P<id>\\d+):GET','bitcore/v1/tasks:POST','bitcore/v1/tasks/(?P<id>\\d+):PUT','bitcore/v1/tasks/(?P<id>\\d+):DELETE','bitcore/v1/tasks/(?P<id>\\d+)/dispatch:POST'] as $expected) {
            $this->assert_true(in_array($expected, $routes, true), 'route_' . preg_replace('/\W+/', '_', $expected));
        }
    }

    private function test_get_items_builds_canonical_agent_task_query(): void {
        $this->reset_state();
        $GLOBALS['wp_query_posts'] = [$this->post(10)];
        $GLOBALS['wp_query_total'] = 5;
        $response = $this->api->get_items(new FakeRequest(['per_page' => 2, 'page' => 3, 'status' => '<b>doing</b>', 'assigned_to' => 'aria']));
        $args = $GLOBALS['last_wp_query_args'];
        $this->assert_same('agent_task', $args['post_type'], 'get_items_uses_agent_task');
        $this->assert_same(2, $args['posts_per_page'], 'get_items_per_page');
        $this->assert_same(4, $args['offset'], 'get_items_offset');
        $this->assert_same('doing', $args['tax_query'][0]['terms'], 'get_items_sanitizes_status');
        $this->assert_same('_bitcore_assigned_to', $args['meta_query'][0]['key'], 'get_items_assigned_meta_key');
        $this->assert_same(5, $this->data($response)['total'], 'get_items_total');
        $this->assert_same(3.0, $this->data($response)['pages'], 'get_items_pages');
    }

    private function test_get_items_filters_by_sanitized_task_type_without_dropping_status(): void {
        $this->reset_state();
        $this->api->get_items(new FakeRequest(['status' => '<b>doing</b>', 'task_type' => '<i>goal</i>']));
        $tax_query = $GLOBALS['last_wp_query_args']['tax_query'] ?? [];
        $by_taxonomy = [];
        foreach ($tax_query as $entry) {
            if (is_array($entry) && isset($entry['taxonomy'])) {
                $by_taxonomy[$entry['taxonomy']] = $entry;
            }
        }
        $this->assert_same('doing', $by_taxonomy['task_status']['terms'] ?? null, 'get_items_keeps_status_filter');
        $this->assert_same('goal', $by_taxonomy['task_type']['terms'] ?? null, 'get_items_sanitizes_task_type_filter');
        $this->assert_same('slug', $by_taxonomy['task_type']['field'] ?? null, 'get_items_task_type_uses_slug_field');
    }

    private function test_get_items_clamps_invalid_pagination(): void {
        $this->reset_state();
        $GLOBALS['wp_query_total'] = 1;

        try {
            $response = $this->api->get_items(new FakeRequest(['per_page' => 0, 'page' => -3]));
            $data = $this->data($response);
            $args = $GLOBALS['last_wp_query_args'];
            $this->assert_same(1, $args['posts_per_page'], 'get_items_clamps_per_page_minimum');
            $this->assert_same(0, $args['offset'], 'get_items_clamps_page_offset_minimum');
            $this->assert_same(1.0, $data['pages'], 'get_items_pages_after_clamp');
        } catch (Throwable $e) {
            $this->fail('get_items_invalid_pagination_no_throw actual=' . get_class($e));
        }
    }

    private function test_get_item_rejects_wrong_post_type(): void {
        $this->reset_state();
        $GLOBALS['posts_by_id'][9] = $this->post(9, 'bitcore_task');
        $error = $this->api->get_item(new FakeRequest(['id' => 9]));
        $this->assert_true($error instanceof WP_Error, 'get_item_wrong_type_returns_wp_error');
        $this->assert_same('not_found', $error->get_error_code(), 'get_item_wrong_type_error_code');
    }

    private function test_prepare_response_defaults_and_meta(): void {
        $this->reset_state();
        $GLOBALS['posts_by_id'][11] = $this->post(11);
        $GLOBALS['object_terms'][11]['task_status'] = ['doing'];
        $GLOBALS['object_terms'][11]['task_type'] = ['goal'];
        $GLOBALS['object_terms'][11]['bitcore_channel'] = ['personal'];
        $GLOBALS['post_meta'][11]['_bitcore_priority'] = 'high';
        $GLOBALS['post_meta'][11]['_bitcore_assigned_to'] = 'forge';
        $GLOBALS['post_meta'][11]['_bitcore_params'] = '{"x":1}';
        $data = $this->data($this->api->get_item(new FakeRequest(['id' => 11])))['data'];
        $this->assert_same('doing', $data['status'], 'response_status_term');
        $this->assert_same('goal', $data['task_type'], 'response_task_type_term');
        $this->assert_same('forge', $data['assigned_to'], 'response_assigned_to_meta');
        $this->assert_same(['x' => 1], $data['params'], 'response_json_params_decoded');
    }

    private function test_create_item_persists_canonical_task(): void {
        $this->reset_state();
        $response = $this->api->create_item(new FakeRequest([], ['title' => '<b>Build</b>', 'description' => '<i>Ship</i>', 'priority' => 'high', 'assigned_to' => 'aria', 'params' => '{"ok":true}']));
        $post = $GLOBALS['inserted_posts'][0];
        $this->assert_same('Build', $post['post_title'], 'create_sanitizes_title');
        $this->assert_same('agent_task', $post['post_type'], 'create_uses_agent_task');
        $this->assert_same(42, $post['post_author'], 'create_uses_current_user');
        $this->assert_same(['success' => true, 'data' => ['id' => 777]], $this->data($response), 'create_response');
        $this->assert_true(in_array(['id' => 777, 'terms' => 'inbox', 'taxonomy' => 'task_status'], $GLOBALS['term_sets'], true), 'create_default_status');
        $this->assert_true(in_array(['id' => 777, 'terms' => 'task', 'taxonomy' => 'task_type'], $GLOBALS['term_sets'], true), 'create_default_type');
    }


    private function test_create_item_persists_supplied_status_and_task_type(): void {
        $this->reset_state();
        $response = $this->api->create_item(new FakeRequest([], [
            'title' => 'Unified goal',
            'status' => '<b>doing</b>',
            'task_type' => '<i>goal</i>',
        ]));
        $this->assert_same(['success' => true, 'data' => ['id' => 777]], $this->data($response), 'create_custom_status_type_response');
        $this->assert_same([
            ['id' => 777, 'terms' => 'doing', 'taxonomy' => 'task_status'],
            ['id' => 777, 'terms' => 'goal', 'taxonomy' => 'task_type'],
        ], $GLOBALS['term_sets'], 'create_uses_supplied_status_and_task_type_only');
    }
    private function test_create_item_rejects_missing_title(): void {
        $this->reset_state();
        $error = $this->api->create_item(new FakeRequest([], ['description' => 'No title']));
        $this->assert_true($error instanceof WP_Error, 'create_missing_title_returns_wp_error');
        $this->assert_same([], $GLOBALS['inserted_posts'], 'create_missing_title_does_not_insert');
    }

    private function test_create_item_rejects_zero_insert_result(): void {
        $this->reset_state();
        $GLOBALS['next_insert_result'] = 0;
        $error = $this->api->create_item(new FakeRequest([], ['title' => 'Will fail']));
        $this->assert_true($error instanceof WP_Error, 'create_zero_insert_returns_wp_error');
        $this->assert_same('create_failed', $error instanceof WP_Error ? $error->get_error_code() : '', 'create_zero_insert_error_code');
        $this->assert_same([], $GLOBALS['term_sets'], 'create_zero_insert_does_not_set_terms');
    }

    private function test_update_item_persists_mutations(): void {
        $this->reset_state();
        $GLOBALS['posts_by_id'][12] = $this->post(12);
        $response = $this->api->update_item(new FakeRequest(['id' => 12], ['title' => 'New', 'description' => 'Body', 'status' => 'done', 'priority' => 'low', 'assigned_to' => 'muse']));
        $this->assert_same(['success' => true], $this->data($response), 'update_success');
        $this->assert_same(['ID' => 12, 'post_title' => 'New', 'post_content' => 'Body'], $GLOBALS['updated_posts'][0], 'update_post_payload');
        $this->assert_true(in_array(['id' => 12, 'terms' => 'done', 'taxonomy' => 'task_status'], $GLOBALS['term_sets'], true), 'update_status_term');
    }


    private function test_update_item_persists_task_type_and_params(): void {
        $this->reset_state();
        $GLOBALS['posts_by_id'][17] = $this->post(17);
        $response = $this->api->update_item(new FakeRequest(['id' => 17], [
            'task_type' => '<b>milestone</b>',
            'params' => '{"scope":"p2.6"}',
        ]));
        $this->assert_same(['success' => true], $this->data($response), 'update_task_type_params_success');
        $this->assert_same([
            ['id' => 17, 'terms' => 'milestone', 'taxonomy' => 'task_type'],
        ], $GLOBALS['term_sets'], 'update_task_type_term');
        $this->assert_same([
            ['id' => 17, 'key' => '_bitcore_params', 'value' => '{"scope":"p2.6"}'],
        ], $GLOBALS['meta_updates'], 'update_params_meta');
    }
    private function test_update_item_rejects_blank_title(): void {
        $this->reset_state();
        $GLOBALS['posts_by_id'][15] = $this->post(15);
        $error = $this->api->update_item(new FakeRequest(['id' => 15], ['title' => '<b></b>']));
        $this->assert_true($error instanceof WP_Error, 'update_blank_title_returns_wp_error');
        $this->assert_same('invalid_title', $error instanceof WP_Error ? $error->get_error_code() : '', 'update_blank_title_error_code');
        $this->assert_same([], $GLOBALS['updated_posts'], 'update_blank_title_does_not_update_post');
    }


    private function test_update_item_rejects_failed_post_update(): void {
        $this->reset_state();
        $GLOBALS['posts_by_id'][16] = $this->post(16);
        $GLOBALS['next_update_result'] = 0;
        $error = $this->api->update_item(new FakeRequest(['id' => 16], [
            'title' => 'Broken',
            'description' => 'Should not partially apply',
            'status' => 'done',
            'priority' => 'high',
            'assigned_to' => 'aria',
        ]));
        $this->assert_true($error instanceof WP_Error, 'update_failed_post_update_returns_wp_error');
        $this->assert_same('update_failed', $error instanceof WP_Error ? $error->get_error_code() : '', 'update_failed_post_update_error_code');
        $this->assert_same([], $GLOBALS['term_sets'], 'update_failed_post_update_no_terms');
        $this->assert_same([], $GLOBALS['meta_updates'], 'update_failed_post_update_no_meta');
    }
    private function test_delete_item_success_and_failure(): void {
        $this->reset_state();
        $GLOBALS['posts_by_id'][13] = $this->post(13);
        $this->assert_same(['success' => true], $this->data($this->api->delete_item(new FakeRequest(['id' => 13]))), 'delete_success');
        $GLOBALS['next_delete_result'] = false;
        $error = $this->api->delete_item(new FakeRequest(['id' => 13]));
        $this->assert_same('delete_failed', $error->get_error_code(), 'delete_failed_error_code');
    }

    private function test_dispatch_task_sets_doing_and_fires_action(): void {
        $this->reset_state();
        $GLOBALS['posts_by_id'][14] = $this->post(14);
        $response = $this->api->dispatch_task(new FakeRequest(['id' => 14]));
        $this->assert_same(true, $this->data($response)['success'], 'dispatch_success');
        $this->assert_true(in_array(['id' => 14, 'terms' => 'doing', 'taxonomy' => 'task_status'], $GLOBALS['term_sets'], true), 'dispatch_sets_doing');
        $this->assert_same('bitcore_task_dispatch', $GLOBALS['actions'][0]['hook'], 'dispatch_action_hook');
    }

    private function test_permission_gates_follow_login_state(): void {
        $this->reset_state();
        $this->assert_same(false, $this->api->create_item_permissions_check(new FakeRequest()), 'create_permission_false_when_logged_out');
        $GLOBALS['logged_in'] = true;
        $this->assert_same(true, $this->api->create_item_permissions_check(new FakeRequest()), 'create_permission_true_when_logged_in');
        $this->assert_same(true, $this->api->dispatch_item_permissions_check(new FakeRequest()), 'dispatch_permission_true_when_logged_in');
    }

    private function assert_true(bool $condition, string $name): void { $condition ? $this->pass($name) : $this->fail($name); }
    private function assert_same($expected, $actual, string $name): void { $expected === $actual ? $this->pass($name) : $this->fail($name . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true)); }
    private function pass(string $name): void { $this->passed++; echo "PASS {$name}\n"; }
    private function fail(string $name): void { $this->failed++; echo "FAIL {$name}\n"; }
    private function report(): int { echo "\nAssertions passed: {$this->passed}\nAssertions failed: {$this->failed}\n"; return $this->failed === 0 ? 0 : 1; }
}

exit((new RESTTasksFunctionalTest())->run());
