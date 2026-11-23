<?php
/**
 * Queue Worker Implementation
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

require_once __DIR__ . '/traits/trait-queue-worker-lifecycle.php';
require_once __DIR__ . '/traits/trait-queue-worker-retry.php';
/**
 * Worker Configuration
 *
 * @since 1.0.0
 */
readonly class WorkerConfig
{
    /**
     * @param int $maxJobs Maximum jobs to process per run
     * @param int $memoryLimit Memory limit in MB
     * @param int $timeLimit Time limit in seconds
     * @param int $sleepDuration Sleep duration between empty polls in seconds
     * @param bool $stopOnError Stop worker on first error
     * @since 1.0.0
     */
    public function __construct(
        public int $maxJobs = 10,
        public int $memoryLimit = 256,
        public int $timeLimit = 300,
        public int $sleepDuration = 5,
        public bool $stopOnError = false
    ) {
    }
}

/**
 * Job Result DTO
 *
 * @since 1.0.0
 */
readonly class JobResult
{
    /**
     * @param bool $success Whether job succeeded
     * @param Job $job The processed job
     * @param Throwable|null $error Error if failed
     * @param int $duration Processing duration in milliseconds
     * @param array $output Job output data
     * @since 1.0.0
     */
    public function __construct(
        public bool $success,
        public Job $job,
        public ?Throwable $error = null,
        public int $duration = 0,
        public array $output = []
    ) {
    }

    /**
     * Check if job succeeded.
     *
     * @since 1.0.0
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Check if job failed.
     *
     * @since 1.0.0
     * @return bool
     */
    public function isFailure(): bool
    {
        return !$this->success;
    }
}

/**
 * Queue Worker
 *
 * Processes jobs from queues with retry logic and cron integration.
 *
 * @since 1.0.0
 */
class QueueWorker
{
    use Queue_Worker_Lifecycle;
    use Queue_Worker_Retry;
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
}

/**
 * Test Scaffolding
 *
 * @todo Implement test cases:
 * - testWorkerProcessesJobSuccessfully
 * - testWorkerRetriesFailedJob
 * - testWorkerRespectsMaxAttempts
 * - testWorkerCalculatesBackoffCorrectly
 * - testWorkerRespectsMemoryLimit
 * - testWorkerRespectsTimeLimit
 * - testWorkerProcessesBatch
 * - testWorkerRegistersCronHook
 * - testWorkerExecutesMiddlewareChain
 * - testJobResultCapturesDuration
 */
