<?php
/**
 * Approvals REST API Controller
 *
 * REST endpoints for approval workflow management.
 *
 * @package BITCORE\Rest
 */

namespace BITCORE\Rest;

if (!defined('ABSPATH')) exit;

use BITCORE\Infrastructure\Governance\ApprovalGate;
use BITCORE\Infrastructure\Governance\ApprovalWorkflow;

/**
 * Class Approvals_Api
 */
class Approvals_Api {
    private string $namespace = 'bitcore/v1';
    private ApprovalGate $gate;
    private ApprovalWorkflow $workflow;

    public function __construct() {
        $this->gate = new ApprovalGate();
        $this->workflow = new ApprovalWorkflow($this->gate);
    }

    public function register_routes(): void {
        register_rest_route($this->namespace, '/approvals', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_pending'],
                'permission_callback' => [$this, 'check_permission']
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'request_approval'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'agent_id' => ['type' => 'string', 'required' => true],
                    'type' => ['type' => 'string', 'required' => true],
                    'payload' => ['type' => 'object', 'required' => true]
                ]
            ]
        ]);

        register_rest_route($this->namespace, '/approvals/(?P<approval_id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'check_approval'],
                'permission_callback' => [$this, 'check_permission']
            ]
        ]);

        register_rest_route($this->namespace, '/approvals/(?P<approval_id>\d+)/approve', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'approve'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'approver_id' => ['type' => 'string', 'required' => true]
                ]
            ]
        ]);

        register_rest_route($this->namespace, '/approvals/(?P<approval_id>\d+)/reject', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'reject'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'rejector_id' => ['type' => 'string', 'required' => true],
                    'reason' => ['type' => 'string']
                ]
            ]
        ]);

        register_rest_route($this->namespace, '/workflows', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'list_workflows'],
                'permission_callback' => [$this, 'check_permission']
            ]
        ]);

        register_rest_route($this->namespace, '/workflows/(?P<workflow_name>[\w-]+)/start', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'start_workflow'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'agent_id' => ['type' => 'string', 'required' => true],
                    'payload' => ['type' => 'object', 'required' => true]
                ]
            ]
        ]);
    }

    public function get_pending(\WP_REST_Request $request): \WP_REST_Response {
        $type = $request->get_param('type');
        $approvals = $this->gate->get_pending($type);
        return rest_ensure_response(['approvals' => $approvals]);
    }

    public function request_approval(\WP_REST_Request $request): \WP_REST_Response {
        $approval_id = $this->gate->request(
            $request->get_param('agent_id'),
            $request->get_param('type'),
            $request->get_param('payload'),
            $request->get_param('ttl') ?? 3600
        );

        return rest_ensure_response(['approval_id' => $approval_id, 'success' => $approval_id !== false]);
    }

    public function check_approval(\WP_REST_Request $request): \WP_REST_Response {
        $status = $this->gate->check($request->get_param('approval_id'));
        return rest_ensure_response($status);
    }

    public function approve(\WP_REST_Request $request): \WP_REST_Response {
        $success = $this->gate->approve(
            $request->get_param('approval_id'),
            $request->get_param('approver_id'),
            $request->get_param('notes')
        );

        return rest_ensure_response(['success' => $success]);
    }

    public function reject(\WP_REST_Request $request): \WP_REST_Response {
        $success = $this->gate->reject(
            $request->get_param('approval_id'),
            $request->get_param('rejector_id'),
            $request->get_param('reason')
        );

        return rest_ensure_response(['success' => $success]);
    }

    public function list_workflows(\WP_REST_Request $request): \WP_REST_Response {
        $workflows = $this->workflow->list_workflows();
        return rest_ensure_response(['workflows' => $workflows]);
    }

    public function start_workflow(\WP_REST_Request $request): \WP_REST_Response {
        $result = $this->workflow->start(
            $request->get_param('workflow_name'),
            $request->get_param('agent_id'),
            $request->get_param('payload')
        );

        return rest_ensure_response($result);
    }

    public function check_permission(): bool {
        return is_user_logged_in();
    }
}
