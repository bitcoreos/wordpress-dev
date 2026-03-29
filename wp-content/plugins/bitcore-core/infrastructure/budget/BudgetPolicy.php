<?php
/**
 * Budget Policy Value Object
 *
 * Represents a budget policy configuration for an agent.
 *
 * @package BITCORE\Infrastructure\Budget
 */

namespace BITCORE\Infrastructure\Budget;

if (!defined('ABSPATH')) exit;

/**
 * Class BudgetPolicy
 */
class BudgetPolicy {
    public int $id;
    public string $agent_id;
    public string $resource_type;
    public float $limit;
    public string $period;
    public bool $is_active;
    public array $thresholds;
    public string $created_at;
    public ?string $updated_at = null;

    const PERIOD_HOURLY = 'hourly';
    const PERIOD_DAILY = 'daily';
    const PERIOD_WEEKLY = 'weekly';
    const PERIOD_MONTHLY = 'monthly';

    const RESOURCE_TOKENS = 'tokens';
    const RESOURCE_API_CALLS = 'api_calls';
    const RESOURCE_COMPUTE = 'compute';
    const RESOURCE_STORAGE = 'storage';

    /**
     * Create from database row
     */
    public static function from_row(object $row): self {
        $policy = new self();
        $policy->id = $row->id;
        $policy->agent_id = $row->agent_id;
        $policy->resource_type = $row->resource_type;
        $policy->limit = (float) $row->limit;
        $policy->period = $row->period;
        $policy->is_active = (bool) $row->is_active;
        $policy->thresholds = isset($row->thresholds) ? json_decode($row->thresholds, true) : [];
        $policy->created_at = $row->created_at;
        $policy->updated_at = $row->updated_at ?? null;
        return $policy;
    }

    /**
     * Check if amount exceeds limit
     */
    public function exceeds_limit(float $amount): bool {
        return $amount > $this->limit;
    }

    /**
     * Get utilization percentage
     */
    public function get_utilization(float $consumed): float {
        return ($consumed / $this->limit) * 100;
    }

    /**
     * Check if at warning threshold
     */
    public function is_at_warning(float $consumed): bool {
        $warning = $this->thresholds['warning'] ?? 0.8;
        return $this->get_utilization($consumed) >= ($warning * 100);
    }

    /**
     * Check if at critical threshold
     */
    public function is_at_critical(float $consumed): bool {
        $critical = $this->thresholds['critical'] ?? 0.95;
        return $this->get_utilization($consumed) >= ($critical * 100);
    }
}
