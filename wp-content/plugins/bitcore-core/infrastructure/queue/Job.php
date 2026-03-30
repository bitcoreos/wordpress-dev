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
