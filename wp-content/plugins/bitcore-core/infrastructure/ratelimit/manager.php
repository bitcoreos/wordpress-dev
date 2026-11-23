<?php

declare(strict_types=1);

namespace BITCORE\Infrastructure\RateLimit;

use BITCORE\Infrastructure\RateLimit\Contracts\RateLimitBackendInterface;
use BITCORE\Infrastructure\RateLimit\Backends\CacheBackend;
use BITCORE\Infrastructure\RateLimit\DTO\RateLimitResult;
use Psr\SimpleCache\CacheInterface;
use DateTimeImmutable;
use DateInterval;

/**
 * Token Bucket Rate Limit Manager
 *
 * Implements distributed rate limiting using the token bucket algorithm.
 * Supports multiple backends for different storage systems.
 *
 * The token bucket algorithm works by:
 * 1. Each client gets a bucket with a fixed capacity (maxAttempts)
 * 2. Each request consumes one token from the bucket
 * 3. Tokens refill at a constant rate (decaySeconds)
 * 4. When bucket is empty, requests are denied until tokens refill
 *
 * @since 1.0.0
 * @package BITCORE\Infrastructure\RateLimit
 * @version     1.0.0
 */
class RateLimitManager
{
    /**
     * Default maximum attempts per time window
     *
     * @var int
     * @since 1.0.0
     */
    private const DEFAULT_MAX_ATTEMPTS = 60;

    /**
     * Default time window in seconds
     *
     * @var int
     * @since 1.0.0
     */
    private const DEFAULT_DECAY_SECONDS = 60;

    /**
     * Key prefix for rate limit buckets
     *
     * @var string
     * @since 1.0.0
     */
    private const KEY_PREFIX = 'ratelimit:';

    /**
     * Configuration array
     *
     * @var array<string, mixed>
     * @since 1.0.0
     */
    private array $config;

    /**
     * Rate limit backend for persistence
     *
     * @var RateLimitBackendInterface
     * @since 1.0.0
     */
    private RateLimitBackendInterface $backend;

    /**
     * Constructor
     *
     * @param CacheInterface $cache PSR-16 compatible cache instance
     * @param array<string, mixed> $config Optional configuration overrides
     * @since 1.0.0
     */
    public function __construct(
        CacheInterface $cache,
        array $config = []
    ) {
        $this->backend = new CacheBackend($cache);
        $this->config = array_merge([
            'max_attempts'   => self::DEFAULT_MAX_ATTEMPTS,
            'decay_seconds'  => self::DEFAULT_DECAY_SECONDS,
            'key_prefix'     => self::KEY_PREFIX,
        ], $config);
    }

    /**
     * Set a custom backend for rate limit storage
     *
     * Allows swapping between cache and Redis backends.
     *
     * @param RateLimitBackendInterface $backend The backend to use
     * @return void
     * @since 1.0.0
     */
    public function setBackend(RateLimitBackendInterface $backend): void
    {
        $this->backend = $backend;
    }

    /**
     * Check rate limit without incrementing
     *
     * Performs a "dry run" rate limit check to determine
     * if a request would be allowed without consuming a token.
     *
     * Token Bucket Algorithm:
     * 1. Get current bucket state
     * 2. Check if reset time passed → reset bucket
     * 3. Return remaining capacity and reset time
     *
     * @param string $key Unique identifier for the rate limit bucket (e.g., IP, user ID, API key)
     * @param int $maxAttempts Maximum allowed requests in time window
     * @param int $decaySeconds Time window in seconds
     * @return RateLimitResult Result with allowed status and metadata
     * @since 1.0.0
     */
    public function check(
        string $key,
        int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS,
        int $decaySeconds = self::DEFAULT_DECAY_SECONDS
    ): RateLimitResult {
        $bucket = $this->getBucket($key);
        $now = time();

        // Check if bucket needs reset
        if ($bucket !== null && $bucket['reset_time'] <= $now) {
            $bucket = null; // Reset bucket
        }

        // Calculate remaining attempts
        if ($bucket === null) {
            $remaining = $maxAttempts;
            $resetTime = $now + $decaySeconds;
            $retryAfter = null;
        } else {
            $remaining = max(0, $maxAttempts - $bucket['count']);
            $resetTime = $bucket['reset_time'];
            $retryAfter = $remaining > 0 ? null : (new DateTimeImmutable())->setTimestamp($resetTime);
        }

        $allowed = $remaining > 0;

        return new RateLimitResult(
            allowed: $allowed,
            remaining: $remaining,
            limit: $maxAttempts,
            resetTime: $resetTime,
            retryAfter: $retryAfter
        );
    }

