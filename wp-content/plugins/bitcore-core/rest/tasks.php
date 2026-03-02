<?php
/**
 * BITCORE_Rest_Tasks implementation
 *
 * @package    Bitcore
 * @since      2026.03.01
 * @version    1.3.0
 * @author     hyperion
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

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
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class BITCORE_Rest_Tasks
 *
 * Handles REST API endpoints for task management with security hardening.
 *
 * @since 1.0.0
 */
class BITCORE_Rest_Tasks {

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
            $data[]   = self::prepare_response_for_collection( $response );
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
     * Update existing task
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Full request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public static function update_item( $request ) {
        $id   = intval( $request['id'] );
        $post = get_post( $id );

        if ( ! $post || $post->post_type !== 'bitcore_task' ) {
            return new \WP_Error(
                'rest_task_not_found',
                __( 'Task not found.', 'bitcore' ),
                array( 'status' => 404 )
            );
        }

        $params = self::sanitize_recursive( $request->get_json_params() );
        $post_data = array(
            'ID' => $id,
        );

        if ( isset( $params['title'] ) ) {
            $post_data['post_title'] = sanitize_text_field( $params['title'] );
        }

        if ( isset( $params['content'] ) ) {
            $post_data['post_content'] = wp_kses_post( $params['content'] );
        }

        $updated = wp_update_post( $post_data, true );

        if ( is_wp_error( $updated ) ) {
            return $updated;
        }

        $post = get_post( $id );
        return self::prepare_item_for_response( $post, $request );
    }

    /**
     * Delete task
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Full request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public static function delete_item( $request ) {
        $id   = intval( $request['id'] );
        $post = get_post( $id );

        if ( ! $post || $post->post_type !== 'bitcore_task' ) {
            return new \WP_Error(
                'rest_task_not_found',
                __( 'Task not found.', 'bitcore' ),
                array( 'status' => 404 )
            );
        }

        $result = wp_delete_post( $id, true );

        if ( ! $result ) {
            return new \WP_Error(
                'rest_task_delete_failed',
                __( 'Failed to delete task.', 'bitcore' ),
                array( 'status' => 500 )
            );
        }

        return new \WP_REST_Response(
            array( 'deleted' => true, 'previous' => self::prepare_item_for_response( $post, $request ) ),
            200
        );
    }

    /**
     * Dispatch task to worker
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Full request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public static function dispatch_task( $request ) {
        $id   = intval( $request['id'] );
        $post = get_post( $id );

        if ( ! $post || $post->post_type !== 'bitcore_task' ) {
            return new \WP_Error(
                'rest_task_not_found',
                __( 'Task not found.', 'bitcore' ),
                array( 'status' => 404 )
            );
        }

        // Update task status to dispatched
        update_post_meta( $id, '_task_status', 'dispatched' );
        update_post_meta( $id, '_task_dispatched_at', current_time( 'mysql' ) );

        // Trigger action for task dispatch
        do_action( 'bitcore_task_dispatched', $id, $post );

        $post = get_post( $id );
        return self::prepare_item_for_response( $post, $request );
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
        return current_user_can( 'bitcore_read_tasks' );
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
        return current_user_can( 'bitcore_create_tasks' );
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
        return current_user_can( 'bitcore_edit_tasks' );
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
        return current_user_can( 'bitcore_delete_tasks' );
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
        return current_user_can( 'bitcore_dispatch_tasks' );
    }

    /**
     * Prepare item for REST response
     *
     * @since 1.0.0
     * @param WP_Post          $post    Post object
     * @param WP_REST_Request $request Full request object
     * @return WP_REST_Response Response object
     */
    public static function prepare_item_for_response( $post, $request ) {
        $data = array(
            'id'      => $post->ID,
            'title'   => $post->post_title,
            'content' => $post->post_content,
            'status'  => $post->post_status,
            'date'    => mysql_to_rfc3339( $post->post_date_gmt ),
            'author'  => intval( $post->post_author ),
        );

        $meta_status = get_post_meta( $post->ID, '_task_status', true );
        if ( $meta_status ) {
            $data['task_status'] = $meta_status;
        }

        return rest_ensure_response( $data );
    }

    /**
     * Get endpoint arguments for schema
     *
     * @since 1.0.0
     * @param string $method HTTP method
     * @return array Args for the endpoint
     */
    public static function get_endpoint_args_for_item_schema( $method = \WP_REST_Server::CREATABLE ) {
        $args = array(
            'title'   => array(
                'description'       => __( 'The title for the task.', 'bitcore' ),
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'content' => array(
                'description'       => __( 'The content for the task.', 'bitcore' ),
                'type'              => 'string',
                'sanitize_callback' => 'wp_kses_post',
            ),
        );

        if ( $method === \WP_REST_Server::EDITABLE ) {
            unset( $args['title']['required'] );
        }

        return $args;
    }

    /**
     * Get collection parameters
     *
     * @since 1.0.0
     * @return array Collection parameters
     */
    public static function get_collection_params() {
        return array(
            'page'     => array(
                'description'       => __( 'Current page of the collection.', 'bitcore' ),
                'type'              => 'integer',
                'default'           => 1,
                'sanitize_callback' => 'absint',
            ),
            'per_page' => array(
                'description'       => __( 'Maximum number of items to be returned.', 'bitcore' ),
                'type'              => 'integer',
                'default'           => 10,
                'minimum'           => 1,
                'maximum'           => 100,
                'sanitize_callback' => 'absint',
            ),
            'search'   => array(
                'description'       => __( 'Limit results to those matching a string.', 'bitcore' ),
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
        );
    }

    /**
     * Recursively sanitize input parameters
     *
     * @since 1.0.0
     * @param mixed $data Data to sanitize
     * @return mixed Sanitized data
     */
    public static function sanitize_recursive( $data ) {
        if ( is_array( $data ) ) {
            $sanitized = array();
            foreach ( $data as $key => $value ) {
                $sanitized[ sanitize_key( $key ) ] = self::sanitize_recursive( $value );
            }
            return $sanitized;
        }

        if ( is_string( $data ) ) {
            return sanitize_text_field( $data );
        }

        if ( is_int( $data ) ) {
            return intval( $data );
        }

        if ( is_float( $data ) ) {
            return floatval( $data );
        }

        if ( is_bool( $data ) ) {
            return boolval( $data );
        }

        return $data;
    }
}
