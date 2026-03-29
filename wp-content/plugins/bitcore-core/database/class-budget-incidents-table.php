<?php
/**
 * Budget Incidents Table Schema
 *
 * @package BITCORE\Database
 */

namespace BITCORE\Database;

if (!defined('ABSPATH')) exit;

class Budget_Incidents_Table {
    private string $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'bitcore_budget_incidents';
    }

    public function create(): bool {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            agent_id varchar(255) NOT NULL,
            policy_id bigint(20) unsigned DEFAULT NULL,
            resource_type varchar(100) NOT NULL DEFAULT 'default',
            amount decimal(15,2) NOT NULL DEFAULT 0.00,
            operation varchar(255) DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_agent (agent_id),
            KEY idx_policy (policy_id),
            KEY idx_resource (resource_type),
            KEY idx_created (created_at)
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
