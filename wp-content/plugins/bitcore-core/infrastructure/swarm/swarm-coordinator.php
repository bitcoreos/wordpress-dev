<?php
/**
 * Swarm Coordinator
 *
 * Manages multi-agent task distribution, load balancing, and consensus.
 * Ported from flux A2A swarm patterns.
 *
 * @package BITCORE\Infrastructure\Swarm
 * @since 1.0.0
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
readonly class TaskAssignment {

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

// ============================================================================
// SWARM COORDINATOR
// ============================================================================

/**
 * Multi-agent swarm coordinator.
 *
 * @package BITCORE\Infrastructure\Swarm
 * @since 1.0.0
 */
class SwarmCoordinator {

    /**
     * A2A Dispatcher.
     *
     * @var A2ADispatcher
     * @since 1.0.0
     */
    private A2ADispatcher $dispatcher;

    /**
     * Logger.
     *
     * @var Logger
     * @since 1.0.0
     */
    private Logger $logger;

    /**
     * Cache manager.
     *
     * @var CacheManager
     * @since 1.0.0
     */
    private CacheManager $cache;

    /**
     * Pending tasks.
     *
     * @var array<string, SwarmTask>
     * @since 1.0.0
     */
    private array $pendingTasks = [];

    /**
     * Active assignments.
     *
     * @var array<string, TaskAssignment>
     * @since 1.0.0
     */
    private array $assignments = [];

    /**
     * Agent load tracking.
     *
     * @var array<string, int>
     * @since 1.0.0
     */
    private array $agentLoad = [];

    /**
     * Task retry count.
     *
     * @var array<string, int>
     * @since 1.0.0
     */
    private array $retryCount = [];

    /**
     * Constructor.
     *
     * @param A2ADispatcher $dispatcher Message dispatcher.
     * @param Logger $logger Logger.
     * @param CacheManager $cache Cache manager.
     * @since 1.0.0
     */
    public function __construct(
        A2ADispatcher $dispatcher,
        Logger $logger,
        CacheManager $cache
    ) {
        $this->dispatcher = $dispatcher;
        $this->logger = $logger;
        $this->cache = $cache;
    }

    /**
     * Submit new task to swarm.
     *
     * @param SwarmTask $task Task to submit.
     * @return string Task ID.
     * @since 1.0.0
     */
    public function submitTask(SwarmTask $task): string {
        $this->pendingTasks[$task->id] = $task;
        $this->logger->info('Task submitted to swarm', [
            'task_id' => $task->id,
            'type' => $task->type->value,
            'priority' => $task->priority,
        ]);

        // Attempt immediate assignment
        $this->distributeTasks();

        return $task->id;
    }

    /**
     * Distribute pending tasks to available agents.
     *
     * @return int Number of tasks assigned.
     * @since 1.0.0
     */
    public function distributeTasks(): int {
        $assigned = 0;

        foreach ($this->pendingTasks as $taskId => $task) {
            $agent = $this->selectAgentForTask($task);

            if ($agent !== null) {
                $this->assignTask($task, $agent->id);
                $assigned++;
            }
        }

        return $assigned;
    }

    /**
     * Select best agent for task.
     *
     * @param SwarmTask $task Task to assign.
     * @return AgentDescriptor|null Selected agent.
     * @since 1.0.0
     */
    private function selectAgentForTask(SwarmTask $task): ?AgentDescriptor {
        $agents = $this->dispatcher->getAgents();
        $candidates = [];

        foreach ($agents as $agent) {
            // Skip inactive agents
            if ($agent->status !== 'active') {
                continue;
            }

            // Check capability requirements
            if (!empty($task->requiredCapabilities)) {
                $hasCapabilities = count(array_intersect(
                    $task->requiredCapabilities,
                    $agent->capabilities
                )) === count($task->requiredCapabilities);

                if (!$hasCapabilities) {
                    continue;
                }
            }

            // Calculate score (lower is better)
            $load = $this->agentLoad[$agent->id] ?? 0;
            $score = $load * 10 + (10 - $task->priority);

            $candidates[] = ['agent' => $agent, 'score' => $score];
        }

        if (empty($candidates)) {
            return null;
        }

        // Sort by score ascending
        usort($candidates, fn($a, $b) => $a['score'] <=> $b['score']);

        return $candidates[0]['agent'];
    }

    /**
     * Assign task to agent.
     *
     * @param SwarmTask $task Task to assign.
     * @param string $agentId Agent ID.
     * @return bool True if assigned.
     * @since 1.0.0
     */
    private function assignTask(SwarmTask $task, string $agentId): bool {
        $assignment = new TaskAssignment($task->id, $agentId);
        $this->assignments[$assignment->id] = $assignment;

        // Update load
        $this->agentLoad[$agentId] = ($this->agentLoad[$agentId] ?? 0) + 1;

        // Send task assignment via A2A
        $result = $this->dispatcher->sendDirect(
            from: 'coordinator',
            to: $agentId,
            payload: [
                'action' => 'execute_task',
                'task' => $task->toArray(),
                'assignment_id' => $assignment->id,
            ],
            priority: MessagePriority::HIGH
        );

        if ($result !== null) {
            unset($this->pendingTasks[$task->id]);
            $this->logger->info('Task assigned', [
                'task_id' => $task->id,
                'agent_id' => $agentId,
            ]);
            return true;
        }

        // Rollback on failure
        unset($this->assignments[$assignment->id]);
        $this->agentLoad[$agentId]--;

        $this->logger->error('Task assignment failed', [
            'task_id' => $task->id,
            'agent_id' => $agentId,
        ]);

        return false;
    }

