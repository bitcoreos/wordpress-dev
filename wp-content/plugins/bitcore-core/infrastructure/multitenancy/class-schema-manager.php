<?php
/**
 * Schema Manager
 *
 * Manages tenant-specific schema isolation, migrations, and table resolution.
 *
 * @package BITCORE\Infrastructure\MultiTenancy
 * @since 1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Infrastructure\MultiTenancy;

use BITCORE\Infrastructure\Auth\TenantContext;
use InvalidArgumentException;
use RuntimeException;

class SchemaManager {
    private \wpdb $db;
    private TenantContext $tenantContext;
    private array $migrations = [];
    private string $versionsTable;
    private array $sharedTables = [];
    private array $rowLevelTables = [];
    private string $isolationStrategy;
    private ?int $activeTenant = null;

    public function __construct(
        \wpdb $db,
        TenantContext $tenantContext,
        string $isolationStrategy = 'prefix'
    ) {
        $this->db = $db;
        $this->tenantContext = $tenantContext;
        $this->isolationStrategy = $isolationStrategy;
        $this->versionsTable = $this->db->prefix . 'bitcore_schema_versions';
    }

    public function createTenantSchema(int $tenantId): void {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $this->switchToTenant($tenantId);
        try {
            $tables = ['posts', 'postmeta', 'options', 'users', 'usermeta'];
            foreach ($tables as $table) {
                $tableName = $this->getTableName($table, $tenantId);
                $charset = $this->db->charset_collate;
                $sql = $this->getTableSql($table, $tableName, $charset);
                if ($sql) { dbDelta($sql); }
            }
            $this->setTenantSchemaVersion($tenantId, '1.0.0');
        } finally {
            $this->resetToShared();
        }
    }

    private function getTableSql(string $table, string $tableName, string $charset): string {
        $schemas = [
            'posts' => "CREATE TABLE IF NOT EXISTS {$tableName} (
                ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                post_author bigint(20) unsigned NOT NULL DEFAULT 0,
                post_date datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
                post_date_gmt datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
                post_content longtext NOT NULL, post_title text NOT NULL,
                post_excerpt text NOT NULL, post_status varchar(20) NOT NULL DEFAULT 'publish',
                comment_status varchar(20) NOT NULL DEFAULT 'open',
                ping_status varchar(20) NOT NULL DEFAULT 'open',
                post_password varchar(255) NOT NULL DEFAULT '',
                post_name varchar(200) NOT NULL DEFAULT '', to_ping text NOT NULL,
                pinged text NOT NULL,
                post_modified datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
                post_modified_gmt datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
                post_content_filtered longtext NOT NULL,
                post_parent bigint(20) unsigned NOT NULL DEFAULT 0,
                guid varchar(255) NOT NULL DEFAULT '', menu_order int(11) NOT NULL DEFAULT 0,
                post_type varchar(20) NOT NULL DEFAULT 'post',
                post_mime_type varchar(100) NOT NULL DEFAULT '',
                comment_count bigint(20) NOT NULL DEFAULT 0,
                PRIMARY KEY (ID), KEY post_name (post_name),
                KEY type_status_date (post_type, post_status, post_date, ID),
                KEY post_parent (post_parent), KEY post_author (post_author)
            ) {$charset};",
            'postmeta' => "CREATE TABLE IF NOT EXISTS {$tableName} (
                meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                post_id bigint(20) unsigned NOT NULL DEFAULT 0,
                meta_key varchar(255) DEFAULT NULL, meta_value longtext,
                PRIMARY KEY (meta_id), KEY post_id (post_id), KEY meta_key (meta_key)
            ) {$charset};",
            'options' => "CREATE TABLE IF NOT EXISTS {$tableName} (
                option_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                option_name varchar(191) NOT NULL DEFAULT '',
                option_value longtext NOT NULL, autoload varchar(20) NOT NULL DEFAULT 'yes',
                PRIMARY KEY (option_id), UNIQUE KEY option_name (option_name),
                KEY autoload (autoload)
            ) {$charset};",
            'users' => "CREATE TABLE IF NOT EXISTS {$tableName} (
                ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_login varchar(60) NOT NULL DEFAULT '',
                user_pass varchar(255) NOT NULL DEFAULT '',
                user_nicename varchar(50) NOT NULL DEFAULT '',
                user_email varchar(100) NOT NULL DEFAULT '',
                user_url varchar(100) NOT NULL DEFAULT '',
                user_registered datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
                user_activation_key varchar(255) NOT NULL DEFAULT '',
                user_status int(11) NOT NULL DEFAULT 0,
                display_name varchar(250) NOT NULL DEFAULT '',
                PRIMARY KEY (ID), KEY user_login_key (user_login),
                KEY user_nicename (user_nicename), KEY user_email (user_email)
            ) {$charset};",
            'usermeta' => "CREATE TABLE IF NOT EXISTS {$tableName} (
                umeta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NOT NULL DEFAULT 0,
                meta_key varchar(255) DEFAULT NULL, meta_value longtext,
                PRIMARY KEY (umeta_id), KEY user_id (user_id), KEY meta_key (meta_key)
            ) {$charset};"
        ];
        return $schemas[$table] ?? '';
    }

    public function migrateTenantSchema(int $tenantId, string $version): void {
        $currentVersion = $this->getTenantSchemaVersion($tenantId);
        if (version_compare($currentVersion, $version, '>=')) { return; }
        $this->switchToTenant($tenantId);
        try {
            $pending = $this->getPendingMigrations($currentVersion, $version);
            foreach ($pending as $migrationVersion => $migration) {
                $startTime = microtime(true);
                try {
                    $migration['up']();
                    $this->recordMigration($tenantId, $migrationVersion,
                        (int)((microtime(true) - $startTime) * 1000), null);
                } catch (\Exception $e) {
                    $this->recordMigration($tenantId, $migrationVersion,
                        (int)((microtime(true) - $startTime) * 1000), $e->getMessage());
                    throw new RuntimeException("Migration {$migrationVersion} failed: " . $e->getMessage(), 0, $e);
                }
            }
        } finally { $this->resetToShared(); }
    }

    public function rollbackTenantSchema(int $tenantId, string $version): void {
        $currentVersion = $this->getTenantSchemaVersion($tenantId);
        if (version_compare($currentVersion, $version, '<=')) { return; }
        $this->switchToTenant($tenantId);
        try {
            $applied = array_reverse($this->getAppliedMigrations($version, $currentVersion), true);
            foreach ($applied as $migrationVersion => $migration) {
                try { $migration['down'](); }
                catch (\Exception $e) {
                    throw new RuntimeException("Rollback {$migrationVersion} failed: " . $e->getMessage(), 0, $e);
                }
                $this->removeMigrationRecord($tenantId, $migrationVersion);
            }
        } finally { $this->resetToShared(); }
    }

    public function getTenantSchemaVersion(int $tenantId): string {
        $version = $this->db->get_var($this->db->prepare(
            "SELECT version FROM {$this->versionsTable} WHERE tenant_id = %d ORDER BY applied_at DESC LIMIT 1", $tenantId));
        return $version ?: '0.0.0';
    }

    private function setTenantSchemaVersion(int $tenantId, string $version): void {
        $this->db->replace($this->versionsTable,
            ['tenant_id' => $tenantId, 'version' => $version, 'applied_at' => current_time('mysql')],
            ['%d', '%s', '%s']);
    }

    public function registerMigration(string $version, callable $up, callable $down): void {
        $this->migrations[$version] = ['up' => $up, 'down' => $down];
    }

    public function runMigrations(int $tenantId): void {
        $versions = array_keys($this->migrations);
        if (empty($versions)) { return; }
        usort($versions, 'version_compare');
        $this->migrateTenantSchema($tenantId, end($versions));
    }

    public function getTableName(string $table, ?int $tenantId = null): string {
        $tenantId = $tenantId ?? $this->activeTenant ?? $this->tenantContext->getCurrentTenant();
        if (in_array($table, $this->sharedTables, true)) { return $this->db->prefix . $table; }
        if ($this->isolationStrategy === 'row' && in_array($table, $this->rowLevelTables, true)) {
            return $this->db->prefix . $table;
        }
        if ($tenantId !== null) { return $this->db->prefix . "tenant_{$tenantId}_{$table}"; }
        return $this->db->prefix . $table;
    }

    public function switchToTenant(int $tenantId): void {
        $this->activeTenant = $tenantId;
        $this->tenantContext->setTenant($tenantId);
    }

    public function resetToShared(): void {
        $this->activeTenant = null;
        $this->tenantContext->clearTenant();
    }

    public function setSharedTables(array $tables): void { $this->sharedTables = $tables; }
    public function setRowLevelTables(array $tables): void { $this->rowLevelTables = $tables; }

    public function setIsolationStrategy(string $strategy): void {
        if (!in_array($strategy, ['prefix', 'row'], true)) {
            throw new InvalidArgumentException("Invalid isolation strategy: {$strategy}");
        }
        $this->isolationStrategy = $strategy;
    }

    private function getPendingMigrations(string $fromVersion, string $toVersion): array {
        $pending = [];
        foreach ($this->migrations as $version => $migration) {
            if (version_compare($version, $fromVersion, '>') && version_compare($version, $toVersion, '<=')) {
                $pending[$version] = $migration;
            }
        }
        uksort($pending, 'version_compare');
        return $pending;
    }

    private function getAppliedMigrations(string $fromVersion, string $toVersion): array {
        $applied = [];
        foreach ($this->migrations as $version => $migration) {
            if (version_compare($version, $fromVersion, '>') && version_compare($version, $toVersion, '<=')) {
                $applied[$version] = $migration;
            }
        }
        return $applied;
    }

    private function recordMigration(int $tenantId, string $version, int $duration, ?string $error): void {
        $this->db->insert($this->versionsTable,
            ['tenant_id' => $tenantId, 'version' => $version, 'applied_at' => current_time('mysql'),
             'duration_ms' => $duration, 'error' => $error],
            ['%d', '%s', '%s', '%d', '%s']);
    }

    private function removeMigrationRecord(int $tenantId, string $version): void {
        $this->db->delete($this->versionsTable, ['tenant_id' => $tenantId, 'version' => $version], ['%d', '%s']);
    }

    public function ensureVersionsTable(): void {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charset = $this->db->charset_collate;
        $sql = "CREATE TABLE IF NOT EXISTS {$this->versionsTable} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL, version varchar(20) NOT NULL,
            applied_at datetime NOT NULL, duration_ms int(11) unsigned NOT NULL DEFAULT 0,
            error text, PRIMARY KEY (id), UNIQUE KEY tenant_version (tenant_id, version),
            KEY tenant_id (tenant_id), KEY applied_at (applied_at)
        ) {$charset};";
        dbDelta($sql);
    }
}
