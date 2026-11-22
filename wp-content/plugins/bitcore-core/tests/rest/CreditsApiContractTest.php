<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);

define('BITCORE_TEST_ROOT', dirname(__DIR__, 2));

$GLOBALS['bitcore_credits_api_test'] = [];
function bitcore_credits_api_reset(): void {
    $GLOBALS['bitcore_credits_api_test'] = [
        'routes' => [],
        'options' => [],
        'user_meta' => [],
        'current_user_id' => 7,
        'logged_in' => true,
        'can_manage' => false,
        'now' => '2026-05-26 12:00:00',
        'x402_result' => false,
    ];
}
function register_rest_route(string $namespace, string $route, array $args): bool { $GLOBALS['bitcore_credits_api_test']['routes'][] = [$namespace, $route, $args]; return true; }
function rest_ensure_response(mixed $data): WP_REST_Response { return $data instanceof WP_REST_Response ? $data : new WP_REST_Response($data); }
function __return_true(): bool { return true; }
function is_user_logged_in(): bool { return (bool) $GLOBALS['bitcore_credits_api_test']['logged_in']; }
function current_user_can(string $cap): bool { return (bool) $GLOBALS['bitcore_credits_api_test']['can_manage']; }
function get_current_user_id(): int { return (int) $GLOBALS['bitcore_credits_api_test']['current_user_id']; }
function get_option(string $key, mixed $default = false): mixed { return $GLOBALS['bitcore_credits_api_test']['options'][$key] ?? $default; }
function update_option(string $key, mixed $value, bool $autoload = true): bool { $GLOBALS['bitcore_credits_api_test']['options'][$key] = $value; return true; }
function get_user_meta(int $user_id, string $key = '', bool $single = false): mixed { $value = $GLOBALS['bitcore_credits_api_test']['user_meta'][$user_id][$key] ?? ($single ? '' : []); return $single ? $value : [$value]; }
function update_user_meta(int $user_id, string $key, mixed $value): bool { $GLOBALS['bitcore_credits_api_test']['user_meta'][$user_id][$key] = $value; return true; }
function current_time(string $type = 'mysql'): string { return $GLOBALS['bitcore_credits_api_test']['now']; }
function sanitize_text_field(mixed $value): string { return trim(strip_tags((string) $value)); }
function absint(mixed $value): int { return max(0, (int) $value); }
function apply_filters(string $hook, mixed $value, mixed ...$args): mixed { return $hook === 'bitcore_eliza_x402_verify' ? (bool) $GLOBALS['bitcore_credits_api_test']['x402_result'] : $value; }
function __($text, $domain = null): string { return (string) $text; }

class WP_REST_Request {
    private array $params;
    private array $headers;
    public function __construct(array $params = [], array $headers = []) { $this->params = $params; $this->headers = $headers; }
    public function get_param(string $key): mixed { return $this->params[$key] ?? null; }
    public function get_header(string $key): string { $key = strtolower($key); foreach ($this->headers as $name => $value) { if (strtolower((string) $name) === $key) { return (string) $value; } } return ''; }
}
class WP_REST_Response {
    private mixed $data;
    private int $status;
    public function __construct(mixed $data = null, int $status = 200) { $this->data = $data; $this->status = $status; }
    public function get_data(): mixed { return $this->data; }
    public function get_status(): int { return $this->status; }
    public function set_status(int $status): void { $this->status = $status; }
}
class WP_Error {
    public string $code;
    public string $message;
    public array $data;
    public function __construct(string $code = '', string $message = '', array $data = []) { $this->code = $code; $this->message = $message; $this->data = $data; }
    public function get_error_code(): string { return $this->code; }
    public function get_error_message(): string { return $this->message; }
    public function get_error_data(): array { return $this->data; }
}

bitcore_credits_api_reset();
$credits = BITCORE_TEST_ROOT . '/infrastructure/class-credits.php';
$api = BITCORE_TEST_ROOT . '/rest/class-credits-api.php';
if (is_file($credits)) { require_once $credits; }
if (is_file($api)) { require_once $api; }

use BITCORE\Rest\Credits_Api;

final class CreditsApiContractTest {
    private int $passed = 0;
    private int $failed = 0;

    public function run(): int {
        $this->test_class_exists();
        if (!class_exists(Credits_Api::class)) { return $this->report(); }
        $this->test_routes_registered_once_with_expected_methods();
        $this->test_public_tiers_endpoint_shape();
        $this->test_status_uses_current_user_and_agent_scope();
        $this->test_consume_rejects_exhausted_quota_with_402_shape();
        $this->test_consume_allows_verified_x402_overage_without_quota_mutation();
        $this->test_anonymous_consume_requires_payment_then_allows_x402();
        return $this->report();
    }

    private function api(): Credits_Api { bitcore_credits_api_reset(); return new Credits_Api(); }

    private function test_class_exists(): void {
        $this->assert_true(class_exists(Credits_Api::class), 'credits_api_class_exists');
        if (!class_exists(Credits_Api::class)) { return; }
        foreach (['register_routes', 'get_tiers', 'get_status', 'check', 'consume', 'check_permission'] as $method) {
            $this->assert_true(method_exists(Credits_Api::class, $method), 'credits_api_method_' . $method);
        }
    }

