<?php
declare(strict_types=1);
/**
 * SSE Events - Server-Sent Events for real-time updates
 * 
 * Pattern extracted from tenacitOS (SSE streaming via ReadableStream)
 * Pattern extracted from mission-control (events.ts broadcasting)
 * 
 * @package BitCore
 * @since 1.0.0
 * @license MIT
 */

defined('ABSPATH') || exit;

class BITCORE_Rest_Sse_Events {
    
    /**
     * Active SSE client connections
     * @var array
     */
    private static $clients = [];
    
    /**
     * Event types supported by the system
     */
    const EVENT_TYPES = [
        'task_created',
        'task_updated',
        'task_deleted',
        'task_completed',
        'agent_spawned',
        'agent_status_changed',
        'activity_logged',
        'message_sent',
        'system'
    ];
    
    /**
     * Register REST routes
     */
    public static function register_routes() {
        register_rest_route('bitcore/v1', '/events/stream', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'handle_stream'],
            'permission_callback' => [__CLASS__, 'stream_permission'],
        ]);
        
        register_rest_route('bitcore/v1', '/events/broadcast', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'handle_broadcast'],
            'permission_callback' => [__CLASS__, 'broadcast_permission'],
        ]);
    }
    
    /**
     * Permission check for streaming
     */
    public static function stream_permission() {
        return current_user_can('read') || self::validate_api_key();
    }
    
    /**
     * Permission check for broadcasting
     */
    public static function broadcast_permission() {
        return current_user_can('edit_posts') || self::validate_api_key();
    }
    
    /**
     * Validate API key from request
     */
    private static function validate_api_key() {
        $headers = getallheaders();
        $api_key = $headers['X-BitCore-API-Key'] ?? $headers['Authorization'] ?? '';
        $api_key = str_replace('Bearer ', '', $api_key);
        return BitCore_Auth_Filter::validate_api_key($api_key);
    }
    
    /**
     * Handle SSE stream connection
     * Pattern from tenacitOS logs/stream/route.ts
     */
    public static function handle_stream() {
        // Disable time limit for long-running connection
        set_time_limit(0);
        
        // Set SSE headers
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');
        
        $client_id = uniqid('sse_', true);
        self::$clients[$client_id] = time();
        
        // Send initial connection event
        self::send_event('connected', ['client_id' => $client_id]);
        
        // Keep connection alive with heartbeats
        $last_heartbeat = time();
        $heartbeat_interval = 15; // seconds
        
        while (connection_aborted() === 0) {
            // Send heartbeat
            if (time() - $last_heartbeat >= $heartbeat_interval) {
                echo ":heartbeat\n\n";
                flush();
                $last_heartbeat = time();
            }
            
            // Check for pending events (from transient or queue)
            $events = get_transient("bitcore_sse_events_{$client_id}");
            if ($events) {
                foreach ($events as $event) {
                    self::send_event($event['type'], $event['payload']);
                }
                delete_transient("bitcore_sse_events_{$client_id}");
            }
            
            sleep(1);
        }
        
        // Cleanup on disconnect
        unset(self::$clients[$client_id]);
        
        exit;
    }
    
    /**
     * Handle broadcast request
     */
    public static function handle_broadcast($request) {
        $type = $request->get_param('type');
        $payload = $request->get_param('payload');
        
        if (!in_array($type, self::EVENT_TYPES)) {
            return new WP_Error('invalid_event_type', 'Invalid event type', ['status' => 400]);
        }
        
        self::broadcast($type, $payload);
        
        return ['success' => true, 'type' => $type, 'clients' => count(self::$clients)];
    }
    
    /**
     * Broadcast event to all connected clients
     * Pattern from mission-control events.ts broadcast()
     * 
     * @param string $type Event type
     * @param array $payload Event payload
     */
    public static function broadcast($type, $payload) {
        $event = [
            'type' => $type,
            'payload' => $payload,
            'timestamp' => current_time('mysql')
        ];
        
        // Store for connected clients
        foreach (self::$clients as $client_id => $connected_time) {
            $events = get_transient("bitcore_sse_events_{$client_id}") ?: [];
            $events[] = $event;
            set_transient("bitcore_sse_events_{$client_id}", $events, 60);
        }
        
        // Also log the event
        do_action('bitcore_sse_event', $type, $payload);
    }
    
    /**
     * Send single SSE event
     */
    private static function send_event($type, $payload) {
        $data = json_encode([
            'type' => $type,
            'payload' => $payload,
            'timestamp' => current_time('mysql')
        ]);
        
        echo "data: {$data}\n\n";
        
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }
    
    /**
     * Get active connection count
     */
    public static function get_active_connection_count() {
        return count(self::$clients);
    }
}
