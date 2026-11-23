<?php

declare(strict_types=1);

/**
 * BITCORE Timeline Feeds - Intent Matcher
 *
 * @package BITCORE\Feeds
 * @since 1.0.0
 * @version     1.0.0
 */

namespace BITCORE\Feeds;

/**
 * Intent Matcher
 *
 * Aligns feed content with user preferences and intent patterns.
 *
 * @since 1.0.0
 */
class Intent_Matcher
{
    /** @var array<string, array<string, int>> User preference weights */
    private array $preferences = [];

    /** @var array<string, int> Global intent weights */
    private const INTENT_WEIGHTS = [
        'research' => 10,
        'development' => 8,
        'orchestration' => 7,
        'analysis' => 6,
        'collaboration' => 5,
        'learning' => 5,
        'automation' => 8,
        'integration' => 7,
    ];

    /** @var array<string, array<string>> Intent keywords mapping */
    private const INTENT_KEYWORDS = [
        'research' => ['api', 'documentation', 'specification', 'analysis', 'investigation', 'study'],
        'development' => ['code', 'plugin', 'theme', 'block', 'feature', 'implementation', 'build'],
        'orchestration' => ['workflow', 'pipeline', 'agent', 'swarm', 'coordination', 'automation'],
        'analysis' => ['data', 'metrics', 'report', 'insights', 'statistics', 'performance'],
        'collaboration' => ['team', 'share', 'discuss', 'comment', 'feedback', 'review'],
        'learning' => ['tutorial', 'guide', 'how-to', 'example', 'documentation', 'learn'],
        'automation' => ['schedule', 'cron', 'trigger', 'auto', 'batch', 'process'],
        'integration' => ['connect', 'api', 'webhook', 'sync', 'import', 'export'],
    ];

    /**
     * Load user preferences.
     *
     * @param int $user_id User ID.
     * @return array<string, mixed> User preferences.
     * @since 1.0.0
     */
    public function load_preferences(int $user_id): array
    {
        $cache_key = "bitcore_feed_prefs_{$user_id}";
        $cached = wp_cache_get($cache_key, 'bitcore');

        if (false !== $cached) {
            $this->preferences[$user_id] = $cached;
            return $cached;
        }

        $prefs = get_user_meta($user_id, 'bitcore_feed_preferences', true);

        if (!is_array($prefs)) {
            $prefs = $this->get_default_preferences();
        }

        $this->preferences[$user_id] = $prefs;
        wp_cache_set($cache_key, $prefs, 'bitcore', 3600);

        return $prefs;
    }

    /**
     * Save user preferences.
     *
     * @param int $user_id User ID.
     * @param array<string, mixed> $preferences Preferences to save.
     * @return bool Success status.
     * @since 1.0.0
     */
    public function save_preferences(int $user_id, array $preferences): bool
    {
        $validated = $this->validate_preferences($preferences);
        $this->preferences[$user_id] = $validated;

        wp_cache_delete("bitcore_feed_prefs_{$user_id}", 'bitcore');

        return (bool) update_user_meta($user_id, 'bitcore_feed_preferences', $validated);
    }

    /**
     * Match content against user intent.
     *
     * @param array<string, mixed> $content Content to match.
     * @param int $user_id User ID for preferences.
     * @return array<string, mixed> Match result with score.
     * @since 1.0.0
     */
    public function match(array $content, int $user_id = 0): array
    {
        $prefs = $user_id > 0 ? $this->load_preferences($user_id) : $this->get_default_preferences();

        $title = strtolower($content['title'] ?? '');
        $body = strtolower($content['content'] ?? $content['excerpt'] ?? '');
        $tags = $content['tags'] ?? [];
        $type = $content['type'] ?? 'article';

        $scores = [];
        $matched_keywords = [];

        foreach (self::INTENT_KEYWORDS as $intent => $keywords) {
            $score = 0;
            $found = [];

            foreach ($keywords as $keyword) {
                // Check title (higher weight)
                if (str_contains($title, $keyword)) {
                    $score += 3;
                    $found[] = $keyword;
                }

                // Check body
                $body_matches = substr_count($body, $keyword);
                if ($body_matches > 0) {
                    $score += $body_matches;
                    $found[] = $keyword;
                }

                // Check tags
                if (in_array($keyword, $tags, true)) {
                    $score += 2;
                    $found[] = $keyword;
                }
            }

            // Apply user preference weight
            $pref_weight = $prefs['intents'][$intent] ?? 1;
            $score = $score * $pref_weight;

            if ($score > 0) {
                $scores[$intent] = $score;
                $matched_keywords[$intent] = array_unique($found);
            }
        }

        // Sort by score
        arsort($scores);

        $primary_intent = array_key_first($scores) ?? 'general';
        $total_score = array_sum($scores);

        // Apply recency boost if applicable
        if (isset($content['created_at'])) {
            $age_days = $this->calculate_age_days($content['created_at']);
            $recency_boost = max(0, (7 - $age_days) * 0.1);
            $total_score = $total_score * (1 + $recency_boost);
        }

        return [
            'content_id' => $content['id'] ?? null,
            'primary_intent' => $primary_intent,
            'score' => round($total_score, 2),
            'intent_scores' => $scores,
            'matched_keywords' => $matched_keywords,
            'relevance' => $this->calculate_relevance($total_score),
        ];
    }

