<?php
/**
 * Cache Manager Implementation
 *
 * PSR-16 SimpleCache implementation with multi-tier caching support
 * for WordPress SaaS infrastructure.
 *
 * @package BITCORE\Infrastructure\Cache
 * @since 1.0.0
 * @version     1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Infrastructure\Cache;

use Psr\SimpleCache\CacheInterface;
use DateInterval;
use DateTime;
use BITCORE\Infrastructure\Multitenancy\TenantContext;

/**
 * Cache tier priority enumeration
 *
 * @since 1.0.0
 */
enum CacheTier: int
{
    case MEMORY = 0;
    case OBJECT_CACHE = 1;
    case TRANSIENTS = 2;
    case DATABASE = 3;
}

/**
 * Cache Manager - PSR-16 SimpleCache Implementation
 *
 * Multi-tier caching system with fallback chain:
 * Memory (priority 0) → WP Object Cache (priority 1) → 
 * Transients (priority 2) → Database (priority 3)
 *
 * @package BITCORE\Infrastructure\Cache
 * @since 1.0.0
 */
class CacheManager implements CacheInterface
{
    /** @var array<string, array{cache: CacheInterface, priority: int}> */
    private array $tiers = [];

    /** @var int Default TTL in seconds */
    private int $defaultTtl;

    /** @var array<string, bool> Tracking for cache hits/misses */
    private array $stats = [
        'hits' => [],
        'misses' => []
    ];

    /** @var CacheInterface|null Primary tier for quick access */
    private ?CacheInterface $primaryTier = null;

    /**
     * Constructor
     *
     * @param int $defaultTtl Default time-to-live in seconds
     * @since 1.0.0
     */
    public function __construct(int $defaultTtl = 3600)
    {
        $this->defaultTtl = $defaultTtl;
        $this->initializeDefaultTiers();
    }

    /**
     * Initialize default WordPress cache tiers
     *
     * @return void
     * @since 1.0.0
     */
    private function initializeDefaultTiers(): void
    {
        // Object Cache tier (if available)
        if (wp_using_ext_object_cache()) {
            $this->addTier(new ObjectCacheTier(), CacheTier::OBJECT_CACHE->value);
        }

        // Transients tier
        $this->addTier(new TransientCacheTier(), CacheTier::TRANSIENTS->value);

        // Database tier as fallback
        $this->addTier(new DatabaseCacheTier(), CacheTier::DATABASE->value);
    }

    /**
     * Add a cache tier to the manager
     *
     * @param CacheInterface $cache Cache implementation
     * @param int $priority Tier priority (lower = faster)
     * @param string $name Unique tier identifier
     * @return void
     * @since 1.0.0
     */
    public function addTier(CacheInterface $cache, int $priority = 0, string $name = ''): void
    {
        if ($name === '') {
            $name = 'tier_' . $priority . '_' . spl_object_id($cache);
        }

        $this->tiers[$name] = [
            'cache' => $cache,
            'priority' => $priority
        ];

        // Re-sort by priority
        uasort($this->tiers, function ($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });

        // Update primary tier (fastest)
        if ($priority === min(array_column($this->tiers, 'priority'))) {
            $this->primaryTier = $cache;
        }
    }

    /**
     * Get a specific tier by name
     *
     * @param string $name Tier identifier
     * @return CacheInterface|null
     * @since 1.0.0
     */
    public function getTier(string $name): ?CacheInterface
    {
        return $this->tiers[$name]['cache'] ?? null;
    }

    /**
     * Remove a tier from the manager
     *
     * @param string $name Tier identifier
     * @return void
     * @since 1.0.0
     */
    public function removeTier(string $name): void
    {
        if (isset($this->tiers[$name])) {
            if ($this->primaryTier === $this->tiers[$name]['cache']) {
                $this->primaryTier = null;
            }
            unset($this->tiers[$name]);

            // Recalculate primary tier
            if (!empty($this->tiers)) {
                $first = reset($this->tiers);
                $this->primaryTier = $first['cache'];
            }
        }
    }

    /**
     * Fetch a value from the cache
     *
     * @param string $key Unique cache key
     * @param mixed $default Default value if key not found
     * @return mixed
     * @since 1.0.0
     */
    public function get(string $key, mixed $default = null): mixed
    {
        foreach ($this->tiers as $tierName => $tier) {
            $value = $tier['cache']->get($key);
            if ($value !== null) {
                $this->stats['hits'][$key] = true;
                // Populate faster tiers (cache warming)
                if ($tierName !== array_key_first($this->tiers)) {
                    $this->propagateToFasterTiers($key, $value);
                }
                return $value;
            }
        }

        $this->stats['misses'][$key] = true;
        return $default;
    }

