<?php

declare(strict_types=1);

/**
 * BITCORE Timeline Feeds - Source Aggregator
 *
 * @package BITCORE\Feeds
 * @since 1.0.0
 */

namespace BITCORE\Feeds;

use WP_Error;

/**
 * Source Aggregator
 *
 * Aggregates content from BITwiki, BIThub, and external sources.
 *
 * @since 1.0.0
 */
class Source_Aggregator
{
    /** @var string BITwiki API URL */
    private string $bitwiki_url;

    /** @var string BIThub API URL */
    private string $bithub_url;

    /** @var int Cache TTL in seconds */
    private const CACHE_TTL = 300;

    /**
     * Constructor.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->bitwiki_url = get_option('bitcore_bitwiki_url', 'https://bitwiki.org/api.php');
        $this->bithub_url = get_option('bitcore_bithub_url', 'https://bithub.bitcoreos.org');
    }

    /**
     * Aggregate from all sources.
     *
     * @param array<string, mixed> $options Aggregation options.
     * @return array<int, array<string, mixed>> Aggregated items.
     * @since 1.0.0
     */
    public function aggregate(array $options = []): array
    {
        $sources = $options['sources'] ?? ['bitwiki', 'bithub'];
        $limit = $options['limit'] ?? 50;
        $items = [];

        foreach ($sources as $source) {
            $method = "fetch_{$source}";
            if (method_exists($this, $method)) {
                $source_items = $this->$method($options);
                if (!is_wp_error($source_items)) {
                    $items = array_merge($items, $source_items);
                }
            }
        }

        // Sort by date
        usort($items, fn($a, $b) => strtotime($b['created_at'] ?? 'now') <=> strtotime($a['created_at'] ?? 'now'));

        return array_slice($items, 0, $limit);
    }

