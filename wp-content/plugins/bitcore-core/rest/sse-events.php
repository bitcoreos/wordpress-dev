<?php
declare(strict_types=1);
/**
 * SSE Events - Server-Sent Events for real-time updates.
 *
 * The stream endpoint is intentionally short-lived. It drains a bounded
 * transient-backed event queue once, emits a reconnect hint, then returns.
 * This avoids tying up PHP workers with long polling loops.
 *
 * @package BitCore
 * @since 1.0.0
 * @license MIT
 * @version     1.0.0
 */

defined('ABSPATH') || exit;

class BITCORE_Rest_Sse_Events {

    /** Shared transient queue key. */
    private const QUEUE_KEY = 'bitcore_sse_events_queue';

    /** Maximum retained queued events. */
    private const MAX_QUEUE_SIZE = 100;

    /** Queue retention in seconds. */
    private const QUEUE_TTL = 300;

    /**
     * Event types supported by the system.
     */
    public const EVENT_TYPES = [
        'task_created',
        'task_updated',
        'task_deleted',
        'task_completed',
        'agent_spawned',
        'agent_status_changed',
        'activity_logged',
        'THINKING',
        'messageComplete',
        'message_sent',
        'system',
    ];

    /**
     * Register REST routes.
     */
    public static function register_routes(): void {
        register_rest_route('bitcore/v1', '/events/stream', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'handle_stream'],
            'permission_callback' => [__CLASS__, 'stream_permission'],
        ]);

        register_rest_route('bitcore/v1', '/events/broadcast', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'handle_broadcast'],
            'permission_callback' => [__CLASS__, 'broadcast_permission'],
        ]);
    }

    /**
     * Permission check for streaming.
     */
    public static function stream_permission(): bool {
        return current_user_can('read') || self::validate_api_key();
    }

    /**
     * Permission check for broadcasting.
     */
    public static function broadcast_permission(): bool {
        return current_user_can('edit_posts') || self::validate_api_key();
    }

    /**
     * Validate API key from request headers.
     */
    private static function validate_api_key(): bool {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $api_key = $headers['X-BitCore-API-Key'] ?? $headers['Authorization'] ?? '';
        $api_key = str_replace('Bearer ', '', (string) $api_key);

        return class_exists('BITCORE_Rest_Auth_Filter')
            && BITCORE_Rest_Auth_Filter::validate_api_key($api_key);
    }

    /**
     * Handle SSE stream connection.
     *
     * This endpoint emits queued events immediately and asks the browser to
     * reconnect. The browser still uses EventSource semantics, but PHP does
     * not retain an open worker for idle waiting.
     */
    public static function handle_stream(): void {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Connection: close');
        header('X-Accel-Buffering: no');

        self::send_event('connected', [
            'mode' => 'short_poll_sse',
        ]);

        foreach (self::drain_queue() as $event) {
            self::send_event((string) $event['type'], $event['payload'] ?? []);
        }

        self::send_event('reconnect', [
            'retry_ms' => 1000,
        ]);

        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
        exit;
    }

    /**
     * Handle broadcast request.
     */
    public static function handle_broadcast($request) {
        $type = (string) $request->get_param('type');
        $payload = $request->get_param('payload');

        if (!in_array($type, self::EVENT_TYPES, true)) {
            return new WP_Error('invalid_event_type', 'Invalid event type', ['status' => 400]);
        }

        self::broadcast($type, is_array($payload) ? $payload : []);

        return [
            'success' => true,
            'type'    => $type,
            'queued'  => count(self::read_queue()),
        ];
    }

    /**
     * Broadcast event to the shared queue.
     *
     * @param string               $type    Event type.
     * @param array<string, mixed> $payload Event payload.
     */
    public static function broadcast($type, $payload): void {
        $event = [
            'type'      => (string) $type,
            'payload'   => is_array($payload) ? $payload : [],
            'timestamp' => current_time('mysql'),
        ];

        $queue = self::read_queue();
        $queue[] = $event;
        if (count($queue) > self::MAX_QUEUE_SIZE) {
            $queue = array_slice($queue, -self::MAX_QUEUE_SIZE);
        }

        set_transient(self::QUEUE_KEY, $queue, self::QUEUE_TTL);
        do_action('bitcore_sse_event', $type, $payload);
    }

    /**
     * Drain and delete queued events.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function drain_queue(): array {
        $queue = self::read_queue();
        delete_transient(self::QUEUE_KEY);
        return $queue;
    }

    /**
     * Read queued events from transient storage.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function read_queue(): array {
        $queue = get_transient(self::QUEUE_KEY);
        if (!is_array($queue)) {
            return [];
        }

        return array_values(array_filter($queue, static function ($event): bool {
            return is_array($event) && isset($event['type']);
        }));
    }

    /**
     * Send one SSE event.
     *
     * @param string               $type    Event type.
     * @param array<string, mixed> $payload Event payload.
     */
    private static function send_event(string $type, array $payload): void {
        $data = wp_json_encode([
            'type'      => $type,
            'payload'   => $payload,
            'timestamp' => current_time('mysql'),
        ]);

        echo "event: {$type}\n";
        echo "data: {$data}\n\n";
    }

    /**
     * Get queued event count.
     */
    public static function get_active_connection_count(): int {
        return count(self::read_queue());
    }
}
