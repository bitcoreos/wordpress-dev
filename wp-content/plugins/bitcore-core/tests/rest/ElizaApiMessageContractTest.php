<?php
/**
 * Standalone contract tests for BITCORE_Rest_Eliza_Api::send_message().
 *
 * Run with:
 *   php tests/rest/ElizaApiMessageContractTest.php
 *
 * These tests intentionally avoid live WordPress, network, database, and ElizaOS.
 * They assert observable bridge behavior: request path, payload contract, response
 * channel tracking, and user-meta side effects.
 */

declare(strict_types=1);

define('ABSPATH', __DIR__);

$GLOBALS['bitcore_test_state'] = [];

final class WP_Error {
    private string $code;
    private string $message;
    private array $data;

    public function __construct(string $code = '', string $message = '', array $data = []) {
        $this->code    = $code;
        $this->message = $message;
        $this->data    = $data;
    }

    public function get_error_code(): string {
        return $this->code;
    }

    public function get_error_message(): string {
        return $this->message;
    }

    public function get_error_data(): array {
        return $this->data;
    }
}

final class WP_REST_Response {
    private mixed $data;
    private int $status;
    private array $headers = [];

    public function __construct(mixed $data = null, int $status = 200) {
        $this->data   = $data;
        $this->status = $status;
    }

    public function get_data(): mixed {
        return $this->data;
    }

    public function get_status(): int {
        return $this->status;
    }

    public function header(string $key, string $value): void {
        $this->headers[$key] = $value;
    }
}

final class WP_REST_Request implements ArrayAccess {
    private array $json;
    private array $params;

    public function __construct(array $json = [], array $params = []) {
        $this->json   = $json;
        $this->params = $params;
    }

    public function get_json_params(): array {
        return $this->json;
    }

    public function get_route(): string {
        return '/bitcore/v1/eliza/chat';
    }

    public function offsetExists(mixed $offset): bool {
        return array_key_exists((string) $offset, $this->params);
    }

    public function offsetGet(mixed $offset): mixed {
        return $this->params[(string) $offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void {
        $this->params[(string) $offset] = $value;
    }

    public function offsetUnset(mixed $offset): void {
        unset($this->params[(string) $offset]);
    }
}

function bitcore_test_reset_state(int $current_user_id = 42): void {
    $GLOBALS['bitcore_test_state'] = [
        'current_user_id'  => $current_user_id,
        'options'          => [],
        'user_meta'        => [],
        'meta_updates'     => [],
        'remote_requests'  => [],
        'routes'           => [],
        'filters'          => [],
        'uuid_counter'     => 0,
        'remote_response_code' => 201,
        'remote_response_body' => ['accepted' => true],
    ];
    unset($_SERVER['HTTP_X_PAYMENT']);
    unset($_SERVER['HTTP_X_API_KEY']);
}

function __(string $text): string { return $text; }
function get_option(string $key, mixed $default = false): mixed { return $GLOBALS['bitcore_test_state']['options'][$key] ?? $default; }
function register_rest_route(string $namespace, string $route, array $args): void { $GLOBALS['bitcore_test_state']['routes'][] = [$namespace, $route, $args]; }
function add_filter(string $hook, mixed $callback, int $priority = 10, int $accepted_args = 1): void { $GLOBALS['bitcore_test_state']['filters'][] = [$hook, $priority, $accepted_args]; }
function apply_filters(string $hook, mixed $value, mixed ...$args): mixed { return $value; }
function is_user_logged_in(): bool { return $GLOBALS['bitcore_test_state']['current_user_id'] > 0; }
function current_user_can(string $capability): bool { return false; }
function get_current_user_id(): int { return $GLOBALS['bitcore_test_state']['current_user_id']; }
function sanitize_text_field(mixed $value): string { return trim(strip_tags((string) $value)); }
function absint(mixed $value): int { return abs((int) $value); }
function wp_unslash(mixed $value): mixed { return is_string($value) ? stripslashes($value) : $value; }
function wp_kses_post(mixed $value): string { return (string) $value; }
function wp_json_encode(mixed $value): string { return json_encode($value, JSON_UNESCAPED_SLASHES); }
function is_wp_error(mixed $value): bool { return $value instanceof WP_Error; }
function wp_is_uuid(mixed $uuid): bool { return is_string($uuid) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid) === 1; }
function wp_generate_uuid4(): string {
    $GLOBALS['bitcore_test_state']['uuid_counter']++;
    return sprintf('00000000-0000-4000-8000-%012d', $GLOBALS['bitcore_test_state']['uuid_counter']);
}
function get_user_meta(int $user_id, string $key, bool $single = false): mixed {
    return $GLOBALS['bitcore_test_state']['user_meta'][$user_id][$key] ?? '';
}
function update_user_meta(int $user_id, string $key, mixed $value): void {
    $GLOBALS['bitcore_test_state']['meta_updates'][] = [$user_id, $key, $value];
    $GLOBALS['bitcore_test_state']['user_meta'][$user_id][$key] = $value;
}
function delete_user_meta(int $user_id, string $key): void { unset($GLOBALS['bitcore_test_state']['user_meta'][$user_id][$key]); }
function wp_remote_request(string $url, array $args): array {
    $GLOBALS['bitcore_test_state']['remote_requests'][] = ['url' => $url, 'args' => $args];
    if (!empty($GLOBALS['bitcore_test_state']['remote_response_queue'])) {
        $next = array_shift($GLOBALS['bitcore_test_state']['remote_response_queue']);
        return [
            'response' => ['code' => (int) ($next['code'] ?? 201)],
            'body'     => json_encode($next['body'] ?? ['accepted' => true], JSON_UNESCAPED_SLASHES),
        ];
    }
    return [
        'response' => ['code' => (int) ($GLOBALS['bitcore_test_state']['remote_response_code'] ?? 201)],
        'body'     => json_encode($GLOBALS['bitcore_test_state']['remote_response_body'] ?? ['accepted' => true], JSON_UNESCAPED_SLASHES),
    ];
}
function wp_remote_retrieve_response_code(array $response): int { return (int) ($response['response']['code'] ?? 0); }
function wp_remote_retrieve_body(array $response): string { return (string) ($response['body'] ?? ''); }
function current_time(string $type = 'mysql'): string { return '2026-05-28T15:30:11+00:00'; }

require_once dirname(__DIR__, 2) . '/rest/class-eliza-api.php';

final class ElizaApiMessageContractStandaloneTest {
    private int $passed = 0;
    private int $failed = 0;

