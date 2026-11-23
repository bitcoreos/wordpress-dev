<?php
declare(strict_types=1);

/**
 * ASHI My Posts REST API
 * Unified feed of user's tasks, prompts, artifacts, and social posts.
 *
 * @package BITCORE
 * @since       1.0.0
 * @version     1.0.0
 */

class BITCORE_Rest_My_Posts_API {

    private string $namespace = 'bitcore/v1';
    private string $base = 'my-posts';

    public function register_routes(): void {
        register_rest_route($this->namespace, "/{$this->base}", [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_posts'],
                'permission_callback' => [$this, 'check_permission'],
                'args'                => [
                    'type'      => ['type' => 'string', 'default' => ''],
                    'channel'   => ['type' => 'string', 'default' => ''],
                    'status'    => ['type' => 'string', 'default' => ''],
                    'limit'     => ['type' => 'integer', 'default' => 20],
                    'offset'    => ['type' => 'integer', 'default' => 0],
                ],
            ],
        ]);

        register_rest_route($this->namespace, "/{$this->base}/export", [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'export_data'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);

        register_rest_route($this->namespace, "/{$this->base}/delete", [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'delete_all_data'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);
    }

    public function check_permission(): bool {
        return is_user_logged_in();
    }

    public function get_posts(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $type = sanitize_text_field($request->get_param('type'));
        $channel = sanitize_text_field($request->get_param('channel'));
        $status = sanitize_text_field($request->get_param('status'));
        $limit = (int) $request->get_param('limit');
        $offset = (int) $request->get_param('offset');

        $post_types = empty($type) ? ['agent_task', 'bitcore_prompt', 'bitcore_artifact', 'post'] : [$type];

        $args = [
            'post_type'      => $post_types,
            'post_author'    => $user_id,
            'posts_per_page' => $limit,
            'offset'         => $offset,
            'post_status'    => 'any',
            'orderby'        => 'modified',
            'order'          => 'DESC',
        ];

        // Channel filter
        if ($channel) {
            $args['tax_query'][] = [
                'taxonomy' => 'bitcore_channel',
                'field'    => 'slug',
                'terms'    => $channel,
            ];
        }

        // Status filter for tasks
        if ($status && in_array('agent_task', $post_types, true)) {
            $args['tax_query'][] = [
                'taxonomy' => 'task_status',
                'field'    => 'slug',
                'terms'    => $status,
            ];
        }

        $posts = get_posts($args);
        $items = array_map([$this, 'format_post'], $posts);

        return new WP_REST_Response([
            'items'  => $items,
            'count'  => count($items),
            'total'  => (int) wp_count_posts('agent_task', 'readable')->publish,
        ], 200);
    }

    public function export_data(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $privacy = new BITCORE_Privacy();
        $data = $privacy->export_user_data($user_id);

        return new WP_REST_Response($data, 200);
    }

    public function delete_all_data(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $privacy = new BITCORE_Privacy();
        $privacy->delete_user_data($user_id);

        return new WP_REST_Response(['deleted' => true, 'user_id' => $user_id], 200);
    }

    private function format_post(WP_Post $post): array {
        $channel_terms = wp_get_object_terms($post->ID, 'bitcore_channel', ['fields' => 'slugs']);
        $channel = !is_wp_error($channel_terms) && !empty($channel_terms) ? $channel_terms[0] : 'personal';

        $item = [
            'id'         => $post->ID,
            'type'       => $post->post_type,
            'title'      => $post->post_title,
            'excerpt'    => wp_trim_words($post->post_content, 20),
            'channel'    => $channel,
            'status'     => $post->post_status,
            'created'    => $post->post_date,
            'modified'   => $post->post_modified,
        ];

        // Type-specific fields
        if ($post->post_type === 'agent_task') {
            $status_terms = wp_get_object_terms($post->ID, 'task_status', ['fields' => 'slugs']);
            $item['task_status'] = !is_wp_error($status_terms) && !empty($status_terms) ? $status_terms[0] : 'inbox';
            $item['assigned_to'] = get_post_meta($post->ID, 'assigned_to', true);
            $item['priority'] = get_post_meta($post->ID, 'priority', true) ?: 'medium';
        }

        if ($post->post_type === 'bitcore_artifact') {
            $status_terms = wp_get_object_terms($post->ID, 'artifact_status', ['fields' => 'slugs']);
            $item['artifact_status'] = !is_wp_error($status_terms) && !empty($status_terms) ? $status_terms[0] : 'draft';
        }

        return $item;
    }
}
