<?php
/**
 * Multi-Tenancy Tenant Management
 *
 * Provides tenant resolution, DTO representation, and repository pattern
 * for multi-tenant SaaS operations in WordPress environment.
 *
 * @package BITCORE
 * @subpackage Infrastructure\MultiTenancy
 * @since 1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Infrastructure\MultiTenancy;

use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;

/**
 * Tenant Data Transfer Object
 *
 * Immutable value object representing a tenant in the multi-tenant system.
 * Provides tenant-specific prefixes for database, cache, and storage isolation.
 *
 * @since 1.0.0
 */
final class Tenant {

    /**
     * Tenant unique identifier
     *
     * @var int
     * @since 1.0.0
     */
    public readonly int $id;

    /**
     * Tenant display name
     *
     * @var string
     * @since 1.0.0
     */
    public readonly string $name;

    /**
     * Primary domain for tenant
     *
     * @var string
     * @since 1.0.0
     */
    public readonly string $domain;

    /**
     * Optional subdomain identifier
     *
     * @var string|null
     * @since 1.0.0
     */
    public readonly ?string $subdomain;

    /**
     * Tenant-specific configuration
     *
     * @var array<string, mixed>
     * @since 1.0.0
     */
    public readonly array $config;

    /**
     * Whether tenant is active
     *
     * @var bool
     * @since 1.0.0
     */
    public readonly bool $isActive;

    /**
     * Tenant creation timestamp
     *
     * @var DateTimeImmutable|null
     * @since 1.0.0
     */
    public readonly ?DateTimeImmutable $createdAt;

    /**
     * Constructor
     *
     * @param int $id Tenant unique identifier
     * @param string $name Tenant display name
     * @param string $domain Primary domain for tenant
     * @param string|null $subdomain Optional subdomain identifier
     * @param array<string, mixed> $config Tenant-specific configuration
     * @param bool $isActive Whether tenant is active
     * @param DateTimeImmutable|null $createdAt Creation timestamp
     * @since 1.0.0
     */
    public function __construct(
        int $id,
        string $name,
        string $domain,
        ?string $subdomain = null,
        array $config = [],
        bool $isActive = true,
        ?DateTimeImmutable $createdAt = null
    ) {
        if ($id <= 0) {
            throw new InvalidArgumentException('Tenant ID must be positive integer');
        }
        if (empty($name)) {
            throw new InvalidArgumentException('Tenant name cannot be empty');
        }
        if (empty($domain)) {
            throw new InvalidArgumentException('Tenant domain cannot be empty');
        }

        $this->id = $id;
        $this->name = $name;
        $this->domain = strtolower($domain);
        $this->subdomain = $subdomain ? strtolower($subdomain) : null;
        $this->config = $config;
        $this->isActive = $isActive;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
    }

    /**
     * Get database table prefix for this tenant
     *
     * Returns isolated table prefix for schema isolation strategy.
     * Format: wp_tenant_{id}_
     *
     * @return string Database table prefix
     * @since 1.0.0
     */
    public function getDatabasePrefix(): string {
        return sprintf('wp_tenant_%d_', $this->id);
    }

    /**
     * Get cache key prefix for this tenant
     *
     * Returns isolated cache prefix for multi-tenant cache isolation.
     * Format: t:{id}:
     *
     * @return string Cache key prefix
     * @since 1.0.0
     */
    public function getCachePrefix(): string {
        return sprintf('t:%d:', $this->id);
    }

    /**
     * Get storage path for this tenant
     *
     * Returns isolated storage path for file uploads and assets.
     * Format: uploads/tenant/{id}/
     *
     * @return string Storage path relative to WordPress uploads
     * @since 1.0.0
     */
    public function getStoragePath(): string {
        return sprintf('uploads/tenant/%d/', $this->id);
    }

    /**
     * Get full storage path
     *
     * Returns absolute storage path using WordPress upload directory.
     *
     * @return string Absolute storage path
     * @since 1.0.0
     */
    public function getFullStoragePath(): string {
        $uploadDir = wp_upload_dir();
        return trailingslashit($uploadDir['basedir']) . $this->getStoragePath();
    }

    /**
     * Get tenant URL
     *
     * Returns the primary URL for this tenant.
     *
     * @param string $scheme URL scheme (http|https)
     * @return string Tenant URL
     * @since 1.0.0
     */
    public function getUrl(string $scheme = 'https'): string {
        if ($this->subdomain) {
            return sprintf('%s://%s.%s', $scheme, $this->subdomain, $this->domain);
        }
        return sprintf('%s://%s', $scheme, $this->domain);
    }

