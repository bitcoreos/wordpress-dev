<?php

declare(strict_types=1);

namespace BITCORE\Infrastructure\RateLimit\DTO;

use DateTimeImmutable;

/**
 * Data Transfer Object for rate limit check results
 *
 * Encapsulates the outcome of a rate limit check with
 * all necessary metadata for rate limiting decisions.
 *
 * @since 1.0.0
 * @package BITCORE\Infrastructure\RateLimit\DTO
 * @version     1.0.0
 */
readonly class RateLimitResult
{
    /**
     * RateLimitResult constructor
     *
     * @param bool $allowed Whether the request is allowed
     * @param int $remaining Number of remaining requests in current window
     * @param int $limit Maximum number of requests allowed in window
     * @param int $resetTime Unix timestamp when the rate limit resets
     * @param DateTimeImmutable|null $retryAfter When the next request will be allowed (null if allowed now)
     * @since 1.0.0
     */
    public function __construct(
        public bool $allowed,
        public int $remaining,
        public int $limit,
        public int $resetTime,
        public ?DateTimeImmutable $retryAfter = null
    ) {
    }

    /**
     * Convert rate limit result to HTTP headers
     *
     * Returns an array of X-RateLimit-* headers suitable for
     * inclusion in HTTP responses. Includes Retry-After header
     * when rate limit is exceeded.
     *
     * @return array<string, string> HTTP headers
     * @since 1.0.0
     */
    public function toHeaders(): array
    {
        $headers = [
            'X-RateLimit-Limit'     => (string) $this->limit,
            'X-RateLimit-Remaining' => (string) max(0, $this->remaining),
            'X-RateLimit-Reset'   => (string) $this->resetTime,
        ];

        if (!$this->allowed && $this->retryAfter !== null) {
            $retryAfterSeconds = $this->retryAfter->getTimestamp() - time();
            $headers['Retry-After'] = (string) max(1, $retryAfterSeconds);
        }

        return $headers;
    }

    /**
     * Create a result for an allowed request
     *
     * Factory method for creating allowed rate limit results.
     *
     * @param int $remaining Number of remaining requests
     * @param int $limit Maximum requests allowed
     * @param int $resetTime Unix timestamp when limit resets
     * @return self Allowed rate limit result
     * @since 1.0.0
     */
    public static function allowed(int $remaining, int $limit, int $resetTime): self
    {
        return new self(
            allowed: true,
            remaining: $remaining,
            limit: $limit,
            resetTime: $resetTime,
            retryAfter: null
        );
    }

    /**
     * Create a result for a denied request
     *
     * Factory method for creating denied rate limit results
     * with retry-after information.
     *
     * @param int $limit Maximum requests allowed
     * @param int $resetTime Unix timestamp when limit resets
     * @param DateTimeImmutable $retryAfter When next request allowed
     * @return self Denied rate limit result
     * @since 1.0.0
     */
    public static function denied(int $limit, int $resetTime, DateTimeImmutable $retryAfter): self
    {
        return new self(
            allowed: false,
            remaining: 0,
            limit: $limit,
            resetTime: $resetTime,
            retryAfter: $retryAfter
        );
    }
}
