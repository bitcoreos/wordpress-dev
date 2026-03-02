<?php
/**
 * BITCORE REST Tasks - CRUD Operations
 *
 * @package    Bitcore
 * @since      2026.03.01
 * @version    1.3.0
 * @author     coraline
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}
class REST_Tasks {

    /**
     * Namespace for REST API routes
     *
     * @since 1.0.0
     * @var string
     */
    private const NAMESPACE = 'bitcore/v1';

    /**
     * REST route base
     *
     * @since 1.0.0
     * @var string
     */
    private const REST_BASE = 'tasks';

    /**
     * Nonce action for verification
     *
     * @since 1.0.0
     * @var string
     */
    private const NONCE_ACTION = 'bitcore_tasks_nonce';

    /**
     * Register REST API routes
     *
     * @since 1.0.0
     * @return void
     */
    public function register_routes(): void {
        // Register GET /tasks endpoint for listing tasks
        register_rest_route(
            self::NAMESPACE,
            '/' . self::REST_BASE,
            array(
                array(
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => array($this, 'get_items'),
                    'permission_callback' => array($this, 'get_items_permissions_check'),
                    'args'                => $this->get_collection_params(),
                ),
                array(
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => array($this, 'create_item'),
                    'permission_callback' => array($this, 'create_item_permissions_check'),
                    'args'                => $this->get_endpoint_args_for_item_schema(
                        \WP_REST_Server::CREATABLE
                    ),
                ),
            )
        );

        // Register individual task endpoints
        register_rest_route(
            self::NAMESPACE,
            '/' . self::REST_BASE . '/(?P<id>\d+)',
            array(
                'args' => array(
                    'id' => array(
                        'description' => __('The task ID.', 'bitcore-tasks'),
                        'type'        => 'integer',
                        'required'    => true,
                    ),
                ),
                array(
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => array($this, 'get_item'),
                    'permission_callback' => array($this, 'get_item_permissions_check'),
                ),
                array(
                    'methods'             => \WP_REST_Server::EDITABLE,
                    'callback'            => array($this, 'update_item'),
                    'permission_callback' => array($this, 'update_item_permissions_check'),
                    'args'                => $this->get_endpoint_args_for_item_schema(
                        \WP_REST_Server::EDITABLE
                    ),
                ),
                array(
                    'methods'             => \WP_REST_Server::DELETABLE,
                    'callback'            => array($this, 'delete_item'),
                    'permission_callback' => array($this, 'delete_item_permissions_check'),
                ),
            )
        );

        // Register task dispatch endpoint
        register_rest_route(
            self::NAMESPACE,
            '/' . self::REST_BASE . '/(?P<id>\d+)/dispatch',
            array(
                'args' => array(
                    'id' => array(
                        'description' => __('The task ID to dispatch.', 'bitcore-tasks'),
                        'type'        => 'integer',
                        'required'    => true,
                    ),
                ),
                array(
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => array($this, 'dispatch_task'),
                    'permission_callback' => array($this, 'dispatch_item_permissions_check'),
                ),
            )
        );
    }

    /**
     * Get collection of tasks
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request Full request object
     * @return \WP_REST_Response|\WP_Error Response object or error
     */
    public function get_items($request) {
        $params = $this->sanitize_recursive($request->get_params());
        $page   = isset($params['page']) ? intval($params['page']) : 1;
        $per_page = isset($params['per_page']) ? intval($params['per_page']) : 10;

        $args = array(
            'post_type'      => 'bitcore_task',
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => $page,
        );

        if (!empty($params['search'])) {
            $args['s'] = sanitize_text_field($params['search']);
        }

        $query = new \WP_Query($args);
        $posts = $query->posts;
        $data  = array();

        foreach ($posts as $post) {
            $response = $this->prepare_item_for_response($post, $request);
            $data[]   = $this->prepare_response_for_collection($response);
        }

        $response = rest_ensure_response($data);
        $response->header('X-WP-Total', $query->found_posts);
        $response->header('X-WP-TotalPages', $query->max_num_pages);

        return $response;
    }

    /**
     * Get single task
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request Full request object
     * @return \WP_REST_Response|\WP_Error Response object or error
     */
    public function get_item($request) {
        $id   = intval($request['id']);
        $post = get_post($id);

        if (!$post || $post->post_type !== 'bitcore_task') {
            return new \WP_Error(
                'rest_task_not_found',
                __('Task not found.', 'bitcore-tasks'),
                array('status' => 404)
            );
        }

        return $this->prepare_item_for_response($post, $request);
    }

    /**
     * Create new task
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request Full request object
     * @return \WP_REST_Response|\WP_Error Response object or error
     */
    public function create_item($request) {
        $params = $this->sanitize_recursive($request->get_json_params());

        if (empty($params['title'])) {
            return new \WP_Error(
                'rest_task_missing_title',
                __('Task title is required.', 'bitcore-tasks'),
                array('status' => 400)
            );
        }

        $post_data = array(
            'post_title'   => sanitize_text_field($params['title']),
            'post_content' => isset($params['content']) ? wp_kses_post($params['content']) : '',
            'post_status'  => 'publish',
            'post_type'    => 'bitcore_task',
        );

        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        $post = get_post($post_id);
        $response = $this->prepare_item_for_response($post, $request);
        $response->set_status(201);

        return $response;
    }

    /**
     * Update existing task
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request Full request object
     * @return \WP_REST_Response|\WP_Error Response object or error
     */
    public function update_item($request) {
        $id   = intval($request['id']);
        $post = get_post($id);

        if (!$post || $post->post_type !== 'bitcore_task') {
            return new \WP_Error(
                'rest_task_not_found',
                __('Task not found.', 'bitcore-tasks'),
                array('status' => 404)
            );
        }

        $params = $this->sanitize_recursive($request->get_json_params());
        $post_data = array(
            'ID' => $id,
        );

        if (isset($params['title'])) {
            $post_data['post_title'] = sanitize_text_field($params['title']);
        }

        if (isset($params['content'])) {
            $post_data['post_content'] = wp_kses_post($params['content']);
        }

        $updated = wp_update_post($post_data, true);

        if (is_wp_error($updated)) {
            return $updated;
        }

        $post = get_post($id);
        return $this->prepare_item_for_response($post, $request);
    }

    /**
     * Delete task
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request Full request object
     * @return \WP_REST_Response|\WP_Error Response object or error
     */
    public function delete_item($request) {
        $id   = intval($request['id']);
        $post = get_post($id);

        if (!$post || $post->post_type !== 'bitcore_task') {
            return new \WP_Error(
                'rest_task_not_found',
                __('Task not found.', 'bitcore-tasks'),
                array('status' => 404)
            );
        }

        $result = wp_delete_post($id, true);

        if (!$result) {
            return new \WP_Error(
                'rest_task_delete_failed',
                __('Failed to delete task.', 'bitcore-tasks'),
                array('status' => 500)
            );
        }

        return new \WP_REST_Response(
            array('deleted' => true, 'previous' => $this->prepare_item_for_response($post, $request)),
            200
        );
    }

    /**
     * Dispatch task to worker
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request Full request object
     * @return \WP_REST_Response|\WP_Error Response object or error
     */
    public function dispatch_task($request) {
        $id   = intval($request['id']);
        $post = get_post($id);

        if (!$post || $post->post_type !== 'bitcore_task') {
            return new \WP_Error(
                'rest_task_not_found',
                __('Task not found.', 'bitcore-tasks'),
                array('status' => 404)
            );
        }

        // Update task status to dispatched
        update_post_meta($id, '_task_status', 'dispatched');
        update_post_meta($id, '_task_dispatched_at', current_time('mysql'));

        // Trigger action for task dispatch
        do_action('bitcore_task_dispatched', $id, $post);

        $post = get_post($id);
        return $this->prepare_item_for_response($post, $request);
    }

    /**
     * Permission check for getting items
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request Full request object
     * @return bool|\WP_Error True if allowed, error otherwise
     */
