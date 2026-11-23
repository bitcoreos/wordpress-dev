<?php
/**
 * Swarm Coordinator
 *
 * Manages multi-agent task distribution, load balancing, and consensus.
 * Ported from flux A2A swarm patterns.
 *
 * @package BITCORE\Infrastructure\Swarm
 * @since 1.0.0
 * @version     1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Infrastructure\Swarm;

use BITCORE\Infrastructure\Logging\Logger;
use BITCORE\Infrastructure\Cache\CacheManager;
use BITCORE\Infrastructure\Queue\QueueWorker;

// ============================================================================
// TASK TYPES
// ============================================================================

/**
 * Enumeration of task types.
 *
 * @package BITCORE\Infrastructure\Swarm
 * @since 1.0.0
 */
enum TaskType: string {
    case COMPUTE = 'compute';
    case IO = 'io';
    case ANALYSIS = 'analysis';
    case CREATIVE = 'creative';
    case COORDINATION = 'coordination';
    case MONITORING = 'monitoring';
}

/**
 * Enumeration of task statuses.
 *
 * @package BITCORE\Infrastructure\Swarm
 * @since 1.0.0
 */
enum TaskStatus: string {
    case PENDING = 'pending';
    case ASSIGNED = 'assigned';
    case RUNNING = 'running';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
}

// ============================================================================
// SWARM TASK
// ============================================================================

/**
 * Distributed task descriptor.
 *
 * @package BITCORE\Infrastructure\Swarm
 * @since 1.0.0
 */
readonly class SwarmTask {

    /**
     * Task ID.
     *
     * @var string
     * @since 1.0.0
     */
    public string $id;

    /**
     * Task type.
     *
     * @var TaskType
     * @since 1.0.0
     */
    public TaskType $type;

    /**
     * Task description.
     *
     * @var string
     * @since 1.0.0
     */
    public string $description;

    /**
     * Task payload.
     *
     * @var array<string, mixed>
     * @since 1.0.0
     */
    public array $payload;

    /**
     * Required capabilities.
     *
     * @var array<string>
     * @since 1.0.0
     */
    public array $requiredCapabilities;

    /**
     * Priority (1-10).
     *
     * @var int
     * @since 1.0.0
     */
    public int $priority;

    /**
     * Created timestamp.
     *
     * @var int
     * @since 1.0.0
     */
    public int $createdAt;

    /**
     * Deadline timestamp.
     *
     * @var int|null
     * @since 1.0.0
     */
    public ?int $deadline;

    /**
     * Timeout in seconds.
     *
     * @var int
     * @since 1.0.0
     */
    public int $timeout;

    /**
     * Constructor.
     *
     * @param TaskType $type Task type.
     * @param string $description Description.
     * @param array<string, mixed> $payload Task data.
     * @param array<string> $requiredCapabilities Required capabilities.
     * @param int $priority Priority.
     * @param int|null $deadline Deadline timestamp.
     * @param int $timeout Timeout.
     * @since 1.0.0
     */
    public function __construct(
        TaskType $type,
        string $description,
        array $payload = [],
        array $requiredCapabilities = [],
        int $priority = 5,
        ?int $deadline = null,
        int $timeout = 300
    ) {
        $this->id = 'task_' . microtime(true) . '_' . bin2hex(random_bytes(4));
        $this->type = $type;
        $this->description = $description;
        $this->payload = $payload;
        $this->requiredCapabilities = $requiredCapabilities;
        $this->priority = $priority;
        $this->createdAt = time();
        $this->deadline = $deadline;
        $this->timeout = $timeout;
    }

    /**
     * Serialize to array.
     *
     * @return array<string, mixed> Serialized.
     * @since 1.0.0
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'description' => $this->description,
            'payload' => $this->payload,
            'required_capabilities' => $this->requiredCapabilities,
            'priority' => $this->priority,
            'created_at' => $this->createdAt,
            'deadline' => $this->deadline,
            'timeout' => $this->timeout,
        ];
    }
}

// ============================================================================
// TASK ASSIGNMENT
// ============================================================================

/**
 * Task assignment to agent.
 *
 * @package BITCORE\Infrastructure\Swarm
 * @since 1.0.0
 */
class TaskAssignment {

    /**
     * Assignment ID.
     *
     * @var string
     * @since 1.0.0
     */
    public string $id;

    /**
     * Task ID.
     *
     * @var string
     * @since 1.0.0
     */
    public string $taskId;

    /**
     * Agent ID.
     *
     * @var string
     * @since 1.0.0
     */
    public string $agentId;

    /**
     * Current status.
     *
     * @var TaskStatus
     * @since 1.0.0
     */
    public TaskStatus $status;

    /**
     * Assigned timestamp.
     *
     * @var int
     * @since 1.0.0
     */
    public int $assignedAt;

    /**
     * Started timestamp.
     *
     * @var int|null
     * @since 1.0.0
     */
    public ?int $startedAt;

    /**
     * Completed timestamp.
     *
     * @var int|null
     * @since 1.0.0
     */
    public ?int $completedAt;

    /**
     * Result data.
     *
     * @var array<string, mixed>|null
     * @since 1.0.0
     */
    public ?array $result;

    /**
     * Error message if failed.
     *
     * @var string|null
     * @since 1.0.0
     */
    public ?string $error;

    /**
     * Constructor.
     *
     * @param string $taskId Task ID.
     * @param string $agentId Agent ID.
     * @since 1.0.0
     */
    public function __construct(string $taskId, string $agentId) {
        $this->id = 'assign_' . $taskId . '_' . $agentId;
        $this->taskId = $taskId;
        $this->agentId = $agentId;
        $this->status = TaskStatus::PENDING;
        $this->assignedAt = time();
        $this->startedAt = null;
        $this->completedAt = null;
        $this->result = null;
        $this->error = null;
    }

    /**
     * Mark as started.
     *
     * @return self Updated.
     * @since 1.0.0
     */
    public function markStarted(): self {
        $new = new self($this->taskId, $this->agentId);
        $new->status = TaskStatus::RUNNING;
        $new->startedAt = time();
        return $new;
    }

    /**
     * Mark as completed.
     *
     * @param array<string, mixed> $result Result data.
     * @return self Updated.
     * @since 1.0.0
     */
    public function markCompleted(array $result): self {
        $new = new self($this->taskId, $this->agentId);
        $new->status = TaskStatus::COMPLETED;
        $new->startedAt = $this->startedAt;
        $new->completedAt = time();
        $new->result = $result;
        return $new;
    }

    /**
     * Mark as failed.
     *
     * @param string $error Error message.
     * @return self Updated.
     * @since 1.0.0
     */
    public function markFailed(string $error): self {
        $new = new self($this->taskId, $this->agentId);
        $new->status = TaskStatus::FAILED;
        $new->startedAt = $this->startedAt;
        $new->completedAt = time();
        $new->error = $error;
        return $new;
    }
}