    public function run(): void {
        $this->test_constructor_registers_required_eliza_routes();
        $this->test_check_permission_accepts_valid_inbound_api_key_for_api_access();
        $this->test_check_permission_rejects_invalid_or_empty_inbound_api_key();
        $this->test_check_anonymous_access_accepts_valid_inbound_api_key_for_chat_routes();
        $this->test_check_permission_accepts_logged_in_user_without_api_key();
        $this->test_logged_in_message_uses_eliza_sessions_api_contract();
        $this->test_chat_without_session_creates_session_then_sends_message();
        $this->test_proxy_omits_api_key_header_when_auth_token_not_configured();
        $this->test_proxy_sends_api_key_header_when_auth_token_configured();
        $this->test_anonymous_x402_author_uuid_is_deterministic_and_does_not_write_user_meta();
        $this->test_anonymous_without_payment_falls_back_to_channel_id_without_user_meta_write();
        $this->test_omitted_agent_id_defaults_to_configured_aria_and_sets_dm_participant_metadata();
        $this->test_explicit_agent_slug_resolves_to_configured_uuid();
        $this->test_logged_in_active_agent_fallback_is_used_when_agent_id_omitted();
        $this->test_invalid_agent_id_omits_routing_metadata_without_arbitrary_participant_injection();
        $this->test_empty_message_returns_error_and_does_not_call_remote();
        $this->test_activate_agent_missing_id_returns_wp_error_not_type_error();
        $this->test_deactivate_agent_missing_id_returns_wp_error_not_type_error();
        $this->test_delete_session_missing_id_returns_wp_error_not_type_error();
        $this->test_get_agents_returns_configured_fallback_when_upstream_agent_index_fails();
        $this->test_get_agents_success_passes_upstream_response_without_fallback();
        $this->test_get_sessions_proxy_error_returns_wp_error_not_type_error();
        $this->test_create_session_proxy_error_returns_wp_error_not_type_error();
        $this->test_session_messages_route_proxies_documented_history_endpoint();
        $this->test_send_message_marks_post_response_as_acknowledgement_not_agent_reply();
        $this->report();
    }


    private function test_check_permission_accepts_valid_inbound_api_key_for_api_access(): void {
        bitcore_test_reset_state(0);
        $GLOBALS['bitcore_test_state']['options']['bitcore_eliza_auth_token'] = 'configured-inbound-token';
        $_SERVER['HTTP_X_API_KEY'] = 'configured-inbound-token';

        $api = new BITCORE_Rest_Eliza_Api(new stdClass());

        $this->assert_true($api->check_permission(), 'valid_inbound_api_key_allows_basic_eliza_routes');
        $this->assert_same(0, $GLOBALS['bitcore_test_state']['current_user_id'], 'valid_inbound_api_key_does_not_fake_logged_in_user');
    }

