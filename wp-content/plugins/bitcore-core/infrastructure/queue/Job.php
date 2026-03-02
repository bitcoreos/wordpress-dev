<?php
/**
 * Main job queue management for Bitcore
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

/**
 * Abstract Job Class
 *
 * @since 1.0.0
 */
abstract class Job implements \Serializable
{
    /** @var string Job ID */
    protected string $id;

    /** @var int Current attempt count */
    protected int $attempts = 0;

    /** @var DateTimeImmutable|null Scheduled execution time */
    protected ?DateTimeImmutable $scheduledAt = null;

    /** @var array Middleware stack */
    protected array $middleware = [];

    /**
     * Constructor.
     *
     * @param string $queue Queue name
     * @param string|null $id Job ID (UUID generated if null)
     * @since 1.0.0
     */
    public function __construct(
        readonly public string $queue = 'default',
        ?string $id = null
    ) {
        $this->id = $id ?? $this->generateUuid();
    }

    /**
     * Generate UUID v4.
     *
     * @since 1.0.0
     * @return string
     */
    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff)
        );
    }

    /**
     * Execute the job.
     *
     * @since 1.0.0
     * @return void
     */
    abstract public function handle(): void;

    /**
     * Get job ID.
     *
     * @since 1.0.0
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get current attempt count.
     *
     * @since 1.0.0
     * @return int
     */
    public function getAttempts(): int
    {
        return $this->attempts;
    }

    /**
     * Increment attempt counter.
     *
     * @since 1.0.0
     * @return void
     */
    public function incrementAttempts(): void
    {
        $this->attempts++;
    }

    /**
     * Get scheduled execution time.
     *
     * @since 1.0.0
     * @return DateTimeImmutable|null
     */
    public function getScheduledAt(): ?DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    /**
     * Delay job execution.
     *
     * @param int $seconds Delay in seconds
     * @since 1.0.0
     * @return self
     */
    public function delay(int $seconds): self
    {
        $this->scheduledAt = new DateTimeImmutable("+{$seconds} seconds");
        return $this;
    }

    /**
     * Add middleware to job.
     *
     * @param array $middleware Middleware classes
     * @since 1.0.0
     * @return self
     */
    public function through(array $middleware): self
    {
        $this->middleware = array_merge($this->middleware, $middleware);
        return $this;
    }

    /**
     * Get middleware stack.
     *
     * @since 1.0.0
     * @return array
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Set attempts.
     *
     * @param int $attempts Attempt count
     * @since 1.0.0
     * @return void
     */
    public function setAttempts(int $attempts): void
    {
        $this->attempts = $attempts;
    }

    /**
     * Serialize job data.
     *
     * @since 1.0.0
     * @return string
     */
    public function serialize(): string
    {
        return serialize([
            'id' => $this->id,
            'queue' => $this->queue,
            'attempts' => $this->attempts,
            'scheduledAt' => $this->scheduledAt,
            'middleware' => $this->middleware,
            'class' => static::class,
            'data' => $this->getSerializableData(),
        ]);
    }

    /**
     * Unserialize job data.
     *
     * @param string $data Serialized data
     * @since 1.0.0
     * @return void
     */
    public function unserialize(string $data): void
    {
        $payload = unserialize($data);

        $this->id = $payload['id'];
        $this->attempts = $payload['attempts'];
        $this->scheduledAt = $payload['scheduledAt'];
        $this->middleware = $payload['middleware'];

        $this->setSerializableData($payload['data'] ?? []);
    }

    /**
     * Get serializable data (override in subclass).
     *
     * @since 1.0.0
     * @return array
     */
    abstract protected function getSerializableData(): array;

    /**
     * Restore serializable data (override in subclass).
     *
     * @param array $data Serialized data
     * @since 1.0.0
     * @return void
     */
    abstract protected function setSerializableData(array $data): void;
}

/**
 * Job Repository
 *
 * Handles database operations for job storage.
 *
 * @since 1.0.0
 */
class JobRepository
{
    /** @var wpdb WordPress database instance */
    private wpdb $db;

    /** @var TenantContext|null Tenant context */
    private ?TenantContext $tenantContext;

    /** @var string Table name */
    private string $table;

    /**
     * Constructor.
     *
     * @param wpdb $db WordPress database
     * @param TenantContext|null $tenantContext Tenant context
     * @since 1.0.0
     */
    public function __construct(wpdb $db, ?TenantContext $tenantContext = null)
    {
        $this->db = $db;
        $this->tenantContext = $tenantContext;
        $this->table = $db->prefix . 'bitcore_jobs';
    }

    /**
     * Get tenant ID from context.
     *
     * @since 1.0.0
     * @return int|null
     */
    private function getTenantId(): ?int
    {
        return $this->tenantContext?->getCurrentTenant();
    }

    /**
     * Push job to queue.
     *
     * @param Job $job Job to queue
     * @since 1.0.0
     * @return void
     */
    public function push(Job $job): void
    {
        $now = new DateTimeImmutable();
        $availableAt = $job->getScheduledAt() ?? $now;

        $this->db->insert(
            $this->table,
            [
                'id' => $job->getId(),
                'queue' => $job->queue,
                'tenant_id' => $this->getTenantId(),
                'payload' => $job->serialize(),
                'attempts' => $job->getAttempts(),
                'reserved_at' => null,
                'available_at' => $availableAt->format('Y-m-d H:i:s'),
                'created_at' => $now->format('Y-m-d H:i:s'),
            ],
            ['%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s']
        );
    }

