<?php
declare(strict_types=1);
/**
 * Learning Loop - trait
 *
 * @package BITCORE
 * @since 1.0.2
 * @version     1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait Learning_Loop_Episodes {

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
}
