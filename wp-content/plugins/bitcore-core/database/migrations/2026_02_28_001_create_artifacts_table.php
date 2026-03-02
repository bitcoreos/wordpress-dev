<?php

declare(strict_types=1);

/**
 * Migration: Create Artifacts Table
 *
 * @package BITCORE\Database\Migrations
 * @since 1.0.0
 */

namespace BITCORE\Database\Migrations;

/**
 * Create_Artifacts_Table Migration
 *
 * Creates the wp_bitcore_artifacts table for storing structured AI resources.
 *
 * @since 1.0.0
 */
class Create_Artifacts_Table
{
    /**
     * Run the migration.
     *
     * @return void
     * @since 1.0.0
     */
    public function up(): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'bitcore_artifacts';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            type VARCHAR(50) NOT NULL DEFAULT 'resource',
            content LONGTEXT NOT NULL,
            schema JSON DEFAULT NULL,
            metadata JSON DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_type (type),
            KEY idx_name (name),
            KEY idx_created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Reverse the migration.
     *
     * @return void
     * @since 1.0.0
     */
    public function down(): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'bitcore_artifacts';
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
    }
}
