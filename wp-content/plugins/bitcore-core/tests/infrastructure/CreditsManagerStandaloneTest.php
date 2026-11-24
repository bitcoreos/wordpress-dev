<?php
/**
 * Creditsmanagerstandalonetest.
 *
 * @package    Bitcore\Tests
 * @since      2026.11.23
 * @version    1.0.0
 * @author     coraline
 */

declare(strict_types=1);

define('ABSPATH', __DIR__);

$GLOBALS['bitcore_credits_test'] = [];
function bitcore_credits_reset(): void {
    $GLOBALS['bitcore_credits_test'] = [
        'options' => [],
        'user_meta' => [],
        'filters' => [],
        'actions' => [],
        'now' => '2026-05-26 12:00:00',
    ];
}
function get_option(string $key, mixed $default = false): mixed { return $GLOBALS['bitcore_credits_test']['options'][$key] ?? $default; }
function update_option(string $key, mixed $value, bool $autoload = true): bool { $GLOBALS['bitcore_credits_test']['options'][$key] = $value; return true; }
function get_user_meta(int $user_id, string $key = '', bool $single = false): mixed {
    $value = $GLOBALS['bitcore_credits_test']['user_meta'][$user_id][$key] ?? ($single ? '' : []);
    return $single ? $value : [$value];
}
function update_user_meta(int $user_id, string $key, mixed $value): bool { $GLOBALS['bitcore_credits_test']['user_meta'][$user_id][$key] = $value; return true; }
function current_time(string $type = 'mysql'): string { return $GLOBALS['bitcore_credits_test']['now']; }
function sanitize_text_field(mixed $value): string { return trim(strip_tags((string) $value)); }
function add_action(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void { $GLOBALS['bitcore_credits_test']['actions'][] = [$hook, $callback, $priority, $accepted_args]; }
function do_action(string $hook, mixed ...$args): void {}
function apply_filters(string $hook, mixed $value, mixed ...$args): mixed {
    if ($hook === 'bitcore_eliza_x402_verify') {
        return $GLOBALS['bitcore_credits_test']['x402_result'] ?? false;
    }
    return $value;
}

bitcore_credits_reset();
$root = dirname(__DIR__, 2);
$credits_file = $root . '/infrastructure/class-credits.php';
if (is_file($credits_file)) {
    require_once $credits_file;
}

use BITCORE\Infrastructure\Credits_Manager;

final class CreditsManagerStandaloneTest {
    private int $passed = 0;
    private int $failed = 0;
    private string $root;

    public function __construct(string $root) { $this->root = $root; }

    public function run(): int {
        $this->test_budget_policy_schema_matches_budget_manager_contract();
        $this->test_class_exists_and_exposes_public_contract();
        if (!class_exists(Credits_Manager::class)) { return $this->report(); }
        $this->test_core_tier_has_100_daily_calls_and_44_monthly_price();
        $this->test_subscription_tiers_expose_agent_limits();
        $this->test_subscription_purchase_provisions_core_tier_and_agent_limit();
        $this->test_subscription_purchase_rejects_invalid_user_and_unknown_tier();
        $this->test_usage_is_scoped_by_day_user_and_agent();
        $this->test_under_quota_consume_increments_usage();
        $this->test_quota_exhaustion_requires_x402_payment();
        $this->test_x402_verified_overage_is_allowed_without_quota_mutation();
        $this->test_anonymous_requires_x402_payment();
        $this->test_anonymous_x402_payment_allows_paid_access();
        $this->test_credit_status_is_stable_and_non_negative();
        return $this->report();
    }

    private function test_budget_policy_schema_matches_budget_manager_contract(): void {
        $schema = file_get_contents($this->root . '/database/class-budget-policies-table.php') ?: '';
        $manager = file_get_contents($this->root . '/infrastructure/budget/BudgetManager.php') ?: '';
        $this->assert_true(str_contains($manager, "'limit' =>") || str_contains($manager, '->limit'), 'budget_manager_uses_limit_contract');
        $this->assert_true(str_contains($schema, 'limit_amount decimal('), 'budget_policy_schema_keeps_safe_limit_amount_column');
        $this->assert_true(str_contains($manager, 'limit_amount AS `limit`') || str_contains($manager, 'limit_amount AS limit'), 'budget_manager_aliases_limit_amount_to_public_limit');
        $this->assert_true(str_contains($manager, "'limit_amount' =>"), 'budget_manager_writes_limit_amount_column');
    }

    private function test_class_exists_and_exposes_public_contract(): void {
        $this->assert_true(class_exists(Credits_Manager::class), 'credits_manager_class_exists');
        if (!class_exists(Credits_Manager::class)) { return; }
        foreach (['get_tier_limits', 'get_status', 'can_consume', 'consume', 'verify_x402_payment', 'get_agent_limit', 'provision_subscription_purchase'] as $method) {
            $this->assert_true(method_exists(Credits_Manager::class, $method), 'credits_manager_method_' . $method);
        }
    }

    private function test_core_tier_has_100_daily_calls_and_44_monthly_price(): void {
        bitcore_credits_reset();
        $credits = new Credits_Manager();
        $limits = $credits->get_tier_limits('core');
        $this->assert_same(100, $limits['daily_api_calls'] ?? null, 'core_tier_daily_api_calls_floor');
        $this->assert_same(44.0, $limits['monthly_price_usd'] ?? null, 'core_tier_monthly_price');
    }

    private function test_subscription_tiers_expose_agent_limits(): void {
        bitcore_credits_reset();
        $credits = new Credits_Manager();
        $this->assert_same(1, $credits->get_tier_limits('free')['agent_limit'] ?? null, 'free_tier_agent_limit');
        $this->assert_same(3, $credits->get_tier_limits('core')['agent_limit'] ?? null, 'core_tier_agent_limit_matches_aria_forge_muse');
        $this->assert_same(10, $credits->get_tier_limits('pro')['agent_limit'] ?? null, 'pro_tier_agent_limit');
    }

    private function test_subscription_purchase_provisions_core_tier_and_agent_limit(): void {
        bitcore_credits_reset();
        $credits = new Credits_Manager();
        $this->assert_true($credits->provision_subscription_purchase(21, 'core', ['source' => 'checkout', 'payment_id' => '<b>pay_123</b>']), 'subscription_purchase_core_success');
        $this->assert_same('core', get_user_meta(21, 'bitcore_subscription_tier', true), 'subscription_purchase_sets_tier');
        $this->assert_same(3, get_user_meta(21, 'bitcore_agent_limit', true), 'subscription_purchase_sets_agent_limit');
        $this->assert_same('2026-05-26 12:00:00', get_user_meta(21, 'bitcore_subscription_purchased_at', true), 'subscription_purchase_sets_timestamp');
        $purchase = get_user_meta(21, 'bitcore_subscription_last_purchase', true);
        $this->assert_same('checkout', $purchase['source'] ?? null, 'subscription_purchase_sanitizes_source_metadata');
        $this->assert_same('pay_123', $purchase['payment_id'] ?? null, 'subscription_purchase_sanitizes_payment_metadata');
        $this->assert_same(3, $credits->get_agent_limit(21), 'get_agent_limit_reads_purchased_tier');
    }

    private function test_subscription_purchase_rejects_invalid_user_and_unknown_tier(): void {
        bitcore_credits_reset();
        $credits = new Credits_Manager();
        $this->assert_same(false, $credits->provision_subscription_purchase(0, 'core'), 'subscription_purchase_rejects_invalid_user');
        $this->assert_same(false, $credits->provision_subscription_purchase(22, 'enterprise'), 'subscription_purchase_rejects_unknown_tier');
        $this->assert_same('', get_user_meta(22, 'bitcore_subscription_tier', true), 'rejected_purchase_does_not_set_tier');
        $this->assert_same(3, $credits->get_agent_limit(22), 'default_core_agent_limit_for_existing_logged_in_user');
    }

    private function test_usage_is_scoped_by_day_user_and_agent(): void {
        bitcore_credits_reset();
        update_user_meta(7, 'bitcore_subscription_tier', 'core');
        $credits = new Credits_Manager();
        $this->assert_true($credits->consume(7, 'aria', 2, 'api_calls', 'chat'), 'consume_aria_success');
        $this->assert_true($credits->consume(7, 'forge', 3, 'api_calls', 'chat'), 'consume_forge_success');
        $aria = $credits->get_status(7, 'aria');
        $forge = $credits->get_status(7, 'forge');
        $this->assert_same(2, $aria['used_today'] ?? null, 'aria_usage_scoped');
        $this->assert_same(3, $forge['used_today'] ?? null, 'forge_usage_scoped');
    }

    private function test_under_quota_consume_increments_usage(): void {
        bitcore_credits_reset();
        update_user_meta(8, 'bitcore_subscription_tier', 'core');
        $credits = new Credits_Manager();
        $decision = $credits->can_consume(8, 'aria', 1, 'api_calls', false);
        $this->assert_same(true, $decision['allowed'] ?? null, 'under_quota_allowed');
        $this->assert_true($credits->consume(8, 'aria', 1, 'api_calls', 'chat'), 'under_quota_consume_success');
        $status = $credits->get_status(8, 'aria');
        $this->assert_same(1, $status['used_today'] ?? null, 'under_quota_usage_incremented');
        $this->assert_same(99, $status['remaining_today'] ?? null, 'under_quota_remaining_decremented');
    }

    private function test_quota_exhaustion_requires_x402_payment(): void {
        bitcore_credits_reset();
        update_user_meta(9, 'bitcore_subscription_tier', 'core');
        $credits = new Credits_Manager();
        $this->assert_true($credits->consume(9, 'aria', 100, 'api_calls', 'seed'), 'seed_quota_to_limit');
        $decision = $credits->can_consume(9, 'aria', 1, 'api_calls', false);
        $this->assert_same(false, $decision['allowed'] ?? null, 'exhausted_quota_rejected');
        $this->assert_same('x402_payment_required', $decision['code'] ?? null, 'exhausted_quota_payment_code');
        $this->assert_same(402, $decision['status'] ?? null, 'exhausted_quota_payment_status');
    }

    private function test_x402_verified_overage_is_allowed_without_quota_mutation(): void {
        bitcore_credits_reset();
        update_user_meta(10, 'bitcore_subscription_tier', 'core');
        $credits = new Credits_Manager();
        $this->assert_true($credits->consume(10, 'aria', 100, 'api_calls', 'seed'), 'seed_quota_before_paid_overage');
        $decision = $credits->can_consume(10, 'aria', 1, 'api_calls', true);
        $this->assert_same(true, $decision['allowed'] ?? null, 'paid_overage_allowed');
        $this->assert_same('x402_paid_overage', $decision['reason'] ?? null, 'paid_overage_reason');
        $this->assert_same(0, $credits->get_status(10, 'aria')['remaining_today'] ?? null, 'paid_overage_does_not_inflate_quota');
    }

    private function test_anonymous_requires_x402_payment(): void {
        bitcore_credits_reset();
        $credits = new Credits_Manager();
        $decision = $credits->can_consume(0, 'anonymous', 1, 'api_calls', false);
        $this->assert_same(false, $decision['allowed'] ?? null, 'anonymous_without_payment_rejected');
        $this->assert_same('x402_payment_required', $decision['code'] ?? null, 'anonymous_without_payment_code');
    }

    private function test_anonymous_x402_payment_allows_paid_access(): void {
        bitcore_credits_reset();
        $credits = new Credits_Manager();
        $decision = $credits->can_consume(0, 'anonymous', 1, 'api_calls', true);
        $this->assert_same(true, $decision['allowed'] ?? null, 'anonymous_x402_allowed');
        $this->assert_same('x402_anonymous_paid', $decision['reason'] ?? null, 'anonymous_x402_reason');
    }

    private function test_credit_status_is_stable_and_non_negative(): void {
        bitcore_credits_reset();
        update_user_meta(11, 'bitcore_subscription_tier', 'core');
        $credits = new Credits_Manager();
        $this->assert_true($credits->consume(11, 'aria', 150, 'api_calls', 'manual_overrun'), 'manual_overrun_records');
        $status = $credits->get_status(11, 'aria');
        $this->assert_same(150, $status['used_today'] ?? null, 'overrun_used_reported');
        $this->assert_same(0, $status['remaining_today'] ?? null, 'remaining_never_negative');
        $this->assert_same(100.0, $status['percentage_used'] ?? null, 'percentage_capped_at_100');
    }

    private function assert_true(bool $condition, string $name): void { $condition ? $this->pass($name) : $this->fail($name); }
    private function assert_same(mixed $expected, mixed $actual, string $name): void { $expected === $actual ? $this->pass($name) : $this->fail($name . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true)); }
    private function pass(string $name): void { $this->passed++; echo "PASS {$name}\n"; }
    private function fail(string $name): void { $this->failed++; echo "FAIL {$name}\n"; }
    private function report(): int { echo "\nAssertions passed: {$this->passed}\nAssertions failed: {$this->failed}\n"; return $this->failed === 0 ? 0 : 1; }
}

exit((new CreditsManagerStandaloneTest($root))->run());