    /**
     * Check if tenant matches domain
     *
     * @param string $domain Domain to check
     * @return bool True if tenant matches domain
     * @since 1.0.0
     */
    public function matchesDomain(string $domain): bool {
        $domain = strtolower($domain);
        return $this->domain === $domain || $this->subdomain . '.' . $this->domain === $domain;
    }

    /**
     * Check if tenant matches subdomain
     *
     * @param string $subdomain Subdomain to check
     * @return bool True if tenant matches subdomain
     * @since 1.0.0
     */
    public function matchesSubdomain(string $subdomain): bool {
        return $this->subdomain === strtolower($subdomain);
    }

    /**
     * Get config value
     *
     * @param string $key Configuration key
     * @param mixed $default Default value if key not found
     * @return mixed Configuration value or default
     * @since 1.0.0
     */
    public function getConfig(string $key, mixed $default = null): mixed {
        return $this->config[$key] ?? $default;
    }

    /**
     * Convert to array
     *
     * @return array<string, mixed> Array representation
     * @since 1.0.0
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'domain' => $this->domain,
            'subdomain' => $this->subdomain,
            'config' => $this->config,
            'isActive' => $this->isActive,
            'createdAt' => $this->createdAt?->format('c'),
        ];
    }
}

/**
 * Tenant Repository
 *
 * Data access layer for tenant entities. Provides CRUD operations
 * and query methods for tenant management.
 *
 * @since 1.0.0
 */
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
class TenantResolver {

    /**
     * Repository for tenant lookups
     *
     * @var TenantRepository
     * @since 1.0.0
     */
    private TenantRepository $repository;

    /**
     * Configuration options
     *
     * @var array<string, mixed>
     * @since 1.0.0
     */
    private array $config;

    /**
     * Currently resolved tenant
     *
     * @var Tenant|null
     * @since 1.0.0
     */
    private ?Tenant $currentTenant = null;

    /**
     * Resolution strategies
     *
     * @var array<string>
     * @since 1.0.0
     */
    private array $strategies = ['header', 'subdomain', 'domain', 'path'];

    /**
     * Header name for tenant ID
     *
     * @var string
     * @since 1.0.0
     */
    private string $tenantHeader = 'X-Tenant-ID';

    /**
     * Constructor
     *
     * @param array<string, mixed> $config Configuration options
     * @since 1.0.0
     */
    public function __construct(array $config = []) {
        global $wpdb;
        $this->repository = new TenantRepository($wpdb);
        $this->config = array_merge([
            'header_name' => 'X-Tenant-ID',
            'path_prefix' => '/tenant/',
            'strategies' => ['header', 'subdomain', 'domain', 'path'],
        ], $config);

        $this->tenantHeader = $this->config['header_name'];
        $this->strategies = $this->config['strategies'];
    }

    /**
     * Resolve tenant from HTTP request
     *
     * Attempts resolution using configured strategies in priority order:
     * 1. Header: X-Tenant-ID
     * 2. Subdomain: tenant.example.com
     * 3. Domain: tenantdomain.com
     * 4. Path: /tenant/{id}/
     *
     * @param array<string, mixed> $request Request data (headers, server, etc)
     * @return Tenant|null Resolved tenant or null
     * @since 1.0.0
     */
    public function resolve(array $request): ?Tenant {
        foreach ($this->strategies as $strategy) {
            $tenant = match ($strategy) {
                'header' => $this->resolveFromHeader($request['headers'][$this->tenantHeader] ?? ''),
                'subdomain' => $this->resolveFromRequestSubdomain($request),
                'domain' => $this->resolveFromRequestDomain($request),
                'path' => $this->resolveFromRequestPath($request),
                default => null,
            };

            if ($tenant !== null) {
                $this->setCurrentTenant($tenant);
                return $tenant;
            }
        }

        return null;
    }

    /**
     * Resolve tenant from domain
     *
     * @param string $domain Domain name
     * @return Tenant|null Tenant entity or null
     * @since 1.0.0
     */
    public function resolveFromDomain(string $domain): ?Tenant {
        if (empty($domain)) {
            return null;
        }
        return $this->repository->findByDomain($domain);
    }

    /**
     * Resolve tenant from subdomain
     *
     * @param string $subdomain Subdomain name
     * @return Tenant|null Tenant entity or null
     * @since 1.0.0
     */
    public function resolveFromSubdomain(string $subdomain): ?Tenant {
        if (empty($subdomain)) {
            return null;
        }
        return $this->repository->findBySubdomain($subdomain);
    }

