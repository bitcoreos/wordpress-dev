<?php
/**
 * Migration logic and version management for Bitcore schema
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

            $this->setTenantSchemaVersion($tenantId, '1.0.0');

        } finally {
            $this->resetToShared();
        }
    }

    /**
     * Migrate tenant schema
     *
     * Upgrades tenant schema to specified version.
     *
     * @param int $tenantId Tenant ID
     * @param string $version Target version
     * @return void
     * @throws RuntimeException If migration fails
     * @since 1.0.0
     */
    public function migrateTenantSchema(int $tenantId, string $version): void {
        $currentVersion = $this->getTenantSchemaVersion($tenantId);

        if (version_compare($currentVersion, $version, '>=')) {
            return; // Already at or above target
        }

        $this->switchToTenant($tenantId);

        try {
            // Get migrations between current and target
            $pending = $this->getPendingMigrations($currentVersion, $version);

            foreach ($pending as $migrationVersion => $migration) {
                $startTime = microtime(true);

                try {
                    $migration['up']();

                    $this->recordMigration(
                        $tenantId,
                        $migrationVersion,
                        (int) ((microtime(true) - $startTime) * 1000),
                        null
                    );

                } catch (\Exception $e) {
                    $this->recordMigration(
                        $tenantId,
                        $migrationVersion,
                        (int) ((microtime(true) - $startTime) * 1000),
                        $e->getMessage()
                    );
                    throw new RuntimeException(
                        "Migration {$migrationVersion} failed for tenant {$tenantId}: " . $e->getMessage(),
                        0,
                        $e
                    );
                }
            }

        } finally {
            $this->resetToShared();
        }
    }

    /**
     * Rollback tenant schema
     *
     * Downgrades tenant schema to specified version.
     *
     * @param int $tenantId Tenant ID
     * @param string $version Target version
     * @return void
     * @throws RuntimeException If rollback fails
     * @since 1.0.0
     */
    public function rollbackTenantSchema(int $tenantId, string $version): void {
        $currentVersion = $this->getTenantSchemaVersion($tenantId);

        if (version_compare($currentVersion, $version, '<=')) {
            return; // Already at or below target
        }

        $this->switchToTenant($tenantId);

        try {
            // Get migrations to rollback (reverse order)
            $applied = $this->getAppliedMigrations($version, $currentVersion);
            $applied = array_reverse($applied, true);

            foreach ($applied as $migrationVersion => $migration) {
                $startTime = microtime(true);

                try {
                    $migration['down']();

                    $this->removeMigrationRecord($tenantId, $migrationVersion);

                } catch (\Exception $e) {
                    throw new RuntimeException(
                        "Rollback {$migrationVersion} failed for tenant {$tenantId}: " . $e->getMessage(),
                        0,
                        $e
                    );
                }
            }

        } finally {
            $this->resetToShared();
        }
    }

    /**
     * Get tenant schema version
     *
     * @param int $tenantId Tenant ID
     * @return string Current schema version
     * @since 1.0.0
     */
    public function getTenantSchemaVersion(int $tenantId): string {
        $version = $this->db->get_var(
            $this->db->prepare(
                "SELECT version FROM {$this->versionsTable} WHERE tenant_id = %d ORDER BY applied_at DESC LIMIT 1",
                $tenantId
            )
        );

        return $version ?: '0.0.0';
    }

    /**
     * Set tenant schema version
     *
     * @param int $tenantId Tenant ID
     * @param string $version Version to set
     * @return void
     * @since 1.0.0
     */
    private function setTenantSchemaVersion(int $tenantId, string $version): void {
        $this->db->replace(
            $this->versionsTable,
            [
                'tenant_id' => $tenantId,
                'version' => $version,
                'applied_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s']
        );
    }

    /**
     * Register migration
     *
     * @param string $version Version string
     * @param callable $up Upgrade callback
     * @param callable $down Downgrade callback
     * @return void
     * @since 1.0.0
     */
    public function registerMigration(string $version, callable $up, callable $down): void {
        $this->migrations[$version] = [
            'up' => $up,
            'down' => $down,
        ];
    }

    /**
     * Run all pending migrations
     *
     * @param int $tenantId Tenant ID
     * @return void
     * @since 1.0.0
     */
    public function runMigrations(int $tenantId): void {
        $versions = array_keys($this->migrations);
        if (empty($versions)) {
            return;
        }

        usort($versions, 'version_compare');
        $targetVersion = end($versions);

        $this->migrateTenantSchema($tenantId, $targetVersion);
    }

    /**
     * Get table name with tenant context
     *
     * Returns table name with appropriate prefix or isolation.
     *
     * @param string $table Base table name
     * @param int|null $tenantId Tenant ID (null for current)
     * @return string Full table name
     * @since 1.0.0
     */
    public function getTableName(string $table, ?int $tenantId = null): string {
        $tenantId = $tenantId ?? $this->activeTenant ?? $this->tenantContext->getCurrentTenant();

        // Shared tables never get prefixed
        if (in_array($table, $this->sharedTables, true)) {
            return $this->db->prefix . $table;
        }

        // Row-level isolation tables use base name with tenant_id column
        if ($this->isolationStrategy === 'row' && in_array($table, $this->rowLevelTables, true)) {
            return $this->db->prefix . $table;
        }

        // Table prefix isolation
        if ($tenantId !== null) {
            return $this->db->prefix . "tenant_{$tenantId}_{$table}";
        }

        return $this->db->prefix . $table;
    }

    /**
     * Switch to tenant context
     *
     * Activates tenant for subsequent operations.
     *
     * @param int $tenantId Tenant ID
     * @return void
     * @since 1.0.0
     */
    public function switchToTenant(int $tenantId): void {
        $this->activeTenant = $tenantId;
        $this->tenantContext->setTenant($tenantId);
    }

    /**
     * Reset to shared context
     *
     * Clears tenant-specific context.
     *
     * @return void
     * @since 1.0.0
     */
    public function resetToShared(): void {
        $this->activeTenant = null;
        $this->tenantContext->clearTenant();
    }

    /**
     * Configure shared tables
     *
     * Tables that are never prefixed per tenant.
     *
     * @param array<string> $tables Table names
     * @return void
     * @since 1.0.0
     */
    public function setSharedTables(array $tables): void {
        $this->sharedTables = $tables;
    }

    /**
     * Configure row-level isolation tables
     *
     * Tables that use tenant_id column instead of prefixing.
     *
     * @param array<string> $tables Table names
     * @return void
     * @since 1.0.0
     */
    public function setRowLevelTables(array $tables): void {
        $this->rowLevelTables = $tables;
    }

    /**
     * Set isolation strategy
     *
     * @param string $strategy 'prefix' or 'row'
     * @return void
     * @since 1.0.0
     */
    public function setIsolationStrategy(string $strategy): void {
        if (!in_array($strategy, ['prefix', 'row'], true)) {
            throw new InvalidArgumentException("Invalid isolation strategy: {$strategy}");
        }
        $this->isolationStrategy = $strategy;
    }

    /**
     * Get pending migrations
     *
     * @param string $fromVersion Current version
     * @param string $toVersion Target version
     * @return array<string, array{up: callable, down: callable}>
     * @since 1.0.0
     */
    private function getPendingMigrations(string $fromVersion, string $toVersion): array {
        $pending = [];

        foreach ($this->migrations as $version => $migration) {
            if (version_compare($version, $fromVersion, '>') &&
                version_compare($version, $toVersion, '<=')) {
                $pending[$version] = $migration;
            }
        }

        uksort($pending, 'version_compare');
        return $pending;
    }

    /**
     * Get applied migrations
     *
     * @param string $fromVersion From version
     * @param string $toVersion To version
     * @return array<string, array{up: callable, down: callable}>
     * @since 1.0.0
     */
    private function getAppliedMigrations(string $fromVersion, string $toVersion): array {
        $applied = [];

        foreach ($this->migrations as $version => $migration) {
            if (version_compare($version, $fromVersion, '>') &&
                version_compare($version, $toVersion, '<=')) {
                $applied[$version] = $migration;
            }
        }

        return $applied;
    }

    /**
     * Record migration in schema versions table
     *
     * @param int $tenantId Tenant ID
     * @param string $version Version
     * @param int $duration Duration in ms
     * @param string|null $error Error message
     * @return void
     * @since 1.0.0
     */
    private function recordMigration(int $tenantId, string $version, int $duration, ?string $error): void {
        $this->db->insert(
            $this->versionsTable,
            [
                'tenant_id' => $tenantId,
                'version' => $version,
                'applied_at' => current_time('mysql'),
                'duration_ms' => $duration,
                'error' => $error,
            ],
            ['%d', '%s', '%s', '%d', '%s']
        );
    }

    /**
     * Remove migration record
     *
     * @param int $tenantId Tenant ID
     * @param string $version Version
     * @return void
     * @since 1.0.0
     */
    private function removeMigrationRecord(int $tenantId, string $version): void {
        $this->db->delete(
            $this->versionsTable,
            ['tenant_id' => $tenantId, 'version' => $version],
            ['%d', '%s']
        );
    }

    /**
     * Ensure schema versions table exists
     *
     * @return void
     * @since 1.0.0
     */
    public function ensureVersionsTable(): void {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $charset = $this->db->charset_collate;

        $sql = "CREATE TABLE IF NOT EXISTS {$this->versionsTable} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            version varchar(20) NOT NULL,
            applied_at datetime NOT NULL,
            duration_ms int(11) unsigned NOT NULL DEFAULT 0,
            error text,
            PRIMARY KEY (id),
            UNIQUE KEY tenant_version (tenant_id, version),
            KEY tenant_id (tenant_id),
            KEY applied_at (applied_at)
        ) {$charset};";

        dbDelta($sql);
    }
}

/*
 * Test Scaffold:
 * TODO: Implement unit tests
 * - SchemaVersion DTO:
 *   - Test constructor validation (empty version)
 *   - Test isSuccessful() returns correct boolean
 *   - Test isApplied() returns correct boolean
 *   - Test toArray() serialization
 * - MigrationInterface:
 *   - Test implementations extend AbstractMigration
 *   - Test up() and down() contracts
 *   - Test getVersion() and getDescription() return strings
 * - AbstractMigration:
 *   - Test getTableName() with and without SchemaManager
 *   - Test execute() error handling
 *   - Test delta() uses dbDelta correctly
 * - SchemaManager:
 *   - Test createTenantSchema() creates tables with prefix
 *   - Test migrateTenantSchema() runs pending migrations
 *   - Test rollbackTenantSchema() reverses migrations
 *   - Test getTenantSchemaVersion() returns correct version
 *   - Test registerMigration() stores callbacks
 *   - Test runMigrations() executes all pending
 *   - Test getTableName() with prefix strategy
 *   - Test getTableName() with row strategy
 *   - Test switchToTenant() and resetToShared()
 *   - Test shared tables not prefixed
 *   - Test ensureVersionsTable() creates schema_versions
 *   - Test error handling and rollback on failure
 */
