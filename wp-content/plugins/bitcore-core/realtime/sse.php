<?php
/**
 * Server-Sent Events (SSE)
 *
 * Real-time updates and task status streaming
 * Pattern adapted from mission-control SSE implementation
 *
 * @package BITCORE\Realtime
 * @since 1.0.0
 * @version     1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Realtime;

/**
 * SSE Event
 *
 * @since 1.0.0
 */
class SseEvent {
    /**
     * Event name.
     *
     * @var string|null
     */
    public ?string $event = null;

    /**
     * Event data.
     *
     * @var mixed
     */
    public $data;

    /**
     * Event ID.
     *
     * @var string|null
     */
    public ?string $id = null;

    /**
     * Retry time in milliseconds.
     *
     * @var int|null
     */
    public ?int $retry = null;

    /**
     * Constructor.
     *
     * @param string|null $event Event name.
     * @param mixed $data Event data.
     * @param string|null $id Event ID.
     * @param int|null $retry Retry time.
     */
    public function __construct(?string $event = null, $data = null, ?string $id = null, ?int $retry = null) {
        $this->event = $event;
        $this->data = $data;
        $this->id = $id;
        $this->retry = $retry;
    }

    /**
     * Format as SSE string.
     *
     * @return string SSE formatted string.
     */
    public function __toString(): string {
        $output = '';

        if ($this->id !== null) {
            $output .= "id: {$this->id}\n";
        }

        if ($this->event !== null) {
            $output .= "event: {$this->event}\n";
        }

        if ($this->retry !== null) {
            $output .= "retry: {$this->retry}\n";
        }

        if ($this->data !== null) {
            $lines = explode("\n", json_encode($this->data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            foreach ($lines as $line) {
                $output .= "data: {$line}\n";
            }
        }

        $output .= "\n";

        return $output;
    }
}

/**
 * SSE Stream Handler
 *
 * @since 1.0.0
 */
class SseStream {
    /**
     * Connection active.
     *
     * @var bool
     */
    private bool $isActive = true;

    /**
     * Last event ID.
     *
     * @var string|null
     */
    private ?string $lastEventId = null;

    /**
     * Heartbeat interval.
     *
     * @var int
     */
    private int $heartbeatInterval = 30;

    /**
     * Last activity time.
     *
     * @var int
     */
    private int $lastActivity;

    /**
     * Constructor.
     *
     * @param int $heartbeatInterval Heartbeat interval in seconds.
     */
    public function __construct(int $heartbeatInterval = 30) {
        $this->heartbeatInterval = $heartbeatInterval;
        $this->lastActivity = time();
    }

    /**
     * Initialize SSE stream.
     *
     * @return void
     */
    public function init(): void {
        // Disable output buffering
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Set headers
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        // Disable time limit
        set_time_limit(0);

        // Get last event ID from client
        $this->lastEventId = $_SERVER['HTTP_LAST_EVENT_ID'] ?? null;

        // Send initial connection event
        $this->send(new SseEvent('connected', ['time' => time()]));
    }

    /**
     * Send event.
     *
     * @param SseEvent $event Event to send.
     * @return void
     */
    public function send(SseEvent $event): void {
        if (!$this->isActive) {
            return;
        }

        echo $event;
        flush();
        
        $this->lastActivity = time();
    }

    /**
     * Send heartbeat.
     *
     * @return void
     */
    public function heartbeat(): void {
        if (time() - $this->lastActivity > $this->heartbeatInterval) {
            $this->send(new SseEvent('heartbeat', ['time' => time()]));
        }
    }

    /**
     * Check if connection is active.
     *
     * @return bool True if active.
     */
    public function isActive(): bool {
        // Check connection aborted
        if (connection_aborted()) {
            $this->isActive = false;
            return false;
        }

        // Check client timeout (5 minutes)
        if (time() - $this->lastActivity > 300) {
            $this->isActive = false;
            return false;
        }

        return $this->isActive;
    }

    /**
     * Close stream.
     *
     * @return void
     */
    public function close(): void {
        $this->isActive = false;
    }

    /**
     * Get last event ID.
     *
     * @return string|null Last event ID.
     */
    public function getLastEventId(): ?string {
        return $this->lastEventId;
    }
}

/**
 * Task Status Stream
 *
 * @since 1.0.0
 */
class TaskStatusStream extends SseStream {
    /**
     * Task ID.
     *
     * @var string
     */
    private string $taskId;

    /**
     * Constructor.
     *
     * @param string $taskId Task ID.
     * @param int $heartbeatInterval Heartbeat interval.
     */
    public function __construct(string $taskId, int $heartbeatInterval = 30) {
        parent::__construct($heartbeatInterval);
        $this->taskId = $taskId;
    }

    /**
     * Send task update.
     *
     * @param string $status Task status.
     * @param array<string,mixed> $data Additional data.
     * @return void
     */
    public function sendUpdate(string $status, array $data = []): void {
        $this->send(new SseEvent('task.update', array_merge([
            'task_id' => $this->taskId,
            'status' => $status,
            'timestamp' => time(),
        ], $data)));
    }

    /**
     * Send task completion.
     *
     * @param array<string,mixed> $result Task result.
     * @return void
     */
    public function sendCompletion(array $result): void {
        $this->send(new SseEvent('task.completed', [
            'task_id' => $this->taskId,
            'result' => $result,
            'timestamp' => time(),
        ]));
    }

    /**
     * Send task error.
     *
     * @param string $error Error message.
     * @return void
     */
    public function sendError(string $error): void {
        $this->send(new SseEvent('task.error', [
            'task_id' => $this->taskId,
            'error' => $error,
            'timestamp' => time(),
        ]));
    }
}