    private function test_check_permission_rejects_invalid_or_empty_inbound_api_key(): void {
        bitcore_test_reset_state(0);
        $GLOBALS['bitcore_test_state']['options']['bitcore_eliza_auth_token'] = 'configured-inbound-token';
        $_SERVER['HTTP_X_API_KEY'] = 'wrong-token';

        $api = new BITCORE_Rest_Eliza_Api(new stdClass());
        $this->assert_true(!$api->check_permission(), 'invalid_inbound_api_key_rejected');

        bitcore_test_reset_state(0);
        $GLOBALS['bitcore_test_state']['options']['bitcore_eliza_auth_token'] = '';
        $_SERVER['HTTP_X_API_KEY'] = '';

        $api = new BITCORE_Rest_Eliza_Api(new stdClass());
        $this->assert_true(!$api->check_permission(), 'empty_configured_token_does_not_enable_anonymous_access');
    }

    private function test_check_anonymous_access_accepts_valid_inbound_api_key_for_chat_routes(): void {
        bitcore_test_reset_state(0);
        $GLOBALS['bitcore_test_state']['options']['bitcore_eliza_auth_token'] = 'configured-inbound-token';
        $GLOBALS['bitcore_test_state']['options']['bitcore_eliza_anonymous_access'] = false;
        $_SERVER['HTTP_X_API_KEY'] = 'configured-inbound-token';

        $api = new BITCORE_Rest_Eliza_Api(new stdClass());
        $result = $api->check_anonymous_access();

        $this->assert_true($result === true, 'valid_inbound_api_key_allows_chat_permission_path');
        $this->assert_same(0, $GLOBALS['bitcore_test_state']['current_user_id'], 'valid_inbound_api_key_chat_does_not_fake_logged_in_user');
    }

    private function test_check_permission_accepts_logged_in_user_without_api_key(): void {
        bitcore_test_reset_state(42);
        $api = new BITCORE_Rest_Eliza_Api(new stdClass());

        $this->assert_true($api->check_permission(), 'logged_in_user_still_allows_basic_eliza_routes');
    }


    private function test_constructor_registers_required_eliza_routes(): void {
        bitcore_test_reset_state(42);
        new BITCORE_Rest_Eliza_Api(new stdClass());

        $routes = [];
        foreach ($GLOBALS['bitcore_test_state']['routes'] as $route) {
            $routes[$route[0] . $route[1]] = $route[2];
        }

        $required = [
            'bitcore/v1/eliza/health',
            'bitcore/v1/eliza/agents',
            'bitcore/v1/eliza/agents/(?P<id>[a-zA-Z0-9_-]+)/activate',
            'bitcore/v1/eliza/agents/(?P<id>[a-zA-Z0-9_-]+)/deactivate',
            'bitcore/v1/eliza/chat',
            'bitcore/v1/eliza/sessions',
            'bitcore/v1/eliza/sessions/(?P<id>[a-zA-Z0-9_-]+)',
            'bitcore/v1/eliza/sessions/(?P<id>[a-zA-Z0-9_-]+)/messages',
            'bitcore/v1/eliza/rate-limit',
        ];

        foreach ($required as $route) {
            $this->assert_true(array_key_exists($route, $routes), 'route_registered_' . str_replace(['/', '(', ')', '?', '<', '>', '[', ']', '+', '*'], '_', $route));
        }

        $this->assert_same('GET', $routes['bitcore/v1/eliza/health']['methods'] ?? null, 'health_route_uses_get');
        $this->assert_same('POST', $routes['bitcore/v1/eliza/chat']['methods'] ?? null, 'chat_route_uses_post');
        $this->assert_same('DELETE', $routes['bitcore/v1/eliza/sessions/(?P<id>[a-zA-Z0-9_-]+)']['methods'] ?? null, 'delete_session_route_uses_delete');
        $this->assert_same('GET', $routes['bitcore/v1/eliza/sessions/(?P<id>[a-zA-Z0-9_-]+)/messages']['methods'] ?? null, 'session_messages_route_uses_get');
        $this->assert_true(is_array($routes['bitcore/v1/eliza/sessions'] ?? null), 'sessions_route_supports_multiple_methods');
    }

