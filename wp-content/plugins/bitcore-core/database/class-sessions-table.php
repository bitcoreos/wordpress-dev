<?php
/**
 * Sessions Table Schema
 *
 * @package BITCORE\Database
 */

namespace BITCORE\Database;

if (!defined('ABSPATH')) exit;

class Sessions_Table {
    private string $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'bitcore_sessions';
    }

    public function create(): bool {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id varchar(64) NOT NULL,
            agent_id varchar(255) NOT NULL,
            status enum('active','idle','ended') DEFAULT 'active',
            state longtext DEFAULT NULL,
            started_at timestamp DEFAULT CURRENT_TIMESTAMP,
            last_activity timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            ended_at timestamp NULL DEFAULT NULL,
            expires_at timestamp NULL DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY idx_session_id (session_id),
            KEY idx_agent (agent_id),
            KEY idx_status (status),
            KEY idx_expires (expires_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        return true;
    }

    public function drop(): bool {
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$this->table_name}");
        return true;
    }
}
