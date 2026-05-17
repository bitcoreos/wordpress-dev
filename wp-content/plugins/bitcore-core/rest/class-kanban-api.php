<?php
declare(strict_types=1);

/**
 * ASHI Kanban REST API
 * CRUD for tasks with kanban status moves.
 *
 * @package BITCORE
 */

class BITCORE_Rest_Kanban_API {

    private string $namespace = 'bitcore/v1';
    private string $base = 'kanban';

    public function register_routes(): void {
        // List tasks with filters
        register_rest_route($this->namespace, "/{$this->base}", [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_tasks'],
                'permission_callback' => [$this, 'check_permission'],
                'args'                => [
                    'status'      => ['type' => 'string', 'default' => ''],
                    'assigned_to' => ['type' => 'string', 'default' => ''],
                    'priority'    => ['type' => 'string', 'default' => ''],
                    'task_type'   => ['type' => 'string', 'default' => ''],
                ],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'create_task'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);

        // Single task operations
        register_rest_route($this->namespace, "/{$this->base}/(?P<id>\d+)", [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_task'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'update_task'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'delete_task'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);

        // Move task status (kanban column change)
        register_rest_route($this->namespace, "/{$this->base}/(?P<id>\d+)/move", [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'move_task'],
                'permission_callback' => [$this, 'check_permission'],
                'args'                => [
                    'status' => ['type' => 'string', 'required' => true],
                ],
            ],
        ]);
    }

    public function check_permission(): bool {
        return is_user_logged_in();
    }

    public function get_tasks(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $args = [
            'post_type'      => 'agent_task',
            'post_author'    => $user_id,
            'posts_per_page' => -1,
            'post_status'    => 'any',
        ];

        $status = $request->get_param('status');
        if ($status) {
            $args['tax_query'][] = [
                'taxonomy' => 'task_status',
                'field'    => 'slug',
                'terms'    => $status,
            ];
        }

        $task_type = $request->get_param('task_type');
        if ($task_type) {
            $args['tax_query'][] = [
                'taxonomy' => 'task_type',
                'field'    => 'slug',
                'terms'    => $task_type,
            ];
        }

        $posts = get_posts($args);
        $tasks = array_map([$this, 'format_task'], $posts);

        return new WP_REST_Response(['tasks' => $tasks, 'count' => count($tasks)], 200);
    }

    public function get_task(WP_REST_Request $request): WP_REST_Response {
        $task_id = (int) $request['id'];
        $post = get_post($task_id);

        if (!$post || $post->post_type !== 'agent_task') {
            return new WP_REST_Response(['error' => 'Task not found'], 404);
        }

        return new WP_REST_Response($this->format_task($post), 200);
    }

    public function create_task(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $title = sanitize_text_field($request->get_param('title') ?? 'New Task');
        $content = sanitize_textarea_field($request->get_param('content') ?? '');
        $assigned_to = sanitize_text_field($request->get_param('assigned_to') ?? '');
        $priority = sanitize_text_field($request->get_param('priority') ?? 'medium');
        $task_type = sanitize_text_field($request->get_param('task_type') ?? 'task');

        $task_id = wp_insert_post([
            'post_title'   => $title,
            'post_content' => $content,
            'post_type'    => 'agent_task',
            'post_author'  => $user_id,
            'post_status'  => 'publish',
        ]);

        if (is_wp_error($task_id)) {
            return new WP_REST_Response(['error' => $task_id->get_error_message()], 500);
        }

        update_post_meta($task_id, 'assigned_to', $assigned_to);
        update_post_meta($task_id, 'priority', $priority);
        wp_set_object_terms($task_id, 'inbox', 'task_status');
        wp_set_object_terms($task_id, $task_type, 'task_type');

        return new WP_REST_Response($this->format_task(get_post($task_id)), 201);
    }

    public function update_task(WP_REST_Request $request): WP_REST_Response {
        $task_id = (int) $request['id'];
        $post = get_post($task_id);

        if (!$post || $post->post_type !== 'agent_task') {
            return new WP_REST_Response(['error' => 'Task not found'], 404);
        }

        $data = [];
        if ($request->has_param('title')) {
            $data['post_title'] = sanitize_text_field($request->get_param('title'));
        }
        if ($request->has_param('content')) {
            $data['post_content'] = sanitize_textarea_field($request->get_param('content'));
        }
        if (!empty($data)) {
            $data['ID'] = $task_id;
            wp_update_post($data);
        }

        if ($request->has_param('assigned_to')) {
            update_post_meta($task_id, 'assigned_to', sanitize_text_field($request->get_param('assigned_to')));
        }
        if ($request->has_param('priority')) {
            update_post_meta($task_id, 'priority', sanitize_text_field($request->get_param('priority')));
        }

        return new WP_REST_Response($this->format_task(get_post($task_id)), 200);
    }

    public function delete_task(WP_REST_Request $request): WP_REST_Response {
        $task_id = (int) $request['id'];
        $result = wp_delete_post($task_id, true);

        if (!$result) {
            return new WP_REST_Response(['error' => 'Failed to delete task'], 500);
        }

        return new WP_REST_Response(['deleted' => true], 200);
    }

    public function move_task(WP_REST_Request $request): WP_REST_Response {
        $task_id = (int) $request['id'];
        $new_status = sanitize_text_field($request->get_param('status'));
        $valid_statuses = ['inbox', 'backlog', 'doing', 'review', 'done', 'archived'];

        if (!in_array($new_status, $valid_statuses, true)) {
            return new WP_REST_Response(['error' => 'Invalid status'], 400);
        }

        wp_set_object_terms($task_id, $new_status, 'task_status');

        // If moved to doing, trigger ElizaOS dispatch
        if ($new_status === 'doing') {
            do_action('bitcore_task_doing', $task_id);
        }

        // If archived, schedule purge
        if ($new_status === 'archived') {
            wp_schedule_single_event(time() + 864000, 'bitcore_purge_archived_task', ['task_id' => $task_id]);
        }

        return new WP_REST_Response($this->format_task(get_post($task_id)), 200);
    }

    private function format_task(WP_Post $post): array {
        $status_terms = wp_get_object_terms($post->ID, 'task_status', ['fields' => 'slugs']);
        $type_terms = wp_get_object_terms($post->ID, 'task_type', ['fields' => 'slugs']);

        return [
            'id'          => $post->ID,
            'title'       => $post->post_title,
            'content'     => $post->post_content,
            'status'      => !is_wp_error($status_terms) && !empty($status_terms) ? $status_terms[0] : 'inbox',
            'task_type'   => !is_wp_error($type_terms) && !empty($type_terms) ? $type_terms[0] : 'task',
            'assigned_to' => get_post_meta($post->ID, 'assigned_to', true),
            'priority'    => get_post_meta($post->ID, 'priority', true) ?: 'medium',
            'due_date'    => get_post_meta($post->ID, 'due_date', true),
            'created'     => $post->post_date,
            'modified'    => $post->post_modified,
        ];
    }
}
