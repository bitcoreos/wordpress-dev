<?php
/**
 * Approvals Table Schema
 *
 * @package BITCORE\Database
 */

namespace BITCORE\Database;

if (!defined('ABSPATH')) exit;

class Approvals_Table {
    private string $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'bitcore_approvals';
    }

    public function create(): bool {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            agent_id varchar(255) NOT NULL,
            approval_type varchar(100) NOT NULL,
            payload longtext DEFAULT NULL,
            status enum('pending','approved','rejected','expired','cancelled') DEFAULT 'pending',
            requested_at timestamp DEFAULT CURRENT_TIMESTAMP,
            approved_by varchar(255) DEFAULT NULL,
            approved_at timestamp NULL DEFAULT NULL,
            rejection_reason text DEFAULT NULL,
            notes text DEFAULT NULL,
            expires_at timestamp NULL DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_agent (agent_id),
            KEY idx_type (approval_type),
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
