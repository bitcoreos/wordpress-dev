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

trait Learning_Loop_Recommendations {

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
    }
}
