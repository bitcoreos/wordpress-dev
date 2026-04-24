<?php
/**
 * Budget REST API Controller
 *
 * REST endpoints for budget management.
 *
 * @package BITCORE\Rest
 */

namespace BITCORE\Rest;

if (!defined('ABSPATH')) exit;

use BITCORE\Infrastructure\Budget\BudgetManager;

/**
 * Class Budget_Api
 */
class Budget_Api {
    private string $namespace = 'bitcore/v1';
    private BudgetManager $budget_manager;

    public function __construct() {
        $this->budget_manager = new BudgetManager();
        $this->register_routes();
    }

    public function register_routes(): void {
        register_rest_route($this->namespace, '/budget', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_all_budgets'],
                'permission_callback' => '__return_true'
            ]
        ]);

        register_rest_route($this->namespace, '/budget/(?P<agent_id>[\w-]+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_status'],
                'permission_callback' => '__return_true'
            ]
        ]);

        register_rest_route($this->namespace, '/budget/(?P<agent_id>[\w-]+)/policy', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'set_policy'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'limit' => ['type' => 'number', 'required' => true],
                    'resource_type' => ['type' => 'string', 'default' => 'default'],
                    'period' => ['type' => 'string', 'default' => 'daily']
                ]
            ]
        ]);

        register_rest_route($this->namespace, '/budget/(?P<agent_id>[\w-]+)/check', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'check_can_perform'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'estimated_cost' => ['type' => 'number', 'required' => true],
                    'resource_type' => ['type' => 'string', 'default' => 'default']
                ]
            ]
        ]);

        register_rest_route($this->namespace, '/budget/(?P<agent_id>[\w-]+)/consume', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'consume'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'amount' => ['type' => 'number', 'required' => true],
                    'resource_type' => ['type' => 'string', 'default' => 'default'],
                    'operation' => ['type' => 'string']
                ]
            ]
        ]);
    }

    public function get_all_budgets(\WP_REST_Request $request): \WP_REST_Response {
        $budgets = $this->budget_manager->get_all_budgets();
        return rest_ensure_response(['budgets' => $budgets]);
    }

    public function get_status(\WP_REST_Request $request): \WP_REST_Response {
        $status = $this->budget_manager->get_status($request->get_param('agent_id'));
        return rest_ensure_response($status);
    }

    public function set_policy(\WP_REST_Request $request): \WP_REST_Response {
        $policy_id = $this->budget_manager->set_policy(
            $request->get_param('agent_id'),
            $request->get_param('limit'),
            $request->get_param('resource_type'),
            $request->get_param('period')
        );

        return rest_ensure_response(['policy_id' => $policy_id, 'success' => $policy_id !== false]);
    }

    public function check_can_perform(\WP_REST_Request $request): \WP_REST_Response {
        $result = $this->budget_manager->can_perform(
            $request->get_param('agent_id'),
            $request->get_param('estimated_cost'),
            $request->get_param('resource_type')
        );

        return rest_ensure_response($result);
    }

    public function consume(\WP_REST_Request $request): \WP_REST_Response {
        $success = $this->budget_manager->consume(
            $request->get_param('agent_id'),
            $request->get_param('amount'),
            $request->get_param('resource_type'),
            $request->get_param('operation') ?? '',
            $request->get_param('metadata') ?? []
        );

        return rest_ensure_response(['success' => $success]);
    }

    public function check_permission(): bool {
        return is_user_logged_in();
    }
}
