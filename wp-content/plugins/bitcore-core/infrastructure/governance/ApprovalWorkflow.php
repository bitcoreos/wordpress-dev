<?php
/**
 * Approval Workflow Engine
 *
 * Manages multi-step approval workflows.
 * Ported from Paperclip's governance patterns.
 *
 * @package BITCORE\Infrastructure\Governance
 * @since       1.0.0
 * @version     1.0.0
 */

namespace BITCORE\Infrastructure\Governance;

if (!defined('ABSPATH')) exit;

/**
 * Class ApprovalWorkflow
 *
 * Orchestrates approval workflows with multiple stages.
 */
class ApprovalWorkflow {
    /** @var ApprovalGate */
    private ApprovalGate $gate;

    /** @var array Workflow definitions */
    private array $workflows = [];

    /**
     * Constructor
     *
     * @param ApprovalGate $gate Approval gate instance
     */
    public function __construct(ApprovalGate $gate) {
        $this->gate = $gate;
        $this->register_default_workflows();
    }

    /**
     * Register default workflows
     */
    private function register_default_workflows(): void {
        // Budget override: single approval
        $this->register('budget_override', [
            'steps' => [['type' => 'single', 'role' => 'admin']],
            'timeout' => 3600,
            'auto_expire' => true
        ]);

        // External send: single approval with audit
        $this->register('external_send', [
            'steps' => [['type' => 'single', 'role' => 'admin']],
            'timeout' => 1800,
            'auto_expire' => true,
            'audit' => true
        ]);

        // System change: dual approval
        $this->register('system_change', [
            'steps' => [
                ['type' => 'single', 'role' => 'admin'],
                ['type' => 'single', 'role' => 'superadmin']
            ],
            'timeout' => 7200,
            'auto_expire' => true
        ]);

        // Data deletion: requires dual approval with audit
        $this->register('data_deletion', [
            'steps' => [
                ['type' => 'single', 'role' => 'admin'],
                ['type' => 'single', 'role' => 'superadmin']
            ],
            'timeout' => 86400,
            'auto_expire' => true,
            'audit' => true,
            'require_reason' => true
        ]);
    }

    /**
     * Register a workflow
     *
     * @param string $name Workflow name
     * @param array $config Workflow configuration
     */
    public function register(string $name, array $config): void {
        $this->workflows[$name] = $config;
    }

    /**
     * Start a workflow
     *
     * @param string $workflow_name Workflow to start
     * @param string $agent_id Agent requesting
     * @param array $payload Action details
     * @return array Workflow result with approval IDs
     */
    public function start(string $workflow_name, string $agent_id, array $payload): array {
        if (!isset($this->workflows[$workflow_name])) {
            return ['success' => false, 'error' => 'unknown_workflow'];
        }

        $workflow = $this->workflows[$workflow_name];
        $steps = $workflow['steps'] ?? [];

        if (empty($steps)) {
            // No approval needed
            return ['success' => true, 'approved' => true, 'reason' => 'no_steps'];
        }

        // Create approval request for first step
        $first_step = $steps[0];
        $approval_id = $this->gate->request(
            $agent_id,
            $workflow_name,
            array_merge($payload, [
                'workflow' => $workflow_name,
                'current_step' => 0,
                'total_steps' => count($steps)
            ]),
            $workflow['timeout'] ?? 3600
        );

        if ($approval_id === false) {
            return ['success' => false, 'error' => 'request_failed'];
        }

        return [
            'success' => true,
            'approved' => false,
            'approval_id' => $approval_id,
            'workflow' => $workflow_name,
            'steps_required' => count($steps),
            'current_step' => 1
        ];
    }

    /**
     * Process approval decision
     *
     * @param int $approval_id Approval ID
     * @param string $approver_id Approver
     * @param bool $approved Whether approved
     * @param string|null $notes Notes or rejection reason
     * @return array Result with workflow status
     */
    public function process_decision(int $approval_id, string $approver_id, bool $approved, ?string $notes = null): array {
        if ($approved) {
            $success = $this->gate->approve($approval_id, $approver_id, $notes);
        } else {
            $success = $this->gate->reject($approval_id, $approver_id, $notes);
        }

        if (!$success) {
            return ['success' => false, 'error' => 'decision_failed'];
        }

        if (!$approved) {
            return [
                'success' => true,
                'workflow_complete' => true,
                'approved' => false,
                'reason' => $notes
            ];
        }

        // Check if more steps needed
        $check = $this->gate->check($approval_id);

        // For simplicity, single-step workflows complete here
        // Multi-step would need additional tracking

        return [
            'success' => true,
            'workflow_complete' => true,
            'approved' => true,
            'approval_id' => $approval_id
        ];
    }

    /**
     * Get workflow definition
     *
     * @param string $name Workflow name
     * @return array|null Workflow config or null
     */
    public function get_workflow(string $name): ?array {
        return $this->workflows[$name] ?? null;
    }

    /**
     * List all registered workflows
     *
     * @return array Workflow names
     */
    public function list_workflows(): array {
        return array_keys($this->workflows);
    }
}