    private function test_logged_in_message_uses_eliza_sessions_api_contract(): void {
        bitcore_test_reset_state(42);
        $api = new BITCORE_Rest_Eliza_Api(new stdClass());
        $request = new WP_REST_Request([
            'message'   => 'hello aria',
            'sessionId' => 'session-123',
            'agentId'   => 'eac677dd-94c4-02ab-bfea-bc554e52b2a3',
        ]);

        $response = $api->send_message($request);
        $remote   = $GLOBALS['bitcore_test_state']['remote_requests'][0] ?? null;
        $body     = json_decode((string) ($remote['args']['body'] ?? ''), true);

        $this->assert_true($response instanceof WP_REST_Response, 'logged_in_returns_rest_response');
        $this->assert_same(201, $response->get_status(), 'logged_in_preserves_eliza_status');
        $this->assert_same('http://172.17.0.1:3000/api/messaging/sessions/session-123/messages', $remote['url'] ?? null, 'logged_in_uses_session_messages_endpoint');
        $this->assert_same('POST', $remote['args']['method'] ?? null, 'logged_in_uses_post');
        $this->assert_same('hello aria', $body['content'] ?? null, 'payload_content_matches_message');
        $this->assert_true(wp_is_uuid($body['metadata']['author_id'] ?? null), 'payload_metadata_author_id_is_uuid');
        $this->assert_same('ashi', $body['metadata']['platform'] ?? null, 'payload_metadata_platform_is_ashi');
        $this->assert_same('user_message', $body['metadata']['source_type'] ?? null, 'payload_metadata_source_type_is_user_message');
        $this->assert_same(['text' => 'hello aria'], $body['metadata']['raw_message'] ?? null, 'payload_metadata_raw_message_is_object');
        $this->assert_same('eac677dd-94c4-02ab-bfea-bc554e52b2a3', $body['metadata']['targetAgentId'] ?? null, 'payload_targetAgentId_uses_agent_uuid');
        $this->assert_true(!array_key_exists('channelId', $body), 'payload_does_not_use_channelId');
        $this->assert_true(!array_key_exists('message_server_id', $body), 'payload_does_not_use_message_server_id');
        $this->assert_true(!array_key_exists('transport', $body), 'payload_does_not_use_legacy_transport');
        $this->assert_same('session-123', $response->get_data()['session_id'] ?? null, 'response_exposes_session_id');
        $this->assert_same('session-123', $response->get_data()['channel_id'] ?? null, 'response_exposes_backward_compatible_channel_id');
        $this->assert_same(1, count($GLOBALS['bitcore_test_state']['meta_updates']), 'logged_in_creates_one_user_uuid_meta_update');
        $this->assert_same(42, $GLOBALS['bitcore_test_state']['meta_updates'][0][0], 'logged_in_uuid_meta_update_uses_real_user_id');
    }

    private function test_chat_without_session_creates_session_then_sends_message(): void {
        bitcore_test_reset_state(42);
        $GLOBALS['bitcore_test_state']['remote_response_queue'] = [
            ['code' => 201, 'body' => ['sessionId' => 'new-session-abc']],
            ['code' => 201, 'body' => ['id' => 'message-1', 'content' => 'hello new session']],
        ];
        $api = new BITCORE_Rest_Eliza_Api(new stdClass());

        $response = $api->send_message(new WP_REST_Request(['message' => 'hello new session', 'agentId' => 'aria']));

        $this->assert_true($response instanceof WP_REST_Response, 'no_session_returns_rest_response');
        $this->assert_same(2, count($GLOBALS['bitcore_test_state']['remote_requests']), 'no_session_makes_create_then_message_requests');

        $create = $GLOBALS['bitcore_test_state']['remote_requests'][0];
        $create_body = json_decode($create['args']['body'], true);
        $this->assert_same('http://172.17.0.1:3000/api/messaging/sessions', $create['url'] ?? null, 'no_session_creates_session_endpoint');
        $this->assert_same('eac677dd-94c4-02ab-bfea-bc554e52b2a3', $create_body['agentId'] ?? null, 'create_session_body_agentId');
        $this->assert_true(wp_is_uuid($create_body['userId'] ?? null), 'create_session_body_userId_is_uuid');
        $this->assert_same('ashi', $create_body['metadata']['platform'] ?? null, 'create_session_metadata_platform');
        $this->assert_same(60, $create_body['timeoutConfig']['timeoutMinutes'] ?? null, 'create_session_timeout_minutes');

        $message = $GLOBALS['bitcore_test_state']['remote_requests'][1];
        $message_body = json_decode($message['args']['body'], true);
        $this->assert_same('http://172.17.0.1:3000/api/messaging/sessions/new-session-abc/messages', $message['url'] ?? null, 'no_session_sends_to_created_session');
        $this->assert_same('hello new session', $message_body['content'] ?? null, 'no_session_message_content');
        $this->assert_same('new-session-abc', $response->get_data()['session_id'] ?? null, 'no_session_response_exposes_created_session');
    }

    private function test_proxy_omits_api_key_header_when_auth_token_not_configured(): void {
        bitcore_test_reset_state(42);
        $api = new BITCORE_Rest_Eliza_Api(new stdClass());

        $api->send_message(new WP_REST_Request(['message' => 'no configured token', 'sessionId' => 'session-no-token']));
        $headers = $GLOBALS['bitcore_test_state']['remote_requests'][0]['args']['headers'] ?? [];

        $this->assert_true(!array_key_exists('X-API-KEY', $headers), 'proxy_omits_x_api_key_when_token_missing');
    }

