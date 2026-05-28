<?php
declare(strict_types=1);

$GLOBALS['bitcore_test_state'] = [];

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

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

class WP_REST_Response {
    private mixed $data;
    private int $status;
    public array $headers = [];
    public function __construct(mixed $data = null, int $status = 200) {
        $this->data = $data;
        $this->status = $status;
    }
    public function get_data(): mixed { return $this->data; }
    public function get_status(): int { return $this->status; }
    public function header(string $key, string $value): void { $this->headers[$key] = $value; }
}

class WP_REST_Request {
    private string $route;
    public function __construct(string $route = '/bitcore/v1/eliza/chat') { $this->route = $route; }
    public function get_route(): string { return $this->route; }
}

function bitcore_test_reset_state(int $user_id = 7, int $credits_limit = 2, int $legacy_limit = 999): void {
    $GLOBALS['bitcore_test_state'] = [
        'current_user_id' => $user_id,
        'is_user_logged_in_calls' => 0,
        'current_user_can_calls' => 0,
        'user_meta' => [
            $user_id => [
                'bitcore_subscription_tier' => 'core',
            ],
        ],
        'options' => [
            'bitcore_eliza_server_url' => 'http://eliza.test/api',
            'bitcore_eliza_auth_token' => 'test-token',
            'bitcore_eliza_daily_limit' => $legacy_limit,
            'bitcore_eliza_x402_wallet' => '0xtest',
            'bitcore_eliza_anonymous_access' => false,
            'bitcore_credit_tiers' => [
                'core' => [
                    'daily_api_calls' => $credits_limit,
                    'monthly_price_usd' => 44.0,
                ],
            ],
        ],
        'filters' => [],
        'registered_routes' => [],
        'now' => '2026-05-26 12:00:00',
    ];
    unset($_SERVER['HTTP_X_PAYMENT']);
}

function __(string $text, ?string $domain = null): string { return $text; }
function add_filter(...$args): void {}
function add_action(...$args): void {}
function register_rest_route(string $namespace, string $route, array $args): void { $GLOBALS['bitcore_test_state']['registered_routes'][] = [$namespace, $route, $args]; }
function get_option(string $key, mixed $default = false): mixed { return $GLOBALS['bitcore_test_state']['options'][$key] ?? $default; }
function is_user_logged_in(): bool { $GLOBALS['bitcore_test_state']['is_user_logged_in_calls']++; return $GLOBALS['bitcore_test_state']['current_user_id'] > 0; }
function get_current_user_id(): int { return (int) $GLOBALS['bitcore_test_state']['current_user_id']; }
function current_user_can(string $cap): bool { $GLOBALS['bitcore_test_state']['current_user_can_calls']++; return $cap === 'manage_options' && $GLOBALS['bitcore_test_state']['current_user_id'] === 1; }
function get_user_meta(int $user_id, string $key, bool $single = false): mixed { return $GLOBALS['bitcore_test_state']['user_meta'][$user_id][$key] ?? ''; }
function update_user_meta(int $user_id, string $key, mixed $value): bool { $GLOBALS['bitcore_test_state']['user_meta'][$user_id][$key] = $value; return true; }
function sanitize_text_field(mixed $value): string { return trim(strip_tags((string) $value)); }
function wp_kses_post(mixed $value): string { return (string) $value; }
function apply_filters(string $hook, mixed $value, mixed ...$args): mixed { return $GLOBALS['bitcore_test_state']['filters'][$hook] ?? $value; }
function is_wp_error(mixed $thing): bool { return $thing instanceof WP_Error; }
function current_time(string $type = 'mysql'): string { return $GLOBALS['bitcore_test_state']['now']; }
function absint(mixed $value): int { return max(0, (int) $value); }

require_once dirname(__DIR__, 2) . '/infrastructure/class-credits.php';
require_once dirname(__DIR__, 2) . '/rest/class-eliza-api.php';

final class ElizaApiRateLimitTest {
    private int $passed = 0;
    private int $failed = 0;

    public function run(): void {
        $this->test_logged_in_permission_checks_login_state_once();
        $this->test_allowed_request_consumes_credits_usage_not_legacy_counter();
        $this->test_credits_limit_exceeded_returns_402_even_when_legacy_limit_allows();
        $this->test_rate_limit_headers_report_credits_status_without_rechecking_login();
        $this->test_verified_x402_overage_allows_without_inflating_credits_usage();
        $this->summary();
    }

    private function api(): BITCORE_Rest_Eliza_Api {
        return new BITCORE_Rest_Eliza_Api(new stdClass());
    }

    private function today_key(): string {
        return 'bitcore_eliza_calls_' . date('Y-m-d', time());
    }

    private function credits_used(int $user_id = 7, string $agent = 'eliza', string $resource = 'api_calls'): int {
        $usage = $GLOBALS['bitcore_test_state']['user_meta'][$user_id]['bitcore_credits_usage'] ?? [];
        return (int) ($usage['2026-05-26'][$agent][$resource]['used'] ?? 0);
    }

