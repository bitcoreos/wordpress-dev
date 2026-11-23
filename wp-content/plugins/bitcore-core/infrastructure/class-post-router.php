<?php
declare(strict_types=1);

/**
 * ASHI Post Router
 * Routes posts based on type, category, and channel taxonomy.
 *
 * @package BITCORE
 * @since       1.0.0
 * @version     1.0.0
 */

class BITCORE_Post_Router {

    public function __construct() {
        add_action('save_post', [$this, 'route_post'], 10, 3);
        add_action('transition_post_status', [$this, 'on_status_change'], 10, 3);
    }

    /**
     * Route post on save.
     */
    public function route_post(int $post_id, WP_Post $post, bool $update): void {
        // Skip autosaves and revisions
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        $post_type = $post->post_type;
        $channel   = $this->get_channel($post_id);

        switch ($post_type) {
            case 'agent_task':
                $this->route_task($post_id, $post, $channel);
                break;

            case 'bitcore_prompt':
                $this->route_prompt($post_id, $post);
                break;

            case 'bitcore_artifact':
                $this->route_artifact($post_id, $post, $channel);
                break;

            case 'post':
                $this->route_social($post_id, $post, $channel);
                break;
        }
    }

    /**
     * Handle status transitions (kanban moves, publish, archive).
     */
    public function on_status_change(string $new_status, string $old_status, WP_Post $post): void {
        if ($post->post_type !== 'agent_task') {
            return;
        }

        // Kanban: inbox -> doing triggers ElizaOS dispatch
        if ($old_status === 'inbox' && $new_status === 'doing') {
            $this->dispatch_to_elizaos($post->ID, $post);
        }

        // Archive: schedule purge after 10 days
        if ($new_status === 'archived') {
            wp_schedule_single_event(time() + 864000, 'bitcore_purge_archived_task', ['task_id' => $post->ID]);
        }
    }

    /**
     * Route a task post.
     */
    private function route_task(int $post_id, WP_Post $post, string $channel): void {
        $assigned_to = get_post_meta($post_id, 'assigned_to', true);
        $status      = $this->get_task_status($post_id);

        // Default: inbox on creation
        if (empty($status)) {
            wp_set_object_terms($post_id, 'inbox', 'task_status');
        }

        // Dispatch to ElizaOS if moved to 'doing'
        if ($status === 'doing') {
            $this->dispatch_to_elizaos($post_id, $post);
        }

        // Webhook to n8n for transitions (if not private)
        if ($channel !== 'personal') {
            do_action('bitcore_task_webhook', $post_id, $post, $channel);
        }
    }

    /**
     * Route a prompt post.
     */
    private function route_prompt(int $post_id, WP_Post $post): void {
        // Inject into user's active agent context
        $user_id = $post->post_author;
        $active_agent = get_user_meta($user_id, 'bitcore_active_agent', true) ?: 'aria';

        update_user_meta($user_id, "bitcore_agent_context_{$active_agent}", [
            'prompt_id'   => $post_id,
            'content'     => $post->post_content,
            'title'       => $post->post_title,
            'created_at'  => $post->post_date,
        ]);
    }

    /**
     * Route an artifact post.
     */
    private function route_artifact(int $post_id, WP_Post $post, string $channel): void {
        if ($channel === 'personal') {
            return; // No federation
        }

        if ($channel === 'bithub') {
            do_action('bitcore_route_to_bithub', $post_id, $post);
        }

        if ($channel === 'fediverse') {
            // ActivityPub disabled until privacy stack complete
            // do_action('bitcore_route_to_activitypub', $post_id, $post);
        }

        if ($channel === 'bitwiki') {
            // Deferred
            // do_action('bitcore_route_to_bitwiki', $post_id, $post);
        }
    }

    /**
     * Route a standard social post.
     */
    private function route_social(int $post_id, WP_Post $post, string $channel): void {
        if ($channel === 'personal') {
            return; // No federation
        }

        if ($channel === 'bithub') {
            do_action('bitcore_route_to_bithub', $post_id, $post);
        }

        if ($channel === 'fediverse') {
            // ActivityPub disabled
        }

        if ($channel === 'bitwiki') {
            // Deferred
        }
    }

    /**
     * Dispatch task to ElizaOS via REST.
     */
    private function dispatch_to_elizaos(int $post_id, WP_Post $post): void {
        $payload = [
            'task_id'     => $post_id,
            'title'       => $post->post_title,
            'content'     => $post->post_content,
            'user_id'     => $post->post_author,
            'assigned_to' => get_post_meta($post_id, 'assigned_to', true),
            'task_type'   => $this->get_task_type($post_id),
        ];

        // Async dispatch via wp_remote_post to ElizaOS bridge
        wp_remote_post(rest_url('bitcore/v1/eliza/chat'), [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-WP-Nonce'   => wp_create_nonce('wp_rest'),
            ],
            'body'    => json_encode([
                'message' => sprintf('Task: %s\n%s', $post->post_title, $post->post_content),
                'session' => get_user_meta($post->post_author, 'bitcore_chat_session', true),
            ]),
            'timeout' => 5,
        ]);
    }

    /**
     * Get the channel taxonomy for a post.
     */
    private function get_channel(int $post_id): string {
        $terms = wp_get_object_terms($post_id, 'bitcore_channel', ['fields' => 'slugs']);
        if (is_wp_error($terms) || empty($terms)) {
            return 'personal';
        }
        return $terms[0];
    }

    /**
     * Get the task status taxonomy.
     */
    private function get_task_status(int $post_id): string {
        $terms = wp_get_object_terms($post_id, 'task_status', ['fields' => 'slugs']);
        if (is_wp_error($terms) || empty($terms)) {
            return '';
        }
        return $terms[0];
    }

    /**
     * Get the task type taxonomy.
     */
    private function get_task_type(int $post_id): string {
        $terms = wp_get_object_terms($post_id, 'task_type', ['fields' => 'slugs']);
        if (is_wp_error($terms) || empty($terms)) {
            return 'task';
        }
        return $terms[0];
    }
}

new BITCORE_Post_Router();
