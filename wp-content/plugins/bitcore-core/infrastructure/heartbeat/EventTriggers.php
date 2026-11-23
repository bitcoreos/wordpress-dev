<?php
/**
 * Event Triggers for Heartbeat System
 *
 * Provides event-driven triggers for agent coordination.
 * Ported from Paperclip's heartbeat patterns.
 *
 * @package BITCORE\Infrastructure\Heartbeat
 * @since       1.0.0
 * @version     1.0.0
 */

namespace BITCORE\Infrastructure\Heartbeat;

if (!defined('ABSPATH')) exit;

/**
 * Class EventTriggers
 *
 * Manages event subscriptions and trigger conditions.
 */
class EventTriggers {
    /** @var array Registered triggers */
    private array $triggers = [];

    /** @var array Event history */
    private array $history = [];

    const PRIORITY_LOW = 10;
    const PRIORITY_NORMAL = 5;
    const PRIORITY_HIGH = 1;

    /**
     * Register a trigger
     *
     * @param string $event_name Event to watch
     * @param callable $callback Trigger callback
     * @param array $options Trigger options
     * @return string Trigger ID
     */
    public function register(string $event_name, callable $callback, array $options = []): string {
        $trigger_id = 'trigger_' . uniqid();

        $this->triggers[$trigger_id] = [
            'event' => $event_name,
            'callback' => $callback,
            'priority' => $options['priority'] ?? self::PRIORITY_NORMAL,
            'once' => $options['once'] ?? false,
            'condition' => $options['condition'] ?? null,
            'max_calls' => $options['max_calls'] ?? 0,
            'call_count' => 0
        ];

        // Register WordPress action
        add_action($event_name, function(...$args) use ($trigger_id) {
            $this->fire($trigger_id, $args);
        }, $this->triggers[$trigger_id]['priority']);

        return $trigger_id;
    }

    /**
     * Fire a trigger
     *
     * @param string $trigger_id Trigger identifier
     * @param array $args Event arguments
     */
    private function fire(string $trigger_id, array $args): void {
        if (!isset($this->triggers[$trigger_id])) {
            return;
        }

        $trigger = $this->triggers[$trigger_id];

        // Check condition
        if ($trigger['condition'] !== null && is_callable($trigger['condition'])) {
            if (!call_user_func_array($trigger['condition'], $args)) {
                return;
            }
        }

        // Check max calls
        if ($trigger['max_calls'] > 0 && $trigger['call_count'] >= $trigger['max_calls']) {
            $this->unregister($trigger_id);
            return;
        }

        // Execute callback
        call_user_func_array($trigger['callback'], $args);

        // Update call count
        $this->triggers[$trigger_id]['call_count']++;

        // Record in history
        $this->history[] = [
            'trigger_id' => $trigger_id,
            'event' => $trigger['event'],
            'timestamp' => current_time('mysql'),
            'args_count' => count($args)
        ];

        // Unregister if once
        if ($trigger['once']) {
            $this->unregister($trigger_id);
        }
    }

    /**
     * Unregister a trigger
     *
     * @param string $trigger_id Trigger identifier
     */
    public function unregister(string $trigger_id): void {
        unset($this->triggers[$trigger_id]);
    }

    /**
     * Get trigger info
     *
     * @param string $trigger_id Trigger identifier
     * @return array|null Trigger info or null
     */
    public function get(string $trigger_id): ?array {
        return $this->triggers[$trigger_id] ?? null;
    }

    /**
     * Get all triggers for an event
     *
     * @param string $event_name Event name
     * @return array Matching triggers
     */
    public function get_for_event(string $event_name): array {
        return array_filter($this->triggers, function($trigger) use ($event_name) {
            return $trigger['event'] === $event_name;
        });
    }

    /**
     * Get trigger history
     *
     * @param int $limit Max results
     * @return array History entries
     */
    public function get_history(int $limit = 100): array {
        return array_slice(array_reverse($this->history), 0, $limit);
    }

    /**
     * Clear all triggers
     */
    public function clear_all(): void {
        $this->triggers = [];
    }
}
