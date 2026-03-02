<?php
/**
 * BITCORE_Activity_Feed implementation
 *
 * @package    Bitcore
 * @since      2026.03.01
 * @version    1.3.0
 * @author     hyperion
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Activity Feed - Activity Aggregation
 *
 * Aggregates activity across multiple tables for digest generation.
 * Pattern derived from DashClaw digest.repository.js
 *
 * @package BITCORE
 * @since 1.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BITCORE_Activity_Feed {

    /**
     * Table configuration for digest data
     *
     * @var array
     */
    private $table_config = [
        [
            'key' => 'actions',
            'table' => 'bitcore_action_records',
            'ts_col' => 'timestamp_start',
            'cols' => 'action_id, action_type, declared_goal, status, risk_score, timestamp_start',
        ],
        [
            'key' => 'decisions',
            'table' => 'bitcore_decisions',
            'ts_col' => 'timestamp',
            'cols' => 'id, decision, outcome, timestamp',
        ],
        [
            'key' => 'lessons',
            'table' => 'bitcore_lessons',
            'ts_col' => 'timestamp',
            'cols' => 'id, lesson, content, timestamp',
        ],
        [
            'key' => 'content',
            'table' => 'bitcore_content',
            'ts_col' => 'created_at',
            'cols' => 'id, title, platform, status, created_at',
        ],
        [
            'key' => 'ideas',
            'table' => 'bitcore_ideas',
            'ts_col' => 'captured_at',
            'cols' => 'id, title, score, captured_at',
            'no_agent' => true,
        ],
        [
            'key' => 'interactions',
            'table' => 'bitcore_interactions',
            'ts_col' => 'created_at',
            'cols' => 'id, summary, direction, created_at',
        ],
        [
            'key' => 'goals',
            'table' => 'bitcore_goals',
            'ts_col' => 'created_at',
            'cols' => 'id, title, progress, status, created_at',
        ],
    ];

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
    }

    /**
     * Fetch digest data across all activity tables
     *
     * @param string $orgId Organization ID
     * @param array  $options Filter options
     * @return array
     */
    public function fetch_digest( $orgId, $options = [] ) {
        $agent_id = $options['agent_id'] ?? null;
        $date = $options['date'] ?? null;

        $data = [];

        foreach ( $this->table_config as $config ) {
            $key = $config['key'];
            $table = $this->wpdb->prefix . $config['table'];
            $ts_col = $config['ts_col'];
            $cols = $config['cols'];
            $no_agent = $config['no_agent'] ?? false;

            // Check if table exists
            if ( ! $this->table_exists( $table ) ) {
                $data[ $key ] = [];
                continue;
            }

            $rows = $this->fetch_from_table( $table, $cols, $ts_col, $orgId, $agent_id, $date, $no_agent );
            $data[ $key ] = $rows;
        }

        return $data;
    }

    /**
     * Check if table exists
     *
     * @param string $table Table name
     * @return bool
     */
    private function table_exists( $table ) {
        $result = $this->wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );
        return $result === $table;
    }

    /**
     * Fetch data from a single table
     *
     * @param string $table Table name
     * @param string $cols Columns to select
     * @param string $tsCol Timestamp column
     * @param string $orgId Organization ID
     * @param string|null $agentId Agent ID
     * @param string|null $date Specific date filter
     * @param bool $noAgent Exclude agent filter
     * @return array
     */
    private function fetch_from_table( $table, $cols, $tsCol, $orgId, $agentId, $date, $noAgent ) {
        $where = [ 'org_id = %s' ];
        $values = [ $orgId ];

        if ( $agentId && ! $noAgent ) {
            $where[] = 'agent_id = %s';
            $values[] = $agentId;
        }

        if ( $date ) {
            $where[] = "DATE({$tsCol}) = %s";
            $values[] = $date;
            $sql = "SELECT {$cols} FROM {$table} WHERE " . implode( ' AND ', $where ) . " ORDER BY {$tsCol} DESC";
        } else {
            $sql = "SELECT {$cols} FROM {$table} WHERE " . implode( ' AND ', $where ) . " ORDER BY {$tsCol} DESC LIMIT 50";
        }

        return $this->wpdb->get_results(
            $this->wpdb->prepare( $sql, $values )
        );
    }

    /**
     * Get activity summary for a date range
     *
     * @param string $orgId Organization ID
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @param array  $options Filter options
     * @return array
     */
    public function get_summary( $orgId, $startDate, $endDate, $options = [] ) {
        $agent_id = $options['agent_id'] ?? null;
        $summary = [
            'total_actions' => 0,
            'by_type' => [],
            'by_status' => [],
            'by_agent' => [],
        ];

        $table = $this->wpdb->prefix . 'bitcore_action_records';

        if ( ! $this->table_exists( $table ) ) {
            return $summary;
        }

        $where = [ 'org_id = %s', 'DATE(timestamp_start) >= %s', 'DATE(timestamp_start) <= %s' ];
        $values = [ $orgId, $startDate, $endDate ];

        if ( $agent_id ) {
            $where[] = 'agent_id = %s';
            $values[] = $agent_id;
        }

        // Total count
        $sql = "SELECT COUNT(*) FROM {$table} WHERE " . implode( ' AND ', $where );
        $summary['total_actions'] = intval( $this->wpdb->get_var( $this->wpdb->prepare( $sql, $values ) ) );

        // By type
        $sql = "SELECT action_type, COUNT(*) as count FROM {$table} WHERE " . implode( ' AND ', $where ) . " GROUP BY action_type";
        $rows = $this->wpdb->get_results( $this->wpdb->prepare( $sql, $values ) );
        foreach ( $rows as $row ) {
            $summary['by_type'][ $row->action_type ] = intval( $row->count );
        }

        // By status
        $sql = "SELECT status, COUNT(*) as count FROM {$table} WHERE " . implode( ' AND ', $where ) . " GROUP BY status";
        $rows = $this->wpdb->get_results( $this->wpdb->prepare( $sql, $values ) );
        foreach ( $rows as $row ) {
            $summary['by_status'][ $row->status ] = intval( $row->count );
        }

        // By agent
        $sql = "SELECT agent_id, COUNT(*) as count FROM {$table} WHERE " . implode( ' AND ', $where ) . " GROUP BY agent_id";
        $rows = $this->wpdb->get_results( $this->wpdb->prepare( $sql, $values ) );
        foreach ( $rows as $row ) {
            $summary['by_agent'][ $row->agent_id ] = intval( $row->count );
        }

        return $summary;
    }

    /**
     * Log activity to action records
     *
     * @param array $data Activity data
     * @return string|false Action ID or false on failure
     */
    public function log_activity( $data ) {
        $table = $this->wpdb->prefix . 'bitcore_action_records';

        if ( ! $this->table_exists( $table ) ) {
            $this->create_action_records_table();
        }

        $action_id = $data['action_id'] ?? 'act_' . substr( wp_generate_uuid4(), 0, 24 );
        $now = current_time( 'mysql' );

        $result = $this->wpdb->insert(
            $table,
            [
                'action_id' => $action_id,
                'org_id' => $data['org_id'] ?? 'org_default',
                'agent_id' => $data['agent_id'],
                'action_type' => $data['action_type'],
                'declared_goal' => $data['declared_goal'] ?? null,
                'status' => $data['status'] ?? 'pending',
                'risk_score' => $data['risk_score'] ?? 0,
                'reversible' => $data['reversible'] ?? 1,
                'confidence' => $data['confidence'] ?? 50,
                'timestamp_start' => $data['timestamp_start'] ?? $now,
                'timestamp_end' => $data['timestamp_end'] ?? null,
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%d', '%f', '%s', '%s' ]
        );

        return $result ? $action_id : false;
    }

    /**
     * Create action records table
     */
    private function create_action_records_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'bitcore_action_records';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            action_id varchar(64) NOT NULL,
            org_id varchar(64) NOT NULL DEFAULT 'org_default',
            agent_id varchar(64) NOT NULL,
            action_type varchar(50) NOT NULL,
            declared_goal text,
            status varchar(20) NOT NULL DEFAULT 'pending',
            risk_score float DEFAULT 0,
            reversible tinyint(1) DEFAULT 1,
            confidence float DEFAULT 50,
            timestamp_start datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            timestamp_end datetime,
            PRIMARY KEY (id),
            UNIQUE KEY idx_action_id (action_id),
            KEY idx_org_agent (org_id, agent_id),
            KEY idx_status (status)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }
}
