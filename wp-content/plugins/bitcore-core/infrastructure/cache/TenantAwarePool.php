<?php
/**
 * Cache Pool Implementation
 *
 * Pooled caching with tenant-aware key scoping and statistics tracking
 * for WordPress SaaS infrastructure.
 *
 * @package BITCORE\Infrastructure\Cache
 * @since 1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Infrastructure\Cache;

use Psr\SimpleCache\CacheInterface;
use DateTime;

/**
 * Pool Statistics DTO
 *
 * Immutable data transfer object for cache pool metrics.
 *
 * @package BITCORE\Infrastructure\Cache
 * @since 1.0.0

 * Tenant-Aware Cache Pool
 *
 * Extends CachePool with automatic tenant scoping for multi-tenant
 * SaaS environments.
 *
 * @package BITCORE\Infrastructure\Cache
 * @since 1.0.0
 */
class TenantAwarePool extends CachePool
{
    /** @var int|null Current tenant ID */
    private ?int $tenantId;

    /**
     * Constructor
     *
     * @param CacheInterface $cache Cache implementation
     * @param string $prefix Key prefix
     * @param TenantContext $tenantContext Tenant context (required)
     * @param int|null $tenantId Optional explicit tenant ID
     * @since 1.0.0
     */
    public function __construct(
        CacheInterface $cache,
        string $prefix = '',
        TenantContext $tenantContext,
        ?int $tenantId = null
    ) {
        parent::__construct($cache, $prefix, $tenantContext);
        $this->tenantId = $tenantId ?? $tenantContext->getCurrentTenant();
    }

    /**
     * Generate tenant-scoped key
     *
     * @param string $key Original key
     * @return string Tenant-scoped key
     * @since 1.0.0
     */
    protected function key(string $key): string
    {
        $tenantPrefix = $this->tenantId ? 't:' . $this->tenantId . ':' : 'global:';
        return $tenantPrefix . parent::key($key);
    }

    /**
     * Flush all cache for current tenant
     *
     * @param int $tenantId Tenant ID to flush
     * @return bool
     * @since 1.0.0
     */
    public function flushTenant(int $tenantId): bool
    {
        if ($this->cache instanceof ClearableInterface) {
            return $this->cache->clearByPattern('/^t:' . $tenantId . ':/');
        }

        // Fallback: manual iteration if clearByPattern not available
        // Note: This is inefficient for large caches
        return $this->clearTenantFallback($tenantId);
    }

    /**
     * Fallback tenant clearing for non-ClearableInterface caches
     *
     * @param int $tenantId Tenant ID
     * @return bool
     * @since 1.0.0
     */
    private function clearTenantFallback(int $tenantId): bool
    {
        // This is a placeholder - in production, you'd need to track keys
        // or use a cache that supports pattern deletion
        return true;
    }

    /**
     * Migrate cache entries from one tenant to another
     *
     * @param int $fromTenantId Source tenant ID
     * @param int $toTenantId Destination tenant ID
     * @return int Number of migrated entries
     * @since 1.0.0
     */
    public function migrate(int $fromTenantId, int $toTenantId): int
    {
        $migrated = 0;

        // Build old tenant prefix
        $oldPrefix = 't:' . $fromTenantId . ':';

        // Get all keys matching old tenant (requires cache support)
        if ($this->cache instanceof ClearableInterface) {
            // Note: This is a simplified implementation
            // Production would need proper key enumeration
            foreach ($this->keyTimestamps as $key => $timestamp) {
                $oldKey = $oldPrefix . parent::getPrefix() . $key;
                if (strpos($oldKey, $oldPrefix) === 0) {
                    $value = $this->cache->get($oldKey);
                    if ($value !== null) {
                        // Store under new tenant
                        $this->tenantId = $toTenantId;
                        $this->set($key, $value);
                        $this->cache->delete($oldKey);
                        $migrated++;
                        $this->tenantId = $fromTenantId; // Reset
                    }
                }
            }
        }

        return $migrated;
    }

    /**
     * Get current tenant ID
     *
     * @return int|null
     * @since 1.0.0
     */
    public function getTenantId(): ?int
    {
        return $this->tenantId;
    }

    /**
     * Set tenant ID for this pool instance
     *
     * @param int|null $tenantId
     * @return void
     * @since 1.0.0
     */
    public function setTenantId(?int $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    /**
     * Check if key belongs to tenant
     *
     * @param string $key Cache key
     * @param int $tenantId Tenant ID
     * @return bool
     * @since 1.0.0
     */
    public function keyBelongsToTenant(string $key, int $tenantId): bool
    {
        $prefixed = $this->key($key);
        return strpos($prefixed, 't:' . $tenantId . ':') === 0;
    }
}

/*
 * ------------------------------------------------------------------------
 * TEST SCAFFOLD - Phase 4: Caching Layer
 * ------------------------------------------------------------------------
 *
 * Test coverage targets:
 *
 * 1. CacheManager Tests:
 *    - PSR-16 compliance: get(), set(), delete(), clear(), has()
 *    - Multi-tier operation: verify fallback chain
 *    - Tier management: addTier(), getTier(), removeTier()
 *    - Bulk operations: getMultiple(), setMultiple(), deleteMultiple()
 *    - TTL handling: DateInterval and integer TTL
 *    - Cache warming: warm(), warmTenant()
 *    - Invalidation: invalidate(), invalidateTenant()
 *    - Statistics: getStats() returns accurate metrics
 *
 * 2. CachePool Tests:
 *    - Key prefixing: verify prefix applied correctly
 *    - Basic operations: get(), set(), delete(), has(), flush()
 *    - Bulk operations: getMultiple(), setMultiple(), deleteMultiple()
 *    - Statistics: getStats() with hits/misses/hitRate
 *    - PoolStats DTO: all getters return correct values
 *
 * 3. TenantAwarePool Tests:
 *    - Tenant scoping: keys include tenant ID
 *    - Tenant operations: flushTenant() clears tenant data
 *    - Migration: migrate() transfers data between tenants
 *    - Tenant isolation: keys from different tenants don't collide
 *
 * 4. CacheTier Tests:
 *    - ObjectCacheTier: uses wp_cache_* functions
 *    - TransientCacheTier: uses transient API
 *    - DatabaseCacheTier: uses options table
 *    - ClearableInterface: clearByPattern() implementation
 *
 * 5. Integration Tests:
 *    - Multi-tenant scenario: separate tenant data
 *    - Fallback behavior: tier chain works correctly
 *    - Statistics accuracy: hits/misses tracked correctly
 *    - TTL expiration: entries expire correctly
 *
 * WordPress Functions to Mock:
 * - wp_cache_get(), wp_cache_set(), wp_cache_delete(), wp_cache_flush()
 * - wp_using_ext_object_cache()
 * - get_transient(), set_transient(), delete_transient()
 * - get_option(), update_option(), delete_option()
 * - global $wpdb for database operations
 *
 * PSR-16 Compliance Checklist:
 * - [ ] get() returns default on miss
 * - [ ] set() stores with TTL
 * - [ ] delete() returns bool
 * - [ ] clear() empties cache
 * - [ ] has() checks existence
 * - [ ] getMultiple() returns iterable
 * - [ ] setMultiple() accepts iterable
 * - [ ] deleteMultiple() accepts iterable
 * - [ ] DateInterval TTL support
 * - [ ] InvalidArgumentException on bad key
 * - [ ] Exceptions on connection errors
 */
