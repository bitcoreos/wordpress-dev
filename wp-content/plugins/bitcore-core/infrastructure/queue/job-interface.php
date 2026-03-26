<?php
/**
 * Job Abstraction and Repository
 *
 * @package BITCORE\Infrastructure\Queue
 * @since 1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Infrastructure\Queue;

use Throwable;
use DateTimeImmutable;
use wpdb;
use BITCORE\Infrastructure\Logging\Logger;
use BITCORE\Infrastructure\Logging\LoggerInterface;
use BITCORE\Infrastructure\RateLimit\RateLimitManager;
use BITCORE\Infrastructure\Auth\TenantContext;

/**
 * Job Middleware Interface
 *
 * @since 1.0.0
 */
interface JobMiddlewareInterface
{
    /**
     * Process job through middleware chain.
     *
     * @param Job $job Current job
     * @param callable $next Next handler in chain
     * @since 1.0.0
     * @return void
     */
    public function handle(Job $job, callable $next): void;
}

