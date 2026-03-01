<?php
/**
 * Research Workspace Management
 *
 * Manages research contexts for AI agents with workspace isolation.
 * Pattern derived from DashClaw learningLoop.repository.js
 *
 * @package BITCORE
 * @since 1.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BITCORE_Research_Workspace {

    /**
     * Table name for workspaces
     *
     * @var string
     */
    private $table_name;

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
        $this->table_name = $wpdb->prefix . 'bitcore_research_workspaces';
    }

    /**
     * Create database table on plugin activation
     */
    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bitcore_research_workspaces';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id varchar(32) NOT NULL,
            org_id varchar(64) NOT NULL DEFAULT 'org_default',
            agent_id varchar(64) NOT NULL,
            name varchar(255) NOT NULL,
            summary text,
            status varchar(20) NOT NULL DEFAULT 'active',
            config longtext,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_org_agent (org_id, agent_id),
            KEY idx_status (status)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    /**
     * Generate unique workspace ID
     *
     * @return string
     */
    private function generate_id() {
        return 'ws_' . substr( wp_generate_uuid4(), 0, 24 );
    }

    /**
     * Create a new research workspace
     *
     * @param array $data Workspace data
     * @return object|false Created workspace or false on failure
     */
    public function create( $data ) {
        $id = $this->generate_id();
        $now = current_time( 'mysql' );

        $result = $this->wpdb->insert(
            $this->table_name,
            [
                'id' => $id,
                'org_id' => $data['org_id'] ?? 'org_default',
                'agent_id' => $data['agent_id'],
                'name' => $data['name'],
                'summary' => $data['summary'] ?? null,
                'status' => 'active',
                'config' => isset( $data['config'] ) ? wp_json_encode( $data['config'] ) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
        );

        if ( false === $result ) {
            return false;
        }

        return $this->get( $id );
    }

    /**
     * Get workspace by ID
     *
     * @param string $id Workspace ID
     * @return object|null
     */
    public function get( $id ) {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %s",
                $id
            )
        );

        if ( $row && $row->config ) {
            $row->config = json_decode( $row->config, true );
        }

        return $row;
    }

    /**
     * List workspaces with filters
     *
     * @param array $filters Filter options
     * @return array
     */
    public function list( $filters = [] ) {
        $where = [ '1=1' ];
        $values = [];

        if ( ! empty( $filters['org_id'] ) ) {
            $where[] = 'org_id = %s';
            $values[] = $filters['org_id'];
        }

        if ( ! empty( $filters['agent_id'] ) ) {
            $where[] = 'agent_id = %s';
            $values[] = $filters['agent_id'];
        }

        if ( ! empty( $filters['status'] ) ) {
            $where[] = 'status = %s';
            $values[] = $filters['status'];
        }

        $limit = intval( $filters['limit'] ?? 50 );
        $where[] = '1=%d';
        $values[] = 1;

        $sql = "SELECT * FROM {$this->table_name} WHERE " . implode( ' AND ', $where ) . " ORDER BY updated_at DESC LIMIT %d";
        $values[] = $limit;

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare( $sql, $values )
        );

        foreach ( $rows as $row ) {
            if ( $row->config ) {
                $row->config = json_decode( $row->config, true );
            }
        }

        return $rows;
    }

    /**
     * Update workspace
     *
     * @param string $id Workspace ID
     * @param array $data Update data
     * @return object|false
     */
    public function update( $id, $data ) {
        $update_data = [];
        $formats = [];

        if ( isset( $data['name'] ) ) {
            $update_data['name'] = $data['name'];
            $formats[] = '%s';
        }

        if ( isset( $data['summary'] ) ) {
            $update_data['summary'] = $data['summary'];
            $formats[] = '%s';
        }

        if ( isset( $data['status'] ) ) {
            $update_data['status'] = $data['status'];
            $formats[] = '%s';
        }

        if ( isset( $data['config'] ) ) {
            $update_data['config'] = wp_json_encode( $data['config'] );
            $formats[] = '%s';
        }

        $update_data['updated_at'] = current_time( 'mysql' );
        $formats[] = '%s';

        $result = $this->wpdb->update(
            $this->table_name,
            $update_data,
            [ 'id' => $id ],
            $formats,
            [ '%s' ]
        );

        if ( false === $result ) {
            return false;
        }

        return $this->get( $id );
    }

    /**
     * Archive workspace (soft delete)
     *
     * @param string $id Workspace ID
     * @return bool
     */
    public function archive( $id ) {
        return $this->update( $id, [ 'status' => 'archived' ] ) !== false;
    }

    /**
     * Upsert workspace (create or update by name)
     *
     * @param array $data Workspace data
     * @return object|false
     */
    public function upsert( $data ) {
        $existing = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE org_id = %s AND agent_id = %s AND name = %s",
                $data['org_id'] ?? 'org_default',
                $data['agent_id'],
                $data['name']
            )
        );

        if ( $existing ) {
            return $this->update( $existing->id, $data );
        }

        return $this->create( $data );
    }
}
