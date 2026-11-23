<?php

declare(strict_types=1);

/**
 * BITCORE Timeline Feeds - Watchlist
 *
 * @package BITCORE\Feeds
 * @since 1.0.0
 * @version     1.0.0
 */

namespace BITCORE\Feeds;

/**
 * Watchlist
 *
 * Keyword and topic monitoring with notifications.
 *
 * @since 1.0.0
 */
class Watchlist
{
    /** @var int Watchlist ID */
    private int $id;

    /** @var string Watchlist name */
    private string $name;

    /** @var array<int, string> Keywords to monitor */
    private array $keywords;

    /** @var array<string, mixed> Notification settings */
    private array $notification_settings;

    /** @var bool Active status */
    private bool $is_active;

    /** @var string Database table name */
    private string $table_name;

    /** @var string Matches table name */
    private string $matches_table;

    /**
     * Constructor.
     *
     * @param int|null $id Optional watchlist ID to load.
     * @since 1.0.0
     */
    public function __construct(?int $id = null)
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'bitcore_watchlists';
        $this->matches_table = $wpdb->prefix . 'bitcore_watchlist_matches';
        $this->keywords = [];
        $this->notification_settings = [
            'email' => false,
            'webhook' => null,
            'digest' => 'instant',
        ];
        $this->is_active = true;

        if ($id !== null) {
            $this->load($id);
        }
    }

    /**
     * Create a new watchlist.
     *
     * @param string $name Watchlist name.
     * @param array<string, mixed> $options Watchlist options.
     * @return int|\WP_Error Watchlist ID or WP_Error.
     * @since 1.0.0
     */
    public function create_watchlist(string $name, array $options = [])
    {
        if (empty($name)) {
            return new \WP_Error('empty_name', __('Watchlist name cannot be empty.', 'bitcore'));
        }

        global $wpdb;

        $data = [
            'name' => $name,
            'keywords' => wp_json_encode($options['keywords'] ?? []),
            'notification_settings' => wp_json_encode($options['notifications'] ?? $this->notification_settings),
            'is_active' => ($options['is_active'] ?? true) ? 1 : 0,
            'user_id' => get_current_user_id(),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ];

        $result = $wpdb->insert($this->table_name, $data);

        if ($result === false) {
            return new \WP_Error('db_error', __('Failed to create watchlist.', 'bitcore'));
        }

        $this->id = $wpdb->insert_id;
        $this->name = $name;
        $this->keywords = $options['keywords'] ?? [];
        $this->notification_settings = $options['notifications'] ?? $this->notification_settings;
        $this->is_active = $options['is_active'] ?? true;

        return $this->id;
    }

    /**
     * Add a keyword to the watchlist.
     *
     * @param string $keyword Keyword to add.
     * @param array<string, mixed> $options Keyword options.
     * @return bool|\WP_Error Success or WP_Error.
     * @since 1.0.0
     */
    public function add_keyword(string $keyword, array $options = [])
    {
        if (empty($keyword)) {
            return new \WP_Error('empty_keyword', __('Keyword cannot be empty.', 'bitcore'));
        }

        if (!$this->id) {
            return new \WP_Error('no_watchlist', __('No watchlist loaded.', 'bitcore'));
        }

        $keyword = strtolower(trim($keyword));

        if (in_array($keyword, $this->keywords)) {
            return new \WP_Error('duplicate_keyword', __('Keyword already exists in watchlist.', 'bitcore'));
        }

        $this->keywords[] = $keyword;

        return $this->save();
    }

    /**
     * Remove a keyword from the watchlist.
     *
     * @param string $keyword Keyword to remove.
     * @return bool Success.
     * @since 1.0.0
     */
    public function remove_keyword(string $keyword): bool
    {
        $keyword = strtolower(trim($keyword));
        $this->keywords = array_filter($this->keywords, fn($k) => $k !== $keyword);
        return $this->save();
    }

    /**
     * Check if content matches watchlist keywords.
     *
     * @param string $content Content to check.
     * @param array<string, mixed> $metadata Content metadata.
     * @return array<string, mixed> Match results.
     * @since 1.0.0
     */
    public function check_match(string $content, array $metadata = []): array
    {
        if (empty($this->keywords) || !$this->is_active) {
            return [
                'matched' => false,
                'keywords' => [],
                'watchlist_id' => $this->id,
            ];
        }

        $content_lower = strtolower($content);
        $matched_keywords = [];

        foreach ($this->keywords as $keyword) {
            if (strpos($content_lower, $keyword) !== false) {
                $matched_keywords[] = $keyword;
            }
        }

        $result = [
            'matched' => !empty($matched_keywords),
            'keywords' => $matched_keywords,
            'watchlist_id' => $this->id,
            'watchlist_name' => $this->name,
            'match_count' => count($matched_keywords),
        ];

        // Store match in database
        if ($result['matched']) {
            $this->store_match($result, $content, $metadata);
        }

        return $result;
    }

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

    /**
     * Delete watchlist.
     *
     * @return bool|\WP_Error Success or WP_Error.
     * @since 1.0.0
     */
    public function delete()
    {
        if (!$this->id) {
            return new \WP_Error('no_watchlist', __('No watchlist loaded.', 'bitcore'));
        }

        global $wpdb;

        // Delete matches
        $wpdb->delete($this->matches_table, ['watchlist_id' => $this->id]);

        // Delete watchlist
        $result = $wpdb->delete($this->table_name, ['id' => $this->id]);

        if ($result === false) {
            return new \WP_Error('db_error', __('Failed to delete watchlist.', 'bitcore'));
        }

        $this->id = 0;
        return true;
    }

    /**
     * Get watchlist ID.
     *
     * @return int|null Watchlist ID.
     * @since 1.0.0
     */
    public function get_id(): ?int
    {
        return $this->id ?? null;
    }

    /**
     * Get watchlist name.
     *
     * @return string Name.
     * @since 1.0.0
     */
    public function get_name(): string
    {
        return $this->name;
    }

    /**
     * Get keywords.
     *
     * @return array<int, string> Keywords.
     * @since 1.0.0
     */
    public function get_keywords(): array
    {
        return $this->keywords;
    }

    /**
     * Set active status.
     *
     * @param bool $active Active status.
     * @since 1.0.0
     */
    public function set_active(bool $active): void
    {
        $this->is_active = $active;
        $this->save();
    }
}
