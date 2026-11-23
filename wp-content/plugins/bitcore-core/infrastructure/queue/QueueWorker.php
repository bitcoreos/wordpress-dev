<?php
/**
 * Queue Worker Implementation
 *
 * @package BITCORE\Infrastructure\Queue
 * @since 1.0.0
 * @author hyperion
 * @version     1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Infrastructure\Queue;

use Throwable;
use Psr\Log\LoggerInterface;

/**
 * Queue Worker
 *
 * Processes jobs from queues with retry logic and cron integration.
 *
 * @since 1.0.0
 */
class QueueWorker
{
    /** @var JobRepository Job repository */
    private JobRepository $jobRepository;

    /** @var LoggerInterface Logger instance */
    private LoggerInterface $logger;

    /** @var int Maximum retry attempts */
    private int $maxAttempts;

    /** @var int Job timeout in seconds */
    private int $timeout;

    /** @var bool Running state */
    private bool $running = false;

    /** @var WorkerConfig Worker configuration */
    private WorkerConfig $config;

    /**
     * Constructor.
     *
     * @param JobRepository $jobRepository Job repository
     * @param LoggerInterface $logger Logger instance
     * @param int $maxAttempts Maximum retry attempts
     * @param int $timeout Job timeout in seconds
     * @since 1.0.0
     */
    public function __construct(
        JobRepository $jobRepository,
        LoggerInterface $logger,
        int $maxAttempts = 3,
        int $timeout = 300
    ) {
        $this->jobRepository = $jobRepository;
        $this->logger = $logger;
        $this->maxAttempts = $maxAttempts;
        $this->timeout = $timeout;
        $this->config = new WorkerConfig();
    }

    /**
     * Set worker configuration.
     *
     * @param WorkerConfig $config Configuration
     * @return self
     * @since 1.0.0
     */
    public function setConfig(WorkerConfig $config): self
    {
        $this->config = $config;
        return $this;
    }

    /**
     * Get current configuration.
     *
     * @since 1.0.0
     * @return WorkerConfig
     */
    public function getConfig(): WorkerConfig
    {
        return $this->config;
    }

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
     * Process next available job.
     *
     * @param string $queue Queue name
     * @since 1.0.0
     * @return JobResult|null
     */
    public function processNext(string $queue = 'default'): ?JobResult
    {
        $job = $this->jobRepository->pop($queue);

        if ($job === null) {
            return null;
        }

        return $this->processJob($job);
    }

    /**
     * Process a specific job.
     *
     * @param Job $job Job to process
     * @since 1.0.0
     * @return JobResult
     */
    public function processJob(Job $job): JobResult
    {
        $startTime = microtime(true);
        $jobId = $job->getId();

        $this->logger->info('Processing job', [
            'job_id' => $jobId,
            'queue' => $job->queue,
            'attempts' => $job->getAttempts(),
        ]);

        // Mark as processing
        $this->jobRepository->markAsProcessing($jobId);

        // Apply middleware chain
        $middleware = $job->getMiddleware();
        $chain = $this->buildMiddlewareChain($middleware, $job);

        try {
            // Set timeout
            set_time_limit($this->timeout);

            // Execute through middleware chain
            $chain();

            // Calculate duration
            $duration = (int) ((microtime(true) - $startTime) * 1000);

            $result = new JobResult(
                success: true,
                job: $job,
                duration: $duration
            );

            $this->jobRepository->markAsCompleted($jobId, $result);

            $this->logger->info('Job completed successfully', [
                'job_id' => $jobId,
                'duration_ms' => $duration,
            ]);

            return $result;

        } catch (Throwable $e) {
            $duration = (int) ((microtime(true) - $startTime) * 1000);

            $this->logger->error('Job failed', [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
                'attempts' => $job->getAttempts(),
            ]);

            // Handle retry or fail
            if ($job->getAttempts() < $this->maxAttempts) {
                $this->scheduleRetry($job, $e);
            } else {
                $this->markFailed($job, $e);
            }

            return new JobResult(
                success: false,
                job: $job,
                error: $e,
                duration: $duration
            );
        }
    }

    /**
     * Build middleware execution chain.
     *
     * @param array $middleware Middleware stack
     * @param Job $job Current job
     * @since 1.0.0
     * @return callable
     */
    private function buildMiddlewareChain(array $middleware, Job $job): callable
    {
        $next = function () use ($job): void {
            $job->handle();
        };

        // Chain middleware in reverse order
        foreach (array_reverse($middleware) as $mw) {
            if ($mw instanceof JobMiddlewareInterface) {
                $next = function () use ($mw, $job, $next): void {
                    $mw->handle($job, $next);
                };
            }
        }

        return $next;
    }

    /**
     * Process batch of jobs.
     *
     * @param int $limit Maximum jobs to process
     * @param string $queue Queue name
     * @since 1.0.0
     * @return array Array of JobResult objects
     */
    public function processBatch(int $limit = 10, string $queue = 'default'): array
    {
        $results = [];

        for ($i = 0; $i < $limit; $i++) {
            $result = $this->processNext($queue);

            if ($result === null) {
                break;
            }

            $results[] = $result;

            if ($result->isFailure() && $this->config->stopOnError) {
                break;
            }
        }

        return $results;
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
