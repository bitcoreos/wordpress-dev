<?php
/**
 * Checkouts Table Schema
 *
 * Migration for exclusive task checkout/lock system.
 * Ported from Paperclip's checkout mechanism.
 *
 * @package BITCORE\Database
 * @since       1.0.0
 * @version     1.0.0
 */

namespace BITCORE\Database;

if (!defined('ABSPATH')) exit;

/**
 * Class Checkouts_Table
 *
 * Manages the bitcore_checkouts database table.
 */
class Checkouts_Table {
    /** @var string Database table name */
    private string $table_name;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'bitcore_checkouts';
    }

    /**
     * Create the checkouts table
     *
     * @return bool Success status
     */
    public function create(): bool {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            issue_id bigint(20) unsigned NOT NULL,
            agent_id varchar(255) NOT NULL,
            run_id varchar(255) DEFAULT NULL,
            locked_at timestamp DEFAULT CURRENT_TIMESTAMP,
            expires_at timestamp NULL DEFAULT NULL,
            status enum('active','completed','expired','released') DEFAULT 'active',
            metadata longtext DEFAULT NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_issue (issue_id),
            KEY idx_agent (agent_id),
            KEY idx_status (status),
            KEY idx_expires (expires_at),
            KEY idx_run_id (run_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        return true;
    }

    /**
     * Drop the checkouts table
     *
     * @return bool Success status
     */
    public function drop(): bool {
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$this->table_name}");
        return true;
    }

    /**
     * Check if table exists
     *
     * @return bool Existence status
     */
    public function exists(): bool {
        global $wpdb;
        return $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") === $this->table_name;
    }

    /**
     * Get table name
     *
     * @return string Full table name with prefix
     */
    public function get_table_name(): string {
        return $this->table_name;
    }
}
