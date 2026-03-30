<?php
/**
 * Org Chart REST API Controller
 *
 * REST endpoints for agent hierarchy management.
 *
 * @package BITCORE\Rest
 */

namespace BITCORE\Rest;

if (!defined('ABSPATH')) exit;

use BITCORE\Infrastructure\Coordination\OrgChart;

/**
 * Class Org_Chart_Api
 */
class Org_Chart_Api {
    private string $namespace = 'bitcore/v1';
    private OrgChart $org_chart;

    public function __construct() {
        $this->org_chart = new OrgChart();
    }

    public function register_routes(): void {
        register_rest_route($this->namespace, '/org-chart', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_all_agents'],
                'permission_callback' => [$this, 'check_permission']
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'assign_role'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'agent_id' => ['type' => 'string', 'required' => true],
                    'role' => ['type' => 'string', 'required' => true],
                    'boss_id' => ['type' => 'string']
                ]
            ]
        ]);

        register_rest_route($this->namespace, '/org-chart/(?P<agent_id>[\w-]+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_agent_info'],
                'permission_callback' => [$this, 'check_permission']
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'remove_agent'],
                'permission_callback' => [$this, 'check_permission']
            ]
        ]);

        register_rest_route($this->namespace, '/org-chart/(?P<agent_id>[\w-]+)/reports', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_reports'],
                'permission_callback' => [$this, 'check_permission']
            ]
        ]);

        register_rest_route($this->namespace, '/org-chart/(?P<agent_id>[\w-]+)/chain', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_chain_of_command'],
                'permission_callback' => [$this, 'check_permission']
            ]
        ]);

        register_rest_route($this->namespace, '/org-chart/(?P<agent_id>[\w-]+)/broadcast', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'broadcast'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'message' => ['type' => 'object', 'required' => true]
                ]
            ]
        ]);
    }

    public function get_all_agents(\WP_REST_Request $request): \WP_REST_Response {
        $agents = $this->org_chart->get_all_agents();
        return rest_ensure_response(['agents' => $agents]);
    }

    public function assign_role(\WP_REST_Request $request): \WP_REST_Response {
        $success = $this->org_chart->assign_role(
            $request->get_param('agent_id'),
            $request->get_param('role'),
            $request->get_param('boss_id')
        );

        return rest_ensure_response(['success' => $success]);
    }

    public function get_agent_info(\WP_REST_Request $request): \WP_REST_Response {
        $agent_id = $request->get_param('agent_id');

        return rest_ensure_response([
            'role' => $this->org_chart->get_role($agent_id),
            'boss' => $this->org_chart->get_boss($agent_id),
            'is_manager' => $this->org_chart->is_manager($agent_id),
            'reports' => $this->org_chart->get_reports($agent_id)
        ]);
    }

    public function remove_agent(\WP_REST_Request $request): \WP_REST_Response {
        $success = $this->org_chart->remove_agent($request->get_param('agent_id'));
        return rest_ensure_response(['success' => $success]);
    }

    public function get_reports(\WP_REST_Request $request): \WP_REST_Response {
        $reports = $this->org_chart->get_all_subordinates($request->get_param('agent_id'));
        return rest_ensure_response(['reports' => $reports]);
    }

    public function get_chain_of_command(\WP_REST_Request $request): \WP_REST_Response {
        $chain = $this->org_chart->get_chain_of_command($request->get_param('agent_id'));
        return rest_ensure_response(['chain' => $chain]);
    }

    public function broadcast(\WP_REST_Request $request): \WP_REST_Response {
        $results = $this->org_chart->broadcast_to_reports(
            $request->get_param('agent_id'),
            $request->get_param('message')
        );

        return rest_ensure_response(['results' => $results]);
    }

    public function check_permission(): bool {
        // Allow public read access for GET requests
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            return true;
        }
        // Require auth for POST/PUT/DELETE
        return current_user_can('manage_options') || current_user_can('bitcore_agent');
    }
}
