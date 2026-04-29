<?php
/**
 * Missions REST API Controller
 *
 * REST endpoints for mission CPT management.
 *
 * @package BITCORE\Rest
 */

namespace BITCORE\Rest;

if (!defined('ABSPATH')) exit;

/**
 * Class Missions_Api
 */
class Missions_Api {
    private string $namespace = 'bitcore/v1';
    private string $post_type = 'mission';
    private string $tax_status = 'mission_status';

    public function __construct() {
        $this->register_routes();
    }


    public function register_routes(): void {
        register_rest_route($this->namespace, '/missions', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_missions'],
                'permission_callback' => '__return_true'
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_mission'],
                'permission_callback' => [$this, 'check_permission']
            ]
        ]);

        register_rest_route($this->namespace, '/missions/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_mission'],
                'permission_callback' => '__return_true'
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'update_mission'],
                'permission_callback' => [$this, 'check_permission']
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_mission'],
                'permission_callback' => [$this, 'check_permission']
            ]
        ]);

        register_rest_route($this->namespace, '/missions/statuses', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_statuses'],
                'permission_callback' => '__return_true'
            ]
        ]);
    }

    public function get_missions(\WP_REST_Request $request) {
        $status_filter = $request->get_param('status');
        $search = $request->get_param('search');

        $args = [
            'post_type' => $this->post_type,
            'posts_per_page' => 50,
            'post_status' => 'publish',
        ];

        if ($status_filter) {
            $args['tax_query'] = [[
                'taxonomy' => $this->tax_status,
                'field' => 'slug',
                'terms' => $status_filter,
            ]];
        }

        if ($search) {
            $args['s'] = sanitize_text_field($search);
        }

        $query = new \WP_Query($args);
        $missions = [];

        foreach ($query->posts as $post) {
            $missions[] = $this->format_mission($post);
        }

        return rest_ensure_response(['missions' => $missions]);
    }

    public function get_mission(\WP_REST_Request $request) {
        $post = get_post($request->get_param('id'));
        if (!$post || $post->post_type !== $this->post_type) {
            return new \WP_Error('not_found', 'Mission not found', ['status' => 404]);
        }
        return rest_ensure_response($this->format_mission($post));
    }

    public function create_mission(\WP_REST_Request $request) {
        $title = sanitize_text_field($request->get_param('title'));
        if (empty($title)) {
            return new \WP_Error('missing_title', 'Title is required', ['status' => 400]);
        }

        $post_data = [
            'post_type' => $this->post_type,
            'post_title' => $title,
            'post_content' => wp_kses_post($request->get_param('content') ?? ''),
            'post_status' => 'publish',
        ];

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // Set mission status term
        $status = sanitize_text_field($request->get_param('status') ?? 'proposed');
        wp_set_object_terms($post_id, $status, $this->tax_status);

        // Set meta if provided
        $priority = $request->get_param('priority');
        if ($priority !== null) {
            update_post_meta($post_id, '_mission_priority', intval($priority));
        }
        $due_date = $request->get_param('due_date');
        if ($due_date) {
            update_post_meta($post_id, '_mission_due_date', sanitize_text_field($due_date));
        }

        $post = get_post($post_id);
        return rest_ensure_response($this->format_mission($post));
    }

    public function update_mission(\WP_REST_Request $request) {
        $post_id = $request->get_param('id');
        $post = get_post($post_id);
        if (!$post || $post->post_type !== $this->post_type) {
            return new \WP_Error('not_found', 'Mission not found', ['status' => 404]);
        }

        $update_data = [];

        if ($request->get_param('title')) {
            $update_data['post_title'] = sanitize_text_field($request->get_param('title'));
        }
        if ($request->get_param('content') !== null) {
            $update_data['post_content'] = wp_kses_post($request->get_param('content'));
        }

        if (!empty($update_data)) {
            $update_data['ID'] = $post_id;
            wp_update_post($update_data);
        }

        // Update status term
        if ($request->get_param('status')) {
            wp_set_object_terms($post_id, sanitize_text_field($request->get_param('status')), $this->tax_status);
        }

        // Update meta
        if ($request->get_param('priority') !== null) {
            update_post_meta($post_id, '_mission_priority', intval($request->get_param('priority')));
        }
        if ($request->get_param('due_date') !== null) {
            update_post_meta($post_id, '_mission_due_date', sanitize_text_field($request->get_param('due_date')));
        }

        $post = get_post($post_id);
        return rest_ensure_response($this->format_mission($post));
    }

    public function delete_mission(\WP_REST_Request $request) {
        $post_id = $request->get_param('id');
        $post = get_post($post_id);
        if (!$post || $post->post_type !== $this->post_type) {
            return new \WP_Error('not_found', 'Mission not found', ['status' => 404]);
        }

        $result = wp_delete_post($post_id, true);
        return rest_ensure_response(['success' => $result !== false, 'id' => $post_id]);
    }

    public function get_statuses(\WP_REST_Request $request) {
        $terms = get_terms([
            'taxonomy' => $this->tax_status,
            'hide_empty' => false,
        ]);

        if (is_wp_error($terms)) {
            $terms = [];
        }

        $statuses = array_map(function($term) {
            return ['slug' => $term->slug, 'name' => $term->name, 'count' => $term->count];
        }, $terms);

        return rest_ensure_response(['statuses' => $statuses]);
    }

    private function format_mission(\WP_Post $post): array {
        $terms = wp_get_object_terms($post->ID, $this->tax_status);
        $status = 'proposed';
        if (!is_wp_error($terms) && !empty($terms)) {
            $status = $terms[0]->slug;
        }

        return [
            'id' => $post->ID,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'status' => $status,
            'priority' => get_post_meta($post->ID, '_mission_priority', true) ?: 5,
            'due_date' => get_post_meta($post->ID, '_mission_due_date', true),
            'author' => get_the_author_meta('display_name', $post->post_author),
            'date' => $post->post_date,
            'modified' => $post->post_modified,
        ];
    }

    public function check_permission(): bool {
        return current_user_can('edit_posts');
    }
}
