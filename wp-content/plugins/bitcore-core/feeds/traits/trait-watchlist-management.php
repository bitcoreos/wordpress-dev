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

trait Watchlist_Management
{
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
}
