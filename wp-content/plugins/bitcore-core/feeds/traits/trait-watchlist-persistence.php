<?php

declare(strict_types=1);

/**
 * BITCORE Timeline Feeds - Watchlist (trait)
 *
 * @package BITCORE\Feeds
 * @since 1.0.0
 * @version     1.0.0
 */

namespace BITCORE\Feeds;

trait Watchlist_Persistence
{

    /**
     * Load watchlist from database.
     *
     * @param int $id Watchlist ID.
     * @return bool Success.
     * @since 1.0.0
     */
    private function load(int $id): bool
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $id
        ));

        if (!$row) {
            return false;
        }

        $this->id = (int) $row->id;
        $this->name = $row->name;
        $this->keywords = json_decode($row->keywords, true) ?? [];
        $this->notification_settings = json_decode($row->notification_settings, true) ?? [];
        $this->is_active = (bool) $row->is_active;

        return true;
    }

    /**
     * Save watchlist to database.
     *
     * @return bool Success.
     * @since 1.0.0
     */
    private function save(): bool
    {
        if (!$this->id) {
            return false;
        }

        global $wpdb;

        $result = $wpdb->update(
            $this->table_name,
            [
                'keywords' => wp_json_encode($this->keywords),
                'notification_settings' => wp_json_encode($this->notification_settings),
                'is_active' => $this->is_active ? 1 : 0,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $this->id]
        );

        return $result !== false;
    }

    /**
     * Store match in database.
     *
     * @param array<string, mixed> $match Match data.
     * @param string $content Content.
     * @param array<string, mixed> $metadata Metadata.
     * @since 1.0.0
     */
    private function store_match(array $match, string $content, array $metadata): void
    {
        global $wpdb;

        $wpdb->insert($this->matches_table, [
            'watchlist_id' => $this->id,
            'keywords_matched' => wp_json_encode($match['keywords']),
            'content_preview' => substr($content, 0, 500),
            'source_url' => $metadata['url'] ?? '',
            'source_title' => $metadata['title'] ?? '',
            'source_type' => $metadata['source_type'] ?? 'feed',
            'matched_at' => current_time('mysql'),
        ]);
    }

    /**
     * Send email alert.
     *
     * @param array<string, mixed> $match Match data.
     * @param array<string, mixed> $content Content data.
     * @return bool Success.
     * @since 1.0.0
     */
    private function send_email_alert(array $match, array $content): bool
    {
        $user_id = get_current_user_id();
        $user = get_user_by('id', $user_id);

        if (!$user) {
            return false;
        }

        $subject = sprintf(
            '[%s] Watchlist Alert: %s',
            get_bloginfo('name'),
            $this->name
        );

        $message = sprintf(
            "Your watchlist '%s' has matched.\n\n" .
            "Matched keywords: %s\n\n" .
            "Content: %s\n\n" .
            "URL: %s\n",
            $this->name,
            implode(', ', $match['keywords']),
            $content['title'] ?? 'Untitled',
            $content['url'] ?? ''
        );

        return wp_mail($user->user_email, $subject, $message);
    }

    /**
     * Send webhook alert.
     *
     * @param array<string, mixed> $match Match data.
     * @param array<string, mixed> $content Content data.
     * @param string $webhook_url Webhook URL.
     * @return bool Success.
     * @since 1.0.0
     */
    private function send_webhook_alert(array $match, array $content, string $webhook_url): bool
    {
        $payload = [
            'watchlist_id' => $this->id,
            'watchlist_name' => $this->name,
            'matched_keywords' => $match['keywords'],
            'content' => $content,
            'timestamp' => current_time('mysql'),
            'site' => get_bloginfo('name'),
        ];

        $response = wp_remote_post($webhook_url, [
            'timeout' => 30,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode($payload),
        ]);

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }

    /**
     * Queue match for digest.
     *
     * @param array<string, mixed> $match Match data.
     * @param array<string, mixed> $content Content data.
     * @since 1.0.0
     */
    private function queue_for_digest(array $match, array $content): void
    {
        $digest_queue = get_transient('bitcore_watchlist_digest_' . $this->id);
        if (!is_array($digest_queue)) {
            $digest_queue = [];
        }

        $digest_queue[] = [
            'match' => $match,
            'content' => $content,
            'queued_at' => current_time('mysql'),
        ];

        set_transient('bitcore_watchlist_digest_' . $this->id, $digest_queue, DAY_IN_SECONDS);
    }

}