    /**
     * Report task progress.
     *
     * @param string $assignmentId Assignment ID.
     * @param string $status New status.
     * @param array<string, mixed> $data Progress data.
     * @return bool True if updated.
     * @since 1.0.0
     */
    public function reportProgress(
        string $assignmentId,
        string $status,
        array $data = []
    ): bool {
        if (!isset($this->assignments[$assignmentId])) {
            return false;
        }

        $assignment = $this->assignments[$assignmentId];

        $newAssignment = match($status) {
            'running' => $assignment->markStarted(),
            'completed' => $assignment->markCompleted($data),
            'failed' => $assignment->markFailed($data['error'] ?? 'Unknown error'),
            default => $assignment,
        };

        $this->assignments[$assignmentId] = $newAssignment;

        // Update agent load
        if (in_array($status, ['completed', 'failed', 'cancelled'], true)) {
            $this->agentLoad[$assignment->agentId] = max(
                0,
                ($this->agentLoad[$assignment->agentId] ?? 1) - 1
            );
        }

        return true;
    }

    /**
     * Get task status.
     *
     * @param string $taskId Task ID.
     * @return array<string, mixed>|null Status or null.
     * @since 1.0.0
     */
    public function getTaskStatus(string $taskId): ?array {
        if (isset($this->pendingTasks[$taskId])) {
            return [
                'task_id' => $taskId,
                'status' => 'pending',
                'task' => $this->pendingTasks[$taskId]->toArray(),
            ];
        }

        foreach ($this->assignments as $assignment) {
            if ($assignment->taskId === $taskId) {
                return [
                    'task_id' => $taskId,
                    'status' => $assignment->status->value,
                    'agent_id' => $assignment->agentId,
                    'result' => $assignment->result,
                    'error' => $assignment->error,
                ];
            }
        }

        return null;
    }

    /**
     * Get swarm statistics.
     *
     * @return array<string, mixed> Statistics.
     * @since 1.0.0
     */
    public function getStats(): array {
        $statusCounts = [
            'pending' => count($this->pendingTasks),
            'assigned' => 0,
            'running' => 0,
            'completed' => 0,
            'failed' => 0,
        ];

        foreach ($this->assignments as $assignment) {
            $statusCounts[$assignment->status->value]++;
        }

        return [
            'agents_registered' => count($this->dispatcher->getAgents()),
            'agents_active' => count(array_filter(
                $this->agentLoad,
                fn($load) => $load > 0
            )),
            'tasks_pending' => $statusCounts['pending'],
            'tasks_running' => $statusCounts['running'],
            'tasks_completed' => $statusCounts['completed'],
            'tasks_failed' => $statusCounts['failed'],
            'agent_load' => $this->agentLoad,
        ];
    }

    /**
     * Cancel task.
     *
     * @param string $taskId Task ID.
     * @return bool True if cancelled.
     * @since 1.0.0
     */
    public function cancelTask(string $taskId): bool {
        if (isset($this->pendingTasks[$taskId])) {
            unset($this->pendingTasks[$taskId]);
            $this->logger->info('Pending task cancelled', ['task_id' => $taskId]);
            return true;
        }

        foreach ($this->assignments as $id => $assignment) {
            if ($assignment->taskId === $taskId) {
                // Send cancellation
                $this->dispatcher->sendDirect(
                    from: 'coordinator',
                    to: $assignment->agentId,
                    payload: ['action' => 'cancel_task', 'task_id' => $taskId]
                );

                // Update load
                $this->agentLoad[$assignment->agentId] = max(
                    0,
                    ($this->agentLoad[$assignment->agentId] ?? 1) - 1
                );

                unset($this->assignments[$id]);
                $this->logger->info('Running task cancelled', ['task_id' => $taskId]);
                return true;
            }
        }

        return false;
    }

    /**
     * Cleanup completed tasks.
     *
     * @param int $olderThanSeconds Cleanup threshold.
     * @return int Number cleared.
     * @since 1.0.0
     */
    public function cleanup(int $olderThanSeconds = 86400): int {
        $cleared = 0;
        $threshold = time() - $olderThanSeconds;

        foreach ($this->assignments as $id => $assignment) {
            if (
                in_array($assignment->status, [TaskStatus::COMPLETED, TaskStatus::FAILED], true) &&
                $assignment->completedAt !== null &&
                $assignment->completedAt < $threshold
            ) {
                unset($this->assignments[$id]);
                $cleared++;
            }
        }

        $this->logger->info('Swarm cleanup completed', ['cleared' => $cleared]);
        return $cleared;
    }
}
