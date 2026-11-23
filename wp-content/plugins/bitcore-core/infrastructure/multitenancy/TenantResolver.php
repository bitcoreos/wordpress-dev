<?php
/**
 * Tenant resolution and context detection for Bitcore multitenancy
 *
 * @package    Bitcore
 * @since      2026.03.01
 * @version    1.3.0
 * @author     hyperion
 */

declare(strict_types=1);

use BITCORE\Infrastructure\MultiTenancy\Tenant;
use BITCORE\Infrastructure\MultiTenancy\TenantRepository;

if (!defined('ABSPATH')) {
    exit;
}

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
