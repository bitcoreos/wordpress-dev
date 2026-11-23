<?php
/**
 * Budget Incident Record
 *
 * Tracks individual budget consumption events.
 *
 * @package BITCORE\Infrastructure\Budget
 * @since       1.0.0
 * @version     1.0.0
 */

namespace BITCORE\Infrastructure\Budget;

if (!defined('ABSPATH')) exit;

/**
 * Class BudgetIncident
 */
class BudgetIncident {
    public int $id;
    public string $agent_id;
    public ?int $policy_id;
    public string $resource_type;
    public float $amount;
    public string $operation;
    public array $metadata;
    public string $created_at;

    /**
     * Create from database row
     */
    public static function from_row(object $row): self {
        $incident = new self();
        $incident->id = $row->id;
        $incident->agent_id = $row->agent_id;
        $incident->policy_id = $row->policy_id ?? null;
        $incident->resource_type = $row->resource_type;
        $incident->amount = (float) $row->amount;
        $incident->operation = $row->operation ?? '';
        $incident->metadata = isset($row->metadata) ? json_decode($row->metadata, true) : [];
        $incident->created_at = $row->created_at;
        return $incident;
    }

    /**
     * Convert to array for API responses
     */
    public function to_array(): array {
        return [
            'id' => $this->id,
            'agent_id' => $this->agent_id,
            'policy_id' => $this->policy_id,
            'resource_type' => $this->resource_type,
            'amount' => $this->amount,
            'operation' => $this->operation,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at
        ];
    }
}
