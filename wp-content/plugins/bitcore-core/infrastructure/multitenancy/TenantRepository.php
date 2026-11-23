<?php
/**
 * Tenant Repository
 *
 * Data access layer for tenant entities using WordPress database.
 * Implements caching and hydration patterns for performance.
 *
 * @package BITCORE\Infrastructure\MultiTenancy
 * @since 1.0.0
 * @version     1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Infrastructure\MultiTenancy;

if (!defined('ABSPATH')) {
    exit;
}

class TenantRepository {

    /**
     * WordPress database instance
     *
     * @var \wpdb
     * @since 1.0.0
     */
    private \wpdb $db;

    /**
     * Table name for tenants
     *
     * @var string
     * @since 1.0.0
     */
    private string $tableName;

    /**
     * Cache group for tenant data
     *
     * @var string
     * @since 1.0.0
     */
    private string $cacheGroup = 'bitcore_tenants';

    /**
     * Constructor
     *
     * @param \wpdb $db WordPress database instance
     * @since 1.0.0
     */
    public function __construct(\wpdb $db) {
        $this->db = $db;
        $this->tableName = $this->db->prefix . 'bitcore_tenants';
    }

    /**
     * Find tenant by ID
     *
     * @param int $id Tenant ID
     * @return Tenant|null Tenant entity or null if not found
     * @since 1.0.0
     */
    public function findById(int $id): ?Tenant {
        $cacheKey = "tenant_id_{$id}";
        $cached = wp_cache_get($cacheKey, $this->cacheGroup);

        if ($cached !== false) {
            return $cached ? $this->hydrateTenant($cached) : null;
        }

        $row = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->tableName} WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        wp_cache_set($cacheKey, $row, $this->cacheGroup, 300);

        return $row ? $this->hydrateTenant($row) : null;
    }

    /**
     * Find tenant by domain
     *
     * @param string $domain Domain name
     * @return Tenant|null Tenant entity or null if not found
     * @since 1.0.0
     */
    public function findByDomain(string $domain): ?Tenant {
        $domain = strtolower($domain);
        $cacheKey = "tenant_domain_{$domain}";
        $cached = wp_cache_get($cacheKey, $this->cacheGroup);

        if ($cached !== false) {
            return $cached ? $this->hydrateTenant($cached) : null;
        }

        $row = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->tableName} WHERE domain = %s AND is_active = 1",
                $domain
            ),
            ARRAY_A
        );

        wp_cache_set($cacheKey, $row, $this->cacheGroup, 300);

        return $row ? $this->hydrateTenant($row) : null;
    }

    /**
     * Find tenant by subdomain
     *
     * @param string $subdomain Subdomain name
     * @return Tenant|null Tenant entity or null if not found
     * @since 1.0.0
     */
    public function findBySubdomain(string $subdomain): ?Tenant {
        $subdomain = strtolower($subdomain);
        $cacheKey = "tenant_subdomain_{$subdomain}";
        $cached = wp_cache_get($cacheKey, $this->cacheGroup);

        if ($cached !== false) {
            return $cached ? $this->hydrateTenant($cached) : null;
        }

        $row = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->tableName} WHERE subdomain = %s AND is_active = 1",
                $subdomain
            ),
            ARRAY_A
        );

        wp_cache_set($cacheKey, $row, $this->cacheGroup, 300);

        return $row ? $this->hydrateTenant($row) : null;
    }

    /**
     * Save tenant
     *
     * Creates new tenant or updates existing.
     *
     * @param Tenant $tenant Tenant to save
     * @return Tenant Saved tenant with ID
     * @throws RuntimeException If save fails
     * @since 1.0.0
     */
    public function save(Tenant $tenant): Tenant {
        $data = [
            'name' => $tenant->name,
            'domain' => $tenant->domain,
            'subdomain' => $tenant->subdomain,
            'config' => wp_json_encode($tenant->config),
            'is_active' => $tenant->isActive ? 1 : 0,
            'updated_at' => current_time('mysql'),
        ];

        if ($tenant->id > 0) {
            // Update existing
            $result = $this->db->update(
                $this->tableName,
                $data,
                ['id' => $tenant->id],
                ['%s', '%s', '%s', '%s', '%d', '%s'],
                ['%d']
            );

            if ($result === false) {
                throw new RuntimeException('Failed to update tenant: ' . $this->db->last_error);
            }

            $this->invalidateCache($tenant);
            return $tenant;
        }

        // Insert new
        $data['created_at'] = current_time('mysql');

        $result = $this->db->insert(
            $this->tableName,
            $data,
            ['%s', '%s', '%s', '%s', '%d', '%s', '%s']
        );

        if ($result === false) {
            throw new RuntimeException('Failed to create tenant: ' . $this->db->last_error);
        }

        $newTenant = new Tenant(
            (int) $this->db->insert_id,
            $tenant->name,
            $tenant->domain,
            $tenant->subdomain,
            $tenant->config,
            $tenant->isActive,
            $tenant->createdAt
        );

        $this->invalidateCache($newTenant);
        return $newTenant;
    }

    /**
     * Deactivate tenant
     *
     * Soft delete - marks tenant as inactive.
     *
     * @param int $tenantId Tenant ID to deactivate
     * @throws RuntimeException If deactivation fails
     * @since 1.0.0
     */
    public function deactivate(int $tenantId): void {
        $result = $this->db->update(
            $this->tableName,
            ['is_active' => 0, 'updated_at' => current_time('mysql')],
            ['id' => $tenantId],
            ['%d', '%s'],
            ['%d']
        );

        if ($result === false) {
            throw new RuntimeException('Failed to deactivate tenant: ' . $this->db->last_error);
        }

        // Invalidate all caches for this tenant
        $tenant = $this->findById($tenantId);
        if ($tenant) {
            $this->invalidateCache($tenant);
        }
    }

    /**
     * List active tenants
     *
     * @return array<Tenant> Array of active tenant entities
     * @since 1.0.0
     */
    public function listActive(): array {
        $cacheKey = 'tenants_active_list';
        $cached = wp_cache_get($cacheKey, $this->cacheGroup);

        if ($cached !== false && is_array($cached)) {
            return array_map([$this, 'hydrateTenant'], $cached);
        }

        $rows = $this->db->get_results(
            "SELECT * FROM {$this->tableName} WHERE is_active = 1 ORDER BY name ASC",
            ARRAY_A
        );

        wp_cache_set($cacheKey, $rows ?: [], $this->cacheGroup, 60);

        return $rows ? array_map([$this, 'hydrateTenant'], $rows) : [];
    }

    /**
     * Hydrate tenant from database row
     *
     * @param array<string, mixed> $row Database row
     * @return Tenant Hydrated tenant entity
     * @since 1.0.0
     */
    private function hydrateTenant(array $row): Tenant {
        return new Tenant(
            (int) $row['id'],
            $row['name'],
            $row['domain'],
            $row['subdomain'] ?? null,
            json_decode($row['config'] ?? '{}', true) ?: [],
            (bool) $row['is_active'],
            isset($row['created_at']) ? new DateTimeImmutable($row['created_at']) : null
        );
    }

    /**
     * Invalidate tenant caches
     *
     * @param Tenant $tenant Tenant to invalidate
     * @since 1.0.0
     */
    private function invalidateCache(Tenant $tenant): void {
        wp_cache_delete("tenant_id_{$tenant->id}", $this->cacheGroup);
        wp_cache_delete("tenant_domain_{$tenant->domain}", $this->cacheGroup);
        if ($tenant->subdomain) {
            wp_cache_delete("tenant_subdomain_{$tenant->subdomain}", $this->cacheGroup);
        }
        wp_cache_delete('tenants_active_list', $this->cacheGroup);
    }
}

/**
 * Tenant Resolver
 *
 * Resolves tenant context from various request sources.
 * Implements priority-based resolution: Header > Subdomain > Domain > Path.
 *
 * @since 1.0.0
 */
