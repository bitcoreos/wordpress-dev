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
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BITCORE_Learning_Loop {

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
     * Create database tables on plugin activation
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Episodes table
        $episodes_table = $wpdb->prefix . 'bitcore_learning_episodes';
        $sql_episodes = "CREATE TABLE $episodes_table (
            id varchar(32) NOT NULL,
            org_id varchar(64) NOT NULL DEFAULT 'org_default',
            action_id varchar(64) NOT NULL,
            agent_id varchar(64) NOT NULL,
            action_type varchar(50) NOT NULL,
            status varchar(20),
            outcome_label varchar(50),
            risk_score float DEFAULT 0,
            reversible tinyint(1) DEFAULT 1,
            confidence float DEFAULT 50,
            duration_ms int DEFAULT NULL,
            cost_estimate float DEFAULT 0,
            invalidated_assumptions int DEFAULT 0,
            open_loops int DEFAULT 0,
            recommendation_id varchar(32),
            recommendation_applied tinyint(1) DEFAULT 0,
            score float DEFAULT 0,
            score_breakdown longtext,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_org_action (org_id, action_id),
            KEY idx_org_agent (org_id, agent_id),
            KEY idx_action_type (action_type),
            KEY idx_score (score)
        ) $charset_collate;";

        // Recommendations table
        $recommendations_table = $wpdb->prefix . 'bitcore_learning_recommendations';
        $sql_recommendations = "CREATE TABLE $recommendations_table (
            id varchar(32) NOT NULL,
            org_id varchar(64) NOT NULL DEFAULT 'org_default',
            agent_id varchar(64) NOT NULL,
            action_type varchar(50) NOT NULL,
            confidence float DEFAULT 0,
            sample_size int DEFAULT 0,
            top_sample_size int DEFAULT 0,
            success_rate float DEFAULT 0,
            avg_score float DEFAULT 0,
            hints longtext,
            guidance longtext,
            active tinyint(1) DEFAULT 1,
            computed_at datetime NOT NULL,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_org_agent_type (org_id, agent_id, action_type),
            KEY idx_active (active)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql_episodes );
        dbDelta( $sql_recommendations );
    }

    /**
     * Generate unique episode ID
     *
     * @return string
     */
    private function generate_id( $prefix = 'lep' ) {
        return $prefix . '_' . substr( wp_generate_uuid4(), 0, 24 );
    }

    /**
     * Upsert learning episode
     *
     * @param string $orgId Organization ID
     * @param array  $source Source data
     * @param array  $scored Scored data
     * @return object|false
     */
    public function upsert_episode( $orgId, $source, $scored ) {
        $id = $this->generate_id( 'lep' );
        $now = current_time( 'mysql' );

        // Check if exists
        $existing = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->episodes_table} WHERE org_id = %s AND action_id = %s",
                $orgId,
                $source['action_id']
            )
        );

        $data = [
            'org_id' => $orgId,
            'action_id' => $source['action_id'],
            'agent_id' => $source['agent_id'],
            'action_type' => $source['action_type'],
            'status' => $source['status'] ?? null,
            'outcome_label' => $scored['outcome_label'] ?? null,
            'risk_score' => $source['risk_score'] ?? 0,
            'reversible' => $source['reversible'] ?? 1,
            'confidence' => $source['confidence'] ?? 50,
            'duration_ms' => $source['duration_ms'] ?? null,
            'cost_estimate' => $source['cost_estimate'] ?? 0,
            'invalidated_assumptions' => $source['invalidated_assumptions'] ?? 0,
            'open_loops' => $source['open_loops'] ?? 0,
            'recommendation_id' => $source['recommendation_id'] ?? null,
            'recommendation_applied' => $source['recommendation_applied'] ?? 0,
            'score' => $scored['score'] ?? 0,
            'score_breakdown' => isset( $scored['breakdown'] ) ? wp_json_encode( $scored['breakdown'] ) : null,
            'updated_at' => $now,
        ];

        if ( $existing ) {
            $result = $this->wpdb->update(
                $this->episodes_table,
                $data,
                [ 'id' => $existing->id ],
                [ '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%d', '%f', '%d', '%f', '%d', '%d', '%s', '%d', '%f', '%s', '%s' ],
                [ '%s' ]
            );
            $id = $existing->id;
        } else {
            $data['id'] = $id;
            $data['created_at'] = $source['timestamp_start'] ?? $now;
            $result = $this->wpdb->insert(
                $this->episodes_table,
                $data,
                [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%d', '%f', '%d', '%f', '%d', '%d', '%s', '%d', '%f', '%s', '%s', '%s' ]
            );
        }

        if ( false === $result ) {
            return false;
        }

        return $this->get_episode( $id );
    }

    /**
     * Get episode by ID
     *
     * @param string $id Episode ID
     * @return object|null
     */
    public function get_episode( $id ) {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->episodes_table} WHERE id = %s",
                $id
            )
        );

        if ( $row ) {
            $row->recommendation_applied = (bool) $row->recommendation_applied;
            $row->reversible = (bool) $row->reversible;
            $row->score_breakdown = $row->score_breakdown ? json_decode( $row->score_breakdown, true ) : [];
        }

        return $row;
    }

    /**
     * List learning episodes
     *
     * @param string $orgId Organization ID
     * @param array  $filters Filter options
     * @return array
     */
    public function list_episodes( $orgId, $filters = [] ) {
        $where = [ 'org_id = %s' ];
        $values = [ $orgId ];

        if ( ! empty( $filters['agent_id'] ) ) {
            $where[] = 'agent_id = %s';
            $values[] = $filters['agent_id'];
        }

        if ( ! empty( $filters['action_type'] ) ) {
            $where[] = 'action_type = %s';
            $values[] = $filters['action_type'];
        }

        $limit = intval( $filters['limit'] ?? 5000 );
        $sql = "SELECT * FROM {$this->episodes_table} WHERE " . implode( ' AND ', $where ) . " ORDER BY updated_at DESC LIMIT %d";
        $values[] = $limit;

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare( $sql, $values )
        );

        foreach ( $rows as $row ) {
            $row->recommendation_applied = (bool) $row->recommendation_applied;
            $row->reversible = (bool) $row->reversible;
            $row->score_breakdown = $row->score_breakdown ? json_decode( $row->score_breakdown, true ) : [];
        }

        return $rows;
    }

    /**
     * List unscored action IDs
     *
     * @param string $orgId Organization ID
     * @param array  $filters Filter options
     * @return array
     */
    public function list_unscored_actions( $orgId, $filters = [] ) {
        $actions_table = $this->wpdb->prefix . 'bitcore_action_records';
        $lookback_days = max( 1, min( intval( $filters['lookback_days'] ?? 30 ), 365 ) );
        $limit = max( 1, min( intval( $filters['limit'] ?? 1000 ), 20000 ) );

        $sql = "SELECT ar.action_id
            FROM {$actions_table} ar
            LEFT JOIN {$this->episodes_table} le
                ON le.org_id = ar.org_id AND le.action_id = ar.action_id
            WHERE ar.org_id = %s
                AND ar.timestamp_start > DATE_SUB(NOW(), INTERVAL %d DAY)
                AND le.action_id IS NULL
            ORDER BY ar.timestamp_start DESC
            LIMIT %d";

        return $this->wpdb->get_col(
            $this->wpdb->prepare( $sql, $orgId, $lookback_days, $limit )
        );
    }

    /**
     * Upsert learning recommendation
     *
     * @param string $orgId Organization ID
     * @param array  $recommendations Recommendations data
     * @return array
     */
    public function upsert_recommendations( $orgId, $recommendations ) {
        $saved = [];
        $now = current_time( 'mysql' );

        foreach ( $recommendations as $rec ) {
            $id = $this->generate_id( 'lrec' );

            $existing = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT id FROM {$this->recommendations_table} WHERE org_id = %s AND agent_id = %s AND action_type = %s",
                    $orgId,
                    $rec['agent_id'],
                    $rec['action_type']
                )
            );

            $data = [
                'org_id' => $orgId,
                'agent_id' => $rec['agent_id'],
                'action_type' => $rec['action_type'],
                'confidence' => $rec['confidence'] ?? 0,
                'sample_size' => $rec['sample_size'] ?? 0,
                'top_sample_size' => $rec['top_sample_size'] ?? 0,
                'success_rate' => $rec['success_rate'] ?? 0,
                'avg_score' => $rec['avg_score'] ?? 0,
                'hints' => isset( $rec['hints'] ) ? wp_json_encode( $rec['hints'] ) : null,
                'guidance' => isset( $rec['guidance'] ) ? wp_json_encode( $rec['guidance'] ) : null,
                'active' => isset( $rec['active'] ) && $rec['active'] === false ? 0 : 1,
                'computed_at' => $now,
                'updated_at' => $now,
            ];

            if ( $existing ) {
                $this->wpdb->update(
                    $this->recommendations_table,
                    $data,
                    [ 'id' => $existing->id ],
                    [ '%s', '%s', '%s', '%f', '%d', '%d', '%f', '%f', '%s', '%s', '%d', '%s', '%s' ],
                    [ '%s' ]
                );
                $id = $existing->id;
            } else {
                $data['id'] = $id;
                $this->wpdb->insert(
                    $this->recommendations_table,
                    $data,
                    [ '%s', '%s', '%s', '%s', '%f', '%d', '%d', '%f', '%f', '%s', '%s', '%d', '%s', '%s' ]
                );
            }

            $saved[] = $this->get_recommendation( $id );
        }

        return $saved;
    }

    /**
     * Get recommendation by ID
     *
     * @param string $id Recommendation ID
     * @return object|null
     */
    public function get_recommendation( $id ) {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->recommendations_table} WHERE id = %s",
                $id
            )
        );

        if ( $row ) {
            $row->hints = $row->hints ? json_decode( $row->hints, true ) : [];
            $row->guidance = $row->guidance ? json_decode( $row->guidance, true ) : [];
            $row->active = (bool) $row->active;
        }

        return $row;
    }

    /**
     * List learning recommendations
     *
     * @param string $orgId Organization ID
     * @param array  $filters Filter options
     * @return array
     */
    public function list_recommendations( $orgId, $filters = [] ) {
        $where = [ 'org_id = %s' ];
        $values = [ $orgId ];

        if ( ! empty( $filters['agent_id'] ) ) {
            $where[] = 'agent_id = %s';
            $values[] = $filters['agent_id'];
        }

        if ( ! empty( $filters['action_type'] ) ) {
            $where[] = 'action_type = %s';
            $values[] = $filters['action_type'];
        }

        if ( empty( $filters['include_inactive'] ) ) {
            $where[] = 'active = 1';
        }

        $limit = max( 1, min( intval( $filters['limit'] ?? 50 ), 200 ) );
        $sql = "SELECT * FROM {$this->recommendations_table} WHERE " . implode( ' AND ', $where ) . " ORDER BY confidence DESC, sample_size DESC LIMIT %d";
        $values[] = $limit;

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare( $sql, $values )
        );

        foreach ( $rows as $row ) {
            $row->hints = $row->hints ? json_decode( $row->hints, true ) : [];
            $row->guidance = $row->guidance ? json_decode( $row->guidance, true ) : [];
            $row->active = (bool) $row->active;
        }

        return $rows;

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
