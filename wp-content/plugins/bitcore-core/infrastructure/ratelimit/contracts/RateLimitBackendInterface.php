<?php

declare(strict_types=1);

namespace BITCORE\Infrastructure\RateLimit\Contracts;

/**
 * Interface for rate limit storage backends
 *
 * Defines the contract for storing and retrieving rate limit
 * bucket state data from various storage systems.
 *
 * @since 1.0.0
 * @package BITCORE\Infrastructure\RateLimit\Contracts
 * @version     1.0.0
 */
interface RateLimitBackendInterface
{
    /**
     * Retrieve the current bucket state for a key
     *
     * Returns an array with 'count' and 'reset_time' keys,
     * or null values if the bucket doesn't exist.
     *
     * @param string $key The rate limit key
     * @return array{count: int, reset_time: int}|null Bucket state or null if not found
     * @since 1.0.0
     */
    public function get(string $key): ?array;

    /**
     * Store the bucket state for a key
     *
     * @param string $key The rate limit key
     * @param array{count: int, reset_time: int} $data The bucket state data
     * @param int $ttl Time to live in seconds
     * @return void
     * @since 1.0.0
     */
    public function set(string $key, array $data, int $ttl): void;

    /**
     * Delete the bucket state for a key
     *
     * @param string $key The rate limit key
     * @return void
     * @since 1.0.0
     */
    public function delete(string $key): void;
}
