<?php
/**
 * Budget Manager for Agent Resource Allocation
 *
 * Enforces per-agent budget limits with incident tracking.
 * Ported from Paperclip's budget enforcement patterns.
 *
 * @package BITCORE\Infrastructure\Budget
 * @since       1.0.0
 * @version     1.0.0
 */

namespace BITCORE\Infrastructure\Budget;

if (!defined('ABSPATH')) exit;

/**
 * Class BudgetManager
 *
 * Manages agent budgets, consumption tracking, and limit enforcement.
 */
class BudgetManager {
    /** @var string Budget policies table */
    private string $policies_table;

    /** @var string Budget incidents table */
    private string $incidents_table;

    const THRESHOLD_WARNING = 0.8;  // 80% warning threshold
    const THRESHOLD_CRITICAL = 0.95; // 95% critical threshold

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->policies_table = $wpdb->prefix . 'bitcore_budget_policies';
        $this->incidents_table = $wpdb->prefix . 'bitcore_budget_incidents';
    }

    /**
     * Check if agent can perform an operation within budget
     *
     * @param string $agent_id Agent identifier
     * @param float $estimated_cost Estimated operation cost
     * @param string $resource_type Resource type (tokens, api_calls, etc.)
     * @return array Result with allowed status and remaining budget
     */
    public function can_perform(string $agent_id, float $estimated_cost, string $resource_type = 'default'): array {
        $policy = $this->get_policy($agent_id, $resource_type);

        if (!$policy) {
            return ['allowed' => true, 'reason' => 'no_policy'];
        }

        $consumed = $this->get_consumed($agent_id, $resource_type);
        $remaining = $policy->limit - $consumed;

        if ($estimated_cost > $remaining) {
            return [
                'allowed' => false,
                'reason' => 'insufficient_budget',
                'remaining' => $remaining,
                'required' => $estimated_cost,
                'policy_id' => $policy->id
            ];
        }

        return [
            'allowed' => true,
            'remaining' => $remaining,
            'policy_id' => $policy->id,
            'warning' => ($remaining - $estimated_cost) < ($policy->limit * (1 - self::THRESHOLD_WARNING))
        ];
    }

    /**
     * Record consumption against budget
     *
     * @param string $agent_id Agent identifier
     * @param float $amount Amount consumed
     * @param string $resource_type Resource type
     * @param string $operation Operation description
     * @param array $metadata Additional context
     * @return bool Success status
     */
    public function consume(string $agent_id, float $amount, string $resource_type = 'default', string $operation = '', array $metadata = []): bool {
        global $wpdb;

        $policy = $this->get_policy($agent_id, $resource_type);

        $result = $wpdb->insert($this->incidents_table, [
            'agent_id' => $agent_id,
            'policy_id' => $policy ? $policy->id : null,
            'resource_type' => $resource_type,
            'amount' => $amount,
            'operation' => $operation,
            'metadata' => json_encode($metadata),
            'created_at' => current_time('mysql')
        ]);

        if ($result === false) {
            return false;
        }

        // Check for threshold breaches
        if ($policy) {
            $this->check_thresholds($agent_id, $policy, $amount);
        }

        return true;
    }

    /**
     * Get budget policy for agent/resource
     *
     * @param string $agent_id Agent identifier
     * @param string $resource_type Resource type
     * @return object|null Policy record or null
     */
    public function get_policy(string $agent_id, string $resource_type = 'default'): ?object {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->policies_table} 
            WHERE agent_id = %s AND resource_type = %s AND is_active = 1
            LIMIT 1",
            $agent_id, $resource_type
        ));
    }

    /**
     * Get total consumed for agent/resource in current period
     *
     * @param string $agent_id Agent identifier
     * @param string $resource_type Resource type
     * @return float Total consumed
     */
    public function get_consumed(string $agent_id, string $resource_type = 'default'): float {
        global $wpdb;

        $policy = $this->get_policy($agent_id, $resource_type);
        if (!$policy) {
            return 0.0;
        }

        // Calculate based on period
        $period_start = $this->get_period_start($policy->period);

        $sum = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount) FROM {$this->incidents_table} 
            WHERE agent_id = %s AND resource_type = %s AND created_at >= %s",
            $agent_id, $resource_type, $period_start
        ));

        return (float) $sum;
    }

    /**
     * Create or update budget policy
     *
     * @param string $agent_id Agent identifier
     * @param float $limit Budget limit
     * @param string $resource_type Resource type
     * @param string $period Period (hourly, daily, weekly, monthly)
     * @return int|false Policy ID or false on failure
     */
    public function set_policy(string $agent_id, float $limit, string $resource_type = 'default', string $period = 'daily') {
        global $wpdb;

        $existing = $this->get_policy($agent_id, $resource_type);

        if ($existing) {
            $wpdb->update(
                $this->policies_table,
                ['limit_amount' => $limit, 'period' => $period, 'updated_at' => current_time('mysql')],
                ['id' => $existing->id]
            );
            return $existing->id;
        }

        $wpdb->insert($this->policies_table, [
            'agent_id' => $agent_id,
            'resource_type' => $resource_type,
            'limit_amount' => $limit,
            'period' => $period,
            'is_active' => 1,
            'created_at' => current_time('mysql')
        ]);

        return $wpdb->insert_id;
    }

    /**
     * Get period start datetime
     *
     * @param string $period Period type
     * @return string MySQL datetime
     */
    private function get_period_start(string $period): string {
        switch ($period) {
            case 'hourly':
                return date('Y-m-d H:00:00');
            case 'daily':
                return date('Y-m-d 00:00:00');
            case 'weekly':
                return date('Y-m-d 00:00:00', strtotime('monday this week'));
            case 'monthly':
                return date('Y-m-01 00:00:00');
            default:
                return date('Y-m-d 00:00:00');
        }
    }

    /**
     * Check and emit threshold warnings
     *
     * @param string $agent_id Agent identifier
     * @param object $policy Budget policy
     * @param float $just_consumed Amount just consumed
     */
    private function check_thresholds(string $agent_id, object $policy, float $just_consumed): void {
        $consumed = $this->get_consumed($agent_id, $policy->resource_type);
        $ratio = $consumed / $policy->limit;

        if ($ratio >= self::THRESHOLD_CRITICAL) {
            do_action('bitcore_budget_critical', $agent_id, $policy, $consumed, $ratio);
        } elseif ($ratio >= self::THRESHOLD_WARNING) {
            do_action('bitcore_budget_warning', $agent_id, $policy, $consumed, $ratio);
        }
    }

    /**
     * Reset budget for agent (start new period)
     *
     * @param string $agent_id Agent identifier
     * @param string $resource_type Resource type
     * @return bool Success status
     */
    public function reset(string $agent_id, string $resource_type = 'default'): bool {
        global $wpdb;

        return $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->incidents_table} 
            WHERE agent_id = %s AND resource_type = %s",
            $agent_id, $resource_type
        )) !== false;
    }

    /**
     * Get budget status summary
     *
     * @param string $agent_id Agent identifier
     * @return array Budget status by resource type
     */
    public function get_status(string $agent_id): array {
        global $wpdb;

        $policies = $wpdb->get_results($wpdb->prepare(
            "SELECT *, limit_amount AS `limit` FROM {$this->policies_table} WHERE agent_id = %s AND is_active = 1",
            $agent_id
        ));

        $status = [];
        foreach ($policies as $policy) {
            $consumed = $this->get_consumed($agent_id, $policy->resource_type);
            $status[$policy->resource_type] = [
                'limit' => $policy->limit,
                'consumed' => $consumed,
                'remaining' => $policy->limit - $consumed,
                'period' => $policy->period,
                'percentage_used' => round(($consumed / $policy->limit) * 100, 2)
            ];
        }

        return $status;
    }

    /**
     * Get all agent budgets (for dashboard listing)
     *
     * @return array List of agent budget summaries
     */
    public function get_all_budgets(): array {
        global $wpdb;

        $policies = $wpdb->get_results(
            "SELECT DISTINCT agent_id FROM {$this->policies_table} WHERE is_active = 1"
        );

        $budgets = [];
        foreach ($policies as $policy) {
            $status = $this->get_status($policy->agent_id);
            foreach ($status as $resource_type => $info) {
                $budgets[] = [
                    'agent' => $policy->agent_id,
                    'name' => $policy->agent_id,
                    'spent' => $info['consumed'] ?? 0,
                    'limit' => $info['limit'] ?? 100,
                    'budget' => $info['limit'] ?? 100,
                    'resource_type' => $resource_type,
                    'period' => $info['period'] ?? 'daily',
                    'percentage_used' => $info['percentage_used'] ?? 0
                ];
            }
        }

        return $budgets;
    }
}
