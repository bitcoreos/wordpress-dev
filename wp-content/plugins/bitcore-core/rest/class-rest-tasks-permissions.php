<?php
/**
 * BITCORE REST Tasks - Permissions & Utilities
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

class BITCORE_REST_Tasks_Permissions {

    public function get_items_permissions_check($request) {
        $nonce = $request->get_header('X-WP-Nonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new \WP_Error(
                'rest_forbidden',
                __('Invalid or missing nonce.', 'bitcore-tasks'),
                array('status' => 403)
            );
        }
        return current_user_can('read');
    }

    /**
     * Permission check for getting single item
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request Full request object
     * @return bool|\WP_Error True if allowed, error otherwise
     */
    public function get_item_permissions_check($request) {
        $nonce = $request->get_header('X-WP-Nonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new \WP_Error(
                'rest_forbidden',
                __('Invalid or missing nonce.', 'bitcore-tasks'),
                array('status' => 403)
            );
        }
        return current_user_can('read');
    }

    /**
     * Permission check for creating items
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request Full request object
     * @return bool|\WP_Error True if allowed, error otherwise
     */
    public function create_item_permissions_check($request) {
        $nonce = $request->get_header('X-WP-Nonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new \WP_Error(
                'rest_forbidden',
                __('Invalid or missing nonce.', 'bitcore-tasks'),
                array('status' => 403)
            );
        }
        return current_user_can('edit_posts');
    }

    /**
     * Permission check for updating items
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request Full request object
     * @return bool|\WP_Error True if allowed, error otherwise
     */
    public function update_item_permissions_check($request) {
        $nonce = $request->get_header('X-WP-Nonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new \WP_Error(
                'rest_forbidden',
                __('Invalid or missing nonce.', 'bitcore-tasks'),
                array('status' => 403)
            );
        }
        return current_user_can('edit_posts');
    }

    /**
     * Permission check for deleting items
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request Full request object
     * @return bool|\WP_Error True if allowed, error otherwise
     */
    public function delete_item_permissions_check($request) {
        $nonce = $request->get_header('X-WP-Nonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new \WP_Error(
                'rest_forbidden',
                __('Invalid or missing nonce.', 'bitcore-tasks'),
                array('status' => 403)
            );
        }
        return current_user_can('delete_posts');
    }

    /**
     * Permission check for dispatching tasks
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request Full request object
     * @return bool|\WP_Error True if allowed, error otherwise
     */
    public function dispatch_item_permissions_check($request) {
        $nonce = $request->get_header('X-WP-Nonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new \WP_Error(
                'rest_forbidden',
                __('Invalid or missing nonce.', 'bitcore-tasks'),
                array('status' => 403)
            );
        }
        return current_user_can('edit_posts');
    }

    /**
     * Prepare item for REST response
     *
     * @since 1.0.0
     * @param \WP_Post          $post    Post object
     * @param \WP_REST_Request $request Full request object
     * @return \WP_REST_Response Response object
     */
    public function prepare_item_for_response($post, $request) {
        $data = array(
            'id'      => $post->ID,
            'title'   => $post->post_title,
            'content' => $post->post_content,
            'status'  => $post->post_status,
            'date'    => mysql_to_rfc3339($post->post_date_gmt),
            'author'  => intval($post->post_author),
        );

        $meta_status = get_post_meta($post->ID, '_task_status', true);
        if ($meta_status) {
            $data['task_status'] = $meta_status;
        }

        $context = !empty($request['context']) ? $request['context'] : 'view';
        $data    = $this->filter_response_by_context($data, $context);

        return rest_ensure_response($data);
    }

    /**
     * Get endpoint arguments for schema
     *
     * @since 1.0.0
     * @param string $method HTTP method
     * @return array Args for the endpoint
     */
    public function get_endpoint_args_for_item_schema($method = \WP_REST_Server::CREATABLE) {
        $args = array(
            'title'   => array(
                'description' => __('The title for the task.', 'bitcore-tasks'),
                'type'        => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'content' => array(
                'description' => __('The content for the task.', 'bitcore-tasks'),
                'type'        => 'string',
                'sanitize_callback' => 'wp_kses_post',
            ),
        );

        if ($method === \WP_REST_Server::EDITABLE) {
            unset($args['title']['required']);
        }

        return $args;
    }

    /**
     * Get collection parameters
     *
     * @since 1.0.0
     * @return array Collection parameters
     */
    public function get_collection_params() {
        return array(
            'page'     => array(
                'description'       => __('Current page of the collection.', 'bitcore-tasks'),
                'type'              => 'integer',
                'default'           => 1,
                'sanitize_callback' => 'absint',
            ),
            'per_page' => array(
                'description'       => __('Maximum number of items to be returned.', 'bitcore-tasks'),
                'type'              => 'integer',
                'default'           => 10,
                'minimum'           => 1,
                'maximum'           => 100,
                'sanitize_callback' => 'absint',
            ),
            'search'   => array(
                'description'       => __('Limit results to those matching a string.', 'bitcore-tasks'),
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
        );
    }

    /**
     * Get the schema for tasks
     *
     * @since 1.0.0
     * @return array Schema array
     */
    public function get_item_schema() {
        return array(
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'task',
            'type'       => 'object',
            'properties' => array(
                'id'      => array(
                    'description' => __('Unique identifier for the task.', 'bitcore-tasks'),
                    'type'        => 'integer',
                    'context'     => array('view', 'edit', 'embed'),
                    'readonly'    => true,
                ),
                'title'   => array(
                    'description' => __('The title for the task.', 'bitcore-tasks'),
                    'type'        => 'string',
                    'context'     => array('view', 'edit', 'embed'),
                ),
                'content' => array(
                    'description' => __('The content for the task.', 'bitcore-tasks'),
                    'type'        => 'string',
                    'context'     => array('view', 'edit'),
                ),
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
    public function sanitize_recursive($data) {
        if (is_array($data)) {
            $sanitized = array();
            foreach ($data as $key => $value) {
                $sanitized[sanitize_key($key)] = $this->sanitize_recursive($value);
            }
            return $sanitized;
        }

        if (is_string($data)) {
            return sanitize_text_field($data);
        }

        if (is_int($data)) {
            return intval($data);
        }

        if (is_float($data)) {
            return floatval($data);
        }

        if (is_bool($data)) {
            return boolval($data);
        }

        return $data;
    }

}
