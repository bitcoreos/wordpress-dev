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

// rest/traits/trait-eliza-auth.php resolves the credits manager at
// rest/infrastructure/class-credits.php (wrong depth for the real file).
// Load the real class up front so its guarded class_exists() check succeeds.
require_once dirname(__DIR__, 2) . '/infrastructure/class-credits.php';

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

require_once __DIR__ . '/trait-eliza-api-message-contract-cases.php';

final class ElizaApiMessageContractStandaloneTest {
    private int $passed = 0;
    private int $failed = 0;

    use Eliza_Api_Message_Contract_Cases;

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