    /**
     * Attempt a request with rate limit check
     *
     * Combines check and increment into a single operation.
     * Returns true if request is allowed, false otherwise.
     *
     * @param string $key Unique identifier for the rate limit bucket
     * @param int $maxAttempts Maximum allowed requests in time window
     * @param int $decaySeconds Time window in seconds
     * @return bool True if request is allowed, false if rate limited
     * @since 1.0.0
     */
    public function attempt(
        string $key,
        int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS,
        int $decaySeconds = self::DEFAULT_DECAY_SECONDS
    ): bool {
        $result = $this->check($key, $maxAttempts, $decaySeconds);

        if (!$result->allowed) {
            return false;
        }

        $this->increment($key, 1, $decaySeconds);

        return true;
    }

    /**
     * Increment the rate limit counter
     *
     * Adds to the bucket count and resets the bucket if necessary.
     * Creates a new bucket if one doesn't exist.
     *
     * @param string $key Unique identifier for the rate limit bucket
     * @param int $amount Amount to increment by (default 1)
     * @param int $decaySeconds Time window in seconds
     * @return void
     * @since 1.0.0
     */
    public function increment(
        string $key,
        int $amount = 1,
        int $decaySeconds = self::DEFAULT_DECAY_SECONDS
    ): void {
        $now = time();
        $bucket = $this->getBucket($key);

        // Reset bucket if decay period passed
        if ($bucket !== null && $bucket['reset_time'] <= $now) {
            $bucket = null;
        }

        if ($bucket === null) {
            // Create new bucket
            $bucket = [
                'count'      => $amount,
                'reset_time' => $now + $decaySeconds,
            ];
        } else {
            // Increment existing bucket
            $bucket['count'] += $amount;
        }

        // Store with TTL >= decay_seconds
        $this->backend->set($key, $bucket, $decaySeconds);
    }

    /**
     * Reset the rate limit bucket for a key
     *
     * Clears all rate limit data for the given key,
     * effectively resetting the rate limit.
     *
     * @param string $key Unique identifier for the rate limit bucket
     * @return void
     * @since 1.0.0
     */
    public function reset(string $key): void
    {
        $this->backend->delete($key);
    }

    /**
     * Get remaining attempts for a key
     *
     * Returns the number of remaining requests allowed
     * in the current time window.
     *
     * @param string $key Unique identifier for the rate limit bucket
     * @param int $maxAttempts Maximum allowed requests in time window
     * @return int Number of remaining requests
     * @since 1.0.0
     */
    public function remaining(string $key, int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS): int
    {
        $result = $this->check($key, $maxAttempts);
        return $result->remaining;
    }

    /**
     * Get seconds until rate limit resets
     *
     * Returns the number of seconds until the current
     * rate limit window expires and attempts reset.
     *
     * @param string $key Unique identifier for the rate limit bucket
     * @return int Seconds until reset, 0 if no active bucket
     * @since 1.0.0
     */
    public function availableIn(string $key): int
    {
        $bucket = $this->getBucket($key);

        if ($bucket === null) {
            return 0;
        }

        $now = time();
        $remaining = $bucket['reset_time'] - $now;

        return max(0, $remaining);
    }

    /**
     * Get rate limit headers for a key
     *
     * Returns X-RateLimit-* headers for HTTP responses.
     * Useful for API responses to inform clients of limits.
     *
     * @param string $key Unique identifier for the rate limit bucket
     * @param int $maxAttempts Maximum allowed requests in time window
     * @return array<string, string> HTTP headers
     * @since 1.0.0
     */
    public function getHeaders(string $key, int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS): array
    {
        $result = $this->check($key, $maxAttempts);
        return $result->toHeaders();
    }

    /**
     * Get the raw bucket data for a key
     *
     * Internal method to retrieve bucket state from backend.
     *
     * @param string $key The rate limit key
     * @return array{count: int, reset_time: int}|null Bucket data or null
     * @since 1.0.0
     */
    private function getBucket(string $key): ?array
    {
        return $this->backend->get($key);
    }

