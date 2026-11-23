<?php
/**
 * Budget Policies Table Schema
 *
 * @package BITCORE\Database
 * @since       1.0.0
 * @version     1.0.0
 */

namespace BITCORE\Database;

if (!defined('ABSPATH')) exit;

class Budget_Policies_Table {
    private string $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'bitcore_budget_policies';
    }

    public function create(): bool {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            agent_id varchar(255) NOT NULL,
            resource_type varchar(100) NOT NULL DEFAULT 'default',
            limit_amount decimal(15,2) NOT NULL DEFAULT 0.00,
            period enum('hourly','daily','weekly','monthly') DEFAULT 'daily',
            thresholds longtext DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_agent_resource (agent_id, resource_type),
            KEY idx_active (is_active)
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