    /**
     * Fetch from BITwiki.
     *
     * @param array<string, mixed> $options Fetch options.
     * @return array<int, array<string, mixed>>|WP_Error Items or error.
     * @since 1.0.0
     */
    public function fetch_bitwiki(array $options = []): array|WP_Error
    {
        $cache_key = 'bitcore_feed_bitwiki_' . md5(json_encode($options));
        $cached = wp_cache_get($cache_key, 'bitcore');

        if (false !== $cached) {
            return $cached;
        }

        $limit = $options['bitwiki_limit'] ?? 20;

        // Query BITwiki API
        $params = [
            'action' => 'query',
            'list' => 'recentchanges',
            'rclimit' => $limit,
            'rcprop' => 'title|timestamp|user|comment|sizes',
            'format' => 'json',
        ];

        $response = wp_remote_get(add_query_arg($params, $this->bitwiki_url), [
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $changes = $body['query']['recentchanges'] ?? [];

        $items = [];
        foreach ($changes as $change) {
            $items[] = [
                'id' => 'bitwiki_' . ($change['rcid'] ?? uniqid()),
                'source' => 'bitwiki',
                'type' => 'wiki_change',
                'title' => $change['title'] ?? 'Untitled',
                'content' => $change['comment'] ?? '',
                'author' => $change['user'] ?? 'Unknown',
                'url' => $this->build_bitwiki_url($change['title'] ?? ''),
                'created_at' => $change['timestamp'] ?? current_time('mysql', true),
                'tags' => $this->extract_tags($change['title'] ?? ''),
            ];
        }

        wp_cache_set($cache_key, $items, 'bitcore', self::CACHE_TTL);

        return $items;
    }

    /**
     * Fetch from BIThub (Discourse).
     *
     * @param array<string, mixed> $options Fetch options.
     * @return array<int, array<string, mixed>>|WP_Error Items or error.
     * @since 1.0.0
     */
    public function fetch_bithub(array $options = []): array|WP_Error
    {
        $cache_key = 'bitcore_feed_bithub_' . md5(json_encode($options));
        $cached = wp_cache_get($cache_key, 'bitcore');

        if (false !== $cached) {
            return $cached;
        }

        $limit = $options['bithub_limit'] ?? 20;
        $category = $options['bithub_category'] ?? '';

        // Build endpoint
        $endpoint = rtrim($this->bithub_url, '/') . '/latest.json';
        if ($category) {
            $endpoint = rtrim($this->bithub_url, '/') . "/c/{$category}/latest.json";
        }

        $response = wp_remote_get($endpoint, [
            'timeout' => 15,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $topics = $body['topic_list']['topics'] ?? [];
        $users = [];

        // Build user lookup
        foreach ($body['users'] ?? [] as $user) {
            $users[$user['id']] = $user['username'] ?? 'Unknown';
        }

        $items = [];
        foreach ($topics as $topic) {
            $items[] = [
                'id' => 'bithub_' . ($topic['id'] ?? uniqid()),
                'source' => 'bithub',
                'type' => 'topic',
                'title' => $topic['title'] ?? 'Untitled',
                'content' => '', // Would need separate API call for content
                'excerpt' => $topic['excerpt'] ?? '',
                'author' => $users[$topic['posters'][0]['user_id'] ?? 0] ?? 'Unknown',
                'url' => rtrim($this->bithub_url, '/') . '/t/' . ($topic['slug'] ?? $topic['id']),
                'created_at' => date('Y-m-d H:i:s', $topic['created_at'] ?? time()),
                'tags' => $topic['tags'] ?? [],
                'views' => $topic['views'] ?? 0,
                'reply_count' => $topic['reply_count'] ?? 0,
            ];
        }

        wp_cache_set($cache_key, $items, 'bitcore', self::CACHE_TTL);

        return $items;
    }

    /**
     * Fetch from external RSS feed.
     *
     * @param string $feed_url RSS feed URL.
     * @param array<string, mixed> $options Fetch options.
     * @return array<int, array<string, mixed>>|WP_Error Items or error.
     * @since 1.0.0
     */
    public function fetch_rss(string $feed_url, array $options = []): array|WP_Error
    {
        $cache_key = 'bitcore_feed_rss_' . md5($feed_url);
        $cached = wp_cache_get($cache_key, 'bitcore');

        if (false !== $cached) {
            return $cached;
        }

        $response = wp_remote_get($feed_url, ['timeout' => 15]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $simplepie = simplexml_load_string($body);

        if (!$simplepie) {
            return new WP_Error('rss_parse_error', 'Failed to parse RSS feed.', ['status' => 500]);
        }

        $items = [];
        $entries = $simplepie->channel->item ?? $simplepie->entry ?? [];
        $limit = $options['limit'] ?? 10;

        foreach ($entries as $i => $entry) {
            if ($i >= $limit) break;

            $items[] = [
                'id' => 'rss_' . md5((string) $entry->link),
                'source' => 'external',
                'type' => 'article',
                'title' => (string) ($entry->title ?? 'Untitled'),
                'content' => (string) ($entry->description ?? $entry->summary ?? ''),
                'author' => (string) ($entry->author ?? $entry->dc->creator ?? 'Unknown'),
                'url' => (string) ($entry->link ?? ''),
                'created_at' => (string) ($entry->pubDate ?? $entry->published ?? 'now'),
                'tags' => [],
            ];
        }

        wp_cache_set($cache_key, $items, 'bitcore', self::CACHE_TTL);

        return $items;
    }

    /**
     * Build BITwiki URL from title.
     *
     * @param string $title Page title.
     * @return string Full URL.
     * @since 1.0.0
     */
    private function build_bitwiki_url(string $title): string
    {
        $base = preg_replace('/\/api\.php$/', '', $this->bitwiki_url);
        return $base . '/wiki/' . urlencode(str_replace(' ', '_', $title));
    }

    /**
     * Extract tags from title (category detection).
     *
     * @param string $title Page title.
     * @return array<int, string> Extracted tags.
     * @since 1.0.0
     */
    private function extract_tags(string $title): array
    {
        $tags = [];

        // Detect common prefixes
        $prefixes = ['BITCORE:', 'Help:', 'Template:', 'Category:'];
        foreach ($prefixes as $prefix) {
            if (str_starts_with($title, $prefix)) {
                $tags[] = strtolower(rtrim($prefix, ':'));
                break;
            }
        }

        return $tags;
    }

    /**
     * Get available sources.
     *
     * @return array<string, string> Source names and descriptions.
     * @since 1.0.0
     */
    public function get_sources(): array
    {
        return [
            'bitwiki' => 'BITwiki recent changes and pages',
            'bithub' => 'BIThub community discussions',
            'external' => 'External RSS/Atom feeds',
        ];
    }

    /**
     * Clear all feed caches.
     *
     * @since 1.0.0
     */
    public function clear_cache(): void
    {
        wp_cache_flush_group('bitcore');
    }
}
