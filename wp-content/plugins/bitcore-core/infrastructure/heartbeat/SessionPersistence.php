<?php
/**
 * Session Persistence for Agent State
 *
 * Provides persistent session management for agents.
 * Ported from Paperclip's session patterns.
 *
 * @package BITCORE\Infrastructure\Heartbeat
 */

namespace BITCORE\Infrastructure\Heartbeat;

if (!defined('ABSPATH')) exit;

/**
 * Class SessionPersistence
 *
 * Manages agent session state with persistence.
 */
class SessionPersistence {
    /** @var string Sessions table name */
    private string $table_name;

    /** @var int Default session TTL */
    private int $default_ttl = 86400; // 24 hours

    const STATUS_ACTIVE = 'active';
    const STATUS_IDLE = 'idle';
    const STATUS_ENDED = 'ended';

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'bitcore_sessions';
    }

    /**
     * Create a new session
     *
     * @param string $agent_id Agent identifier
     * @param array $initial_state Initial session state
     * @return string|false Session ID or false on failure
     */
    public function create(string $agent_id, array $initial_state = []) {
        global $wpdb;

        $session_id = 'sess_' . bin2hex(random_bytes(16));

        $result = $wpdb->insert($this->table_name, [
            'session_id' => $session_id,
            'agent_id' => $agent_id,
            'status' => self::STATUS_ACTIVE,
            'state' => json_encode($initial_state),
            'started_at' => current_time('mysql'),
            'last_activity' => current_time('mysql'),
            'expires_at' => date('Y-m-d H:i:s', time() + $this->default_ttl)
        ]);

        if ($result === false) {
            return false;
        }

        return $session_id;
    }

    /**
     * Get session by ID
     *
     * @param string $session_id Session identifier
     * @return object|null Session record or null
     */
    public function get(string $session_id): ?object {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE session_id = %s",
            $session_id
        ));
    }

    /**
     * Get active session for agent
     *
     * @param string $agent_id Agent identifier
     * @return object|null Session record or null
     */
    public function get_active(string $agent_id): ?object {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
            WHERE agent_id = %s AND status = %s AND expires_at > %s
            ORDER BY last_activity DESC LIMIT 1",
            $agent_id, self::STATUS_ACTIVE, current_time('mysql')
        ));
    }

    /**
     * Update session state
     *
     * @param string $session_id Session identifier
     * @param array $state State updates
     * @return bool Success status
     */
    public function update_state(string $session_id, array $state): bool {
        global $wpdb;

        $session = $this->get($session_id);
        if (!$session) {
            return false;
        }

        $current_state = json_decode($session->state ?? '{}', true);
        $new_state = array_merge($current_state, $state);

        return $wpdb->update(
            $this->table_name,
            [
                'state' => json_encode($new_state),
                'last_activity' => current_time('mysql')
            ],
            ['session_id' => $session_id]
        ) !== false;
    }

    /**
     * Touch session (update activity)
     *
     * @param string $session_id Session identifier
     * @return bool Success status
     */
    public function touch(string $session_id): bool {
        global $wpdb;

        return $wpdb->update(
            $this->table_name,
            ['last_activity' => current_time('mysql')],
            ['session_id' => $session_id]
        ) !== false;
    }

    /**
     * End a session
     *
     * @param string $session_id Session identifier
     * @return bool Success status
     */
    public function end(string $session_id): bool {
        global $wpdb;

        return $wpdb->update(
            $this->table_name,
            [
                'status' => self::STATUS_ENDED,
                'ended_at' => current_time('mysql')
            ],
            ['session_id' => $session_id]
        ) !== false;
    }

    /**
     * Set session idle
     *
     * @param string $session_id Session identifier
     * @return bool Success status
     */
    public function set_idle(string $session_id): bool {
        global $wpdb;

        return $wpdb->update(
            $this->table_name,
            ['status' => self::STATUS_IDLE],
            ['session_id' => $session_id]
        ) !== false;
    }

    /**
     * Clean up expired sessions
     *
     * @return int Number of sessions cleaned
     */
    public function cleanup_expired(): int {
        global $wpdb;

        return $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table_name} SET status = %s 
            WHERE status = %s AND expires_at < %s",
            self::STATUS_ENDED, self::STATUS_ACTIVE, current_time('mysql')
        ));
    }

    /**
     * Get all sessions for an agent
     *
     * @param string $agent_id Agent identifier
     * @return array Session records
     */
    public function get_all_for_agent(string $agent_id): array {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE agent_id = %s ORDER BY started_at DESC",
            $agent_id
        ));
    }
}
