<?php
/**
 * BITCORE Vote Engine
 * General-purpose voting system for decisions, tasks, and coordination
 *
 * @package BITCORE_Core
 * @since 1.3.0
 */

declare(strict_types=1);

/**
 * Class BITCORE_Coordination_VoteEngine
 * Handles voting on proposals, tasks, direction, and assignments
 */
class BITCORE_Coordination_VoteEngine {

    public array $votes = [];
    private string $entity_type;
    private int $entity_id;
    private array $voters = [];
    private array $options = [];
    private int $deadline;

    public function __construct(
        string $entity_type,
        int $entity_id,
        array $options,
        array $voters,
        int $deadline_seconds = 300
    ) {
        $this->entity_type = $entity_type;
        $this->entity_id = $entity_id;
        $this->options = $options;
        $this->voters = $voters;
        $this->deadline = time() + $deadline_seconds;
    }

    public function cast_vote(string $voter_id, string $option, float $weight = 1.0, string $rationale = ''): bool {
        if (!in_array($voter_id, $this->voters, true)) { return false; }
        if (!in_array($option, $this->options, true)) { return false; }
        if (time() > $this->deadline) { return false; }

        $this->votes[$voter_id] = [
            'option' => $option,
            'weight' => $weight,
            'rationale' => $rationale,
            'timestamp' => time(),
        ];
        return true;
    }

    public function is_complete(): bool {
        if (time() > $this->deadline) { return true; }
        return count($this->votes) === count($this->voters);
    }

    public function get_tally(): array {
        $tally = array_fill_keys($this->options, 0.0);
        foreach ($this->votes as $vote) {
            $tally[$vote['option']] += $vote['weight'];
        }
        arsort($tally);
        return $tally;
    }

    public function get_winner(): ?string {
        if (!$this->is_complete()) { return null; }
        $tally = $this->get_tally();
        return array_key_first($tally);
    }

    public function get_participation(): float {
        return count($this->votes) / max(1, count($this->voters));
    }

    public static function from_array(array $data): self {
        $engine = new self(
            $data['entity_type'] ?? '',
            $data['entity_id'] ?? 0,
            $data['options'] ?? [],
            $data['voters'] ?? [],
            0
        );
        $engine->votes = $data['votes'] ?? [];
        $engine->deadline = $data['deadline'] ?? time();
        return $engine;
    }

    public function to_array(): array {
        return [
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'options' => $this->options,
            'voters' => $this->voters,
            'votes' => $this->votes,
            'deadline' => $this->deadline,
            'is_complete' => $this->is_complete(),
            'tally' => $this->get_tally(),
            'winner' => $this->get_winner(),
            'participation' => $this->get_participation(),
        ];
    }
}
