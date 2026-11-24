<?php
/**
 * X402facilitatorclienttest.
 *
 * @package    Bitcore\Tests
 * @since      2026.11.23
 * @version    1.0.0
 * @author     coraline
 */

declare(strict_types=1);

define('ABSPATH', __DIR__);
$GLOBALS['x402_facilitator_test'] = [];
function x402_facilitator_reset(): void {
    $GLOBALS['x402_facilitator_test'] = [
        'options' => [
            'bitcore_eliza_x402_facilitator_url' => 'https://facilitator.example',
            'bitcore_eliza_x402_facilitator_token' => 'token-123',
            'bitcore_eliza_x402_facilitator_settle' => '1',
            'bitcore_eliza_x402_resource' => '/wp-json/bitcore/v1/eliza/chat',
        ],
        'filters' => [],
        'requests' => [],
        'responses' => [],
    ];
}
function get_option(string $key, mixed $default = false): mixed { return $GLOBALS['x402_facilitator_test']['options'][$key] ?? $default; }
function add_filter(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void { $GLOBALS['x402_facilitator_test']['filters'][] = [$hook, $priority, $accepted_args, $callback]; }
function sanitize_text_field(mixed $value): string { return trim(strip_tags((string) $value)); }
function esc_url_raw(mixed $value): string { return trim((string) $value); }
function wp_json_encode(mixed $data, int $flags = 0, int $depth = 512): string|false { return json_encode($data, $flags, $depth); }
function is_wp_error(mixed $value): bool { return $value instanceof WP_Error; }
function wp_remote_retrieve_response_code(array $response): int { return (int) ($response['response']['code'] ?? 0); }
function wp_remote_retrieve_body(array $response): string { return (string) ($response['body'] ?? ''); }
function wp_remote_post(string $url, array $args = []): mixed {
    $GLOBALS['x402_facilitator_test']['requests'][] = [$url, $args];
    return array_shift($GLOBALS['x402_facilitator_test']['responses']) ?: ['response' => ['code' => 500], 'body' => '{}'];
}
final class WP_Error { public function __construct(public string $code, public string $message = '') {} }

x402_facilitator_reset();
$file = dirname(__DIR__, 2) . '/infrastructure/class-x402-facilitator.php';
if (file_exists($file)) { require_once $file; }

use BITCORE\Infrastructure\X402_Facilitator_Client;

final class X402FacilitatorClientStandaloneTest {
    private int $passed = 0;
    private int $failed = 0;
    public function run(): void {
        $this->test_class_exists();
        if (!class_exists(X402_Facilitator_Client::class)) { $this->report(); return; }
        $this->test_registers_facilitator_filter();
        $this->test_missing_url_fails_closed_without_http();
        $this->test_verify_request_body_and_auth_header();
        $this->test_verify_invalid_does_not_settle();
        $this->test_verify_wp_error_fails_closed();
        $this->test_verify_non_2xx_fails_closed();
        $this->test_invalid_json_fails_closed();
        $this->test_verify_true_but_settle_false_rejects();
        $this->test_verify_and_settle_boolean_true_accepts();
        $this->report();
    }
    private function test_class_exists(): void { $this->assert_true(class_exists(X402_Facilitator_Client::class), 'class_exists_x402_facilitator_client'); }
    private function test_registers_facilitator_filter(): void {
        x402_facilitator_reset(); new X402_Facilitator_Client();
        $row = $GLOBALS['x402_facilitator_test']['filters'][0] ?? null;
        $this->assert_same('bitcore_x402_facilitator_verify', $row[0] ?? null, 'registers_facilitator_filter');
        $this->assert_same(5, $row[2] ?? null, 'registers_five_args');
    }
    private function test_missing_url_fails_closed_without_http(): void {
        x402_facilitator_reset(); $GLOBALS['x402_facilitator_test']['options']['bitcore_eliza_x402_facilitator_url'] = '';
        $client = new X402_Facilitator_Client();
        $this->assert_same(false, $client->verify_and_settle(false, $this->payload(), $this->requirements(), $this->normalized(), $this->payment_header()), 'missing_url_rejects');
        $this->assert_same(0, count($GLOBALS['x402_facilitator_test']['requests']), 'missing_url_no_http');
    }
    private function test_verify_request_body_and_auth_header(): void {
        x402_facilitator_reset(); $GLOBALS['x402_facilitator_test']['responses'] = [$this->json(['isValid' => false])];
        $client = new X402_Facilitator_Client();
        $client->verify_and_settle(false, $this->payload(), $this->requirements(), $this->normalized(), $this->payment_header());
        [$url, $args] = $GLOBALS['x402_facilitator_test']['requests'][0];
        $body = json_decode($args['body'], true, 64, JSON_THROW_ON_ERROR);
        $this->assert_same('https://facilitator.example/verify', $url, 'verify_url');
        $this->assert_same('Bearer token-123', $args['headers']['Authorization'] ?? null, 'auth_header');
        $this->assert_same($this->payment_header(), $body['paymentHeader'] ?? null, 'sends_payment_header');
        $this->assert_same('0xReceiver', $body['paymentRequirements']['payTo'] ?? null, 'sends_pay_to');
        $this->assert_same('100', $body['paymentRequirements']['maxAmountRequired'] ?? null, 'sends_max_amount_required');
    }
    private function test_verify_invalid_does_not_settle(): void {
        x402_facilitator_reset(); $GLOBALS['x402_facilitator_test']['responses'] = [$this->json(['isValid' => false])];
        $client = new X402_Facilitator_Client();
        $this->assert_same(false, $client->verify_and_settle(false, $this->payload(), $this->requirements(), $this->normalized(), $this->payment_header()), 'verify_false_rejects');
        $this->assert_same(1, count($GLOBALS['x402_facilitator_test']['requests']), 'verify_false_no_settle');
    }
    private function test_verify_wp_error_fails_closed(): void {
        x402_facilitator_reset(); $GLOBALS['x402_facilitator_test']['responses'] = [new WP_Error('http_fail')];
        $client = new X402_Facilitator_Client();
        $this->assert_same(false, $client->verify_and_settle(false, $this->payload(), $this->requirements(), $this->normalized(), $this->payment_header()), 'wp_error_rejects');
    }
    private function test_verify_non_2xx_fails_closed(): void {
        x402_facilitator_reset(); $GLOBALS['x402_facilitator_test']['responses'] = [['response' => ['code' => 503], 'body' => '{"isValid":true}']];
        $client = new X402_Facilitator_Client();
        $this->assert_same(false, $client->verify_and_settle(false, $this->payload(), $this->requirements(), $this->normalized(), $this->payment_header()), 'non_2xx_rejects');
    }
    private function test_invalid_json_fails_closed(): void {
        x402_facilitator_reset(); $GLOBALS['x402_facilitator_test']['responses'] = [['response' => ['code' => 200], 'body' => 'not-json']];
        $client = new X402_Facilitator_Client();
        $this->assert_same(false, $client->verify_and_settle(false, $this->payload(), $this->requirements(), $this->normalized(), $this->payment_header()), 'invalid_json_rejects');
    }
    private function test_verify_true_but_settle_false_rejects(): void {
        x402_facilitator_reset(); $GLOBALS['x402_facilitator_test']['responses'] = [$this->json(['isValid' => true]), $this->json(['success' => false])];
        $client = new X402_Facilitator_Client();
        $this->assert_same(false, $client->verify_and_settle(false, $this->payload(), $this->requirements(), $this->normalized(), $this->payment_header()), 'settle_false_rejects');
        $this->assert_same('https://facilitator.example/settle', $GLOBALS['x402_facilitator_test']['requests'][1][0] ?? null, 'settle_url');
    }
    private function test_verify_and_settle_boolean_true_accepts(): void {
        x402_facilitator_reset(); $GLOBALS['x402_facilitator_test']['responses'] = [$this->json(['isValid' => true]), $this->json(['success' => true, 'transaction' => '0xabc'])];
        $client = new X402_Facilitator_Client();
        $this->assert_same(true, $client->verify_and_settle(false, $this->payload(), $this->requirements(), $this->normalized(), $this->payment_header()), 'verify_settle_true_accepts');
    }
    private function payload(): array { return ['x402Version' => 2, 'scheme' => 'exact']; }
    private function requirements(): array { return ['destination' => '0xReceiver', 'min_amount' => 100, 'network' => 'base', 'asset' => 'USDC']; }
    private function normalized(): array { return ['amount' => 100, 'destination' => '0xReceiver', 'network' => 'base', 'asset' => 'USDC']; }
    private function payment_header(): string { return base64_encode('{"x402Version":2}'); }
    private function json(array $body): array { return ['response' => ['code' => 200], 'body' => json_encode($body, JSON_THROW_ON_ERROR)]; }
    private function assert_true(bool $condition, string $name): void { $condition ? $this->pass($name) : $this->fail($name); }
    private function assert_same(mixed $expected, mixed $actual, string $name): void { $expected === $actual ? $this->pass($name) : $this->fail($name . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true)); }
    private function pass(string $name): void { $this->passed++; echo "PASS {$name}\n"; }
    private function fail(string $name): void { $this->failed++; echo "FAIL {$name}\n"; }
    private function report(): void { echo "\nAssertions passed: {$this->passed}\nAssertions failed: {$this->failed}\n"; if ($this->failed > 0) { exit(1); } }
}
(new X402FacilitatorClientStandaloneTest())->run();
