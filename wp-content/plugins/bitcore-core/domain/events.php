<?php
/**
 * Domain Events
 *
 * Event dispatcher and subscribers
 * Pattern extracted from mcp-adapter and WordPress hooks
 *
 * @package BITCORE\Domain
 * @since 1.0.0
 */

namespace BITCORE\Domain;

/**
 * Domain Event Interface
 *
 * @since 1.0.0
 */
interface DomainEventInterface {
    /**
     * Get event name.
     *
     * @return string Event name.
     */
    public function getName(): string;

    /**
     * Get event payload.
     *
     * @return array<string,mixed> Event data.
     */
    public function getPayload(): array;

    /**
     * Get event timestamp.
     *
     * @return int Unix timestamp.
     */
    public function getTimestamp(): int;
}

/**
 * Abstract Domain Event
 *
 * @since 1.0.0
 */
abstract class AbstractDomainEvent implements DomainEventInterface {
    /**
     * Event name.
     *
     * @var string
     */
    protected string $name;

    /**
     * Event payload.
     *
     * @var array<string,mixed>
     */
    protected array $payload;

    /**
     * Event timestamp.
     *
     * @var int
     */
    protected int $timestamp;

    /**
     * Constructor.
     *
     * @param string $name Event name.
     * @param array<string,mixed> $payload Event data.
     */
    public function __construct(string $name, array $payload = []) {
        $this->name = $name;
        $this->payload = $payload;
        $this->timestamp = time();
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * {@inheritDoc}
     */
    public function getPayload(): array {
        return $this->payload;
    }

    /**
     * {@inheritDoc}
     */
    public function getTimestamp(): int {
        return $this->timestamp;
    }
}

/**
 * Task Created Event
 *
 * @since 1.0.0
 */
final class TaskCreatedEvent extends AbstractDomainEvent {
    /**
     * Constructor.
     *
     * @param string $taskId Task ID.
     * @param string $taskName Task name.
     * @param int $userId User ID.
     */
    public function __construct(string $taskId, string $taskName, int $userId) {
        parent::__construct('task.created', [
            'task_id' => $taskId,
            'task_name' => $taskName,
            'user_id' => $userId,
            'created_at' => current_time('mysql'),
        ]);
    }
}

/**
 * Task Completed Event
 *
 * @since 1.0.0
 */
final class TaskCompletedEvent extends AbstractDomainEvent {
    /**
     * Constructor.
     *
     * @param string $taskId Task ID.
     * @param string $result Task result.
     * @param int $duration Duration in seconds.
     */
    public function __construct(string $taskId, string $result, int $duration) {
        parent::__construct('task.completed', [
            'task_id' => $taskId,
            'result' => $result,
            'duration' => $duration,
            'completed_at' => current_time('mysql'),
        ]);
    }
}

/**
 * Event Dispatcher
 *
 * @since 1.0.0
 */
class EventDispatcher {
    /**
     * Event subscribers.
     *
     * @var array<string,callable[]>
     */
    private static array $subscribers = [];

    /**
     * Subscribe to event.
     *
     * @param string $eventName Event name.
     * @param callable $callback Callback function.
     * @param int $priority Priority.
     * @return void
     */
    public static function subscribe(string $eventName, callable $callback, int $priority = 10): void {
        if (!isset(self::$subscribers[$eventName])) {
            self::$subscribers[$eventName] = [];
        }
        self::$subscribers[$eventName][] = ['callback' => $callback, 'priority' => $priority];
        
        // Sort by priority
        usort(self::$subscribers[$eventName], function ($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });
    }

    /**
     * Dispatch event.
     *
     * @param DomainEventInterface $event Event to dispatch.
     * @return void
     */
    public static function dispatch(DomainEventInterface $event): void {
        $eventName = $event->getName();
        
        if (!isset(self::$subscribers[$eventName])) {
            return;
        }
        
        foreach (self::$subscribers[$eventName] as $subscriber) {
            call_user_func($subscriber['callback'], $event);
        }
        
        // Also dispatch through WordPress
        do_action("bitcore_event_{$eventName}", $event);
    }

    /**
     * Get subscribers.
     *
     * @param string|null $eventName Event name filter.
     * @return array Subscribers.
     */
    public static function getSubscribers(?string $eventName = null): array {
        if ($eventName === null) {
            return self::$subscribers;
        }
        return self::$subscribers[$eventName] ?? [];
    }

    /**
     * Clear all subscribers.
     *
     * @return void
     */
    public static function clear(): void {
        self::$subscribers = [];
    }
}
