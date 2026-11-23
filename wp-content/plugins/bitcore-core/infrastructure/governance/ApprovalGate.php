<?php
/**
 * Approval Gate for Agent Actions
 *
 * Provides approval workflow for high-impact agent operations.
 * Ported from Paperclip's governance patterns.
 *
 * @package BITCORE\Infrastructure\Governance
 * @since       1.0.0
 * @version     1.0.0
 */

namespace BITCORE\Infrastructure\Governance;

if (!defined('ABSPATH')) exit;

/**
 * Class ApprovalGate
 *
 * Manages approval requirements and verification for agent actions.
 */
class ApprovalGate {
    /** @var string Approvals table name */
    private string $table_name;

    const TYPE_BUDGET_OVERRIDE = 'budget_override';
    const TYPE_EXTERNAL_SEND = 'external_send';
    const TYPE_SYSTEM_CHANGE = 'system_change';
    const TYPE_DATA_DELETION = 'data_deletion';
    const TYPE_AGENT_PROMOTION = 'agent_promotion';
    const TYPE_CRITICAL_TASK = 'critical_task';

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_EXPIRED = 'expired';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'bitcore_approvals';
    }

    /**
     * Request approval for an action
     *
     * @param string $agent_id Agent requesting approval
     * @param string $type Approval type constant
     * @param array $payload Action details
     * @param int $ttl_seconds Time to live for approval request
     * @return int|false Approval request ID
     */
    public function request(string $agent_id, string $type, array $payload, int $ttl_seconds = 3600) {
        global $wpdb;

        $expires_at = date('Y-m-d H:i:s', time() + $ttl_seconds);

        $result = $wpdb->insert($this->table_name, [
            'agent_id' => $agent_id,
            'approval_type' => $type,
            'payload' => json_encode($payload),
            'status' => self::STATUS_PENDING,
            'requested_at' => current_time('mysql'),
            'expires_at' => $expires_at
        ]);

        if ($result === false) {
            return false;
        }

        $approval_id = $wpdb->insert_id;

        // Fire action for notification hooks
        do_action('bitcore_approval_requested', $approval_id, $agent_id, $type, $payload);

        return $approval_id;
    }

    /**
     * Check if action is approved
     *
     * @param int $approval_id Approval request ID
     * @return array Approval status
     */
    public function check(int $approval_id): array {
        global $wpdb;

        $approval = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $approval_id
        ));

        if (!$approval) {
            return ['valid' => false, 'error' => 'not_found'];
        }

        // Check expiration
        if ($approval->status === self::STATUS_PENDING) {
            if (strtotime($approval->expires_at) < time()) {
                $wpdb->update(
                    $this->table_name,
                    ['status' => self::STATUS_EXPIRED],
                    ['id' => $approval_id]
                );
                $approval->status = self::STATUS_EXPIRED;
            }
        }

        return [
            'valid' => $approval->status === self::STATUS_APPROVED,
            'status' => $approval->status,
            'approved_by' => $approval->approved_by,
            'approved_at' => $approval->approved_at,
            'rejection_reason' => $approval->rejection_reason
        ];
    }

    /**
     * Approve a request
     *
     * @param int $approval_id Approval request ID
     * @param string $approver_id Who approved
     * @param string|null $notes Optional approval notes
     * @return bool Success status
     */
    public function approve(int $approval_id, string $approver_id, ?string $notes = null): bool {
        global $wpdb;

        $approval = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $approval_id
        ));

        if (!$approval || $approval->status !== self::STATUS_PENDING) {
            return false;
        }

        $result = $wpdb->update(
            $this->table_name,
            [
                'status' => self::STATUS_APPROVED,
                'approved_by' => $approver_id,
                'approved_at' => current_time('mysql'),
                'notes' => $notes
            ],
            ['id' => $approval_id]
        );

        if ($result !== false) {
            do_action('bitcore_approval_granted', $approval_id, $approval->agent_id, $approval->approval_type);
        }

        return $result !== false;
    }

    /**
     * Reject a request
     *
     * @param int $approval_id Approval request ID
     * @param string $rejector_id Who rejected
     * @param string|null $reason Rejection reason
     * @return bool Success status
     */
    public function reject(int $approval_id, string $rejector_id, ?string $reason = null): bool {
        global $wpdb;

        $approval = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $approval_id
        ));

        if (!$approval || $approval->status !== self::STATUS_PENDING) {
            return false;
        }

        $result = $wpdb->update(
            $this->table_name,
            [
                'status' => self::STATUS_REJECTED,
                'approved_by' => $rejector_id,
                'approved_at' => current_time('mysql'),
                'rejection_reason' => $reason
            ],
            ['id' => $approval_id]
        );

        if ($result !== false) {
            do_action('bitcore_approval_rejected', $approval_id, $approval->agent_id, $reason);
        }

        return $result !== false;
    }

    /**
     * Get pending approvals
     *
     * @param string|null $type Optional type filter
     * @return array Pending approval requests
     */
    public function get_pending(?string $type = null): array {
        global $wpdb;

        if ($type) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$this->table_name} 
                WHERE status = %s AND approval_type = %s 
                ORDER BY requested_at ASC",
                self::STATUS_PENDING, $type
            ));
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
            WHERE status = %s 
            ORDER BY requested_at ASC",
            self::STATUS_PENDING
        ));
    }

    /**
     * Get approvals by agent
     *
     * @param string $agent_id Agent identifier
     * @param string|null $status Optional status filter
     * @return array Approval requests
     */
    public function get_by_agent(string $agent_id, ?string $status = null): array {
        global $wpdb;

        if ($status) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$this->table_name} 
                WHERE agent_id = %s AND status = %s 
                ORDER BY requested_at DESC",
                $agent_id, $status
            ));
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
            WHERE agent_id = %s 
            ORDER BY requested_at DESC",
            $agent_id
        ));
    }

    /**
     * Cancel a pending request
     *
     * @param int $approval_id Approval request ID
     * @param string $agent_id Requesting agent (must match)
     * @return bool Success status
     */
    public function cancel(int $approval_id, string $agent_id): bool {
        global $wpdb;

        $result = $wpdb->update(
            $this->table_name,
            ['status' => self::STATUS_CANCELLED],
            [
                'id' => $approval_id,
                'agent_id' => $agent_id,
                'status' => self::STATUS_PENDING
            ]
        );

        return $result !== false && $result > 0;
    }
}
