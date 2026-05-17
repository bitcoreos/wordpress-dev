<?php
declare(strict_types=1);
/**
 * REST Tasks Controller
 * Migrated from custom table to agent_task post type queries.
 *
 * @package BITCORE\REST
 * @since 1.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/trait-rest-tasks-permissions.php';
require_once __DIR__ . '/trait-rest-tasks-schema.php';

class REST_Tasks {
    use REST_Tasks_Permissions;
    use REST_Tasks_Schema;

    const NAMESPACE = 'bitcore/v1';
    const REST_BASE = 'tasks';
    const POST_TYPE = 'agent_task';

    public function __construct() {}

    public function register_routes(): void {
        register_rest_route(self::NAMESPACE, '/' . self::REST_BASE, [
            'methods' => 'GET',
            'callback' => [$this, 'get_items'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::REST_BASE . '/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_item'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::REST_BASE, [
            'methods' => 'POST',
            'callback' => [$this, 'create_item'],
            'permission_callback' => [$this, 'create_item_permissions_check'],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::REST_BASE . '/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'update_item'],
            'permission_callback' => [$this, 'update_item_permissions_check'],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::REST_BASE . '/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_item'],
            'permission_callback' => [$this, 'delete_item_permissions_check'],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::REST_BASE . '/(?P<id>\d+)/dispatch', [
            'methods' => 'POST',
            'callback' => [$this, 'dispatch_task'],
            'permission_callback' => [$this, 'dispatch_item_permissions_check'],
        ]);
    }

    public function get_items($request) {
        $params = $request->get_params();
        $per_page = (int) ($params['per_page'] ?? 10);
        $page = (int) ($params['page'] ?? 1);
        $offset = ($page - 1) * $per_page;

        $args = [
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => $per_page,
            'offset'         => $offset,
            'post_status'    => 'any',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if (!empty($params['status'])) {
            $args['tax_query'][] = [
                'taxonomy' => 'task_status',
                'field'    => 'slug',
                'terms'    => sanitize_text_field($params['status']),
            ];
        }

        if (!empty($params['assigned_to'])) {
            $assigned = sanitize_text_field($params['assigned_to']);
            $args['meta_query'][] = [
                'key'     => '_bitcore_assigned_to',
                'value'   => $assigned,
                'compare' => 'LIKE',
            ];
        }

        $query = new WP_Query($args);
        $posts = $query->posts;
        $total = $query->found_posts;

        return rest_ensure_response([
            'success' => true,
            'data' => array_map([$this, 'prepare_item_for_response'], $posts),
            'total' => (int) $total,
            'pages' => ceil($total / $per_page),
        ]);
    }

    public function get_item($request) {
        $id = (int) $request['id'];
        $post = get_post($id);

        if (!$post || $post->post_type !== self::POST_TYPE) {
            return new WP_Error('not_found', 'Task not found', ['status' => 404]);
        }

        return rest_ensure_response([
            'success' => true,
            'data' => $this->prepare_item_for_response($post),
        ]);
    }

    public function create_item($request) {
        $data = $request->get_json_params();

        $post_id = wp_insert_post([
            'post_title'   => sanitize_text_field($data['title']),
            'post_content' => sanitize_textarea_field($data['description'] ?? ''),
            'post_type'    => self::POST_TYPE,
            'post_status'  => 'publish',
            'post_author'  => get_current_user_id(),
        ]);

        if (is_wp_error($post_id)) {
            return new WP_Error('create_failed', 'Failed to create task', ['status' => 500]);
        }

        // Set meta
        if (isset($data['priority'])) {
            update_post_meta($post_id, '_bitcore_priority', sanitize_text_field($data['priority']));
        }
        if (isset($data['assigned_to'])) {
            update_post_meta($post_id, '_bitcore_assigned_to', sanitize_text_field($data['assigned_to']));
        }
        if (isset($data['params'])) {
            $params = wp_unslash($data['params']);
            if ($this->is_valid_json($params)) {
                update_post_meta($post_id, '_bitcore_params', $params);
            }
        }

        // Set default status
        wp_set_object_terms($post_id, 'inbox', 'task_status');
        wp_set_object_terms($post_id, 'task', 'task_type');

        return rest_ensure_response([
            'success' => true,
            'data' => ['id' => $post_id],
        ]);
    }

    public function update_item($request) {
        $id = (int) $request['id'];
        $data = $request->get_json_params();

        $post = get_post($id);
        if (!$post || $post->post_type !== self::POST_TYPE) {
            return new WP_Error('not_found', 'Task not found', ['status' => 404]);
        }

        $update_data = ['ID' => $id];

        if (isset($data['title'])) {
            $update_data['post_title'] = sanitize_text_field($data['title']);
        }
        if (isset($data['description'])) {
            $update_data['post_content'] = sanitize_textarea_field($data['description']);
        }
        if (isset($data['status'])) {
            wp_set_object_terms($id, sanitize_text_field($data['status']), 'task_status');
        }
        if (isset($data['priority'])) {
            update_post_meta($id, '_bitcore_priority', sanitize_text_field($data['priority']));
        }
        if (isset($data['assigned_to'])) {
            update_post_meta($id, '_bitcore_assigned_to', sanitize_text_field($data['assigned_to']));
        }

        if (count($update_data) > 1) {
            wp_update_post($update_data);
        }

        return rest_ensure_response(['success' => true]);
    }

    public function delete_item($request) {
        $id = (int) $request['id'];

        $post = get_post($id);
        if (!$post || $post->post_type !== self::POST_TYPE) {
            return new WP_Error('not_found', 'Task not found', ['status' => 404]);
        }

        $result = wp_delete_post($id, true);

        if (!$result) {
            return new WP_Error('delete_failed', 'Failed to delete task', ['status' => 500]);
        }

        return rest_ensure_response(['success' => true]);
    }

    public function dispatch_task($request) {
        $id = (int) $request['id'];

        $post = get_post($id);
        if (!$post || $post->post_type !== self::POST_TYPE) {
            return new WP_Error('not_found', 'Task not found', ['status' => 404]);
        }

        // Set status to doing
        wp_set_object_terms($id, 'doing', 'task_status');

        // Trigger dispatch action
        do_action('bitcore_task_dispatch', $post);

        return rest_ensure_response([
            'success' => true,
            'message' => 'Task dispatched',
        ]);
    }

    /**
     * Prepare a post for response
     */
    private function prepare_item_for_response($post): array {
        $status_terms = wp_get_object_terms($post->ID, 'task_status', ['fields' => 'slugs']);
        $type_terms = wp_get_object_terms($post->ID, 'task_type', ['fields' => 'slugs']);
        $channel_terms = wp_get_object_terms($post->ID, 'bitcore_channel', ['fields' => 'slugs']);

        return [
            'id'          => $post->ID,
            'title'       => $post->post_title,
            'description' => $post->post_content,
            'status'      => !is_wp_error($status_terms) && !empty($status_terms) ? $status_terms[0] : 'inbox',
            'task_type'   => !is_wp_error($type_terms) && !empty($type_terms) ? $type_terms[0] : 'task',
            'channel'     => !is_wp_error($channel_terms) && !empty($channel_terms) ? $channel_terms[0] : 'personal',
            'priority'    => get_post_meta($post->ID, '_bitcore_priority', true) ?: 'normal',
            'assigned_to' => get_post_meta($post->ID, '_bitcore_assigned_to', true),
            'deadline'    => get_post_meta($post->ID, '_bitcore_deadline', true),
            'params'      => json_decode(get_post_meta($post->ID, '_bitcore_params', true) ?: '{}', true),
            'created_by'  => $post->post_author,
            'created_at'  => $post->post_date,
            'updated_at'  => $post->post_modified,
        ];
    }

    /**
     * Validate JSON
     */
    private function is_valid_json($string): bool {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}
