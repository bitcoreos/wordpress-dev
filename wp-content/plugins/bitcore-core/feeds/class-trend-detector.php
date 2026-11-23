<?php

declare(strict_types=1);

/**
 * BITCORE Timeline Feeds - Trend Detector
 *
 * @package BITCORE\Feeds
 * @since 1.0.0
 * @version     1.0.0
 */

namespace BITCORE\Feeds;

/**
 * Trend Detector
 *
 * Identifies trending topics and content velocity.
 *
 * @since 1.0.0
 */
class Trend_Detector
{
    /** @var int Time window for trend detection in hours */
    private int $time_window;

    /** @var int Minimum occurrences to consider a trend */
    private int $min_occurrences;

    /** @var float Velocity threshold for trending */
    private float $velocity_threshold;

    /** @var string Cache key prefix */
    private string $cache_prefix;

    /**
     * Constructor.
     *
     * @param int $time_window Time window in hours.
     * @since 1.0.0
     */
    public function __construct(int $time_window = 24)
    {
        $this->time_window = $time_window;
        $this->min_occurrences = 3;
        $this->velocity_threshold = 2.0;
        $this->cache_prefix = 'bitcore_trends_';
    }

    /**
     * Detect trending topics from content.
     *
     * @param array<int, array<string, mixed>> $items Content items.
     * @param array<string, mixed> $options Detection options.
     * @return array<string, mixed> Trending topics with scores.
     * @since 1.0.0
     */
    public function detect_trends(array $items, array $options = []): array
    {
        if (empty($items)) {
            return [
                'trending' => [],
                'stable' => [],
                'declining' => [],
                'metadata' => [
                    'total_items' => 0,
                    'time_window' => $this->time_window,
                ],
            ];
        }

        $time_field = $options['time_field'] ?? 'created_at';
        $content_field = $options['content_field'] ?? 'content';
        $tags_field = $options['tags_field'] ?? 'tags';

        // Extract term frequencies over time
        $term_timeline = $this->build_term_timeline($items, $time_field, $content_field, $tags_field);

        // Calculate velocity for each term
        $velocities = $this->calculate_all_velocities($term_timeline);

        // Categorize terms
        $trending = [];
        $stable = [];
        $declining = [];

        foreach ($velocities as $term => $data) {
            if ($data['count'] < $this->min_occurrences) {
                continue;
            }

            $entry = [
                'term' => $term,
                'velocity' => $data['velocity'],
                'count' => $data['count'],
                'recent_count' => $data['recent_count'],
                'previous_count' => $data['previous_count'],
                'trend_score' => $this->calculate_trend_score($data),
            ];

            if ($data['velocity'] >= $this->velocity_threshold) {
                $trending[] = $entry;
            } elseif ($data['velocity'] <= 0.5) {
                $declining[] = $entry;
            } else {
                $stable[] = $entry;
            }
        }

        // Sort by trend score
        usort($trending, fn($a, $b) => $b['trend_score'] <=> $a['trend_score']);
        usort($declining, fn($a, $b) => $a['velocity'] <=> $b['velocity']);

        return [
            'trending' => array_slice($trending, 0, 20),
            'stable' => array_slice($stable, 0, 20),
            'declining' => array_slice($declining, 0, 20),
            'metadata' => [
                'total_items' => count($items),
                'time_window' => $this->time_window,
                'total_terms' => count($velocities),
            ],
        ];
    }

    /**
     * Calculate velocity for a specific term.
     *
     * @param string $term Term to check.
     * @param array<int, array<string, mixed>> $items Content items.
     * @param string $time_field Time field name.
     * @return array<string, mixed> Velocity data.
     * @since 1.0.0
     */
    public function calculate_velocity(string $term, array $items, string $time_field = 'created_at'): array
    {
        $now = time();
        $window_start = $now - ($this->time_window * 3600);
        $midpoint = $now - ($this->time_window * 1800); // Half window

        $recent_count = 0;
        $previous_count = 0;

        foreach ($items as $item) {
            $content = strtolower(($item['content'] ?? '') . ' ' . ($item['title'] ?? ''));
            $tags = $item['tags'] ?? [];

            $has_term = strpos($content, strtolower($term)) !== false || in_array($term, $tags);

            if (!$has_term) {
                continue;
            }

            $timestamp = strtotime($item[$time_field] ?? 'now');
            if ($timestamp === false) {
                $timestamp = $now;
            }

            if ($timestamp >= $midpoint) {
                $recent_count++;
            } else {
                $previous_count++;
            }
        }

        $velocity = $this->compute_velocity($recent_count, $previous_count);

        return [
            'term' => $term,
            'recent_count' => $recent_count,
            'previous_count' => $previous_count,
            'velocity' => $velocity,
            'is_trending' => $velocity >= $this->velocity_threshold && $recent_count >= $this->min_occurrences,
        ];
    }

    /**
     * Get emerging topics.
     *
     * @param array<int, array<string, mixed>> $items Content items.
     * @param int $limit Maximum topics to return.
     * @return array<int, array<string, mixed>> Emerging topics.
     * @since 1.0.0
     */
    public function get_emerging_topics(array $items, int $limit = 10): array
    {
        $trends = $this->detect_trends($items);
        return array_slice($trends['trending'], 0, $limit);
    }

    /**
     * Get declining topics.
     *
     * @param array<int, array<string, mixed>> $items Content items.
     * @param int $limit Maximum topics to return.
     * @return array<int, array<string, mixed>> Declining topics.
     * @since 1.0.0
     */
    public function get_declining_topics(array $items, int $limit = 10): array
    {
        $trends = $this->detect_trends($items);
        return array_slice($trends['declining'], 0, $limit);
    }

