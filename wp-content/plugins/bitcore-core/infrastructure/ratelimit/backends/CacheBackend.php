<?php

declare(strict_types=1);

namespace BITCORE\Infrastructure\RateLimit\Backends;

use BITCORE\Infrastructure\RateLimit\Contracts\RateLimitBackendInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * PSR-16 Cache-based rate limit backend
 *
 * Implements rate limit storage using PSR-16 compatible
 * cache systems for distributed rate limiting.
 *
 * @since 1.0.0
 * @package BITCORE\Infrastructure\RateLimit\Backends
 * @version     1.0.0
 */
class CacheBackend implements RateLimitBackendInterface
{
    /**
     * Key prefix for rate limit buckets
     *
     * @var string
     * @since 1.0.0
     */
    private const KEY_PREFIX = 'ratelimit:';

    /**
     * Constructor
     *
     * @param CacheInterface $cache PSR-16 compatible cache instance
     * @since 1.0.0
     */
    public function __construct(
        private readonly CacheInterface $cache
    ) {
    }

    /**
     * Retrieve the current bucket state for a key
     *
     * Retrieves stored bucket data from cache and validates
     * the structure before returning.
     *
     * @param string $key The rate limit key
     * @return array{count: int, reset_time: int}|null Bucket state or null if not found
     * @since 1.0.0
     */
    public function get(string $key): ?array
    {
        $data = $this->cache->get(self::KEY_PREFIX . $key);

        if ($data === null) {
            return null;
        }

        // Validate data structure
        if (!is_array($data) || !isset($data['count'], $data['reset_time'])) {
            return null;
        }

        return [
            'count'      => (int) $data['count'],
            'reset_time' => (int) $data['reset_time'],
        ];
    }

    /**
     * Store the bucket state for a key
     *
     * Stores rate limit bucket data in cache with the
     * specified TTL.
     *
     * @param string $key The rate limit key
     * @param array{count: int, reset_time: int} $data The bucket state data
     * @param int $ttl Time to live in seconds
     * @return void
     * @since 1.0.0
     */
    public function set(string $key, array $data, int $ttl): void
    {
        $this->cache->set(
            self::KEY_PREFIX . $key,
            $data,
            $ttl
        );
    }

    /**
     * Delete the bucket state for a key
     *
     * Removes rate limit bucket data from cache.
     *
     * @param string $key The rate limit key
     * @return void
     * @since 1.0.0
     */
    public function delete(string $key): void
    {
        $this->cache->delete(self::KEY_PREFIX . $key);
    }

    /**
     * Get the cache instance
     *
     * Provides access to the underlying cache for
     * advanced operations or debugging.
     *
     * @return CacheInterface The cache instance
     * @since 1.0.0
     */
    public function getCache(): CacheInterface
    {
        return $this->cache;
    }
}
