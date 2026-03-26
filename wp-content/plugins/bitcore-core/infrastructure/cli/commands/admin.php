<?php
/**
 * Tenant WP-CLI commands for Bitcore multitenancy
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

    // TENANT COMMANDS
    // =================================================================================

    /**
     * List all tenants
     *
     * Lists all registered tenants in the system with their current status.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format. Accepts: table, json, csv, yaml, count
     * ---
     * default: table
     * ---
     *
     * [--active-only]
     * : Show only active tenants
     *
     * ## EXAMPLES
     *
     *     # List all tenants in table format
     *     $ wp bitcore tenant list
     *
     *     # List tenants as JSON
     *     $ wp bitcore tenant list --format=json
     *
     *     # List only active tenants
     *     $ wp bitcore tenant list --active-only
     *
     * @since 1.0.0
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function tenant_list($args, $assoc_args): void {
        try {
            $format = $assoc_args['format'] ?? 'table';
            $active_only = isset($assoc_args['active-only']);

            $this->logger->info('Executing tenant list command', [
                'format' => $format,
                'active_only' => $active_only
            ]);

            // Get tenant repository
            $repository = $this->getTenantRepository();
            $tenants = $active_only ? $repository->listActive() : $this->getAllTenants();

            if (empty($tenants)) {
                WP_CLI::log('No tenants found.');
                return;
            }

            $items = [];
            foreach ($tenants as $tenant) {
                $items[] = [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'domain' => $tenant->domain,
                    'subdomain' => $tenant->subdomain ?? '',
                    'active' => $tenant->isActive ? 'yes' : 'no',
                    'created' => $tenant->createdAt->format('Y-m-d H:i:s'),
                ];
            }

            if ($format === 'count') {
                WP_CLI::log(count($items));
            } else {
                Utils::format_items($format, $items, ['id', 'name', 'domain', 'subdomain', 'active', 'created']);
            }

            $this->logger->info('Tenant list command completed', [
                'count' => count($items)
            ]);

        } catch (Throwable $e) {
            $this->logger->error('Tenant list command failed', [
                'error' => $e->getMessage()
            ]);
            WP_CLI::error($e->getMessage(), false);
        }
    }

    /**
     * Create new tenant
     *
     * Creates a new tenant with the specified name and domain.
     *
     * ## OPTIONS
     *
     * <name>
     * : Tenant name
     *
     * [--domain=<domain>]
     * : Primary domain for the tenant
     *
     * [--subdomain=<subdomain>]
     * : Subdomain prefix (optional)
     *
     * [--config=<config>]
     * : JSON configuration for tenant
     *
     * [--dry-run]
     * : Preview changes without executing
     *
     * ## EXAMPLES
     *
     *     $ wp bitcore tenant create "Acme Corp" --domain=acme.example.com
     *
     *     $ wp bitcore tenant create "Test Tenant" --domain=test.com --subdomain=test --dry-run
     *
     * @since 1.0.0
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function tenant_create($args, $assoc_args): void {
        try {
            $name = $args[0] ?? null;
            $domain = $assoc_args['domain'] ?? null;
            $subdomain = $assoc_args['subdomain'] ?? null;
            $config_json = $assoc_args['config'] ?? '{}';
            $dry_run = isset($assoc_args['dry-run']);

            if (empty($name)) {
                WP_CLI::error('Tenant name is required.', false);
                return;
            }

            if (empty($domain)) {
                WP_CLI::error('Domain is required. Use --domain=<domain>', false);
                return;
            }

            $config = json_decode($config_json, true) ?: [];

            $this->logger->info('Executing tenant create command', [
                'name' => $name,
                'domain' => $domain,
                'dry_run' => $dry_run
            ]);

            if ($dry_run) {
                WP_CLI::log("Would create tenant:");
                WP_CLI::log("  Name: {$name}");
                WP_CLI::log("  Domain: {$domain}");
                WP_CLI::log("  Subdomain: " . ($subdomain ?: '(none)'));
                WP_CLI::log("  Config: " . json_encode($config));
                return;
            }

            $repository = $this->getTenantRepository();
            $tenant = $repository->save(null, [
                'name' => $name,
                'domain' => $domain,
                'subdomain' => $subdomain,
                'config' => $config,
                'is_active' => 1,
            ]);

            if ($tenant) {
                WP_CLI::success("Tenant created successfully: ID {$tenant->id}");
                $this->logger->info('Tenant created', [
                    'tenant_id' => $tenant->id,
                    'name' => $name
                ]);

                // Initialize schema for new tenant
                try {
                    $this->schemaManager->createTenantSchema($tenant->id);
                    WP_CLI::log("Tenant schema initialized.");
                } catch (Throwable $schemaError) {
                    WP_CLI::warning("Schema initialization failed: " . $schemaError->getMessage());
                    $this->logger->warning('Tenant schema initialization failed', [
                        'tenant_id' => $tenant->id,
                        'error' => $schemaError->getMessage()
                    ]);
                }
            } else {
                WP_CLI::error('Failed to create tenant.', false);
            }

        } catch (Throwable $e) {
            $this->logger->error('Tenant create command failed', [
                'error' => $e->getMessage()
            ]);
            WP_CLI::error($e->getMessage(), false);
        }
    }

    /**
     * Delete tenant
     *
     * Soft-deletes a tenant by deactivating it.
     *
     * ## OPTIONS
     *
     * <id>
     * : Tenant ID to delete
     *
     * [--force]
     * : Permanently delete (instead of soft delete)
     *
     * [--dry-run]
     * : Preview changes without executing
     *
     * ## EXAMPLES
     *
     *     $ wp bitcore tenant delete 123
     *
     *     $ wp bitcore tenant delete 123 --force
     *
     * @since 1.0.0
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function tenant_delete($args, $assoc_args): void {
        try {
            $id = (int) ($args[0] ?? 0);
            $force = isset($assoc_args['force']);
            $dry_run = isset($assoc_args['dry-run']);

            if ($id <= 0) {
                WP_CLI::error('Valid tenant ID is required.', false);
                return;
            }

            $this->logger->info('Executing tenant delete command', [
                'tenant_id' => $id,
                'force' => $force,
                'dry_run' => $dry_run
            ]);

            // Verify tenant exists
            $repository = $this->getTenantRepository();
            $tenant = $repository->findById($id);

            if (!$tenant) {
                WP_CLI::error("Tenant {$id} not found.", false);
                return;
            }

            if ($dry_run) {
                WP_CLI::log("Would " . ($force ? 'permanently delete' : 'deactivate') . " tenant:");
                WP_CLI::log("  ID: {$tenant->id}");
                WP_CLI::log("  Name: {$tenant->name}");
                WP_CLI::log("  Domain: {$tenant->domain}");
                return;
            }

            if ($force) {
                // Permanent deletion would require additional implementation
                WP_CLI::warning('Permanent deletion not implemented. Use deactivate instead.');
            }

            $result = $repository->deactivate($id);

            if ($result) {
                WP_CLI::success("Tenant {$id} deactivated successfully.");
                $this->logger->info('Tenant deactivated', ['tenant_id' => $id]);

                // Clear tenant cache
                $this->cacheManager->clear();
            } else {
                WP_CLI::error("Failed to deactivate tenant {$id}.", false);
            }

        } catch (Throwable $e) {
            $this->logger->error('Tenant delete command failed', [
                'tenant_id' => $args[0] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            WP_CLI::error($e->getMessage(), false);
        }
    }

    /**
     * Switch to tenant context
     *
     * Sets the current tenant context for subsequent operations.
     *
     * ## OPTIONS
     *
     * <id>
     * : Tenant ID to switch to
     *
     * ## EXAMPLES
     *
     *     $ wp bitcore tenant switch 123
     *
     * @since 1.0.0
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function tenant_switch($args, $assoc_args): void {
        try {
            $id = (int) ($args[0] ?? 0);

            if ($id <= 0) {
                WP_CLI::error('Valid tenant ID is required.', false);
                return;
            }

            // Verify tenant exists
            $repository = $this->getTenantRepository();
            $tenant = $repository->findById($id);

            if (!$tenant) {
                WP_CLI::error("Tenant {$id} not found.", false);
                return;
            }

            $this->tenantResolver->setCurrentTenant($tenant);

            WP_CLI::success("Switched to tenant: {$tenant->name} (ID: {$id})");
            $this->logger->info('Tenant context switched', [
                'tenant_id' => $id,
                'tenant_name' => $tenant->name
            ]);

        } catch (Throwable $e) {
            $this->logger->error('Tenant switch command failed', [
                'error' => $e->getMessage()
            ]);
            WP_CLI::error($e->getMessage(), false);
        }
    }

    // =================================================================================