    private function seed_credits_usage(int $used, int $user_id = 7, string $agent = 'eliza', string $resource = 'api_calls'): void {
        $GLOBALS['bitcore_test_state']['user_meta'][$user_id]['bitcore_credits_usage'] = [
            '2026-05-26' => [
                $agent => [
                    $resource => [
                        'used' => $used,
                        'events' => [],
                    ],
                ],
            ],
        ];
    }

    private function assert_same(mixed $expected, mixed $actual, string $label): void {
        if ($expected === $actual) {
            $this->passed++;
            echo "PASS {$label} expected=" . var_export($expected, true) . " actual=" . var_export($actual, true) . PHP_EOL;
            return;
        }
        $this->failed++;
        echo "FAIL {$label} expected=" . var_export($expected, true) . " actual=" . var_export($actual, true) . PHP_EOL;
    }

    private function assert_true(mixed $actual, string $label): void {
        $this->assert_same(true, $actual, $label);
    }

    private function test_logged_in_permission_checks_login_state_once(): void {
        bitcore_test_reset_state();
        $api = $this->api();
        $result = $api->check_anonymous_access();
        $this->assert_true($result, 'logged_in_allowed_before_limit');
        $this->assert_same(1, $GLOBALS['bitcore_test_state']['is_user_logged_in_calls'], 'permission_login_checked_once');
        $this->assert_same(1, $GLOBALS['bitcore_test_state']['current_user_can_calls'], 'admin_capability_checked_once');
    }

    private function test_allowed_request_consumes_credits_usage_not_legacy_counter(): void {
        bitcore_test_reset_state();
        $api = $this->api();
        $api->check_anonymous_access();
        $this->assert_same(1, $this->credits_used(), 'allowed_request_increments_credits_usage');
        $this->assert_same(null, $GLOBALS['bitcore_test_state']['user_meta'][7][$this->today_key()] ?? null, 'allowed_request_does_not_write_legacy_counter');
    }

    private function test_credits_limit_exceeded_returns_402_even_when_legacy_limit_allows(): void {
        bitcore_test_reset_state(7, 2, 999);
        $this->seed_credits_usage(2);
        $api = $this->api();
        $result = $api->check_anonymous_access();
        $this->assert_same(true, $result instanceof WP_Error, 'credits_limit_exceeded_returns_error');
        $this->assert_same('x402_payment_required', $result instanceof WP_Error ? $result->get_error_code() : '', 'credits_limit_exceeded_error_code');
        $this->assert_same(2, $this->credits_used(), 'credits_limit_exceeded_does_not_increment');
        $this->assert_same(null, $GLOBALS['bitcore_test_state']['user_meta'][7][$this->today_key()] ?? null, 'credits_limit_exceeded_does_not_write_legacy_counter');
    }

    private function test_rate_limit_headers_report_credits_status_without_rechecking_login(): void {
        bitcore_test_reset_state(7, 2, 999);
        $api = $this->api();
        $permission = $api->check_anonymous_access();
        $this->assert_true($permission, 'permission_allowed_before_headers');
        $response = new WP_REST_Response(['ok' => true], 200);
        $api->add_rate_limit_headers($response, new stdClass(), new WP_REST_Request('/bitcore/v1/eliza/chat'));
        $this->assert_same(1, $GLOBALS['bitcore_test_state']['is_user_logged_in_calls'], 'headers_do_not_duplicate_login_check');
        $this->assert_same('2', $response->headers['X-RateLimit-Limit'] ?? null, 'headers_use_credits_limit');
        $this->assert_same('1', $response->headers['X-RateLimit-Remaining'] ?? null, 'headers_use_credits_remaining_after_one_call');
    }

    private function test_verified_x402_overage_allows_without_inflating_credits_usage(): void {
        bitcore_test_reset_state(7, 2, 999);
        $this->seed_credits_usage(2);
        $_SERVER['HTTP_X_PAYMENT'] = 'valid-payment';
        $GLOBALS['bitcore_test_state']['filters']['bitcore_eliza_x402_verify'] = true;
        $api = $this->api();
        $result = $api->check_anonymous_access();
        $this->assert_true($result, 'verified_x402_overage_allowed');
        $this->assert_same(2, $this->credits_used(), 'verified_x402_overage_does_not_increment_credits_usage');
        $this->assert_same(null, $GLOBALS['bitcore_test_state']['user_meta'][7][$this->today_key()] ?? null, 'verified_x402_overage_does_not_write_legacy_counter');
    }

    private function summary(): void {
        echo PHP_EOL . "Assertions passed: {$this->passed}" . PHP_EOL;
        echo "Assertions failed: {$this->failed}" . PHP_EOL;
        if ($this->failed > 0) {
            exit(1);
        }
    }
}

(new ElizaApiRateLimitTest())->run();
