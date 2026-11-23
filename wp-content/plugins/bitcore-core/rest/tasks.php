<?php
declare(strict_types=1);
/**
 * REST API Controller for Tasks
 *
 * Handles REST API endpoints for task management with security hardening.
 *
 * Based on patterns from bitcore/wordpress-security
 *
 * @package BITCORE
 * @since 1.0.0
 * @author BITCORE Team
 * @version     1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/trait-rest-tasks-actions.php';
require_once __DIR__ . '/trait-rest-tasks-response-methods.php';

/**
 * Class BITCORE_Rest_Tasks
 *
 * Handles REST API endpoints for task management with security hardening.
 *
 * @since 1.0.0
 */
class BITCORE_Rest_Tasks {

    use REST_Tasks_Response_Methods;

    use REST_Tasks_Actions;

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
     * Register REST API routes
     *
     * @since 1.0.0
     * @return void
     */
    public static function register_routes(): void {
        // Register GET /tasks endpoint for listing tasks
        register_rest_route(
            self::NAMESPACE,
            '/' . self::REST_BASE,
            array(
                array(
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => array( __CLASS__, 'get_items' ),
                    'permission_callback' => array( __CLASS__, 'get_items_permissions_check' ),
                    'args'                => self::get_collection_params(),
                ),
                array(
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => array( __CLASS__, 'create_item' ),
                    'permission_callback' => array( __CLASS__, 'create_item_permissions_check' ),
                    'args'                => self::get_endpoint_args_for_item_schema(
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
                        'description' => __( 'The task ID.', 'bitcore' ),
                        'type'        => 'integer',
                        'required'    => true,
                    ),
                ),
                array(
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => array( __CLASS__, 'get_item' ),
                    'permission_callback' => array( __CLASS__, 'get_item_permissions_check' ),
                ),
                array(
                    'methods'             => \WP_REST_Server::EDITABLE,
                    'callback'            => array( __CLASS__, 'update_item' ),
                    'permission_callback' => array( __CLASS__, 'update_item_permissions_check' ),
                    'args'                => self::get_endpoint_args_for_item_schema(
                        \WP_REST_Server::EDITABLE
                    ),
                ),
                array(
                    'methods'             => \WP_REST_Server::DELETABLE,
                    'callback'            => array( __CLASS__, 'delete_item' ),
                    'permission_callback' => array( __CLASS__, 'delete_item_permissions_check' ),
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
                        'description' => __( 'The task ID to dispatch.', 'bitcore' ),
                        'type'        => 'integer',
                        'required'    => true,
                    ),
                ),
                array(
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => array( __CLASS__, 'dispatch_task' ),
                    'permission_callback' => array( __CLASS__, 'dispatch_item_permissions_check' ),
                ),
            )
        );
    }

    /**
     * Get collection of tasks
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Full request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public static function get_items( $request ) {
        $params = self::sanitize_recursive( $request->get_params() );
        $page   = isset( $params['page'] ) ? intval( $params['page'] ) : 1;
        $per_page = isset( $params['per_page'] ) ? intval( $params['per_page'] ) : 10;

        $args = array(
            'post_type'      => 'bitcore_task',
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => $page,
        );

        if ( ! empty( $params['search'] ) ) {
            $args['s'] = sanitize_text_field( $params['search'] );
        }

        $query = new \WP_Query( $args );
        $posts = $query->posts;
        $data  = array();

        foreach ( $posts as $post ) {
            $response = self::prepare_item_for_response( $post, $request );
            $data[]   = $response->get_data();
        }

        $response = rest_ensure_response( $data );
        $response->header( 'X-WP-Total', $query->found_posts );
        $response->header( 'X-WP-TotalPages', $query->max_num_pages );

        return $response;
    }

    /**
     * Get single task
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Full request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public static function get_item( $request ) {
        $id   = intval( $request['id'] );
        $post = get_post( $id );

        if ( ! $post || $post->post_type !== 'bitcore_task' ) {
            return new \WP_Error(
                'rest_task_not_found',
                __( 'Task not found.', 'bitcore' ),
                array( 'status' => 404 )
            );
        }

        return self::prepare_item_for_response( $post, $request );
    }

    /**
     * Create new task
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Full request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public static function create_item( $request ) {
        $params = self::sanitize_recursive( $request->get_json_params() );

        if ( empty( $params['title'] ) ) {
            return new \WP_Error(
                'rest_task_missing_title',
                __( 'Task title is required.', 'bitcore' ),
                array( 'status' => 400 )
            );
        }

        $post_data = array(
            'post_title'   => sanitize_text_field( $params['title'] ),
            'post_content' => isset( $params['content'] ) ? wp_kses_post( $params['content'] ) : '',
            'post_status'  => 'publish',
            'post_type'    => 'bitcore_task',
        );

        $post_id = wp_insert_post( $post_data, true );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        $post = get_post( $post_id );
        $response = self::prepare_item_for_response( $post, $request );
        $response->set_status( 201 );

        return $response;
    }




    /**
     * Permission check for getting items
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Full request object
     * @return bool|WP_Error True if allowed, error otherwise
     */
    public static function get_items_permissions_check( $request ) {
        $nonce = $request->get_header( 'X-WP-Nonce' );
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new \WP_Error(
                'rest_forbidden',
                __( 'Invalid or missing nonce.', 'bitcore' ),
                array( 'status' => 403 )
            );
        }
        return current_user_can( 'read' );
    }

    /**
     * Permission check for getting single item
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Full request object
     * @return bool|WP_Error True if allowed, error otherwise
     */
    public static function get_item_permissions_check( $request ) {
        return self::get_items_permissions_check( $request );
    }

    /**
     * Permission check for creating items
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Full request object
     * @return bool|WP_Error True if allowed, error otherwise
     */
    public static function create_item_permissions_check( $request ) {
        $nonce = $request->get_header( 'X-WP-Nonce' );
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new \WP_Error(
                'rest_forbidden',
                __( 'Invalid or missing nonce.', 'bitcore' ),
                array( 'status' => 403 )
            );
        }
        return current_user_can( 'edit_posts' );
    }

    /**
     * Permission check for updating items
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Full request object
     * @return bool|WP_Error True if allowed, error otherwise
     */
    public static function update_item_permissions_check( $request ) {
        $nonce = $request->get_header( 'X-WP-Nonce' );
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new \WP_Error(
                'rest_forbidden',
                __( 'Invalid or missing nonce.', 'bitcore' ),
                array( 'status' => 403 )
            );
        }
        return current_user_can( 'edit_posts' );
    }

    /**
     * Permission check for deleting items
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Full request object
     * @return bool|WP_Error True if allowed, error otherwise
     */
    public static function delete_item_permissions_check( $request ) {
        $nonce = $request->get_header( 'X-WP-Nonce' );
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new \WP_Error(
                'rest_forbidden',
                __( 'Invalid or missing nonce.', 'bitcore' ),
                array( 'status' => 403 )
            );
        }
        return current_user_can( 'delete_posts' );
    }

    /**
     * Permission check for dispatching tasks
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Full request object
     * @return bool|WP_Error True if allowed, error otherwise
     */
    public static function dispatch_item_permissions_check( $request ) {
        $nonce = $request->get_header( 'X-WP-Nonce' );
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new \WP_Error(
                'rest_forbidden',
                __( 'Invalid or missing nonce.', 'bitcore' ),
                array( 'status' => 403 )
            );
        }
        return current_user_can( 'edit_posts' );
    }




}