    /**
     * Pop next available job from queue.
     *
     * @param string $queue Queue name
     * @since 1.0.0
     * @return Job|null
     */
    public function pop(string $queue = 'default'): ?Job
    {
        $tenantId = $this->getTenantId();
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        // Find available job with row locking simulation
        $sql = $this->db->prepare(
            "SELECT * FROM {$this->table} 
             WHERE queue = %s 
             AND available_at <= %s 
             AND reserved_at IS NULL
             AND (tenant_id = %d OR tenant_id IS NULL)
             ORDER BY available_at ASC, created_at ASC 
             LIMIT 1",
            $queue,
            $now,
            $tenantId ?? 0
        );

        $row = $this->db->get_row($sql);

        if (!$row) {
            return null;
        }

        // Mark as reserved
        $this->db->update(
            $this->table,
            ['reserved_at' => $now],
            ['id' => $row->id],
            ['%s'],
            ['%s']
        );

        return $this->unserializeJob($row->payload);
    }

    /**
     * Find job by ID.
     *
     * @param string $id Job ID
     * @since 1.0.0
     * @return Job|null
     */
    public function find(string $id): ?Job
    {
        $row = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->table} WHERE id = %s",
                $id
            )
        );

        if (!$row) {
            return null;
        }

        return $this->unserializeJob($row->payload);
    }

    /**
     * Unserialize job from database payload.
     *
     * @param string $payload Serialized payload
     * @since 1.0.0
     * @return Job|null
     */
    private function unserializeJob(string $payload): ?Job
    {
        $data = unserialize($payload);

        if (!$data || !isset($data['class'])) {
            return null;
        }

        $class = $data['class'];

        if (!class_exists($class) || !is_subclass_of($class, Job::class)) {
            return null;
        }

        /** @var Job $job */
        $job = unserialize($payload);
        return $job;
    }

    /**
     * Mark job as processing.
     *
     * @param string $id Job ID
     * @since 1.0.0
     * @return void
     */
    public function markAsProcessing(string $id): void
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->db->update(
            $this->table,
            ['reserved_at' => $now],
            ['id' => $id],
            ['%s'],
            ['%s']
        );
    }

    /**
     * Mark job as completed.
     *
     * @param string $id Job ID
     * @param JobResult $result Job result
     * @since 1.0.0
     * @return void
     */
    public function markAsCompleted(string $id, JobResult $result): void
    {
        $this->db->delete(
            $this->table,
            ['id' => $id],
            ['%s']
        );
    }

    /**
     * Mark job as failed.
     *
     * @param string $id Job ID
     * @param Throwable $error Error
     * @since 1.0.0
     * @return void
     */
    public function markAsFailed(string $id, Throwable $error): void
    {
        // Move to failed jobs table or update status
        $this->db->insert(
            $this->db->prefix . 'bitcore_failed_jobs',
            [
                'id' => $id,
                'queue' => 'default',
                'tenant_id' => $this->getTenantId(),
                'payload' => $this->find($id)?->serialize() ?? '',
                'error' => $error->getMessage(),
                'failed_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ],
            ['%s', '%s', '%d', '%s', '%s', '%s']
        );

        // Remove from jobs table
        $this->db->delete(
            $this->table,
            ['id' => $id],
            ['%s']
        );
    }

    /**
     * Retry a failed job.
     *
     * @param string $id Job ID
     * @param int $delay Delay in seconds
     * @since 1.0.0
     * @return void
     */
    public function retry(string $id, int $delay = 0): void
    {
        $job = $this->find($id);

        if (!$job) {
            return;
        }

        $job->incrementAttempts();
        $job->delay($delay);

        $this->db->update(
            $this->table,
            [
                'payload' => $job->serialize(),
                'attempts' => $job->getAttempts(),
                'reserved_at' => null,
                'available_at' => (new DateTimeImmutable("+{$delay} seconds"))->format('Y-m-d H:i:s'),
            ],
            ['id' => $id],
            ['%s', '%d', '%s', '%s'],
            ['%s']
        );
    }

    /**
     * Count pending jobs in queue.
     *
     * @param string $queue Queue name
     * @since 1.0.0
     * @return int
     */
    public function countPending(string $queue = 'default'): int
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $tenantId = $this->getTenantId();

        return (int) $this->db->get_var(
            $this->db->prepare(
                "SELECT COUNT(*) FROM {$this->table} 
                 WHERE queue = %s 
                 AND available_at <= %s 
                 AND reserved_at IS NULL
                 AND (tenant_id = %d OR tenant_id IS NULL)",
                $queue,
                $now,
                $tenantId ?? 0
            )
        );
    }

    /**
     * Count failed jobs.
     *
     * @since 1.0.0
     * @return int
     */
    public function countFailed(): int
    {
        return (int) $this->db->get_var(
            "SELECT COUNT(*) FROM {$this->db->prefix}bitcore_failed_jobs"
        );
    }

    /**
     * Get failed jobs.
     *
     * @param int $limit Maximum results
     * @since 1.0.0
     * @return array
     */
    public function getFailed(int $limit = 100): array
    {
        return $this->db->get_results(
            $this->db->prepare(
                "SELECT * FROM {$this->db->prefix}bitcore_failed_jobs 
                 ORDER BY failed_at DESC 
                 LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
    }

    /**
     * Prune old jobs.
     *
     * @param DateTimeImmutable $before Cutoff date
     * @since 1.0.0
     * @return int Number of pruned jobs
     */
    public function prune(DateTimeImmutable $before): int
    {
        $this->db->query(
            $this->db->prepare(
                "DELETE FROM {$this->table} 
                 WHERE reserved_at IS NULL 
                 AND available_at < %s",
                $before->format('Y-m-d H:i:s')
            )
        );

        return $this->db->rows_affected;
    }
}

/**
 * Rate Limit Middleware
 *
 * Prevents job processing rate limit violations.
 *
 * @since 1.0.0
 */