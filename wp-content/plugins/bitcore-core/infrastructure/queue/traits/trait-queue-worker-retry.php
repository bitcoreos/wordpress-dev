<?php
/**
 * Queue Worker Implementation - trait
 *
 * @package BITCORE\Infrastructure\Queue
 * @since 1.0.0
 * @version     1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Infrastructure\Queue;

use Throwable;
use DateTimeImmutable;
use BITCORE\Infrastructure\Logging\Logger;
use Psr\Log\LoggerInterface;

trait Queue_Worker_Retry {

    /**
     * Schedule a job retry.
     *
     * @param Job $job Failed job
     * @param Throwable $error The error
     * @since 1.0.0
     * @return void
     */
    public function scheduleRetry(Job $job, Throwable $error): void
    {
        $attempt = $job->getAttempts();
        $delay = $this->calculateBackoff($attempt);

        $this->logger->info('Scheduling job retry', [
            'job_id' => $job->getId(),
            'attempt' => $attempt,
            'delay_seconds' => $delay,
        ]);

        $this->jobRepository->retry($job->getId(), $delay);
    }

    /**
     * Calculate backoff delay.
     *
     * Formula: delay = 60 * (3 ^ attempt)
     *
     * @param int $attempt Current attempt number
     * @since 1.0.0
     * @return int Delay in seconds
     */
    public function calculateBackoff(int $attempt): int
    {
        // Exponential backoff: 60s, 180s, 540s
        return 60 * (3 ** $attempt);
    }

    /**
     * Mark job as permanently failed.
     *
     * @param Job $job Failed job
     * @param Throwable $error The error
     * @since 1.0.0
     * @return void
     */
    public function markFailed(Job $job, Throwable $error): void
    {
        $this->logger->error('Job permanently failed', [
            'job_id' => $job->getId(),
            'attempts' => $job->getAttempts(),
            'error' => $error->getMessage(),
        ]);

        $this->jobRepository->markAsFailed($job->getId(), $error);
    }
}