    private function test_proxy_sends_api_key_header_when_auth_token_configured(): void {
        bitcore_test_reset_state(42);
        $GLOBALS['bitcore_test_state']['options']['bitcore_eliza_auth_token'] = 'configured-token';
        $api = new BITCORE_Rest_Eliza_Api(new stdClass());

        $api->send_message(new WP_REST_Request(['message' => 'configured token', 'sessionId' => 'session-token']));
        $headers = $GLOBALS['bitcore_test_state']['remote_requests'][0]['args']['headers'] ?? [];

        $this->assert_same('configured-token', $headers['X-API-KEY'] ?? null, 'proxy_sends_configured_x_api_key');
    }

    private function test_anonymous_x402_author_uuid_is_deterministic_and_does_not_write_user_meta(): void {
        bitcore_test_reset_state(0);
        $_SERVER['HTTP_X_PAYMENT'] = 'x402-proof-token';
        $api = new BITCORE_Rest_Eliza_Api(new stdClass());

        $first = $api->send_message(new WP_REST_Request(['message' => 'paid hello', 'sessionId' => 'paid-session']));
        $first_body = json_decode($GLOBALS['bitcore_test_state']['remote_requests'][0]['args']['body'], true);
        $second = $api->send_message(new WP_REST_Request(['message' => 'paid hello again', 'sessionId' => 'paid-session']));
        $second_body = json_decode($GLOBALS['bitcore_test_state']['remote_requests'][1]['args']['body'], true);

        $this->assert_true($first instanceof WP_REST_Response && $second instanceof WP_REST_Response, 'anonymous_x402_returns_responses');
        $this->assert_true(wp_is_uuid($first_body['metadata']['author_id'] ?? null), 'anonymous_x402_author_id_is_uuid');
        $this->assert_same($first_body['metadata']['author_id'], $second_body['metadata']['author_id'], 'anonymous_x402_author_id_is_deterministic_for_payment');
        $this->assert_same([], $GLOBALS['bitcore_test_state']['meta_updates'], 'anonymous_x402_does_not_write_user_meta');
        $this->assert_same('0', $GLOBALS['bitcore_test_state']['remote_requests'][0]['args']['headers']['X-Entity-Id'] ?? null, 'anonymous_x402_entity_id_is_zero');
        $this->assert_same('x402-proof-token', $GLOBALS['bitcore_test_state']['remote_requests'][0]['args']['headers']['X-Payment'] ?? null, 'anonymous_x402_forwards_payment_header');
    }

    private function test_anonymous_without_payment_falls_back_to_channel_id_without_user_meta_write(): void {
        bitcore_test_reset_state(0);
        $api = new BITCORE_Rest_Eliza_Api(new stdClass());

        $api->send_message(new WP_REST_Request(['message' => 'session fallback', 'sessionId' => 'anon-session']));
        $body = json_decode($GLOBALS['bitcore_test_state']['remote_requests'][0]['args']['body'], true);

        $this->assert_true(wp_is_uuid($body['metadata']['author_id'] ?? null), 'anonymous_session_fallback_author_id_is_uuid');
        $this->assert_same([], $GLOBALS['bitcore_test_state']['meta_updates'], 'anonymous_channel_fallback_does_not_write_user_meta');
        $this->assert_true(!isset($GLOBALS['bitcore_test_state']['remote_requests'][0]['args']['headers']['X-Payment']), 'anonymous_channel_fallback_does_not_forward_missing_payment_header');
    }

    private function test_omitted_agent_id_defaults_to_configured_aria_and_sets_dm_participant_metadata(): void {
        bitcore_test_reset_state(42);
        $GLOBALS['bitcore_test_state']['options']['bitcore_eliza_agent_aria'] = 'eac677dd-94c4-02ab-bfea-bc554e52b2a3';
        $api = new BITCORE_Rest_Eliza_Api(new stdClass());

        $api->send_message(new WP_REST_Request(['message' => 'no agent', 'sessionId' => 'session-no-agent']));
        $body = json_decode($GLOBALS['bitcore_test_state']['remote_requests'][0]['args']['body'], true);

        $this->assert_same('eac677dd-94c4-02ab-bfea-bc554e52b2a3', $body['metadata']['targetAgentId'] ?? null, 'default_agent_sets_targetAgentId_for_routing');
    }

