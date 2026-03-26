<?php
/**
 * Exponential Backoff Retry Strategy
 *
 * Implements exponential backoff with jitter for webhook retries.
 *
 * @package BITCORE\Infrastructure\Webhooks
 * @since 1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Infrastructure\Webhooks;

use Throwable;

/**
 * Exponential Backoff Retry Strategy
 *
 * Implements exponential backoff with jitter and configurable limits.
 *
 * @since 1.0.0
 */
class ExponentialBackoffStrategy implements RetryStrategyInterface {
    private int $baseDelay;
    private int $maxDelay;
    private float $multiplier;

    /**
     * Constructor.
     *
     * @since 1.0.0
     * @param int $baseDelay Base delay in seconds (default: 60)
     * @param int $maxDelay Maximum delay in seconds (default: 3600)
     * @param float $multiplier Exponential multiplier (default: 2.0)
     */
    public function __construct(
        int $baseDelay = 60,
        int $maxDelay = 3600,
        float $multiplier = 2.0
    ) {
        $this->baseDelay = $baseDelay;
        $this->maxDelay = $maxDelay;
        $this->multiplier = $multiplier;
    }

    /**
     * Calculate delay with exponential backoff and jitter.
     *
     * @since 1.0.0
     * @param int $attempt Attempt number (0-indexed)
     * @return int Delay in seconds
     */
    public function getDelay(int $attempt): int {
        // Exponential: base * multiplier^attempt
        $delay = (int) ($this->baseDelay * pow($this->multiplier, $attempt));

        // Cap at max delay
        $delay = min($delay, $this->maxDelay);

        // Add ±20% jitter for thundering herd protection
        $jitter = (int) ($delay * 0.2);
        $delay += random_int(-$jitter, $jitter);

        return max(1, $delay);
    }

    /**
     * Determine if retry should be attempted based on error.
     *
     * 2xx = success, 4xx (except 429) = permanent failure, 5xx/429 = retry
     *
     * @since 1.0.0
     * @param int $attempt Current attempt number
     * @param Throwable $error The error
     * @return bool True if should retry
     */
    public function shouldRetry(int $attempt, Throwable $error): bool {
        // Check if it's a WebhookResult with status code
        if ($error instanceof WebhookResult) {
            $statusCode = $error->statusCode;

            // 2xx = success, no retry
            if ($statusCode >= 200 && $statusCode < 300) {
                return false;
            }

            // 4xx client errors (except 429) = permanent failure
            if ($statusCode >= 400 && $statusCode < 500 && $statusCode !== 429) {
                return false;
            }

            // 5xx server errors and 429 = retry
            return true;
        }

        // Network errors = retry
        return true;
    }
}