    /**
     * Build term timeline from items.
     *
     * @param array<int, array<string, mixed>> $items Content items.
     * @param string $time_field Time field.
     * @param string $content_field Content field.
     * @param string $tags_field Tags field.
     * @return array<string, array<int, int>> Term timeline.
     * @since 1.0.0
     */
    private function build_term_timeline(array $items, string $time_field, string $content_field, string $tags_field): array
    {
        $timeline = [];
        $now = time();

        foreach ($items as $item) {
            $timestamp = strtotime($item[$time_field] ?? 'now');
            if ($timestamp === false) {
                $timestamp = $now;
            }

            $hour_slot = (int) floor($timestamp / 3600);

            // Extract terms from content
            $content = $item[$content_field] ?? '';
            $terms = $this->extract_terms($content);

            // Add tags
            if (!empty($item[$tags_field]) && is_array($item[$tags_field])) {
                $terms = array_merge($terms, $item[$tags_field]);
            }

            // Count terms per time slot
            foreach ($terms as $term) {
                if (!isset($timeline[$term])) {
                    $timeline[$term] = [];
                }
                $timeline[$term][$hour_slot] = ($timeline[$term][$hour_slot] ?? 0) + 1;
            }
        }

        return $timeline;
    }

    /**
     * Calculate velocities for all terms.
     *
     * @param array<string, array<int, int>> $timeline Term timeline.
     * @return array<string, array<string, mixed>> Velocity data.
     * @since 1.0.0
     */
    private function calculate_all_velocities(array $timeline): array
    {
        $now = time();
        $current_hour = (int) floor($now / 3600);
        $half_window = (int) floor($this->time_window / 2);

        $velocities = [];

        foreach ($timeline as $term => $slots) {
            $recent_count = 0;
            $previous_count = 0;
            $total_count = 0;

            foreach ($slots as $hour => $count) {
                $hours_ago = $current_hour - $hour;

                if ($hours_ago <= $half_window) {
                    $recent_count += $count;
                } elseif ($hours_ago <= $this->time_window) {
                    $previous_count += $count;
                }

                $total_count += $count;
            }

            $velocities[$term] = [
                'velocity' => $this->compute_velocity($recent_count, $previous_count),
                'count' => $total_count,
                'recent_count' => $recent_count,
                'previous_count' => $previous_count,
            ];
        }

        return $velocities;
    }

    /**
     * Compute velocity from counts.
     *
     * @param int $recent Recent count.
     * @param int $previous Previous count.
     * @return float Velocity.
     * @since 1.0.0
     */
    private function compute_velocity(int $recent, int $previous): float
    {
        if ($previous === 0) {
            return $recent > 0 ? PHP_FLOAT_MAX : 1.0;
        }

        return $recent / $previous;
    }

    /**
     * Calculate trend score.
     *
     * @param array<string, mixed> $data Velocity data.
     * @return float Trend score.
     * @since 1.0.0
     */
    private function calculate_trend_score(array $data): float
    {
        // Combine velocity and count for ranking
        $velocity_weight = 0.6;
        $count_weight = 0.4;

        $normalized_velocity = min($data['velocity'] / 5.0, 1.0); // Cap at 5x
        $normalized_count = min($data['recent_count'] / 20.0, 1.0); // Cap at 20

        return ($velocity_weight * $normalized_velocity) + ($count_weight * $normalized_count);
    }

    /**
     * Extract terms from content.
     *
     * @param string $content Content.
     * @return array<int, string> Terms.
     * @since 1.0.0
     */
    private function extract_terms(string $content): array
    {
        // Normalize
        $content = strtolower($content);
        $content = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $content);

        // Tokenize
        $terms = preg_split('/\s+/', trim($content));
        if ($terms === false) {
            return [];
        }

        // Filter short terms
        $terms = array_filter($terms, fn($t) => strlen($t) > 3);

        // Remove stop words
        $stop_words = ['this', 'that', 'with', 'from', 'have', 'been', 'were', 'they', 'their', 'would', 'could', 'should'];
        $terms = array_filter($terms, fn($t) => !in_array($t, $stop_words));

        return array_values($terms);
    }

    /**
     * Set time window.
     *
     * @param int $hours Window in hours.
     * @since 1.0.0
     */
    public function set_time_window(int $hours): void
    {
        $this->time_window = max(1, $hours);
    }

    /**
     * Set minimum occurrences.
     *
     * @param int $min Minimum occurrences.
     * @since 1.0.0
     */
    public function set_min_occurrences(int $min): void
    {
        $this->min_occurrences = max(1, $min);
    }

    /**
     * Set velocity threshold.
     *
     * @param float $threshold Threshold value.
     * @since 1.0.0
     */
    public function set_velocity_threshold(float $threshold): void
    {
        $this->velocity_threshold = max(0.1, $threshold);
    }

    /**
     * Get historical trend data from cache.
     *
     * @param string $key Cache key.
     * @return array<string, mixed>|null Cached data or null.
     * @since 1.0.0
     */
    private function get_cached_trends(string $key): ?array
    {
        $cached = get_transient($this->cache_prefix . $key);
        return is_array($cached) ? $cached : null;
    }

    /**
     * Store trend data in cache.
     *
     * @param string $key Cache key.
     * @param array<string, mixed> $data Data to cache.
     * @since 1.0.0
     */
    private function cache_trends(string $key, array $data): void
    {
        set_transient($this->cache_prefix . $key, $data, $this->time_window * 3600);
    }
}
