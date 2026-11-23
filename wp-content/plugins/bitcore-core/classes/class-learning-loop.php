<?php
declare(strict_types=1);
/**
 * Learning Loop - Episode Scoring and Knowledge Retention
 *
 * Manages learning episodes, scoring, and knowledge retention for AI agents.
 * Pattern derived from DashClaw learningLoop.repository.js
 *
 * @package BITCORE
 * @since 1.0.2
 * @version     1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/traits/trait-learning-loop-schema.php';
require_once __DIR__ . '/traits/trait-learning-loop-episodes.php';
require_once __DIR__ . '/traits/trait-learning-loop-recommendations.php';
class BITCORE_Learning_Loop {
    use Learning_Loop_Schema;
    use Learning_Loop_Episodes;
    use Learning_Loop_Recommendations;


    /**
     * Table name for learning episodes
     *
     * @var string
     */
    private $episodes_table;

    /**
     * Table name for learning recommendations
     *
     * @var string
     */
    private $recommendations_table;

    /**
     * WordPress database instance
     *
     * @var wpdb
     */
    private $wpdb;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->episodes_table = $wpdb->prefix . 'bitcore_learning_episodes';
        $this->recommendations_table = $wpdb->prefix . 'bitcore_learning_recommendations';
    }


    /**
     * Compute deterministic intelligence score for an action
     *
     * Uses real learning episode and recommendation data.
     * Same action_id yields same score on the same day.
     * Falls back to 0.5 if no data available.
     *
     * @param string $action_id Action identifier
     * @param string $org_id     Organization ID (default: org_default)
     * @return array{score: float, breakdown: array, computed_at: string}
     */
    public function compute_action_score( $action_id, $org_id = 'org_default' ) {
        $action_id = sanitize_text_field( $action_id );
        $org_id    = sanitize_text_field( $org_id );
        $now       = current_time( 'mysql' );
        $breakdown = [];

        // --- Strategy 1: Look up existing learning episode for this action ---
        $episode = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT score, confidence, risk_score, outcome_label, action_type, agent_id 
                 FROM {$this->episodes_table} 
                 WHERE action_id = %s AND org_id = %s 
                 ORDER BY updated_at DESC LIMIT 1",
                $action_id,
                $org_id
            )
        );

        if ( $episode && ! is_null( $episode->score ) ) {
            // Derive score from episode data (scale 0-100 → 0.0-1.0)
            $raw_score     = max( 0, min( 100, (float) $episode->score ) ) / 100;
            $confidence     = max( 0, min( 100, (float) $episode->confidence ) ) / 100;
            $risk_penalty   = max( 0, min( 100, (float) $episode->risk_score ) ) / 100;

            // Weighted formula: 70% episode score, 20% confidence, 10% risk mitigation
            $final_score = ( $raw_score * 0.70 )
                         + ( $confidence * 0.20 )
                         + ( ( 1 - $risk_penalty ) * 0.10 );

            // Apply outcome modifier
            if ( ! empty( $episode->outcome_label ) ) {
                $outcome_modifiers = [
                    'success'  =>  0.05,
                    'partial'  =>  0.00,
                    'failure'  => -0.10,
                    'error'    => -0.15,
                ];
                $modifier = $outcome_modifiers[ strtolower( $episode->outcome_label ) ] ?? 0;
                $final_score += $modifier;
            }

            $breakdown = [
                'source'        => 'episode',
                'episode_score' => round( $raw_score, 4 ),
                'confidence'    => round( $confidence, 4 ),
                'risk_score'     => round( $risk_penalty, 4 ),
                'outcome'       => $episode->outcome_label ?? 'unknown',
                'action_type'   => $episode->action_type ?? 'unknown',
                'agent_id'      => $episode->agent_id ?? 'unknown',
            ];

            return [
                'score'       => round( max( 0, min( 1, $final_score ) ), 4 ),
                'breakdown'   => $breakdown,
                'computed_at' => $now,
            ];
        }

        // --- Strategy 2: Look up recommendations by action_type hint ---
        // Derive action_type from action_id prefix (e.g., "task_create_xyz" → "task_create")
        $action_type_hint = preg_replace( '/_[a-z0-9]+$/', '', $action_id );

        $recommendation = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT avg_score, success_rate, confidence, sample_size 
                 FROM {$this->recommendations_table} 
                 WHERE org_id = %s AND action_type = %s AND active = 1 
                 ORDER BY confidence DESC LIMIT 1",
                $org_id,
                $action_type_hint
            )
        );

        if ( $recommendation && $recommendation->sample_size > 0 ) {
            $avg_score   = max( 0, min( 100, (float) $recommendation->avg_score ) ) / 100;
            $success_rate = max( 0, min( 1, (float) $recommendation->success_rate ) );
            $confidence  = max( 0, min( 1, (float) $recommendation->confidence ) );
            $sample_mod  = min( 1, log( 1 + $recommendation->sample_size ) / log( 101 ) );

            // Weighted: 50% avg_score, 30% success_rate, 20% confidence adjusted by sample size
            $final_score = ( $avg_score * 0.50 )
                         + ( $success_rate * 0.30 )
                         + ( $confidence * $sample_mod * 0.20 );

            $breakdown = [
                'source'       => 'recommendation',
                'avg_score'    => round( $avg_score, 4 ),
                'success_rate' => round( $success_rate, 4 ),
                'confidence'   => round( $confidence, 4 ),
                'sample_size'  => (int) $recommendation->sample_size,
                'action_type'  => $action_type_hint,
            ];

            return [
                'score'       => round( max( 0, min( 1, $final_score ) ), 4 ),
                'breakdown'   => $breakdown,
                'computed_at' => $now,
            ];
        }

        // --- Strategy 3: Hash-based deterministic fallback ---
        // Produces a stable value for the same action_id on the same day
        $day_seed   = gmdate( 'Y-m-d' );
        $hash_input = $org_id . '|' . $action_id . '|' . $day_seed;
        $hash_val   = hexdec( substr( hash( 'sha256', $hash_input ), 0, 8 ) );
        $det_score  = ( $hash_val % 10000 ) / 10000; // 0.0 to ~0.9999
        // Center around 0.5 with ±0.3 variance
        $final_score = 0.5 + ( ( $det_score - 0.5 ) * 0.6 );

        $breakdown = [
            'source'      => 'hash_fallback',
            'day_seed'    => $day_seed,
            'action_type' => $action_type_hint,
        ];

        return [
            'score'       => round( max( 0, min( 1, $final_score ) ), 4 ),
            'breakdown'   => $breakdown,
            'computed_at' => $now,
        ];
    }
}
