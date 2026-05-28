<?php
/**
 * Org Chart REST API Controller
 *
 * Flat role endpoints for agent access control and UI labeling.
 * Hierarchy tree operations were retired in P3.1.
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
        $this->register_routes();
    }

    public function register_routes(): void {
        register_rest_route($this->namespace, '/org-chart', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_all_agents'],
                'permission_callback' => '__return_true'
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'assign_role'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'agent_id' => ['type' => 'string', 'required' => true],
                    'role' => ['type' => 'string', 'required' => true]
                ]
            ]
        ]);

        register_rest_route($this->namespace, '/org-chart/(?P<agent_id>[\w-]+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_agent_info'],
                'permission_callback' => '__return_true'
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'remove_agent'],
                'permission_callback' => [$this, 'check_permission']
            ]
        ]);
    }

    public function get_all_agents(\WP_REST_Request $request): \WP_REST_Response {
        $agents = [];
        foreach ($this->org_chart->get_all_agents() as $agent_id) {
            $agents[] = [
                'agent_id' => $agent_id,
                'role' => $this->org_chart->get_role($agent_id),
            ];
        }

        return rest_ensure_response(['agents' => $agents]);
    }

    public function assign_role(\WP_REST_Request $request): \WP_REST_Response {
        $success = $this->org_chart->assign_role(
            (string) $request->get_param('agent_id'),
            (string) $request->get_param('role')
        );

        return rest_ensure_response(['success' => $success]);
    }

    public function get_agent_info(\WP_REST_Request $request): \WP_REST_Response {
        $agent_id = (string) $request->get_param('agent_id');

        return rest_ensure_response([
            'agent_id' => $agent_id,
            'role' => $this->org_chart->get_role($agent_id),
            'exists' => $this->org_chart->has_agent($agent_id),
        ]);
    }

    public function remove_agent(\WP_REST_Request $request): \WP_REST_Response {
        $success = $this->org_chart->remove_agent((string) $request->get_param('agent_id'));
        return rest_ensure_response(['success' => $success]);
    }

    public function check_permission(): bool {
        return is_user_logged_in();
    }
}
