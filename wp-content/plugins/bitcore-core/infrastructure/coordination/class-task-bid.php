<?php
/**
 * BITCORE Task Bid System
 * General-purpose bidding for tasks, projects, and assignments
 *
 * @package BITCORE_Core
 * @since 1.3.0
 */

declare(strict_types=1);

/**
 * Class BITCORE_Coordination_TaskBid
 * Represents an agent's bid on any entity (task, project, mission, etc.)
 */
class BITCORE_Coordination_TaskBid {

    public string $id;
    public string $entity_type;
    public int $entity_id;
    public string $agent_id;
    public float $confidence;
    public int $estimated_tokens;
    public int $estimated_seconds;
    public int $priority;
    public string $rationale;
    public array $capabilities;
    public int $created_at;

    public function __construct(
        string $entity_type,
        int $entity_id,
        string $agent_id,
        float $confidence = 0.5,
        int $estimated_tokens = 0,
        int $estimated_seconds = 0,
        int $priority = 5,
        string $rationale = '',
        array $capabilities = []
    ) {
        $this->id = 'bid_' . bin2hex(random_bytes(8));
        $this->entity_type = $entity_type;
        $this->entity_id = $entity_id;
        $this->agent_id = $agent_id;
        $this->confidence = max(0.0, min(1.0, $confidence));
        $this->estimated_tokens = $estimated_tokens;
        $this->estimated_seconds = $estimated_seconds;
        $this->priority = max(1, min(10, $priority));
        $this->rationale = $rationale;
        $this->capabilities = $capabilities;
        $this->created_at = time();
    }

    public function calculate_score(): float {
        return ($this->confidence * 50) + 
               ($this->priority * 5) - 
               ($this->estimated_tokens / 1000) - 
               ($this->estimated_seconds / 60);
    }

    public function to_array(): array {
        return [
            'id' => $this->id,
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'agent_id' => $this->agent_id,
            'confidence' => $this->confidence,
            'estimated_tokens' => $this->estimated_tokens,
            'estimated_seconds' => $this->estimated_seconds,
            'priority' => $this->priority,
            'rationale' => $this->rationale,
            'capabilities' => $this->capabilities,
            'score' => $this->calculate_score(),
            'created_at' => $this->created_at,
        ];
    }
}
