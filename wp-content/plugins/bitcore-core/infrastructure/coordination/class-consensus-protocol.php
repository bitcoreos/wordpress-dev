<?php
/**
 * BITCORE Consensus Protocol
 * Decision-making logic for agent coordination
 *
 * @package BITCORE\Coordination
 * @since 1.3.0
 */

declare(strict_types=1);

namespace BITCORE\Coordination;

/**
 * Class ConsensusProtocol
 * Implements consensus mechanisms for TRINITY coordination
 */
class ConsensusProtocol {

    const TYPE_UNANIMOUS = 'unanimous';
    const TYPE_MAJORITY = 'majority';
    const TYPE_WEIGHTED = 'weighted';
    const TYPE_USER_DECIDES = 'user_decides';

    private string $type;
    private array $agent_weights;
    private float $threshold;

    public function __construct(
        string $type = self::TYPE_MAJORITY,
        array $agent_weights = ['hyperion' => 2, 'coraline' => 1, 'hermes' => 1],
        float $threshold = 0.5
    ) {
        $this->type = $type;
        $this->agent_weights = $agent_weights;
        $this->threshold = $threshold;
    }

    /**
     * Evaluate consensus on a vote engine result
     */
    public function evaluate(VoteEngine $engine): array {
        $tally = $engine->get_tally();
        $winner = $engine->get_winner();
        $participation = $engine->get_participation();
        $total_weight = array_sum($this->agent_weights);
        $winner_weight = $tally[$winner] ?? 0;
        $consensus_ratio = $winner_weight / max(1, $total_weight);

        $decision = $this->make_decision($consensus_ratio, $participation, count($tally));

        return [
            'type' => $this->type,
            'winner' => $winner,
            'consensus_ratio' => $consensus_ratio,
            'threshold' => $this->threshold,
            'participation' => $participation,
            'decision' => $decision,
            'status' => $decision === 'approved' ? 'consensus_reached' : 'no_consensus',
            'requires_user_input' => $decision === 'user_decision_needed',
        ];
    }

    /**
     * Make decision based on consensus type
     */
    private function make_decision(float $ratio, float $participation, int $option_count): string {
        return match ($this->type) {
            self::TYPE_UNANIMOUS => $ratio >= 1.0 ? 'approved' : 'rejected',
            self::TYPE_MAJORITY => $ratio >= $this->threshold ? 'approved' : 'rejected',
            self::TYPE_WEIGHTED => $ratio >= $this->threshold ? 'approved' : 'rejected',
            self::TYPE_USER_DECIDES => $ratio >= $this->threshold ? 'approved' : 'user_decision_needed',
            default => 'unknown',
        };
    }

    /**
     * Evaluate bid auction and select winner
     */
    public function evaluate_bids(array $bids): array {
        if (empty($bids)) {
            return ['winner' => null, 'reason' => 'no_bids'];
        }

        usort($bids, fn($a, $b) => $b->calculate_score() <=> $a->calculate_score());
        $winner = $bids[0];

        return [
            'winner' => $winner->agent_id,
            'winner_bid' => $winner->to_array(),
            'score' => $winner->calculate_score(),
            'total_bids' => count($bids),
            'all_bids' => array_map(fn($b) => $b->to_array(), $bids),
        ];
    }

    /**
     * Set agent weight for weighted consensus
     */
    public function set_weight(string $agent_id, float $weight): void {
        $this->agent_weights[$agent_id] = $weight;
    }
}
