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

trait Queue_Worker_Lifecycle {

    /**
     * Start the worker loop.
     *
     * @since 1.0.0
     * @return void
     */
    public function start(): void
    {
        $this->running = true;
        $processed = 0;
        $startTime = time();

        $this->logger->info('Queue worker started', [
            'max_jobs' => $this->config->maxJobs,
            'memory_limit' => $this->config->memoryLimit,
            'time_limit' => $this->config->timeLimit,
        ]);

        while ($this->running) {
            // Check time limit
            if ((time() - $startTime) >= $this->config->timeLimit) {
                $this->logger->info('Worker time limit reached');
                break;
            }

            // Check memory limit
            $memoryUsage = memory_get_usage(true) / 1024 / 1024;
            if ($memoryUsage >= $this->config->memoryLimit) {
                $this->logger->warning('Worker memory limit reached', ['usage_mb' => $memoryUsage]);
                break;
            }

            // Check max jobs
            if ($processed >= $this->config->maxJobs) {
                $this->logger->info('Worker max jobs reached', ['processed' => $processed]);
                break;
            }

            // Process next job
            $result = $this->processNext();

            if ($result === null) {
                // No jobs available, sleep
                sleep($this->config->sleepDuration);
                continue;
            }

            $processed++;

            // Stop on error if configured
            if ($result->isFailure() && $this->config->stopOnError) {
                $this->logger->error('Stopping worker on error', [
                    'job_id' => $result->job->getId(),
                    'error' => $result->error?->getMessage(),
                ]);
                break;
            }
        }

        $this->running = false;
        $this->logger->info('Queue worker stopped', ['processed' => $processed]);
    }

    /**
     * Stop the worker.
     *
     * @since 1.0.0
     * @return void
     */
    public function stop(): void
    {
        $this->running = false;
        $this->logger->info('Queue worker stop signal received');
    }

    /**
     * Check if worker is running.
     *
     * @since 1.0.0
     * @return bool
     */
    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * Register WordPress cron hook.
     *
     * @since 1.0.0
     * @return void
     */
    public function registerCronHook(): void
    {
        add_action('bitcore_queue_worker', [$this, 'runCronWorker']);

        // Schedule if not exists
        if (!wp_next_scheduled('bitcore_queue_worker')) {
            wp_schedule_event(time(), 'every_minute', 'bitcore_queue_worker');
        }

        // Register custom cron interval
        add_filter('cron_schedules', function ($schedules) {
            $schedules['every_minute'] = [
                'interval' => 60,
                'display' => __('Every Minute', 'bitcore-core'),
            ];
            return $schedules;
        });

        $this->logger->info('Cron hook registered');
    }

    /**
     * Run worker via cron.
     *
     * @since 1.0.0
     * @return void
     */
    public function runCronWorker(): void
    {
        $this->logger->info('Cron worker started');

        // Use config for cron - shorter batches
        $cronConfig = new WorkerConfig(
            maxJobs: 5,
            memoryLimit: 128,
            timeLimit: 30,
            sleepDuration: 0,
            stopOnError: false
        );

        $this->setConfig($cronConfig);

        // Process batch
        $results = $this->processBatch($cronConfig->maxJobs);

        $successCount = count(array_filter($results, fn($r) => $r->isSuccess()));
        $failureCount = count($results) - $successCount;

        $this->logger->info('Cron worker completed', [
            'processed' => count($results),
            'success' => $successCount,
            'failed' => $failureCount,
        ]);
    }
}
