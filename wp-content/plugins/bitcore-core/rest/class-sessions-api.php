<?php
namespace BITCORE\Rest;

use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Sessions API Controller
 * Handles session management endpoints for BITCORE
 * @package     Bitcore
 * @since       1.0.0
 * @version     1.0.0
 */
class Sessions_Api extends WP_REST_Controller {

    public function __construct() {
        $this->namespace = 'bitcore/v1';
        $this->rest_base = 'sessions';
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                [
                    'methods' => \WP_REST_Server::READABLE,
                    'callback' => [$this, 'get_items'],
                    'permission_callback' => '__return_true',
                ],
                [
                    'methods' => \WP_REST_Server::CREATABLE,
                    'callback' => [$this, 'create_item'],
                    'permission_callback' => [$this, 'create_item_permissions_check'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>[\\d]+)',
            [
                [
                    'methods' => \WP_REST_Server::READABLE,
                    'callback' => [$this, 'get_item'],
                    'permission_callback' => '__return_true',
                ],
                [
                    'methods' => \WP_REST_Server::DELETABLE,
                    'callback' => [$this, 'delete_item'],
                    'permission_callback' => [$this, 'delete_item_permissions_check'],
                ],
            ]
        );
    }

    public function get_items_permissions_check($request): bool {
        return current_user_can('read') || true; // Public read access
    }

    public function get_item_permissions_check($request): bool {
        return current_user_can('read') || true;
    }

    /**
     * @return bool True when the current user can edit posts.
     */
    public function create_item_permissions_check($request): bool {
        return current_user_can('edit_posts');
    }

    /**
     * @return bool True when the current user can edit posts.
     */
    public function delete_item_permissions_check($request): bool {
        return current_user_can('edit_posts');
    }

    public function get_items($request): WP_REST_Response {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bitcore_sessions';

        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

        if (!$table_exists) {
            return new WP_REST_Response(['sessions' => [], 'message' => 'Sessions table not found'], 200);
        }

        $sessions = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC", ARRAY_A);

        return new WP_REST_Response(['sessions' => $sessions ?: []], 200);
    }

    public function get_item($request): WP_REST_Response|WP_Error {
        global $wpdb;
        $id = (int) $request['id'];
        $table_name = $wpdb->prefix . 'bitcore_sessions';

        $session = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id), ARRAY_A);

        if (!$session) {
            return new WP_Error('session_not_found', 'Session not found', ['status' => 404]);
        }

        return new WP_REST_Response(['session' => $session], 200);
    }

    public function create_item($request): WP_REST_Response {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bitcore_sessions';

        $data = [
            'agent_id' => sanitize_text_field($request->get_param('agent_id') ?? ''),
            'status' => sanitize_text_field($request->get_param('status') ?? 'active'),
            'created_at' => current_time('mysql'),
        ];

        $wpdb->insert($table_name, $data);
        $data['id'] = $wpdb->insert_id;

        return new WP_REST_Response(['session' => $data], 201);
    }

    public function delete_item($request): WP_REST_Response|WP_Error {
        global $wpdb;
        $id = (int) $request['id'];
        $table_name = $wpdb->prefix . 'bitcore_sessions';

        $result = $wpdb->delete($table_name, ['id' => $id]);

        if (!$result) {
            return new WP_Error('session_not_found', 'Session not found', ['status' => 404]);
        }

        return new WP_REST_Response(['deleted' => true], 200);
    }
}
