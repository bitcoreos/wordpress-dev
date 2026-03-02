<?php
/**
 * Supporting job runner logic for Bitcore
 *
 * @package    Bitcore
 * @since      2026.03.01
 * @version    1.3.0
 * @author     hyperion
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class RateLimitMiddleware implements JobMiddlewareInterface
{
    /** @var RateLimitManager Rate limiter */
    private RateLimitManager $rateLimiter;

    /**
     * Constructor.
     *
     * @param RateLimitManager $rateLimiter Rate limit manager
     * @since 1.0.0
     */
    public function __construct(RateLimitManager $rateLimiter)
    {
        $this->rateLimiter = $rateLimiter;
    }

    /**
     * Handle job with rate limiting.
     *
     * @param Job $job Current job
     * @param callable $next Next handler
     * @since 1.0.0
     * @return void
     * @throws \RuntimeException If rate limit exceeded
     */
    public function handle(Job $job, callable $next): void
    {
        $key = 'job:' . $job->queue . ':' . $job->getId();

        if (!$this->rateLimiter->attempt($key)) {
            $availableIn = $this->rateLimiter->availableIn($key);
            throw new \RuntimeException(
                "Rate limit exceeded. Retry after {$availableIn} seconds."
            );
        }

        $next();
    }
}

/**
 * Log Middleware
 *
 * Logs job execution.
 *
 * @since 1.0.0
 */
class LogMiddleware implements JobMiddlewareInterface
{
    /** @var LoggerInterface Logger */
    private LoggerInterface $logger;

    /**
     * Constructor.
     *
     * @param LoggerInterface $logger Logger
     * @since 1.0.0
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Handle job with logging.
     *
     * @param Job $job Current job
     * @param callable $next Next handler
     * @since 1.0.0
     * @return void
     */
    public function handle(Job $job, callable $next): void
    {
        $this->logger->info('Job middleware start', [
            'job_id' => $job->getId(),
            'queue' => $job->queue,
            'class' => get_class($job),
        ]);

        $start = microtime(true);

        try {
            $next();

            $duration = (int) ((microtime(true) - $start) * 1000);

            $this->logger->info('Job middleware complete', [
                'job_id' => $job->getId(),
                'duration_ms' => $duration,
            ]);
        } catch (Throwable $e) {
            $duration = (int) ((microtime(true) - $start) * 1000);

            $this->logger->error('Job middleware failed', [
                'job_id' => $job->getId(),
                'error' => $e->getMessage(),
                'duration_ms' => $duration,
            ]);

            throw $e;
        }
    }
}

/**
 * Test Scaffolding
 *
 * @todo Implement test cases:
 * - testJobSerializesAndUnserializes
 * - testJobRepositoryPushesAndPops
 * - testJobRepositoryMarksProcessing
 * - testJobRepositoryMarksCompleted
 * - testJobRepositoryMarksFailed
 * - testJobRepositoryCalculatesBackoff
 * - testJobRepositoryCountsPending
 * - testJobRepositoryPrunesOldJobs
 * - testRateLimitMiddlewareBlocksWhenLimited
 * - testLogMiddlewareLogsExecution
 * - testAbstractJobRequiresHandleImplementation
 * - testJobQueueIsConfigurable
 * - testJobSupportsDelay
 * - testJobSupportsMiddleware
 */
