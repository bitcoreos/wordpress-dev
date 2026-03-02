<?php
/**
 * BITCORE_Context_Store implementation
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
 * Context Store for RAG Operations
 *
 * Context vector storage for Retrieval-Augmented Generation.
 * Pattern derived from DashClaw messagesContext.repository.js
 *
 * @package BITCORE
 * @since 1.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BITCORE_Context_Store {

    /**
     * Table name for context vectors
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
        $this->table_name = $wpdb->prefix . 'bitcore_context_vectors';
    }

    /**
     * Create database table on plugin activation
     */
    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bitcore_context_vectors';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id varchar(32) NOT NULL,
            org_id varchar(64) NOT NULL DEFAULT 'org_default',
            thread_id varchar(32) DEFAULT NULL,
            agent_id varchar(64) NOT NULL,
            content_type varchar(50) NOT NULL DEFAULT 'text',
            content longtext NOT NULL,
            embedding longtext,
            metadata longtext,
            relevance_score float DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_org_agent (org_id, agent_id),
            KEY idx_thread (thread_id),
            KEY idx_content_type (content_type)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    /**
     * Generate unique context ID
     *
     * @return string
     */
    private function generate_id() {
        return 'ctx_' . substr( wp_generate_uuid4(), 0, 24 );
    }

    /**
     * Store context vector
     *
     * @param array $data Context data
     * @return object|false
     */
    public function store( $data ) {
        $id = $this->generate_id();
        $now = current_time( 'mysql' );

        $result = $this->wpdb->insert(
            $this->table_name,
            [
                'id' => $id,
                'org_id' => $data['org_id'] ?? 'org_default',
                'thread_id' => $data['thread_id'] ?? null,
                'agent_id' => $data['agent_id'],
                'content_type' => $data['content_type'] ?? 'text',
                'content' => $data['content'],
                'embedding' => isset( $data['embedding'] ) ? wp_json_encode( $data['embedding'] ) : null,
                'metadata' => isset( $data['metadata'] ) ? wp_json_encode( $data['metadata'] ) : null,
                'relevance_score' => $data['relevance_score'] ?? 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s' ]
        );

        if ( false === $result ) {
            return false;
        }

        return $this->get( $id );
    }

    /**
     * Get context by ID
     *
     * @param string $id Context ID
     * @return object|null
     */
    public function get( $id ) {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %s",
                $id
            )
        );

        if ( $row ) {
            $row->embedding = $row->embedding ? json_decode( $row->embedding, true ) : null;
            $row->metadata = $row->metadata ? json_decode( $row->metadata, true ) : null;
        }

        return $row;
    }

    /**
     * Search contexts by similarity (basic implementation)
     *
     * @param string $query Query text
     * @param array $filters Filter options
     * @return array
     */
    public function search( $query, $filters = [] ) {
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

        if ( ! empty( $filters['thread_id'] ) ) {
            $where[] = 'thread_id = %s';
            $values[] = $filters['thread_id'];
        }

        if ( ! empty( $filters['content_type'] ) ) {
            $where[] = 'content_type = %s';
            $values[] = $filters['content_type'];
        }

        $limit = intval( $filters['limit'] ?? 20 );

        // Basic text search (extend with vector similarity if embeddings available)
        $where[] = 'content LIKE %s';
        $values[] = '%' . $this->wpdb->esc_like( $query ) . '%';

        $sql = "SELECT * FROM {$this->table_name} WHERE " . implode( ' AND ', $where ) . " ORDER BY relevance_score DESC, updated_at DESC LIMIT %d";
        $values[] = $limit;

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare( $sql, $values )
        );

        foreach ( $rows as $row ) {
            $row->embedding = $row->embedding ? json_decode( $row->embedding, true ) : null;
            $row->metadata = $row->metadata ? json_decode( $row->metadata, true ) : null;
        }

        return $rows;
    }

    /**
     * List contexts for thread
     *
     * @param string $threadId Thread ID
     * @param array $filters Filter options
     * @return array
     */
    public function list_for_thread( $threadId, $filters = [] ) {
        $filters['thread_id'] = $threadId;
        return $this->search( '', $filters );
    }

    /**
     * Update context
     *
     * @param string $id Context ID
     * @param array $data Update data
     * @return object|false
     */
    public function update( $id, $data ) {
        $update_data = [];
        $formats = [];

        $allowed = [ 'content', 'content_type', 'embedding', 'metadata', 'relevance_score' ];

        foreach ( $allowed as $field ) {
            if ( isset( $data[ $field ] ) ) {
                if ( in_array( $field, [ 'embedding', 'metadata' ] ) ) {
                    $update_data[ $field ] = wp_json_encode( $data[ $field ] );
                } else {
                    $update_data[ $field ] = $data[ $field ];
                }
                $formats[] = in_array( $field, [ 'relevance_score' ] ) ? '%f' : '%s';
            }
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
     * Delete context
     *
     * @param string $id Context ID
     * @return bool
     */
    public function delete( $id ) {
        return $this->wpdb->delete(
            $this->table_name,
            [ 'id' => $id ],
            [ '%s' ]
        ) !== false;
    }

    /**
     * Get context thread
     *
     * @param string $threadId Thread ID
     * @param string $orgId Organization ID
     * @return object|null
     */
    public function get_thread( $threadId, $orgId = 'org_default' ) {
        $table_threads = $this->wpdb->prefix . 'bitcore_context_threads';

        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$table_threads} WHERE id = %s AND org_id = %s",
                $threadId,
                $orgId
            )
        );
    }
}
