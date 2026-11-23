<?php
/**
 * Webhook Retry Strategy
 *
 * Retry strategy interface and exponential backoff implementation.
 *
 * @package BITCORE\Infrastructure\Webhooks
 * @since 1.0.0
 * @version     1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Infrastructure\Webhooks;

/**
 * Retry Strategy Interface
 *
 * Defines the contract for retry delay calculation strategies.
 *
 * @since 1.0.0
 */
interface RetryStrategyInterface {
    /**
     * Get delay before next retry attempt.
     *
     * @since 1.0.0
     * @param int $attempt Current attempt number (1-based)
     * @return int Delay in seconds
     */
    public function getDelay(int $attempt): int;

    /**
     * Get maximum number of retry attempts.
     *
     * @since 1.0.0
     * @return int Maximum attempts
     */
    public function getMaxAttempts(): int;

    /**
     * Check if retries are exhausted.
     *
     * @since 1.0.0
     * @param int $attempts Number of attempts made
     * @return bool True if no more retries allowed
     */
    public function isExhausted(int $attempts): bool;
}

/**
 * Exponential Backoff Strategy
 *
 * Implements exponential backoff with optional jitter for retry delays.
 *
 * @since 1.0.0
 */
class ExponentialBackoffStrategy implements RetryStrategyInterface {
    /**
     * @param int $baseDelay Base delay in seconds
     * @param float $multiplier Exponential multiplier
     * @param int $maxDelay Maximum delay cap in seconds
     * @param int $maxAttempts Maximum retry attempts
     * @param float $jitterFactor Random jitter factor (0-1)
     */
    public function __construct(
        private int $baseDelay = 1,
        private float $multiplier = 2.0,
        private int $maxDelay = 300,
        private int $maxAttempts = 5,
        private float $jitterFactor = 0.1
    ) {}

    /**
     * Calculate delay with exponential backoff and jitter.
     *
     * @since 1.0.0
     * @param int $attempt Current attempt number
     * @return int Delay in seconds
     */
    public function getDelay(int $attempt): int {
        // Calculate exponential delay
        $delay = $this->baseDelay * pow($this->multiplier, $attempt - 1);
        
        // Cap at maximum delay
        $delay = min($delay, $this->maxDelay);
        
        // Add jitter to prevent thundering herd
        if ($this->jitterFactor > 0) {
            $jitter = $delay * $this->jitterFactor * (mt_rand(0, 100) / 100 - 0.5) * 2;
            $delay = max(1, $delay + $jitter);
        }
        
        return (int) round($delay);
    }

    /**
     * Get maximum retry attempts.
     *
     * @since 1.0.0
     * @return int Maximum attempts
     */
    public function getMaxAttempts(): int {
        return $this->maxAttempts;
    }

    /**
     * Check if retries are exhausted.
     *
     * @since 1.0.0
     * @param int $attempts Number of attempts made
     * @return bool True if no more retries allowed
     */
    public function isExhausted(int $attempts): bool {
        return $attempts >= $this->maxAttempts;
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
    public function shouldRetry(int $attempt, \Throwable $error): bool {
        if ($error instanceof WebhookResult) {
            $statusCode = $error->statusCode;
            if ($statusCode >= 200 && $statusCode < 300) {
                return false;
            }
            if ($statusCode >= 400 && $statusCode < 500 && $statusCode !== 429) {
                return false;
            }
            return true;
        }
        return true;
    }
}
