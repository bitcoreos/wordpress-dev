<?php
/**
 * BITCORE Vote Engine
 * General-purpose voting system for decisions, tasks, and coordination
 *
 * @package BITCORE\Coordination
 * @since 1.3.0
 */

declare(strict_types=1);

namespace BITCORE\Coordination;

/**
 * Class VoteEngine
 * Handles voting on proposals, tasks, direction, and assignments
 */
class VoteEngine {

    private array $votes = [];
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

    /**
     * Cast a vote
     */
    public function cast_vote(string $voter_id, string $option, float $weight = 1.0, string $rationale = ''): bool {
        if (!in_array($voter_id, $this->voters, true)) {
            return false; // Not an authorized voter
        }
        if (!in_array($option, $this->options, true)) {
            return false; // Invalid option
        }
        if (time() > $this->deadline) {
            return false; // Voting closed
        }

        $this->votes[$voter_id] = [
            'option' => $option,
            'weight' => $weight,
            'rationale' => $rationale,
            'timestamp' => time(),
        ];
        return true;
    }

    /**
     * Check if voting is complete (all voters voted or deadline passed)
     */
    public function is_complete(): bool {
        if (time() > $this->deadline) {
            return true;
        }
        return count($this->votes) === count($this->voters);
    }

    /**
     * Get current tally
     */
    public function get_tally(): array {
        $tally = array_fill_keys($this->options, 0.0);
        foreach ($this->votes as $vote) {
            $tally[$vote['option']] += $vote['weight'];
        }
        arsort($tally);
        return $tally;
    }

    /**
     * Get winner (option with most weighted votes)
     */
    public function get_winner(): ?string {
        if (!$this->is_complete()) {
            return null;
        }
        $tally = $this->get_tally();
        return array_key_first($tally);
    }

    /**
     * Get participation rate
     */
    public function get_participation(): float {
        return count($this->votes) / max(1, count($this->voters));
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