    private function test_explicit_agent_slug_resolves_to_configured_uuid(): void {
        bitcore_test_reset_state(42);
        $GLOBALS['bitcore_test_state']['options']['bitcore_eliza_agent_forge'] = '3ab11b75-ca36-0cee-a3d7-7bfcab3c27cd';
        $api = new BITCORE_Rest_Eliza_Api(new stdClass());

        $api->send_message(new WP_REST_Request(['message' => 'forge please', 'sessionId' => 'session-forge', 'agentId' => 'forge']));
        $body = json_decode($GLOBALS['bitcore_test_state']['remote_requests'][0]['args']['body'], true);

        $this->assert_same('3ab11b75-ca36-0cee-a3d7-7bfcab3c27cd', $body['metadata']['targetAgentId'] ?? null, 'explicit_slug_sets_targetAgentId');
    }

    private function test_logged_in_active_agent_fallback_is_used_when_agent_id_omitted(): void {
        bitcore_test_reset_state(42);
        $GLOBALS['bitcore_test_state']['user_meta'][42]['bitcore_eliza_active_agent'] = 'muse';
        $GLOBALS['bitcore_test_state']['options']['bitcore_eliza_agent_muse'] = 'cf0dee2b-9fc6-041b-83a0-90a644d26362';
        $api = new BITCORE_Rest_Eliza_Api(new stdClass());

        $api->send_message(new WP_REST_Request(['message' => 'active agent', 'sessionId' => 'session-active']));
        $body = json_decode($GLOBALS['bitcore_test_state']['remote_requests'][0]['args']['body'], true);

        $this->assert_same('cf0dee2b-9fc6-041b-83a0-90a644d26362', $body['metadata']['targetAgentId'] ?? null, 'active_agent_fallback_sets_targetAgentId');
    }

    private function test_invalid_agent_id_omits_routing_metadata_without_arbitrary_participant_injection(): void {
        bitcore_test_reset_state(42);
        $GLOBALS['bitcore_test_state']['options']['bitcore_eliza_agent_aria'] = '';
        $api = new BITCORE_Rest_Eliza_Api(new stdClass());

        $result = $api->send_message(new WP_REST_Request(['message' => 'invalid agent', 'sessionId' => 'session-invalid', 'agentId' => 'not-a-valid-agent-id']));

        $this->assert_true($result instanceof WP_Error, 'invalid_agent_returns_wp_error');
        if ($result instanceof WP_Error) {
            $this->assert_same('invalid_agent_id', $result->get_error_code(), 'invalid_agent_error_code');
        }
        $this->assert_same([], $GLOBALS['bitcore_test_state']['remote_requests'], 'invalid_agent_does_not_call_remote');
    }

    private function test_empty_message_returns_error_and_does_not_call_remote(): void {
        bitcore_test_reset_state(42);
        $api = new BITCORE_Rest_Eliza_Api(new stdClass());

        $response = $api->send_message(new WP_REST_Request(['message' => '']));

        $this->assert_true($response instanceof WP_Error, 'empty_message_returns_wp_error');
        $this->assert_same('no_message', $response->get_error_code(), 'empty_message_error_code');
        $this->assert_same([], $GLOBALS['bitcore_test_state']['remote_requests'], 'empty_message_does_not_call_remote');
    }


    private function test_activate_agent_missing_id_returns_wp_error_not_type_error(): void {
        bitcore_test_reset_state(42);
        $api = new BITCORE_Rest_Eliza_Api(new stdClass());
        $result = $this->capture_throwable(fn() => $api->activate_agent(new WP_REST_Request([], ['id' => ''])));

        $this->assert_true($result instanceof WP_Error, 'activate_missing_id_returns_wp_error_not_type_error');
        if ($result instanceof WP_Error) {
            $this->assert_same('missing_agent_id', $result->get_error_code(), 'activate_missing_id_error_code');
        }
    }

    private function test_deactivate_agent_missing_id_returns_wp_error_not_type_error(): void {
        bitcore_test_reset_state(42);
        $api = new BITCORE_Rest_Eliza_Api(new stdClass());
        $result = $this->capture_throwable(fn() => $api->deactivate_agent(new WP_REST_Request([], ['id' => ''])));

        $this->assert_true($result instanceof WP_Error, 'deactivate_missing_id_returns_wp_error_not_type_error');
        if ($result instanceof WP_Error) {
            $this->assert_same('missing_agent_id', $result->get_error_code(), 'deactivate_missing_id_error_code');
        }
    }

    private function test_delete_session_missing_id_returns_wp_error_not_type_error(): void {
        bitcore_test_reset_state(42);
        $api = new BITCORE_Rest_Eliza_Api(new stdClass());
        $result = $this->capture_throwable(fn() => $api->delete_session(new WP_REST_Request([], ['id' => ''])));

        $this->assert_true($result instanceof WP_Error, 'delete_session_missing_id_returns_wp_error_not_type_error');
        if ($result instanceof WP_Error) {
            $this->assert_same('missing_session_id', $result->get_error_code(), 'delete_session_missing_id_error_code');
        }
    }