    /**
     * Get the current backend instance
     *
     * Provides access to the underlying backend for
     * debugging or advanced operations.
     *
     * @return RateLimitBackendInterface The current backend
     * @since 1.0.0
     */
    public function getBackend(): RateLimitBackendInterface
    {
        return $this->backend;
    }

    /**
     * Get the current configuration
     *
     * Returns the merged configuration array.
     *
     * @return array<string, mixed> Current configuration
     * @since 1.0.0
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Set a configuration value
     *
     * Overrides a specific configuration option.
     *
     * @param string $key Configuration key
     * @param mixed $value Configuration value
     * @return void
     * @since 1.0.0
     */
    public function setConfig(string $key, mixed $value): void
    {
        $this->config[$key] = $value;
    }

    /*
     * =====================================================================
     * TEST SCAFFOLD COMMENTS - Remove when tests implemented
     * =====================================================================
     *
     * @test Check rate limit without increment
     * public function test_check_returns_allowed_for_new_bucket(): void {
     *     $manager = new RateLimitManager($this->cache);
     *     $result = $manager->check('test-key');
     *     $this->assertTrue($result->allowed);
     *     $this->assertEquals(60, $result->remaining);
     * }
     *
     * @test Increment decreases remaining
     * public function test_increment_decreases_remaining(): void {
     *     $manager = new RateLimitManager($this->cache);
     *     $manager->increment('test-key');
     *     $result = $manager->check('test-key');
     *     $this->assertEquals(59, $result->remaining);
     * }
     *
     * @test Attempt returns true when allowed
     * public function test_attempt_returns_true_when_allowed(): void {
     *     $manager = new RateLimitManager($this->cache);
     *     $this->assertTrue($manager->attempt('test-key'));
     * }
     *
     * @test Attempt returns false when limited
     * public function test_attempt_returns_false_when_limited(): void {
     *     $manager = new RateLimitManager($this->cache);
     *     // Fill bucket
     *     for ($i = 0; $i < 61; $i++) {
     *         $manager->attempt('test-key', 60, 60);
     *     }
     *     $this->assertFalse($manager->attempt('test-key'));
     * }
     *
     * @test Reset clears bucket
     * public function test_reset_clears_bucket(): void {
     *     $manager = new RateLimitManager($this->cache);
     *     $manager->increment('test-key', 10);
     *     $manager->reset('test-key');
     *     $result = $manager->check('test-key');
     *     $this->assertEquals(60, $result->remaining);
     * }
     *
     * @test Headers contain rate limit info
     * public function test_getHeaders_returns_rate_limit_headers(): void {
     *     $manager = new RateLimitManager($this->cache);
     *     $headers = $manager->getHeaders('test-key');
     *     $this->assertArrayHasKey('X-RateLimit-Limit', $headers);
     *     $this->assertArrayHasKey('X-RateLimit-Remaining', $headers);
     *     $this->assertArrayHasKey('X-RateLimit-Reset', $headers);
     * }
     *
     * @test Retry-After header when limited
     * public function test_headers_include_retry_after_when_limited(): void {
     *     $manager = new RateLimitManager($this->cache);
     *     // Fill bucket
     *     for ($i = 0; $i < 61; $i++) {
     *         $manager->attempt('test-key');
     *     }
     *     $result = $manager->check('test-key');
     *     $headers = $result->toHeaders();
     *     $this->assertArrayHasKey('Retry-After', $headers);
     * }
     *
     * @test Bucket resets after decay period
     * public function test_bucket_resets_after_decay(): void {
     *     $manager = new RateLimitManager($this->cache);
     *     $manager->increment('test-key', 60, 1);
     *     sleep(2);
     *     $result = $manager->check('test-key');
     *     $this->assertEquals(60, $result->remaining);
     * }
     *
     * @test AvailableIn returns seconds until reset
     * public function test_available_in_returns_reset_time(): void {
     *     $manager = new RateLimitManager($this->cache);
     *     $manager->increment('test-key');
     *     $this->assertGreaterThan(0, $manager->availableIn('test-key'));
     * }
     *
     * @test Custom backend works
     * public function test_custom_backend_works(): void {
     *     $redis = $this->createMock(\Redis::class);
     *     $backend = new RedisBackend($redis);
     *     $manager = new RateLimitManager($this->cache);
     *     $manager->setBackend($backend);
     *     $this->assertInstanceOf(RedisBackend::class, $manager->getBackend());
     * }
     *
     * =====================================================================
     */
}
