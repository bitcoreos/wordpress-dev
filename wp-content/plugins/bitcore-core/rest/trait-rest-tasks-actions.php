<?php
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

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait REST_Tasks_Actions {

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
}
