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

trait Watchlist_Alerting
{

    /**
     * Trigger an alert for matches.
     *
     * @param array<string, mixed> $match Match data.
     * @param array<string, mixed> $content Content data.
     * @return bool|\WP_Error Success or WP_Error.
     * @since 1.0.0
     */
    public function trigger_alert(array $match, array $content)
    {
        if (!$match['matched']) {
            return false;
        }

        $notifications = $this->notification_settings;
        $sent = [];

        // Email notification
        if (!empty($notifications['email']) && $notifications['email'] !== false) {
            $email_result = $this->send_email_alert($match, $content);
            $sent['email'] = $email_result;
        }

        // Webhook notification
        if (!empty($notifications['webhook'])) {
            $webhook_result = $this->send_webhook_alert($match, $content, $notifications['webhook']);
            $sent['webhook'] = $webhook_result;
        }

        // Digest mode - store for batch sending
        if (($notifications['digest'] ?? 'instant') !== 'instant') {
            $this->queue_for_digest($match, $content);
        }

        return [
            'alert_sent' => !empty($sent),
            'channels' => $sent,
            'timestamp' => current_time('mysql'),
        ];
    }

    /**
     * Get all watchlists for a user.
     *
     * @param int|null $user_id User ID or current user.
     * @return array<int, array<string, mixed>> Watchlists.
     * @since 1.0.0
     */
    public static function get_user_watchlists(?int $user_id = null): array
    {
        global $wpdb;
        $user_id = $user_id ?? get_current_user_id();
        $table_name = $wpdb->prefix . 'bitcore_watchlists';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE user_id = %d ORDER BY created_at DESC",
            $user_id
        ));

        return array_map(function ($row) {
            return [
                'id' => (int) $row->id,
                'name' => $row->name,
                'keywords' => json_decode($row->keywords, true) ?? [],
                'notification_settings' => json_decode($row->notification_settings, true) ?? [],
                'is_active' => (bool) $row->is_active,
                'created_at' => $row->created_at,
            ];
        }, $results ?? []);
    }

    /**
     * Get recent matches for watchlist.
     *
     * @param int $limit Maximum matches to return.
     * @return array<int, array<string, mixed>> Recent matches.
     * @since 1.0.0
     */
    public function get_recent_matches(int $limit = 50): array
    {
        if (!$this->id) {
            return [];
        }

        global $wpdb;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->matches_table} WHERE watchlist_id = %d ORDER BY matched_at DESC LIMIT %d",
            $this->id,
            $limit
        ));

        return array_map(function ($row) {
            return [
                'id' => (int) $row->id,
                'keywords_matched' => json_decode($row->keywords_matched, true) ?? [],
                'content_preview' => $row->content_preview,
                'source_url' => $row->source_url,
                'source_title' => $row->source_title,
                'matched_at' => $row->matched_at,
            ];
        }, $results ?? []);
    }
}
