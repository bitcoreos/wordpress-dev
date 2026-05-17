<?php
declare(strict_types=1);

/**
 * ASHI Privacy Enforcer
 * Per-user isolation, export, delete, cron cleanup.
 *
 * @package BITCORE
 */

class BITCORE_Privacy {

    public function __construct() {
        add_filter('map_meta_cap', [$this, 'enforce_caps'], 10, 4);
        add_action('bitcore_purge_archived_task', [$this, 'purge_archived_task']);
        add_action('bitcore_cleanup_sessions', [$this, 'cleanup_sessions']);
        add_action('bitcore_cleanup_transcriptions', [$this, 'cleanup_transcriptions']);
    }

    /**
     * Enforce per-user isolation for private post types.
     */
    public function enforce_caps(array $caps, string $cap, int $user_id, array $args): array {
        $private_types = ['agent_task', 'bitcore_prompt', 'bitcore_artifact'];

        // Only intercept read caps for private post types
        if (!in_array($cap, ['read_post', 'edit_post', 'delete_post'], true)) {
            return $caps;
        }

        $post_id = $args[0] ?? 0;
        if (!$post_id) {
            return $caps;
        }

        $post = get_post($post_id);
        if (!$post || !in_array($post->post_type, $private_types, true)) {
            return $caps;
        }

        // Admin can do anything
        if (user_can($user_id, 'manage_options')) {
            return $caps;
        }

        // User can only read/edit/delete their own posts
        if ($post->post_author != $user_id) {
            $caps[] = 'do_not_allow';
        }

        return $caps;
    }

    /**
     * Export all user data (tasks, prompts, artifacts) as JSON.
     */
    public function export_user_data(int $user_id): array {
        $data = [
            'user_id'   => $user_id,
            'exported_at' => gmdate('c'),
            'tasks'     => [],
            'prompts'   => [],
            'artifacts' => [],
        ];

        $post_types = [
            'agent_task'     => 'tasks',
            'bitcore_prompt'   => 'prompts',
            'bitcore_artifact' => 'artifacts',
        ];

        foreach ($post_types as $post_type => $key) {
            $posts = get_posts([
                'post_type'      => $post_type,
                'post_author'    => $user_id,
                'posts_per_page' => -1,
                'post_status'    => 'any',
            ]);

            foreach ($posts as $post) {
                $data[$key][] = [
                    'id'        => $post->ID,
                    'title'     => $post->post_title,
                    'content'   => $post->post_content,
                    'status'    => $post->post_status,
                    'created'   => $post->post_date,
                    'modified'  => $post->post_modified,
                    'meta'      => get_post_meta($post->ID),
                ];
            }
        }

        return $data;
    }

    /**
     * GDPR-style purge: delete all user posts and metadata.
     */
    public function delete_user_data(int $user_id): void {
        $post_types = ['agent_task', 'bitcore_prompt', 'bitcore_artifact'];

        foreach ($post_types as $post_type) {
            $posts = get_posts([
                'post_type'      => $post_type,
                'post_author'    => $user_id,
                'posts_per_page' => -1,
                'post_status'    => 'any',
                'fields'         => 'ids',
            ]);

            foreach ($posts as $post_id) {
                wp_delete_post($post_id, true); // force delete
            }
        }

        // Clean user meta
        delete_user_meta($user_id, 'bitcore_active_agent');
        delete_user_meta($user_id, 'bitcore_chat_session');

        $agents = ['aria', 'forge', 'muse'];
        foreach ($agents as $agent) {
            delete_user_meta($user_id, "bitcore_agent_context_{$agent}");
        }
    }

    /**
     * Purge a single archived task (called by cron).
     */
    public function purge_archived_task(array $args): void {
        $task_id = $args['task_id'] ?? 0;
        if ($task_id) {
            wp_delete_post($task_id, true);
        }
    }

    /**
     * Cleanup old sessions (called by cron).
     */
    public function cleanup_sessions(): void {
        global $wpdb;
        $ttl = apply_filters('bitcore_session_ttl', 24 * HOUR_IN_SECONDS);
        $cutoff = gmdate('Y-m-d H:i:s', time() - $ttl);

        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}bitcore_sessions WHERE updated_at < %s",
            $cutoff
        ));
    }

    /**
     * Cleanup old transcription logs (called by cron).
     */
    public function cleanup_transcriptions(): void {
        $ttl = apply_filters('bitcore_transcription_ttl', 7 * DAY_IN_SECONDS);
        $cutoff = time() - $ttl;

        $upload_dir = wp_upload_dir();
        $transcript_dir = $upload_dir['basedir'] . '/bitcore/transcriptions';
        if (!is_dir($transcript_dir)) {
            return;
        }

        $files = glob($transcript_dir . '/*.json');
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
            }
        }
    }
}

new BITCORE_Privacy();
