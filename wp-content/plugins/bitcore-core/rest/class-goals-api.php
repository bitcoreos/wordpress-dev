<?php
/**
 * Goals REST API Controller
 *
 * REST endpoints for goal hierarchy management.
 *
 * @package BITCORE\Rest
 */

namespace BITCORE\Rest;

if (!defined('ABSPATH')) exit;

use BITCORE\Infrastructure\Goals\GoalHierarchy;

/**
 * Class Goals_Api
 */
class Goals_Api {
    private string $namespace = 'bitcore/v1';
    private GoalHierarchy $hierarchy;

    public function __construct() {
        $this->hierarchy = new GoalHierarchy();
    }

    public function register_routes(): void {
        register_rest_route($this->namespace, '/goals', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_roots'],
                'permission_callback' => [$this, 'check_permission']
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_goal'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'title' => ['type' => 'string', 'required' => true],
                    'level' => ['type' => 'string', 'default' => 'task'],
                    'parent_id' => ['type' => 'integer']
                ]
            ]
        ]);

        register_rest_route($this->namespace, '/goals/(?P<goal_id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_goal'],
                'permission_callback' => [$this, 'check_permission']
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'update_goal'],
                'permission_callback' => [$this, 'check_permission']
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_goal'],
                'permission_callback' => [$this, 'check_permission']
            ]
        ]);

        register_rest_route($this->namespace, '/goals/(?P<goal_id>\d+)/children', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_children'],
                'permission_callback' => [$this, 'check_permission']
            ]
        ]);

        register_rest_route($this->namespace, '/goals/(?P<goal_id>\d+)/ancestry', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_ancestry'],
                'permission_callback' => [$this, 'check_permission']
            ]
        ]);

        register_rest_route($this->namespace, '/goals/agent/(?P<agent_id>[\w-]+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_assigned'],
                'permission_callback' => [$this, 'check_permission']
            ]
        ]);
    }

    public function get_roots(\WP_REST_Request $request): \WP_REST_Response {
        $status = $request->get_param('status');
        $roots = $this->hierarchy->get_roots($status);
        return rest_ensure_response(['goals' => $roots]);
    }

    public function create_goal(\WP_REST_Request $request): \WP_REST_Response {
        $goal_id = $this->hierarchy->create([
            'title' => $request->get_param('title'),
            'description' => $request->get_param('description') ?? '',
            'level' => $request->get_param('level'),
            'parent_id' => $request->get_param('parent_id'),
            'assigned_agent_id' => $request->get_param('assigned_agent_id'),
            'priority' => $request->get_param('priority') ?? 5
        ]);

        return rest_ensure_response(['goal_id' => $goal_id, 'success' => $goal_id !== false]);
    }

    public function get_goal(\WP_REST_Request $request): \WP_REST_Response {
        $goal = $this->hierarchy->get($request->get_param('goal_id'));
        return rest_ensure_response($goal);
    }

    public function update_goal(\WP_REST_Request $request): \WP_REST_Response {
        $data = [];
        foreach (['title', 'description', 'status', 'assigned_agent_id', 'priority'] as $field) {
            if ($request->get_param($field) !== null) {
                $data[$field] = $request->get_param($field);
            }
        }

        $success = $this->hierarchy->update($request->get_param('goal_id'), $data);
        return rest_ensure_response(['success' => $success]);
    }

    public function delete_goal(\WP_REST_Request $request): \WP_REST_Response {
        $cascade = $request->get_param('cascade') ?? false;
        $success = $this->hierarchy->delete($request->get_param('goal_id'), $cascade);
        return rest_ensure_response(['success' => $success]);
    }

    public function get_children(\WP_REST_Request $request): \WP_REST_Response {
        $children = $this->hierarchy->get_children($request->get_param('goal_id'));
        return rest_ensure_response(['children' => $children]);
    }

    public function get_ancestry(\WP_REST_Request $request): \WP_REST_Response {
        $ancestry = $this->hierarchy->get_ancestry($request->get_param('goal_id'));
        return rest_ensure_response(['ancestry' => $ancestry]);
    }

    public function get_assigned(\WP_REST_Request $request): \WP_REST_Response {
        $status = $request->get_param('status');
        $goals = $this->hierarchy->get_assigned($request->get_param('agent_id'), $status);
        return rest_ensure_response(['goals' => $goals]);
    }

    public function check_permission(): bool {
        return current_user_can('manage_options') || current_user_can('bitcore_agent');
    }
}
