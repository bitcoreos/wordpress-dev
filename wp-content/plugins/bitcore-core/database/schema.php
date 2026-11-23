<?php
/**
 * Database Schema Management
 *
 * Versioned database migrations using dbDelta
 * Pattern extracted from userswp class-activator.php
 *
 * @package BITCORE\Database
 * @since 1.0.0
 * @version     1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Database;

/**
 * Schema Manager
 *
 * Handles database schema creation and versioned migrations.
 *
 * @since 1.0.0
 */
class SchemaManager {
    /**
     * Database schema version option name.
     *
     * @var string
     */
    private const DB_VERSION_OPTION = 'bitcore_db_version';

    /**
     * Current database schema version.
     *
     * @var string
     */
    private string $currentVersion;

    /**
     * WordPress database instance.
     *
     * @var \wpdb
     */
    private $wpdb;

    /**
     * Constructor.
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->currentVersion = get_option(self::DB_VERSION_OPTION, '1.0.0');
    }

    /**
     * Install database tables.
     *
     * @return void
     */
    public function install(): void {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $this->createTasksTable();
        $this->createConnectorsTable();
        $this->createSessionsTable();
        $this->createEventsTable();
        $this->createKnowledgeTable();

        // Run updates if needed
        if ($this->needsUpdate()) {
            $this->update();
        }

        update_option(self::DB_VERSION_OPTION, BITCORE_DB_VERSION);
    }

    /**
     * Check if database needs update.
     *
     * @return bool True if update needed.
     */
    private function needsUpdate(): bool {
        return version_compare($this->currentVersion, BITCORE_DB_VERSION, '<');
    }

    /**
     * Update database.
     *
     * @return void
     */
    private function update(): void {
        // Run migration callbacks based on version
        $migrations = [
            '1.1.0' => [$this, 'migrate_1_1_0'],
            '1.2.0' => [$this, 'migrate_1_2_0'],
        ];

        foreach ($migrations as $version => $callback) {
            if (version_compare($this->currentVersion, $version, '<')) {
                call_user_func($callback);
            }
        }
    }

    /**
     * Create tasks table.
     *
     * @return void
     */
    private function createTasksTable(): void {
        $table = $this->wpdb->prefix . 'bitcore_tasks';
        $charsetCollate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description longtext,
            status varchar(50) NOT NULL DEFAULT 'pending',
            priority varchar(20) NOT NULL DEFAULT 'normal',
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            meta longtext,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY priority (priority),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) {$charsetCollate};";

        dbDelta($sql);
    }

    /**
     * Create connectors table.
     *
     * @return void
     */
    private function createConnectorsTable(): void {
        $table = $this->wpdb->prefix . 'bitcore_connectors';
        $charsetCollate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            type varchar(50) NOT NULL,
            name varchar(255) NOT NULL,
            endpoint varchar(500) NOT NULL,
            credentials longtext,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            last_used datetime,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY type (type),
            KEY is_active (is_active)
        ) {$charsetCollate};";

        dbDelta($sql);
    }

    /**
     * Create sessions table.
     *
     * @return void
     */
    private function createSessionsTable(): void {
        $table = $this->wpdb->prefix . 'bitcore_sessions';
        $charsetCollate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id varchar(100) NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            data longtext,
            expires_at datetime NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY session_id (session_id),
            KEY user_id (user_id),
            KEY expires_at (expires_at)
        ) {$charsetCollate};";

        dbDelta($sql);
    }

    /**
     * Create events table.
     *
     * @return void
     */
    private function createEventsTable(): void {
        $table = $this->wpdb->prefix . 'bitcore_events';
        $charsetCollate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(100) NOT NULL,
            event_data longtext,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            session_id varchar(100),
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) {$charsetCollate};";

        dbDelta($sql);
    }

    /**
     * Create knowledge pipeline table.
     *
     * Backs BITCORE\Rest\Knowledge_Api (six-state permission model) and the
     * Eliza/Knowledge surfaces that read and write knowledge items.
     *
     * @return void
     */
    private function createKnowledgeTable(): void {
        $table = $this->wpdb->prefix . 'bitcore_knowledge';
        $charsetCollate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            session_id varchar(100) DEFAULT NULL,
            title varchar(255) NOT NULL DEFAULT '',
            content longtext,
            content_type varchar(50) NOT NULL DEFAULT 'markdown',
            tags longtext,
            status varchar(20) NOT NULL DEFAULT 'private',
            bitwiki_page_title varchar(255) DEFAULT NULL,
            bitwiki_page_id bigint(20) unsigned DEFAULT NULL,
            published_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status),
            KEY session_id (session_id),
            KEY created_at (created_at)
        ) {$charsetCollate};";

        dbDelta($sql);
    }

    /**
     * Migration for version 1.1.0.
     *
     * @return void
     */
    private function migrate_1_1_0(): void {
        // Add new columns or tables for 1.1.0
        $table = $this->wpdb->prefix . 'bitcore_tasks';
        $this->wpdb->query("ALTER TABLE {$table} ADD COLUMN parent_id bigint(20) unsigned DEFAULT NULL AFTER id");
        $this->wpdb->query("ALTER TABLE {$table} ADD KEY parent_id (parent_id)");
    }

    /**
     * Migration for version 1.2.0.
     *
     * @return void
     */
    private function migrate_1_2_0(): void {
        // Add new columns or tables for 1.2.0
        $table = $this->wpdb->prefix . 'bitcore_tasks';
        $this->wpdb->query("ALTER TABLE {$table} ADD COLUMN assigned_to bigint(20) unsigned DEFAULT NULL AFTER user_id");
        $this->wpdb->query("ALTER TABLE {$table} ADD KEY assigned_to (assigned_to)");
    }

    /**
     * Clean expired sessions.
     *
     * @return int Number of deleted sessions.
     */
    public function cleanExpiredSessions(): int {
        $table = $this->wpdb->prefix . 'bitcore_sessions';
        return $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$table} WHERE expires_at < %s",
                current_time('mysql')
            )
        );
    }
}