    /**
     * Store a value in cache across all tiers
     *
     * @param string $key Cache key
     * @param mixed $value Value to store
     * @param null|int|DateInterval $ttl Time-to-live
     * @return bool
     * @since 1.0.0
     */
    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $success = true;
        foreach ($this->tiers as $tier) {
            if (!$tier['cache']->set($key, $value, $ttl)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * Delete a value from cache across all tiers
     *
     * @param string $key Cache key
     * @return bool
     * @since 1.0.0
     */
    public function delete(string $key): bool
    {
        $success = true;
        foreach ($this->tiers as $tier) {
            if (!$tier['cache']->delete($key)) {
                $success = false;
            }
        }
        unset($this->stats['hits'][$key], $this->stats['misses'][$key]);
        return $success;
    }

    /**
     * Clear all cache across all tiers
     *
     * @return bool
     * @since 1.0.0
     */
    public function clear(): bool
    {
        $success = true;
        foreach ($this->tiers as $tier) {
            if (!$tier['cache']->clear()) {
                $success = false;
            }
        }
        $this->stats = ['hits' => [], 'misses' => []];
        return $success;
    }

    /**
     * Retrieve multiple values from cache
     *
     * @param iterable $keys List of cache keys
     * @param mixed $default Default value for missing keys
     * @return iterable
     * @since 1.0.0
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
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
     * @param iterable $values Key-value pairs
     * @param null|int|DateInterval $ttl Time-to-live
     * @return bool
     * @since 1.0.0
     */
    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
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
     * @param iterable $keys List of cache keys
     * @return bool
     * @since 1.0.0
     */
    public function deleteMultiple(iterable $keys): bool
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
     * Check if a key exists in cache
     *
     * @param string $key Cache key
     * @return bool
     * @since 1.0.0
     */
    public function has(string $key): bool
    {
        foreach ($this->tiers as $tier) {
            if ($tier['cache']->has($key)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Warm cache with specific keys
     *
     * @param array $keys Keys to warm
     * @return void
     * @since 1.0.0
     */
    public function warm(array $keys): void
    {
        $this->getMultiple($keys);
    }

    /**
     * Warm cache for a specific tenant
     *
     * @param int $tenantId Tenant identifier
     * @return void
     * @since 1.0.0
     */
    public function warmTenant(int $tenantId): void
    {
        $pattern = 'tenant:' . $tenantId . ':*';
        $this->invalidate($pattern);
    }

    /**
     * Invalidate cache entries matching a pattern
     *
     * @param string $pattern Glob-style pattern (e.g., "user:*:profile")
     * @return int Number of invalidated entries
     * @since 1.0.0
     */
    public function invalidate(string $pattern): int
    {
        $count = 0;
        // Convert glob to regex
        $regex = '/^' . str_replace(['*', '?'], ['.*', '.'], $pattern) . '$/';

        foreach ($this->tiers as $tier) {
            if ($tier['cache'] instanceof ClearableInterface) {
                $count += $tier['cache']->clearByPattern($regex);
            }
        }

        return $count;
    }

    /**
     * Invalidate all cache for a specific tenant
     *
     * @param int $tenantId Tenant identifier
     * @return int Number of invalidated entries
     * @since 1.0.0
     */
    public function invalidateTenant(int $tenantId): int
    {
        return $this->invalidate('tenant:' . $tenantId . ':*');
    }

    /**
     * Propagate a value to faster cache tiers
     *
     * @param string $key Cache key
     * @param mixed $value Value to propagate
     * @return void
     * @since 1.0.0
     */
    private function propagateToFasterTiers(string $key, mixed $value): void
    {
        $found = false;
        foreach ($this->tiers as $tier) {
            if ($found) {
                $tier['cache']->set($key, $value, $this->defaultTtl);
            }
            // Check if this is where we found the value
            if ($tier['cache']->has($key)) {
                $found = true;
            }
        }
    }

    /**
     * Get cache statistics
     *
     * @return array{hits: int, misses: int, hitRate: float}
     * @since 1.0.0
     */
    public function getStats(): array
    {
        $hits = count($this->stats['hits']);
        $misses = count($this->stats['misses']);
        $total = $hits + $misses;

        return [
            'hits' => $hits,
            'misses' => $misses,
            'hitRate' => $total > 0 ? ($hits / $total) * 100 : 0
        ];
    }
}


