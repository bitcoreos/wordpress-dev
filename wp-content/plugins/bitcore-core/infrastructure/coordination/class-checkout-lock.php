<?php
/**
 * Checkout/Lock System for Exclusive Task Execution
 * 
 * Prevents multiple agents from working on the same task simultaneously.
 * Ported from Paperclip's checkout mechanism.
 *
 * @package BITCORE\Infrastructure\Coordination
 */

namespace BITCORE\Infrastructure\Coordination;

if (!defined('ABSPATH')) exit;

/**
 * Class CheckoutLock
 * 
 * Manages exclusive locks on tasks/issues for agent coordination.
 */
class CheckoutLock {
    /** @var string Database table name */
    private string $table_name;

    /** @var int Default time-to-live in seconds */
    private int $default_ttl = 3600; // 1 hour default

    const STATUS_ACTIVE = 'active';
    const STATUS_COMPLETED = 'completed';
    const STATUS_EXPIRED = 'expired';
    const STATUS_RELEASED = 'released';

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'bitcore_checkouts';
    }

    /**
     * Checkout an issue for exclusive execution
     *
     * @param int $issue_id Issue/task ID
     * @param string $agent_id Agent identifier
     * @param string|null $run_id Optional run identifier
     * @param int|null $ttl Optional TTL override in seconds
     * @return array Result with success status and checkout details
     */
    public function checkout(int $issue_id, string $agent_id, ?string $run_id = null, ?int $ttl = null): array {
        global $wpdb;

        $ttl = $ttl ?? $this->default_ttl;
        $run_id = $run_id ?? $this->generate_run_id();

        // Check for existing active checkout
        $existing = $this->get_active_checkout($issue_id);
        if ($existing) {
            // Check if expired
            if (strtotime($existing->expires_at) < time()) {
                $this->release($issue_id, $existing->agent_id);
            } else {
                return [
                    'success' => false,
                    'error' => 'issue_already_checked_out',
                    'checkout' => $existing
                ];
            }
        }

        $result = $wpdb->insert($this->table_name, [
            'issue_id' => $issue_id,
            'agent_id' => $agent_id,
            'run_id' => $run_id,
            'locked_at' => current_time('mysql'),
            'expires_at' => date('Y-m-d H:i:s', time() + $ttl),
            'status' => self::STATUS_ACTIVE
        ]);

        if ($result === false) {
            return ['success' => false, 'error' => 'database_error'];
        }

        return [
            'success' => true,
            'checkout_id' => $wpdb->insert_id,
            'run_id' => $run_id,
            'expires_at' => date('Y-m-d H:i:s', time() + $ttl)
        ];
    }

    /**
     * Release a checkout
     *
     * @param int $issue_id Issue/task ID
     * @param string $agent_id Agent identifier
     * @return bool Success status
     */
    public function release(int $issue_id, string $agent_id): bool {
        global $wpdb;

        $result = $wpdb->update(
            $this->table_name,
            ['status' => self::STATUS_RELEASED],
            [
                'issue_id' => $issue_id,
                'agent_id' => $agent_id,
                'status' => self::STATUS_ACTIVE
            ]
        );

        return $result !== false;
    }

    /**
     * Mark checkout as completed
     *
     * @param int $issue_id Issue/task ID
     * @param string $agent_id Agent identifier
     * @return bool Success status
     */
    public function complete(int $issue_id, string $agent_id): bool {
        global $wpdb;

        $result = $wpdb->update(
            $this->table_name,
            ['status' => self::STATUS_COMPLETED],
            [
                'issue_id' => $issue_id,
                'agent_id' => $agent_id,
                'status' => self::STATUS_ACTIVE
            ]
        );

        return $result !== false;
    }

    /**
     * Get active checkout for an issue
     *
     * @param int $issue_id Issue/task ID
     * @return object|null Checkout record or null
     */
    public function get_active_checkout(int $issue_id): ?object {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
            WHERE issue_id = %d AND status = %s 
            ORDER BY locked_at DESC LIMIT 1",
            $issue_id, self::STATUS_ACTIVE
        ));
    }

    /**
     * Get checkout status
     *
     * @param int $issue_id Issue/task ID
     * @return array Status information
     */
    public function get_status(int $issue_id): array {
        $checkout = $this->get_active_checkout($issue_id);

        if (!$checkout) {
            return ['checked_out' => false];
        }

        $expired = strtotime($checkout->expires_at) < time();

        return [
            'checked_out' => !$expired,
            'agent_id' => $checkout->agent_id,
            'run_id' => $checkout->run_id,
            'locked_at' => $checkout->locked_at,
            'expires_at' => $checkout->expires_at,
            'expired' => $expired
        ];
    }

    /**
     * Extend checkout TTL
     *
     * @param int $issue_id Issue/task ID
     * @param string $agent_id Agent identifier
     * @param int $additional_seconds Seconds to add
     * @return bool Success status
     */
    public function extend(int $issue_id, string $agent_id, int $additional_seconds): bool {
        global $wpdb;

        $checkout = $this->get_active_checkout($issue_id);
        if (!$checkout || $checkout->agent_id !== $agent_id) {
            return false;
        }

        $new_expires = strtotime($checkout->expires_at) + $additional_seconds;

        $result = $wpdb->update(
            $this->table_name,
            ['expires_at' => date('Y-m-d H:i:s', $new_expires)],
            ['id' => $checkout->id]
        );

        return $result !== false;
    }

    /**
     * Clean up expired checkouts
     *
     * @return int Number of expired checkouts cleaned
     */
    public function cleanup_expired(): int {
        global $wpdb;

        return $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table_name} SET status = %s 
            WHERE status = %s AND expires_at < %s",
            self::STATUS_EXPIRED, self::STATUS_ACTIVE, current_time('mysql')
        ));
    }

    /**
     * Get all checkouts for an agent
     *
     * @param string $agent_id Agent identifier
     * @param string|null $status Optional status filter
     * @return array List of checkouts
     */
    public function get_agent_checkouts(string $agent_id, ?string $status = null): array {
        global $wpdb;

        if ($status) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$this->table_name} 
                WHERE agent_id = %s AND status = %s 
                ORDER BY locked_at DESC",
                $agent_id, $status
            ));
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
            WHERE agent_id = %s 
            ORDER BY locked_at DESC",
            $agent_id
        ));
    }

    /**
     * Generate unique run ID
     *
     * @return string Unique run identifier
     */
    private function generate_run_id(): string {
        return 'run_' . uniqid() . '_' . time();
    }

    /**
     * Get checkout by run ID
     *
     * @param string $run_id Run identifier
     * @return object|null Checkout record or null
     */
    public function get_by_run_id(string $run_id): ?object {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE run_id = %s",
            $run_id
        ));
    }

    /**
     * Force release all checkouts for an agent (emergency use)
     *
     * @param string $agent_id Agent identifier
     * @return int Number of checkouts released
     */
    public function force_release_all(string $agent_id): int {
        global $wpdb;

        return $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table_name} SET status = %s 
            WHERE agent_id = %s AND status = %s",
            self::STATUS_RELEASED, $agent_id, self::STATUS_ACTIVE
        ));
    }
}
