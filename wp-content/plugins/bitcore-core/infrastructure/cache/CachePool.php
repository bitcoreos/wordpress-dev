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
