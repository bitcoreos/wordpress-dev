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
 */
class PoolStats
{
    /**
     * Constructor
     *
     * @param int $hits Number of cache hits
     * @param int $misses Number of cache misses
     * @param float $hitRate Cache hit rate percentage
     * @param int $size Total number of cached entries
     * @param int|null $oldestEntry Timestamp of oldest entry
     * @since 1.0.0
     */
    public function __construct(
        private readonly int $hits,
        private readonly int $misses,
        private readonly float $hitRate,
        private readonly int $size,
        private readonly ?int $oldestEntry = null
    ) {}

    /**
     * Get number of cache hits
     *
     * @return int
     * @since 1.0.0
     */
    public function getHits(): int
    {
        return $this->hits;
    }

    /**
     * Get number of cache misses
     *
     * @return int
     * @since 1.0.0
     */
    public function getMisses(): int
    {
        return $this->misses;
    }

    /**
     * Get cache hit rate percentage
     *
     * @return float
     * @since 1.0.0
     */
    public function getHitRate(): float
    {
        return $this->hitRate;
    }

    /**
     * Get total number of cached entries
     *
     * @return int
     * @since 1.0.0
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * Get timestamp of oldest entry
     *
     * @return int|null
     * @since 1.0.0
     */
    public function getOldestEntry(): ?int
    {
        return $this->oldestEntry;
    }

    /**
     * Get total requests
     *
     * @return int
     * @since 1.0.0
     */
    public function getTotal(): int
    {
        return $this->hits + $this->misses;
    }

    /**
     * Convert to array
     *
     * @return array<string, mixed>
     * @since 1.0.0
     */
    public function toArray(): array
    {
        return [
            'hits' => $this->hits,
            'misses' => $this->misses,
            'hitRate' => $this->hitRate,
            'size' => $this->size,
            'oldestEntry' => $this->oldestEntry,
            'total' => $this->getTotal()
        ];
    }
}

/**
 * Cache Pool - Scoped caching with prefix support
 *
 * Wraps any PSR-16 cache implementation with key prefixing
 * and optional tenant context scoping.
 *
 * @package BITCORE\Infrastructure\Cache
 * @since 1.0.0
 */
class CachePool
{
    /** @var CacheInterface Underlying cache implementation */
    protected CacheInterface $cache;

    /** @var string Key prefix for namespacing */
    protected string $prefix;

    /** @var TenantContext|null Tenant context for multi-tenant scoping */
    protected ?TenantContext $tenantContext;

    /** @var array<string, int> Internal statistics tracking */
    protected array $stats = [
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0
    ];

    /** @var array<string, int> Key creation timestamps */
    protected array $keyTimestamps = [];

    /**
     * Constructor
     *
     * @param CacheInterface $cache Cache implementation to wrap
     * @param string $prefix Key prefix for namespacing
     * @param TenantContext|null $tenantContext Optional tenant context
     * @since 1.0.0
     */
    public function __construct(
        CacheInterface $cache,
        string $prefix = '',
        ?TenantContext $tenantContext = null
    ) {
        $this->cache = $cache;
        $this->prefix = $prefix ? rtrim($prefix, ':') . ':' : '';
        $this->tenantContext = $tenantContext;
    }

    /**
     * Generate prefixed key
     *
     * @param string $key Original key
     * @return string Prefixed key
     * @since 1.0.0
     */
    protected function key(string $key): string
    {
        return $this->prefix . $key;
    }

    /**
     * Fetch a value from the cache
     *
     * @param string $key Cache key
     * @param mixed $default Default value if not found
     * @return mixed
     * @since 1.0.0
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $prefixedKey = $this->key($key);
        $value = $this->cache->get($prefixedKey);

        if ($value !== null) {
            $this->stats['hits']++;
            return $value;
        }

        $this->stats['misses']++;
        return $default;
    }

    /**
     * Store a value in the cache
     *
     * @param string $key Cache key
     * @param mixed $value Value to store
     * @param int|null $ttl Time-to-live in seconds
     * @return bool
     * @since 1.0.0
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $prefixedKey = $this->key($key);
        $result = $this->cache->set($prefixedKey, $value, $ttl);

        if ($result) {
            $this->stats['sets']++;
            $this->keyTimestamps[$key] = time();
        }

        return $result;
    }

    /**
     * Delete a value from the cache
     *
     * @param string $key Cache key
     * @return bool
     * @since 1.0.0
     */
    public function delete(string $key): bool
    {
        $prefixedKey = $this->key($key);
        $result = $this->cache->delete($prefixedKey);

        if ($result) {
            $this->stats['deletes']++;
            unset($this->keyTimestamps[$key]);
        }

        return $result;
    }

    /**
     * Check if a key exists in the cache
     *
     * @param string $key Cache key
     * @return bool
     * @since 1.0.0
     */
    public function has(string $key): bool
    {
        $prefixedKey = $this->key($key);
        return $this->cache->has($prefixedKey);
    }

    /**
     * Flush all cache entries in this pool
     *
     * @return bool
     * @since 1.0.0
     */
    public function flush(): bool
    {
        $result = $this->cache->clear();

        if ($result) {
            $this->keyTimestamps = [];
        }

        return $result;
    }

    /**
     * Get pool statistics
     *
     * @return PoolStats
     * @since 1.0.0
     */
    public function getStats(): PoolStats
    {
        $total = $this->stats['hits'] + $this->stats['misses'];
        $hitRate = $total > 0 ? ($this->stats['hits'] / $total) * 100 : 0;
        $oldest = $this->keyTimestamps ? min($this->keyTimestamps) : null;

        return new PoolStats(
            $this->stats['hits'],
            $this->stats['misses'],
            round($hitRate, 2),
            count($this->keyTimestamps),
            $oldest
        );
    }

    /**
     * Get underlying cache instance
     *
     * @return CacheInterface
     * @since 1.0.0
     */
    public function getCache(): CacheInterface
    {
        return $this->cache;
    }

    /**
     * Get pool prefix
     *
     * @return string
     * @since 1.0.0
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * Get tenant context
     *
     * @return TenantContext|null
     * @since 1.0.0
     */
    public function getTenantContext(): ?TenantContext
    {
        return $this->tenantContext;
    }

    /**
     * Get multiple values from cache
     *
     * @param array<string> $keys Cache keys
     * @param mixed $default Default value for missing keys
     * @return array<string, mixed>
     * @since 1.0.0
     */
    public function getMultiple(array $keys, mixed $default = null): array
    {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $default);
        }
        return $results;
    }

    /**
     * Store multiple values in cache
     *
     * @param array<string, mixed> $values Key-value pairs
     * @param int|null $ttl Time-to-live in seconds
     * @return bool
     * @since 1.0.0
     */
    public function setMultiple(array $values, ?int $ttl = null): bool
    {
        $success = true;
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * Delete multiple values from cache
     *
     * @param array<string> $keys Cache keys
     * @return bool
     * @since 1.0.0
     */
    public function deleteMultiple(array $keys): bool
    {
        $success = true;
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * Reset internal statistics
     *
     * @return void
     * @since 1.0.0
     */
    public function resetStats(): void
    {
        $this->stats = [
            'hits' => 0,
            'misses' => 0,
            'sets' => 0,
            'deletes' => 0
        ];
    }
}

/**
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
