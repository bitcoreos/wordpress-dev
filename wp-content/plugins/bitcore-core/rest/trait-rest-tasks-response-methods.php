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

trait REST_Tasks_Response_Methods {

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