    /**
     * Match and rank multiple content items.
     *
     * @param array<int, array<string, mixed>> $items Content items.
     * @param int $user_id User ID for preferences.
     * @param int $limit Maximum items to return.
     * @return array<int, array<string, mixed>> Ranked items with match data.
     * @since 1.0.0
     */
    public function rank(array $items, int $user_id = 0, int $limit = 20): array
    {
        $ranked = [];

        foreach ($items as $item) {
            $match = $this->match($item, $user_id);
            $ranked[] = array_merge($item, [
                'match' => $match,
            ]);
        }

        // Sort by match score
        usort($ranked, fn($a, $b) => ($b['match']['score'] ?? 0) <=> ($a['match']['score'] ?? 0));

        return array_slice($ranked, 0, $limit);
    }

    /**
     * Get user's top intents.
     *
     * @param int $user_id User ID.
     * @param int $limit Maximum intents to return.
     * @return array<string, int> Top intents with weights.
     * @since 1.0.0
     */
    public function get_top_intents(int $user_id, int $limit = 3): array
    {
        $prefs = $this->load_preferences($user_id);
        $intents = $prefs['intents'] ?? [];

        arsort($intents);

        return array_slice($intents, 0, $limit, true);
    }

    /**
     * Track user interaction for preference learning.
     *
     * @param int $user_id User ID.
     * @param string $content_id Content ID.
     * @param string $action Action type (view, like, save, share).
     * @param array<string, mixed> $content Content data.
     * @since 1.0.0
     */
    public function track_interaction(int $user_id, string $content_id, string $action, array $content = []): void
    {
        $prefs = $this->load_preferences($user_id);

        // Determine action weight
        $action_weights = [
            'view' => 0.1,
            'like' => 0.5,
            'save' => 1.0,
            'share' => 1.5,
        ];

        $weight = $action_weights[$action] ?? 0.1;

        // Detect intent from content
        $match = $this->match($content, 0);
        $primary_intent = $match['primary_intent'];

        // Update preference weight
        if (!isset($prefs['intents'][$primary_intent])) {
            $prefs['intents'][$primary_intent] = 1;
        }

        $prefs['intents'][$primary_intent] = min(10, $prefs['intents'][$primary_intent] + $weight);

        // Track interaction history
        if (!isset($prefs['history'])) {
            $prefs['history'] = [];
        }

        $prefs['history'][] = [
            'content_id' => $content_id,
            'action' => $action,
            'intent' => $primary_intent,
            'timestamp' => current_time('mysql', true),
        ];

        // Keep only last 100 interactions
        $prefs['history'] = array_slice($prefs['history'], -100);

        $this->save_preferences($user_id, $prefs);
    }

    /**
     * Get default preferences.
     *
     * @return array<string, mixed> Default preferences.
     * @since 1.0.0
     */
    private function get_default_preferences(): array
    {
        return [
            'intents' => self::INTENT_WEIGHTS,
            'sources' => [
                'bitwiki' => 1.0,
                'bithub' => 1.0,
                'external' => 0.5,
            ],
            'frequency' => 'daily',
            'max_items' => 20,
        ];
    }

    /**
     * Validate preferences structure.
     *
     * @param array<string, mixed> $preferences Preferences to validate.
     * @return array<string, mixed> Validated preferences.
     * @since 1.0.0
     */
    private function validate_preferences(array $preferences): array
    {
        $defaults = $this->get_default_preferences();

        return [
            'intents' => array_merge(
                $defaults['intents'],
                array_intersect_key($preferences['intents'] ?? [], $defaults['intents'])
            ),
            'sources' => array_merge(
                $defaults['sources'],
                array_intersect_key($preferences['sources'] ?? [], $defaults['sources'])
            ),
            'frequency' => in_array($preferences['frequency'] ?? '', ['hourly', 'daily', 'weekly'], true)
                ? $preferences['frequency']
                : $defaults['frequency'],
            'max_items' => max(5, min(100, (int) ($preferences['max_items'] ?? $defaults['max_items']))),
        ];
    }

    /**
     * Calculate content age in days.
     *
     * @param string $created_at Creation timestamp.
     * @return int Age in days.
     * @since 1.0.0
     */
    private function calculate_age_days(string $created_at): int
    {
        $created = strtotime($created_at);
        if (!$created) {
            return 0;
        }

        return (int) floor((time() - $created) / DAY_IN_SECONDS);
    }

    /**
     * Calculate relevance category from score.
     *
     * @param float $score The match score.
     * @return string Relevance category.
     * @since 1.0.0
     */
    private function calculate_relevance(float $score): string
    {
        if ($score >= 20) {
            return 'high';
        }
        if ($score >= 10) {
            return 'medium';
        }
        if ($score >= 5) {
            return 'low';
        }
        return 'minimal';
    }
}
