<?php
/**
 * X402verifiertest.
 *
 * @package    Bitcore\Tests
 * @since      2026.11.23
 * @version    1.0.0
 * @author     coraline
 */

declare(strict_types=1);

/**
 * Standalone tests for x402 payment verifier safety boundaries.
 *
 * These tests intentionally avoid pretending a local header claim proves payment.
 * Valid acceptance requires a structured Base64 JSON payload plus facilitator
 * approval through bitcore_x402_facilitator_verify.
 */

define('ABSPATH', __DIR__);

$GLOBALS['bitcore_x402_test_state'] = [];
function bitcore_x402_test_reset(): void {
    $GLOBALS['bitcore_x402_test_state'] = [
        'options' => [
            'bitcore_eliza_x402_wallet' => '0xReceiver000000000000000000000000000000000001',
            'bitcore_eliza_x402_min_amount' => 100,
            'bitcore_eliza_x402_network' => 'base',
            'bitcore_eliza_x402_asset' => 'USDC',
        ],
        'filters' => [],
        'facilitator_result' => false,
        'facilitator_calls' => [],
    ];
}
function get_option(string $key, mixed $default = false): mixed {
    return $GLOBALS['bitcore_x402_test_state']['options'][$key] ?? $default;
}
function add_filter(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void {
    $GLOBALS['bitcore_x402_test_state']['filters'][] = [$hook, $priority, $accepted_args, $callback];
}
function apply_filters(string $hook, mixed $value, mixed ...$args): mixed {
    if ($hook === 'bitcore_x402_facilitator_verify') {
        $GLOBALS['bitcore_x402_test_state']['facilitator_calls'][] = [$value, ...$args];
        return $GLOBALS['bitcore_x402_test_state']['facilitator_result'];
    }
    return $value;
}
function sanitize_text_field(mixed $value): string {
    return trim(strip_tags((string) $value));
}

bitcore_x402_test_reset();
require_once dirname(__DIR__, 2) . '/infrastructure/class-x402.php';

use BITCORE\Infrastructure\X402_Verifier;

final class X402VerifierStandaloneTest {
    private int $passed = 0;
    private int $failed = 0;

    public function run(): void {
        $this->test_registers_eliza_x402_filter();
        $this->test_rejects_empty_payment();
        $this->test_rejects_legacy_comma_claim_even_when_wallet_and_amount_match();
        $this->test_rejects_raw_json_because_protocol_header_must_be_base64_json();
        $this->test_rejects_base64_json_without_signature_material();
        $this->test_rejects_wallet_mismatch_before_facilitator();
        $this->test_rejects_amount_below_minimum_before_facilitator();
        $this->test_rejects_missing_required_network_before_facilitator();
        $this->test_rejects_missing_required_asset_before_facilitator();
        $this->test_rejects_valid_shape_without_facilitator_approval();
        $this->test_accepts_valid_shape_only_with_facilitator_approval();
        $this->test_supports_nested_x402_payment_payload_shape();
        $this->report();
    }

    private function test_registers_eliza_x402_filter(): void {
        bitcore_x402_test_reset();
        new X402_Verifier();
        $hooks = array_map(fn($row) => $row[0], $GLOBALS['bitcore_x402_test_state']['filters']);
        $this->assert_true(in_array('bitcore_eliza_x402_verify', $hooks, true), 'registers_bitcore_eliza_x402_verify');
    }

    private function test_rejects_empty_payment(): void {
        bitcore_x402_test_reset();
        $verifier = new X402_Verifier();
        $this->assert_same(false, $verifier->verify_payment(false, ''), 'rejects_empty_payment');
    }

    private function test_rejects_legacy_comma_claim_even_when_wallet_and_amount_match(): void {
        bitcore_x402_test_reset();
        $verifier = new X402_Verifier();
        $legacy = 'x402,amount=100,wallet=0xReceiver000000000000000000000000000000000001';
        $this->assert_same(false, $verifier->verify_payment(false, $legacy), 'rejects_legacy_comma_claim');
        $this->assert_same([], $GLOBALS['bitcore_x402_test_state']['facilitator_calls'], 'legacy_claim_does_not_reach_facilitator');
    }

    private function test_rejects_raw_json_because_protocol_header_must_be_base64_json(): void {
        bitcore_x402_test_reset();
        $verifier = new X402_Verifier();
        $raw_json = json_encode($this->flat_payload(), JSON_THROW_ON_ERROR);
        $this->assert_same(false, $verifier->verify_payment(false, $raw_json), 'rejects_raw_json_not_base64');
    }

    private function test_rejects_base64_json_without_signature_material(): void {
        bitcore_x402_test_reset();
        $verifier = new X402_Verifier();
        $payload = $this->flat_payload();
        unset($payload['signature']);
        $this->assert_same(false, $verifier->verify_payment(false, $this->encode($payload)), 'rejects_missing_signature');
    }

    private function test_rejects_wallet_mismatch_before_facilitator(): void {
        bitcore_x402_test_reset();
        $verifier = new X402_Verifier();
        $payload = $this->flat_payload(['destination' => '0xWrong0000000000000000000000000000000000001']);
        $this->assert_same(false, $verifier->verify_payment(false, $this->encode($payload)), 'rejects_wallet_mismatch');
        $this->assert_same([], $GLOBALS['bitcore_x402_test_state']['facilitator_calls'], 'wallet_mismatch_does_not_reach_facilitator');
    }

    private function test_rejects_amount_below_minimum_before_facilitator(): void {
        bitcore_x402_test_reset();
        $verifier = new X402_Verifier();
        $payload = $this->flat_payload(['amount' => 99]);
        $this->assert_same(false, $verifier->verify_payment(false, $this->encode($payload)), 'rejects_amount_below_minimum');
        $this->assert_same([], $GLOBALS['bitcore_x402_test_state']['facilitator_calls'], 'low_amount_does_not_reach_facilitator');
    }

    private function test_rejects_missing_required_network_before_facilitator(): void {
        bitcore_x402_test_reset();
        $verifier = new X402_Verifier();
        $payload = $this->flat_payload();
        unset($payload['network']);
        $this->assert_same(false, $verifier->verify_payment(false, $this->encode($payload)), 'rejects_missing_required_network');
        $this->assert_same([], $GLOBALS['bitcore_x402_test_state']['facilitator_calls'], 'missing_network_does_not_reach_facilitator');
    }

    private function test_rejects_missing_required_asset_before_facilitator(): void {
        bitcore_x402_test_reset();
        $verifier = new X402_Verifier();
        $payload = $this->flat_payload();
        unset($payload['asset']);
        $this->assert_same(false, $verifier->verify_payment(false, $this->encode($payload)), 'rejects_missing_required_asset');
        $this->assert_same([], $GLOBALS['bitcore_x402_test_state']['facilitator_calls'], 'missing_asset_does_not_reach_facilitator');
    }

    private function test_rejects_valid_shape_without_facilitator_approval(): void {
        bitcore_x402_test_reset();
        $verifier = new X402_Verifier();
        $this->assert_same(false, $verifier->verify_payment(false, $this->encode($this->flat_payload())), 'rejects_without_facilitator_approval');
        $this->assert_same(1, count($GLOBALS['bitcore_x402_test_state']['facilitator_calls']), 'valid_shape_reaches_facilitator_once');
    }

    private function test_accepts_valid_shape_only_with_facilitator_approval(): void {
        bitcore_x402_test_reset();
        $GLOBALS['bitcore_x402_test_state']['facilitator_result'] = true;
        $verifier = new X402_Verifier();
        $this->assert_same(true, $verifier->verify_payment(false, $this->encode($this->flat_payload())), 'accepts_only_with_facilitator_approval');
        $call = $GLOBALS['bitcore_x402_test_state']['facilitator_calls'][0] ?? [];
        $requirements = $call[2] ?? [];
        $this->assert_same('0xReceiver000000000000000000000000000000000001', $requirements['destination'] ?? null, 'passes_destination_requirement');
        $this->assert_same(100, $requirements['min_amount'] ?? null, 'passes_min_amount_requirement');
        $this->assert_same('base', $requirements['network'] ?? null, 'passes_network_requirement');
        $this->assert_same('USDC', $requirements['asset'] ?? null, 'passes_asset_requirement');
    }

    private function test_supports_nested_x402_payment_payload_shape(): void {
        bitcore_x402_test_reset();
        $GLOBALS['bitcore_x402_test_state']['facilitator_result'] = true;
        $verifier = new X402_Verifier();
        $payload = [
            'x402Version' => 2,
            'scheme' => 'exact',
            'network' => 'base',
            'asset' => 'USDC',
            'payload' => [
                'authorization' => [
                    'to' => '0xReceiver000000000000000000000000000000000001',
                    'value' => '150',
                ],
                'signature' => '0xabcdef',
            ],
        ];
        $this->assert_same(true, $verifier->verify_payment(false, $this->encode($payload)), 'accepts_nested_payload_with_facilitator');
    }

    private function flat_payload(array $overrides = []): array {
        return array_merge([
            'x402Version' => 2,
            'scheme' => 'exact',
            'network' => 'base',
            'asset' => 'USDC',
            'amount' => 100,
            'destination' => '0xReceiver000000000000000000000000000000000001',
            'signature' => '0xabcdef',
        ], $overrides);
    }

    private function encode(array $payload): string {
        return base64_encode(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function assert_true(bool $condition, string $name): void { $condition ? $this->pass($name) : $this->fail($name); }
    private function assert_same(mixed $expected, mixed $actual, string $name): void {
        $expected === $actual ? $this->pass($name) : $this->fail($name . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
    private function pass(string $name): void { $this->passed++; echo "PASS {$name}\n"; }
    private function fail(string $name): void { $this->failed++; echo "FAIL {$name}\n"; }
    private function report(): void {
        echo "\nAssertions passed: {$this->passed}\n";
        echo "Assertions failed: {$this->failed}\n";
        if ($this->failed > 0) { exit(1); }
    }
}

(new X402VerifierStandaloneTest())->run();
