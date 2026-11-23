<?php
/**
 * Webhook Interfaces
 *
 * Contract definitions for webhook components.
 *
 * @package BITCORE\Infrastructure\Webhooks
 * @since 1.0.0
 * @version     1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Infrastructure\Webhooks;

use Throwable;

/**
 * Retry Strategy Interface
 *
 * Defines contract for retry backoff strategies.
 *
 * @since 1.0.0
 */
interface RetryStrategyInterface {
    /**
     * Calculate delay before next retry attempt.
     *
     * @since 1.0.0
     * @param int $attempt Current attempt number (0-indexed)
     * @return int Delay in seconds
     */
    public function getDelay(int $attempt): int;

    /**
     * Determine if another retry should be attempted.
     *
     * @since 1.0.0
     * @param int $attempt Current attempt number
     * @param Throwable $error The error that caused failure
     * @return bool True if retry should be attempted
     */
    public function shouldRetry(int $attempt, Throwable $error): bool;
}