    private function test_get_agents_returns_configured_fallback_when_upstream_agent_index_fails(): void {
        bitcore_test_reset_state(42);
        $GLOBALS['bitcore_test_state']['remote_response_code'] = 500;
        $GLOBALS['bitcore_test_state']['remote_response_body'] = [
            'success' => false,
            'error' => [
                'code' => '500',
                'message' => 'Error retrieving agents',
                'details' => 'Failed query: select "id", "name", "bio" from "agents"',
            ],
        ];
        $GLOBALS['bitcore_test_state']['options']['bitcore_eliza_agent_aria'] = 'aria-custom-uuid';
        $api = new BITCORE_Rest_Eliza_Api(new stdClass());
        $result = $this->capture_throwable(fn() => $api->get_agents());

        $this->assert_true($result instanceof WP_REST_Response, 'get_agents_fallback_returns_rest_response');
        if (!$result instanceof WP_REST_Response) {
            return;
        }

        $data = $result->get_data();
        $this->assert_same(200, $result->get_status(), 'get_agents_fallback_uses_200_for_ui_resilience');
        $this->assert_same('degraded', $data['status'] ?? null, 'get_agents_fallback_marks_degraded');
        $this->assert_same('wordpress_config', $data['source'] ?? null, 'get_agents_fallback_source_wordpress_config');
        $this->assert_same(500, $data['upstream_error']['status'] ?? null, 'get_agents_fallback_preserves_upstream_status');
        $this->assert_same('Error retrieving agents', $data['upstream_error']['message'] ?? null, 'get_agents_fallback_normalizes_nested_upstream_message');
        $this->assert_same('Failed query: select "id", "name", "bio" from "agents"', $data['upstream_error']['raw_error']['details'] ?? null, 'get_agents_fallback_preserves_raw_upstream_details');
        $this->assert_same(3, count($data['agents'] ?? []), 'get_agents_fallback_returns_three_agents');
        $this->assert_same('aria-custom-uuid', $data['agents'][0]['id'] ?? null, 'get_agents_fallback_uses_configured_aria_id');
        $this->assert_same('aria', $data['agents'][0]['slug'] ?? null, 'get_agents_fallback_sets_aria_slug');
        $this->assert_same('Aria', $data['agents'][0]['name'] ?? null, 'get_agents_fallback_sets_aria_name');
        $this->assert_same('unknown', $data['agents'][0]['status'] ?? null, 'get_agents_fallback_status_unknown_not_fake_active');
        $this->assert_same('forge', $data['agents'][1]['slug'] ?? null, 'get_agents_fallback_sets_forge_slug');
        $this->assert_same('muse', $data['agents'][2]['slug'] ?? null, 'get_agents_fallback_sets_muse_slug');
        $this->assert_same(1, count($GLOBALS['bitcore_test_state']['remote_requests']), 'get_agents_fallback_does_not_retry_or_fan_out');
    }

    private function test_get_agents_success_passes_upstream_response_without_fallback(): void {
        bitcore_test_reset_state(42);
        $GLOBALS['bitcore_test_state']['remote_response_code'] = 200;
        $GLOBALS['bitcore_test_state']['remote_response_body'] = [
            'agents' => [
                ['id' => 'live-aria', 'name' => 'Aria', 'status' => 'active'],
            ],
            'source' => 'elizaos',
        ];
        $api = new BITCORE_Rest_Eliza_Api(new stdClass());
        $result = $api->get_agents();

        $this->assert_true($result instanceof WP_REST_Response, 'get_agents_success_returns_rest_response');
        if (!$result instanceof WP_REST_Response) {
            return;
        }
        $data = $result->get_data();
        $this->assert_same(200, $result->get_status(), 'get_agents_success_preserves_status');
        $this->assert_same('elizaos', $data['source'] ?? null, 'get_agents_success_preserves_upstream_source');
        $this->assert_same('live-aria', $data['agents'][0]['id'] ?? null, 'get_agents_success_preserves_upstream_agent_id');
        $this->assert_true(!array_key_exists('upstream_error', $data), 'get_agents_success_does_not_add_fallback_error');
        $this->assert_same(1, count($GLOBALS['bitcore_test_state']['remote_requests']), 'get_agents_success_single_upstream_request');
    }

