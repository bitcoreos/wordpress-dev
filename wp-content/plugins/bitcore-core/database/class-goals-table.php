<?php
/**
 * Goals Table Schema
 *
 * @package BITCORE\Database
 */

namespace BITCORE\Database;

if (!defined('ABSPATH')) exit;

class Goals_Table {
    private string $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'bitcore_goals';
    }

    public function create(): bool {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            description longtext DEFAULT NULL,
            level enum('mission','objective','task') DEFAULT 'task',
            status enum('draft','active','completed','cancelled','blocked') DEFAULT 'draft',
            parent_id bigint(20) unsigned DEFAULT NULL,
            ancestry_path longtext DEFAULT NULL,
            depth int DEFAULT 0,
            assigned_agent_id varchar(255) DEFAULT NULL,
            priority int DEFAULT 5,
            due_date date DEFAULT NULL,
            completed_at timestamp NULL DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_parent (parent_id),
            KEY idx_level (level),
            KEY idx_status (status),
            KEY idx_agent (assigned_agent_id),
            KEY idx_priority (priority)
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
