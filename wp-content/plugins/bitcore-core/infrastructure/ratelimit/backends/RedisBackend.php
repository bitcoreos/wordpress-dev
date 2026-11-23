<?php

declare(strict_types=1);

namespace BITCORE\Infrastructure\RateLimit\Backends;

use BITCORE\Infrastructure\RateLimit\Contracts\RateLimitBackendInterface;
use Redis;
use RedisException;

/**
 * Redis-based rate limit backend
 *
 * Implements rate limit storage using Redis with atomic
 * operations for high-performance distributed rate limiting.
 *
 * @since 1.0.0
 * @package BITCORE\Infrastructure\RateLimit\Backends
 * @version     1.0.0
 */
class RedisBackend implements RateLimitBackendInterface
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
     * @param Redis $redis Redis instance
     * @since 1.0.0
     */
    public function __construct(
        private readonly Redis $redis
    ) {
    }

    /**
     * Retrieve the current bucket state for a key
     *
     * Retrieves stored bucket data from Redis hash and
     * validates the structure before returning.
     *
     * @param string $key The rate limit key
     * @return array{count: int, reset_time: int}|null Bucket state or null if not found
     * @since 1.0.0
     */
    public function get(string $key): ?array
    {
        try {
            $data = $this->redis->hGetAll(self::KEY_PREFIX . $key);

            if (empty($data)) {
                return null;
            }

            // Validate data structure
            if (!isset($data['count'], $data['reset_time'])) {
                return null;
            }

            return [
                'count'      => (int) $data['count'],
                'reset_time' => (int) $data['reset_time'],
            ];
        } catch (RedisException $e) {
            return null;
        }
    }

    /**
     * Store the bucket state for a key
     *
     * Stores rate limit bucket data in Redis hash with TTL.
     * Uses HMSET for atomic storage of multiple fields.
     *
     * @param string $key The rate limit key
     * @param array{count: int, reset_time: int} $data The bucket state data
     * @param int $ttl Time to live in seconds
     * @return void
     * @since 1.0.0
     */
    public function set(string $key, array $data, int $ttl): void
    {
        try {
            $redisKey = self::KEY_PREFIX . $key;

            $this->redis->hMSet($redisKey, [
                'count'      => $data['count'],
                'reset_time' => $data['reset_time'],
            ]);

            $this->redis->expire($redisKey, $ttl);
        } catch (RedisException $e) {
            // Silently fail - rate limiting is best-effort
        }
    }

    /**
     * Delete the bucket state for a key
     *
     * Removes rate limit bucket data from Redis.
     *
     * @param string $key The rate limit key
     * @return void
     * @since 1.0.0
     */
    public function delete(string $key): void
    {
        try {
            $this->redis->del(self::KEY_PREFIX . $key);
        } catch (RedisException $e) {
            // Silently fail
        }
    }

    /**
     * Get the Redis instance
     *
     * Provides access to the underlying Redis connection
     * for advanced operations or debugging.
     *
     * @return Redis The Redis instance
     * @since 1.0.0
     */
    public function getRedis(): Redis
    {
        return $this->redis;
    }

    /**
     * Perform atomic increment on bucket counter
     *
     * Uses Redis HINCRBY for atomic counter operations.
     * Returns the new count or null on failure.
     *
     * @param string $key The rate limit key
     * @param int $amount Amount to increment by
     * @return int|null New count or null on failure
     * @since 1.0.0
     */
    public function increment(string $key, int $amount = 1): ?int
    {
        try {
            return (int) $this->redis->hIncrBy(
                self::KEY_PREFIX . $key,
                'count',
                $amount
            );
        } catch (RedisException $e) {
            return null;
        }
    }
}