    /**
     * Resolve tenant from path
     *
     * @param string $path Request path
     * @return Tenant|null Tenant entity or null
     * @since 1.0.0
     */
    public function resolveFromPath(string $path): ?Tenant {
        if (empty($path)) {
            return null;
        }

        $prefix = $this->config['path_prefix'];
        if (strpos($path, $prefix) !== 0) {
            return null;
        }

        $remainder = substr($path, strlen($prefix));
        if (!preg_match('/^(\d+)/', $remainder, $matches)) {
            return null;
        }

        return $this->repository->findById((int) $matches[1]);
    }

    /**
     * Resolve tenant from header value
     *
     * @param string $header Header value (tenant ID or domain)
     * @return Tenant|null Tenant entity or null
     * @since 1.0.0
     */
    public function resolveFromHeader(string $header): ?Tenant {
        if (empty($header)) {
            return null;
        }

        // Try as numeric ID first
        if (is_numeric($header)) {
            return $this->repository->findById((int) $header);
        }

        // Try as domain
        return $this->repository->findByDomain($header);
    }

    /**
     * Set current tenant
     *
     * @param Tenant $tenant Tenant to set as current
     * @since 1.0.0
     */
    public function setCurrentTenant(Tenant $tenant): void {
        $this->currentTenant = $tenant;
    }

    /**
     * Get current tenant
     *
     * @return Tenant|null Current tenant or null
     * @since 1.0.0
     */
    public function getCurrentTenant(): ?Tenant {
        return $this->currentTenant;
    }

    /**
     * Clear current tenant
     *
     * @since 1.0.0
     */
    public function clearCurrentTenant(): void {
        $this->currentTenant = null;
    }

    /**
     * Check if tenant is resolved
     *
     * @return bool True if tenant context is active
     * @since 1.0.0
     */
    public function hasCurrentTenant(): bool {
        return $this->currentTenant !== null;
    }

    /**
     * Get current tenant ID
     *
     * @return int|null Tenant ID or null
     * @since 1.0.0
     */
    public function getCurrentTenantId(): ?int {
        return $this->currentTenant?->id;
    }

    /**
     * Resolve from request subdomain
     *
     * @param array<string, mixed> $request Request data
     * @return Tenant|null Tenant entity or null
     * @since 1.0.0
     */
    private function resolveFromRequestSubdomain(array $request): ?Tenant {
        $host = $request['server']['HTTP_HOST'] ?? '';
        $mainDomain = $this->config['main_domain'] ?? '';

        if (empty($host) || empty($mainDomain)) {
            return null;
        }

        // Check if host is a subdomain of main domain
        $pattern = '/^([^.]+)\.' . preg_quote($mainDomain, '/') . '$/i';
        if (!preg_match($pattern, $host, $matches)) {
            return null;
        }

        return $this->resolveFromSubdomain($matches[1]);
    }

    /**
     * Resolve from request domain
     *
     * @param array<string, mixed> $request Request data
     * @return Tenant|null Tenant entity or null
     * @since 1.0.0
     */
    private function resolveFromRequestDomain(array $request): ?Tenant {
        $host = $request['server']['HTTP_HOST'] ?? '';
        return $this->resolveFromDomain($host);
    }

    /**
     * Resolve from request path
     *
     * @param array<string, mixed> $request Request data
     * @return Tenant|null Tenant entity or null
     * @since 1.0.0
     */
    private function resolveFromRequestPath(array $request): ?Tenant {
        $path = $request['server']['REQUEST_URI'] ?? '';
        $path = parse_url($path, PHP_URL_PATH) ?: $path;
        return $this->resolveFromPath($path);
    }
}

/*
 * Test Scaffold:
 * TODO: Implement unit tests
 * - Tenant DTO:
 *   - Test constructor validation (negative ID, empty name/domain)
 *   - Test getDatabasePrefix() returns correct format
 *   - Test getCachePrefix() returns correct format
 *   - Test getStoragePath() returns correct format
 *   - Test matchesDomain() with exact and subdomain matches
 *   - Test matchesSubdomain() exact match
 *   - Test getUrl() with and without subdomain
 *   - Test toArray() serialization
 * - TenantRepository:
 *   - Test findById() returns Tenant or null
 *   - Test findByDomain() case insensitive lookup
 *   - Test findBySubdomain() case insensitive lookup
 *   - Test save() creates and updates tenants
 *   - Test deactivate() soft deletes
 *   - Test listActive() returns only active tenants
 *   - Test caching behavior
 * - TenantResolver:
 *   - Test resolve() priority order
 *   - Test resolveFromHeader() with ID and domain
 *   - Test resolveFromSubdomain() exact match
 *   - Test resolveFromDomain() lookup
 *   - Test resolveFromPath() extracts ID from path
 *   - Test set/get/clearCurrentTenant()
 *   - Test hasCurrentTenant() and getCurrentTenantId()
 *   - Test request subdomain extraction with main_domain config
 */
