<?php
/**
 * BITCORE Credits Manager
 *
 * Unified credits/quota service for subscription call quotas and x402 paid overages.
 * This service is intentionally storage-light: daily usage is stored in user meta,
 * keyed by UTC-local WordPress date, user, agent, and resource type.
 *
 * @package BITCORE\Infrastructure
 * @since       1.0.0
 * @version     1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Infrastructure;

if (!defined('ABSPATH')) {
    exit;
}

class Credits_Manager {

    /** @var array<string,array<string,int|float|string>> */
    private array $tiers = [
        'free' => [
            'daily_api_calls' => 0,
            'monthly_price_usd' => 0.0,
            'agent_limit' => 1,
        ],
        'core' => [
            'daily_api_calls' => 100,
            'monthly_price_usd' => 44.0,
            'agent_limit' => 3,
        ],
        'pro' => [
            'daily_api_calls' => 1000,
            'monthly_price_usd' => 144.0,
            'agent_limit' => 10,
        ],
    ];

    private string $usage_meta_key = 'bitcore_credits_usage';
    private string $tier_meta_key = 'bitcore_subscription_tier';

    public function __construct() {
        $configured = get_option('bitcore_credit_tiers', []);
        if (is_array($configured)) {
            foreach ($configured as $tier => $limits) {
                if (!is_string($tier) || !is_array($limits)) {
                    continue;
                }
                $this->tiers[sanitize_text_field($tier)] = array_merge(
                    $this->tiers[$tier] ?? [],
                    $this->normalize_limits($limits)
                );
            }
        }
    }

    /**
     * Return normalized limits for a subscription tier.
     *
     * @return array<string,int|float|string>
     */
    public function get_tier_limits(string $tier): array {
        $tier = sanitize_text_field($tier !== '' ? $tier : 'free');
        $limits = $this->tiers[$tier] ?? $this->tiers['free'];
        return $this->normalize_limits($limits);
    }

    /**
     * Return daily credit status for a user/agent/resource tuple.
     *
     * @return array<string,mixed>
     */
    public function get_status(int $user_id, string $agent_id = 'default', string $resource_type = 'api_calls'): array {
        $tier = $this->get_user_tier($user_id);
        $limits = $this->get_tier_limits($tier);
        $daily_limit = (int) ($limits['daily_api_calls'] ?? 0);
        $used = $this->get_used_today($user_id, $agent_id, $resource_type);
        $remaining = max(0, $daily_limit - $used);
        $percentage = $daily_limit > 0 ? min(100.0, round(($used / $daily_limit) * 100, 2)) : 100.0;

        return [
            'user_id' => $user_id,
            'agent_id' => sanitize_text_field($agent_id),
            'resource_type' => sanitize_text_field($resource_type),
            'tier' => $tier,
            'daily_limit' => $daily_limit,
            'used_today' => $used,
            'remaining_today' => $remaining,
            'percentage_used' => $percentage,
            'period' => 'daily',
            'date' => $this->today(),
            'monthly_price_usd' => (float) ($limits['monthly_price_usd'] ?? 0.0),
            'agent_limit' => max(0, (int) ($limits['agent_limit'] ?? 0)),
        ];
    }

    /**
     * Decide whether a request can consume credits or requires x402 payment.
     *
     * @return array<string,mixed>
     */
    public function can_consume(
        int $user_id,
        string $agent_id = 'default',
        int $amount = 1,
        string $resource_type = 'api_calls',
        bool $x402_verified = false
    ): array {
        $amount = max(1, $amount);

        if ($user_id <= 0) {
            if ($x402_verified) {
                return ['allowed' => true, 'reason' => 'x402_anonymous_paid', 'paid' => true];
            }
            return [
                'allowed' => false,
                'reason' => 'anonymous_requires_payment',
                'code' => 'x402_payment_required',
                'status' => 402,
            ];
        }

        $status = $this->get_status($user_id, $agent_id, $resource_type);
        if (($status['used_today'] + $amount) <= $status['daily_limit']) {
            return [
                'allowed' => true,
                'reason' => 'within_quota',
                'remaining_after' => max(0, (int) $status['remaining_today'] - $amount),
                'paid' => false,
            ];
        }

        if ($x402_verified) {
            return ['allowed' => true, 'reason' => 'x402_paid_overage', 'paid' => true];
        }

        return [
            'allowed' => false,
            'reason' => 'quota_exhausted',
            'code' => 'x402_payment_required',
            'status' => 402,
            'remaining_today' => (int) $status['remaining_today'],
            'required' => $amount,
        ];
    }

    /**
     * Record quota consumption. Paid x402 overages should not call this method.
     */
    public function consume(
        int $user_id,
        string $agent_id = 'default',
        int $amount = 1,
        string $resource_type = 'api_calls',
        string $operation = '',
        array $metadata = []
    ): bool {
        if ($user_id <= 0) {
            return false;
        }

        $amount = max(1, $amount);
        $usage = $this->get_usage($user_id);
        $date = $this->today();
        $agent_key = sanitize_text_field($agent_id !== '' ? $agent_id : 'default');
        $resource_key = sanitize_text_field($resource_type !== '' ? $resource_type : 'api_calls');

        if (!isset($usage[$date]) || !is_array($usage[$date])) {
            $usage[$date] = [];
        }
        if (!isset($usage[$date][$agent_key]) || !is_array($usage[$date][$agent_key])) {
            $usage[$date][$agent_key] = [];
        }
        if (!isset($usage[$date][$agent_key][$resource_key]) || !is_array($usage[$date][$agent_key][$resource_key])) {
            $usage[$date][$agent_key][$resource_key] = ['used' => 0, 'events' => []];
        }

        $usage[$date][$agent_key][$resource_key]['used'] = (int) ($usage[$date][$agent_key][$resource_key]['used'] ?? 0) + $amount;
        $usage[$date][$agent_key][$resource_key]['events'][] = [
            'amount' => $amount,
            'operation' => sanitize_text_field($operation),
            'metadata' => $metadata,
            'created_at' => current_time('mysql'),
        ];

        return update_user_meta($user_id, $this->usage_meta_key, $usage);
    }

    public function verify_x402_payment(string $payment): bool {
        $payment = trim($payment);
        if ($payment === '') {
            return false;
        }
        return (bool) apply_filters('bitcore_eliza_x402_verify', false, $payment);
    }

    public function get_agent_limit(int $user_id): int {
        $tier = $this->get_user_tier($user_id);
        $limits = $this->get_tier_limits($tier);
        return max(0, (int) ($limits['agent_limit'] ?? 0));
    }

    /**
     * Provision a user after a verified subscription purchase.
     *
     * This method is payment-provider agnostic. Checkout, WooCommerce,
     * PMPro, EDD, or x402 adapters should call it only after payment is
     * verified by their own integration layer.
     *
     * @param array<string,mixed> $metadata Sanitized before persistence.
     */
    public function provision_subscription_purchase(int $user_id, string $tier, array $metadata = []): bool {
        if ($user_id <= 0) {
            return false;
        }

        $tier = sanitize_text_field($tier);
        if ($tier === '' || !isset($this->tiers[$tier])) {
            return false;
        }

        $limits = $this->get_tier_limits($tier);
        $agent_limit = max(0, (int) ($limits['agent_limit'] ?? 0));
        $purchased_at = current_time('mysql');
        $clean_metadata = [];

        foreach ($metadata as $key => $value) {
            if (!is_string($key) || !is_scalar($value)) {
                continue;
            }
            $clean_key = sanitize_text_field($key);
            if ($clean_key === '') {
                continue;
            }
            $clean_metadata[$clean_key] = sanitize_text_field((string) $value);
        }

        update_user_meta($user_id, $this->tier_meta_key, $tier);
        update_user_meta($user_id, 'bitcore_agent_limit', $agent_limit);
        update_user_meta($user_id, 'bitcore_subscription_purchased_at', $purchased_at);
        update_user_meta($user_id, 'bitcore_subscription_last_purchase', array_merge($clean_metadata, [
            'tier' => $tier,
            'agent_limit' => $agent_limit,
            'purchased_at' => $purchased_at,
        ]));

        do_action('bitcore_subscription_provisioned', $user_id, $tier, $limits, $clean_metadata);

        return get_user_meta($user_id, $this->tier_meta_key, true) === $tier
            && (int) get_user_meta($user_id, 'bitcore_agent_limit', true) === $agent_limit;
    }

    private function get_used_today(int $user_id, string $agent_id, string $resource_type): int {
        $usage = $this->get_usage($user_id);
        $date = $this->today();
        $agent_key = sanitize_text_field($agent_id !== '' ? $agent_id : 'default');
        $resource_key = sanitize_text_field($resource_type !== '' ? $resource_type : 'api_calls');
        return max(0, (int) ($usage[$date][$agent_key][$resource_key]['used'] ?? 0));
    }

    /** @return array<string,mixed> */
    private function get_usage(int $user_id): array {
        $usage = get_user_meta($user_id, $this->usage_meta_key, true);
        return is_array($usage) ? $usage : [];
    }

    private function get_user_tier(int $user_id): string {
        if ($user_id <= 0) {
            return 'free';
        }
        $tier = get_user_meta($user_id, $this->tier_meta_key, true);
        $tier = is_string($tier) && $tier !== '' ? sanitize_text_field($tier) : 'core';
        return isset($this->tiers[$tier]) ? $tier : 'free';
    }

    /** @param array<string,mixed> $limits */
    private function normalize_limits(array $limits): array {
        return [
            'daily_api_calls' => max(0, (int) ($limits['daily_api_calls'] ?? 0)),
            'monthly_price_usd' => (float) ($limits['monthly_price_usd'] ?? 0.0),
            'agent_limit' => max(0, (int) ($limits['agent_limit'] ?? 0)),
        ];
    }

    private function today(): string {
        return substr(current_time('mysql'), 0, 10);
    }
}