    private function test_routes_registered_once_with_expected_methods(): void {
        $this->api();
        $routes = $GLOBALS['bitcore_credits_api_test']['routes'];
        $this->assert_same(4, count($routes), 'credits_route_count');
        $this->assert_route('bitcore/v1', '/credits/tiers', 'GET');
        $this->assert_route('bitcore/v1', '/credits/(?P<agent_id>[\\w-]+)', 'GET');
        $this->assert_route('bitcore/v1', '/credits/(?P<agent_id>[\\w-]+)/check', 'POST');
        $this->assert_route('bitcore/v1', '/credits/(?P<agent_id>[\\w-]+)/consume', 'POST');
    }

    private function test_public_tiers_endpoint_shape(): void {
        $api = $this->api();
        $data = $api->get_tiers(new WP_REST_Request())->get_data();
        $this->assert_same(100, $data['tiers']['core']['daily_api_calls'] ?? null, 'tiers_core_daily_calls');
        $this->assert_same(44.0, $data['tiers']['core']['monthly_price_usd'] ?? null, 'tiers_core_price');
    }

    private function test_status_uses_current_user_and_agent_scope(): void {
        $api = $this->api();
        update_user_meta(7, 'bitcore_subscription_tier', 'core');
        $consume = $api->consume(new WP_REST_Request(['agent_id' => 'aria', 'amount' => 2, 'resource_type' => 'api_calls', 'operation' => 'chat']))->get_data();
        $this->assert_same(true, $consume['success'] ?? null, 'status_seed_consume_success');
        $status = $api->get_status(new WP_REST_Request(['agent_id' => 'aria']))->get_data();
        $this->assert_same(7, $status['user_id'] ?? null, 'status_current_user');
        $this->assert_same('aria', $status['agent_id'] ?? null, 'status_agent_scope');
        $this->assert_same(2, $status['used_today'] ?? null, 'status_used_today');
        $this->assert_same(98, $status['remaining_today'] ?? null, 'status_remaining_today');
    }

    private function test_consume_rejects_exhausted_quota_with_402_shape(): void {
        $api = $this->api();
        update_user_meta(7, 'bitcore_subscription_tier', 'core');
        $api->consume(new WP_REST_Request(['agent_id' => 'aria', 'amount' => 100, 'operation' => 'seed']));
        $response = $api->consume(new WP_REST_Request(['agent_id' => 'aria', 'amount' => 1]));
        $this->assert_same(402, $response->get_status(), 'consume_exhausted_http_402');
        $data = $response->get_data();
        $this->assert_same(false, $data['success'] ?? null, 'consume_exhausted_success_false');
        $this->assert_same('x402_payment_required', $data['code'] ?? null, 'consume_exhausted_code');
    }

    private function test_consume_allows_verified_x402_overage_without_quota_mutation(): void {
        $api = $this->api();
        update_user_meta(7, 'bitcore_subscription_tier', 'core');
        $api->consume(new WP_REST_Request(['agent_id' => 'aria', 'amount' => 100, 'operation' => 'seed']));
        $GLOBALS['bitcore_credits_api_test']['x402_result'] = true;
        $response = $api->consume(new WP_REST_Request(['agent_id' => 'aria', 'amount' => 1], ['X-Payment' => 'valid-payment']));
        $data = $response->get_data();
        $this->assert_same(200, $response->get_status(), 'paid_overage_http_200');
        $this->assert_same(true, $data['success'] ?? null, 'paid_overage_success');
        $this->assert_same(true, $data['paid'] ?? null, 'paid_overage_paid_flag');
        $status = $api->get_status(new WP_REST_Request(['agent_id' => 'aria']))->get_data();
        $this->assert_same(100, $status['used_today'] ?? null, 'paid_overage_no_quota_mutation');
    }

    private function test_anonymous_consume_requires_payment_then_allows_x402(): void {
        $api = $this->api();
        $GLOBALS['bitcore_credits_api_test']['logged_in'] = false;
        $GLOBALS['bitcore_credits_api_test']['current_user_id'] = 0;
        $rejected = $api->consume(new WP_REST_Request(['agent_id' => 'anonymous', 'amount' => 1]));
        $this->assert_same(402, $rejected->get_status(), 'anonymous_without_payment_402');
        $GLOBALS['bitcore_credits_api_test']['x402_result'] = true;
        $accepted = $api->consume(new WP_REST_Request(['agent_id' => 'anonymous', 'amount' => 1], ['X-Payment' => 'valid-payment']));
        $this->assert_same(200, $accepted->get_status(), 'anonymous_with_x402_200');
        $this->assert_same(true, $accepted->get_data()['paid'] ?? null, 'anonymous_with_x402_paid_flag');
    }

    private function assert_route(string $namespace, string $route, string $method): void {
        foreach ($GLOBALS['bitcore_credits_api_test']['routes'] as $registered) {
            [$ns, $rt, $args] = $registered;
            if ($ns === $namespace && $rt === $route && (($args[0]['methods'] ?? null) === $method)) { $this->pass('route_' . $method . '_' . $route); return; }
        }
        $this->fail('route_missing_' . $method . '_' . $route);
    }
    private function assert_true(bool $condition, string $name): void { $condition ? $this->pass($name) : $this->fail($name); }
    private function assert_same(mixed $expected, mixed $actual, string $name): void { $expected === $actual ? $this->pass($name) : $this->fail($name . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true)); }
    private function pass(string $name): void { $this->passed++; echo "PASS {$name}\n"; }
    private function fail(string $name): void { $this->failed++; echo "FAIL {$name}\n"; }
    private function report(): int { echo "\nAssertions passed: {$this->passed}\nAssertions failed: {$this->failed}\n"; return $this->failed === 0 ? 0 : 1; }
}

exit((new CreditsApiContractTest())->run());