    private function test_get_sessions_proxy_error_returns_wp_error_not_type_error(): void {
        bitcore_test_reset_state(42);
        $GLOBALS['bitcore_test_state']['remote_response_code'] = 503;
        $GLOBALS['bitcore_test_state']['remote_response_body'] = ['error' => 'sessions unavailable'];
        $api = new BITCORE_Rest_Eliza_Api(new stdClass());
        $result = $this->capture_throwable(fn() => $api->get_sessions());

        $this->assert_true($result instanceof WP_Error, 'get_sessions_proxy_error_returns_wp_error_not_type_error');
        if ($result instanceof WP_Error) {
            $this->assert_same('eliza_error', $result->get_error_code(), 'get_sessions_proxy_error_code');
        }
    }

    private function test_create_session_proxy_error_returns_wp_error_not_type_error(): void {
        bitcore_test_reset_state(42);
        $GLOBALS['bitcore_test_state']['remote_response_code'] = 503;
        $GLOBALS['bitcore_test_state']['remote_response_body'] = ['error' => 'create session unavailable'];
        $api = new BITCORE_Rest_Eliza_Api(new stdClass());
        $result = $this->capture_throwable(fn() => $api->create_session(new WP_REST_Request(['agentId' => 'aria'])));

        $this->assert_true($result instanceof WP_Error, 'create_session_proxy_error_returns_wp_error_not_type_error');
        if ($result instanceof WP_Error) {
            $this->assert_same('eliza_error', $result->get_error_code(), 'create_session_proxy_error_code');
        }
    }

    private function test_session_messages_route_proxies_documented_history_endpoint(): void {
        bitcore_test_reset_state(42);
        $GLOBALS['bitcore_test_state']['remote_response_code'] = 200;
        $GLOBALS['bitcore_test_state']['remote_response_body'] = [
            'messages' => [
                ['id' => 'agent-message-1', 'content' => 'assistant reply', 'isAgent' => true],
            ],
            'hasMore' => false,
            'cursors' => ['after' => 1780428115051],
        ];
        $api = new BITCORE_Rest_Eliza_Api(new stdClass());
        $request = new WP_REST_Request([], ['id' => 'session-abc', 'limit' => 25, 'after' => 1780428115000]);
        $result = $api->get_session_messages($request);

        $this->assert_true($result instanceof WP_REST_Response, 'session_messages_returns_rest_response');
        $this->assert_same(200, $result->get_status(), 'session_messages_preserves_status');
        $this->assert_same('http://172.17.0.1:3000/api/messaging/sessions/session-abc/messages?limit=25&after=1780428115000', $GLOBALS['bitcore_test_state']['remote_requests'][0]['url'] ?? null, 'session_messages_uses_documented_history_endpoint_with_query');
        $this->assert_same('GET', $GLOBALS['bitcore_test_state']['remote_requests'][0]['args']['method'] ?? null, 'session_messages_uses_get');
        $this->assert_same('assistant reply', $result->get_data()['messages'][0]['content'] ?? null, 'session_messages_passes_agent_message_payload');
    }

    private function test_send_message_marks_post_response_as_acknowledgement_not_agent_reply(): void {
        bitcore_test_reset_state(42);
        $GLOBALS['bitcore_test_state']['remote_response_body'] = [
            'success' => true,
            'userMessage' => ['id' => 'user-message-1', 'content' => 'test', 'isAgent' => false],
            'sessionStatus' => ['wasRenewed' => true],
        ];
        $api = new BITCORE_Rest_Eliza_Api(new stdClass());
        $result = $api->send_message(new WP_REST_Request([
            'message' => 'test',
            'sessionId' => 'session-ack',
            'agentId' => 'aria',
        ]));

        $this->assert_true($result instanceof WP_REST_Response, 'send_ack_returns_rest_response');
        $data = $result instanceof WP_REST_Response ? $result->get_data() : [];
        $this->assert_same('acknowledgement', $data['response_type'] ?? null, 'send_ack_marks_response_type_acknowledgement');
        $this->assert_same(true, $data['reply_pending'] ?? null, 'send_ack_marks_reply_pending');
        $this->assert_same(true, $data['submitted_only'] ?? null, 'send_ack_marks_submitted_only');
        $this->assert_same('session-ack', $data['session_id'] ?? null, 'send_ack_keeps_session_id');
    }

    private function capture_throwable(callable $callback): mixed {
        try {
            return $callback();
        } catch (Throwable $throwable) {
            return $throwable;
        }
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

    private function assert_same(mixed $expected, mixed $actual, string $name): void {
        if ($expected === $actual) {
            $this->passed++;
            echo "PASS {$name}\n";
            return;
        }
        $this->failed++;
        echo "FAIL {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
    }

    private function report(): void {
        echo "\nAssertions passed: {$this->passed}\n";
        echo "Assertions failed: {$this->failed}\n";
        if ($this->failed > 0) {
            exit(1);
        }
    }
}

(new ElizaApiMessageContractStandaloneTest())->run();
