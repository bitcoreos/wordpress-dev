<?php
/**
 * Migration WP-CLI commands for Bitcore schema migrations
 *
 * @package    Bitcore
 * @since      2026.03.01
 * @version    1.3.0
 * @author     hyperion
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;

class Bitcore_Migration_Commands {
    private $logger;

    public function __construct() {
        $this->logger = new BITCORE_Infrastructure_Logging_Logger();
    }

}

    // MIGRATION COMMANDS
    // =================================================================================

    /**
     * Run migrations
     *
     * Executes pending database migrations.
     *
     * ## OPTIONS
     *
     * [--tenant=<tenant>]
     * : Run migrations for specific tenant
     *
     * [--version=<version>]
     * : Target specific version (default: latest)
     *
     * [--dry-run]
     * : Preview changes without executing
     *
     * ## EXAMPLES
     *
     *     # Run all pending migrations
     *     $ wp bitcore migrate
     *
     *     # Migrate specific tenant
     *     $ wp bitcore migrate --tenant=123
     *
     * @since 1.0.0
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function migrate($args, $assoc_args): void {
        try {
            $tenantId = isset($assoc_args['tenant']) ? (int) $assoc_args['tenant'] : null;
            $version = $assoc_args['version'] ?? null;
            $dry_run = isset($assoc_args['dry-run']);

            $this->logger->info('Running migrations', [
                'tenant_id' => $tenantId,
                'version' => $version,
                'dry_run' => $dry_run
            ]);

            if ($dry_run) {
                WP_CLI::log('Would run migrations' . ($tenantId ? " for tenant {$tenantId}" : ' for all tenants') . ($version ? " to version {$version}" : '') . '.');
                return;
            }

            if ($tenantId) {
                // Migrate specific tenant
                $migrated = $this->schemaManager->migrateTenantSchema($tenantId, $version);
                WP_CLI::success("Migrated tenant {$tenantId}: {$migrated} migrations applied.");
            } else {
                // Migrate all tenants
                $repository = $this->getTenantRepository();
                $tenants = $repository->listActive();
                $totalMigrated = 0;

                $progress = Utils::make_progress_bar('Migrating tenants', count($tenants));

                foreach ($tenants as $tenant) {
                    try {
                        $migrated = $this->schemaManager->migrateTenantSchema($tenant->id, $version);
                        $totalMigrated += $migrated;
                        $progress->tick();
                    } catch (Throwable $e) {
                        WP_CLI::warning("Failed to migrate tenant {$tenant->id}: {$e->getMessage()}");
                    }
                }

                $progress->finish();
                WP_CLI::success("Migrations completed: {$totalMigrated} total migrations applied across " . count($tenants) . " tenants.");
            }

            $this->logger->info('Migrations completed');

        } catch (Throwable $e) {
            $this->logger->error('Migrate command failed', [
                'error' => $e->getMessage()
            ]);
            WP_CLI::error($e->getMessage(), false);
        }
    }

    /**
     * Rollback migration
     *
     * Reverts migrations to a previous version.
     *
     * ## OPTIONS
     *
     * <version>
     * : Target version to rollback to
     *
     * [--tenant=<tenant>]
     * : Rollback specific tenant
     *
     * [--steps=<steps>]
     * : Number of migrations to rollback
     * ---
     * default: 1
     * ---
     *
     * [--dry-run]
     * : Preview changes without executing
     *
     * ## EXAMPLES
     *
     *     # Rollback last migration
     *     $ wp bitcore rollback
     *
     *     # Rollback to specific version
     *     $ wp bitcore rollback 1.0.0
     *
     * @since 1.0.0
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function rollback($args, $assoc_args): void {
        try {
            $version = $args[0] ?? null;
            $tenantId = isset($assoc_args['tenant']) ? (int) $assoc_args['tenant'] : null;
            $steps = (int) ($assoc_args['steps'] ?? 1);
            $dry_run = isset($assoc_args['dry-run']);

            $this->logger->info('Rolling back migrations', [
                'version' => $version,
                'tenant_id' => $tenantId,
                'steps' => $steps,
                'dry_run' => $dry_run
            ]);

            if ($dry_run) {
                WP_CLI::log("Would rollback {$steps} migration(s)" . ($tenantId ? " for tenant {$tenantId}" : '') . ($version ? " to version {$version}" : '') . '.');
                return;
            }

            if ($tenantId) {
                $rolled = $this->schemaManager->rollbackTenantSchema($tenantId, $steps);
                WP_CLI::success("Rolled back {$rolled} migration(s) for tenant {$tenantId}.");
            } else {
                // Rollback all tenants
                $repository = $this->getTenantRepository();
                $tenants = $repository->listActive();
                $totalRolled = 0;

                foreach ($tenants as $tenant) {
                    try {
                        $rolled = $this->schemaManager->rollbackTenantSchema($tenant->id, $steps);
                        $totalRolled += $rolled;
                    } catch (Throwable $e) {
                        WP_CLI::warning("Failed to rollback tenant {$tenant->id}: {$e->getMessage()}");
                    }
                }

                WP_CLI::success("Rollback completed: {$totalRolled} total migration(s) across " . count($tenants) . " tenants.");
            }

            $this->logger->info('Rollback completed');

        } catch (Throwable $e) {
            $this->logger->error('Rollback command failed', [
                'error' => $e->getMessage()
            ]);
            WP_CLI::error($e->getMessage(), false);
        }
    }

    /**
     * Show migration status
     *
     * Displays current migration version and history.
     *
     * ## OPTIONS
     *
     * [--tenant=<tenant>]
     * : Show status for specific tenant
     *
     * [--format=<format>]
     * : Output format
     * ---
     * default: table
     * ---
     *
     * ## EXAMPLES
     *
     *     $ wp bitcore migrate-status
     *
     *     $ wp bitcore migrate-status --tenant=123
     *
     * @since 1.0.0
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function migrate_status($args, $assoc_args): void {
        try {
            $tenantId = isset($assoc_args['tenant']) ? (int) $assoc_args['tenant'] : null;
            $format = $assoc_args['format'] ?? 'table';

            $this->logger->info('Checking migration status', [
                'tenant_id' => $tenantId
            ]);

            if ($tenantId) {
                // Show status for specific tenant
                $version = $this->schemaManager->getTenantSchemaVersion($tenantId);
                $items = [
                    [
                        'tenant_id' => $tenantId,
                        'current_version' => $version ?? 'N/A',
                        'status' => $version ? 'Migrated' : 'Not initialized',
                    ]
                ];
            } else {
                // Show status for all tenants
                $repository = $this->getTenantRepository();
                $tenants = $repository->listActive();
                $items = [];

                foreach ($tenants as $tenant) {
                    $version = $this->schemaManager->getTenantSchemaVersion($tenant->id);
                    $items[] = [
                        'tenant_id' => $tenant->id,
                        'tenant_name' => $tenant->name,
                        'current_version' => $version ?? 'N/A',
                        'status' => $version ? 'Migrated' : 'Not initialized',
                    ];
                }
            }

            if ($format === 'json') {
                WP_CLI::log(json_encode($items, JSON_PRETTY_PRINT));
            } else {
                Utils::format_items('table', $items, array_keys($items[0]));
            }

            $this->logger->info('Migration status retrieved');

        } catch (Throwable $e) {
            $this->logger->error('Migrate status command failed', [
                'error' => $e->getMessage()
            ]);
            WP_CLI::error($e->getMessage(), false);
        }
    }

    // =================================================================================
}
