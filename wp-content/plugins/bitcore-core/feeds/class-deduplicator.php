<?php
/**
 * Deduplicator implementation
 *
 * @package    Bitcore
 * @since      2026.03.01
 * @version    1.3.0
 * @author     hyperion
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * BITCORE Timeline Feeds - Deduplicator
 *
 * @package BITCORE\Feeds
 * @since 1.0.0
 */

namespace BITCORE\Feeds;

/**
 * Deduplicator
 *
 * Removes duplicate and near-duplicate content.
 *
 * @since 1.0.0
 */
class Deduplicator
{
    /** @var float Similarity threshold for near-duplicates */
    private float $similarity_threshold;

    /** @var int Minimum content length to consider */
    private int $min_length;

    /** @var array<string, string> Content hash cache */
    private array $hash_cache = [];

    /** @var bool Use phonetic matching */
    private bool $use_phonetic;

    /**
     * Constructor.
     *
     * @param float $similarity_threshold Threshold for considering content similar.
     * @since 1.0.0
     */
    public function __construct(float $similarity_threshold = 0.85)
    {
        $this->similarity_threshold = $similarity_threshold;
        $this->min_length = 50;
        $this->use_phonetic = false;
    }

    /**
     * Deduplicate an array of content items.
     *
     * @param array<int, array<string, mixed>> $items Items to deduplicate.
     * @param string $content_field Field containing content to compare.
     * @return array<int, array<string, mixed>> Deduplicated items.
     * @since 1.0.0
     */
    public function deduplicate(array $items, string $content_field = 'content'): array
    {
        if (empty($items)) {
            return [];
        }

        $unique = [];
        $seen_hashes = [];
        $seen_content = [];

        foreach ($items as $index => $item) {
            $content = $item[$content_field] ?? '';
            
            if (strlen($content) < $this->min_length) {
                $unique[] = $item;
                continue;
            }

            // Check exact hash first
            $hash = $this->compute_hash($content);
            if (isset($seen_hashes[$hash])) {
                continue;
            }
            $seen_hashes[$hash] = true;

            // Check near-duplicates
            $is_duplicate = false;
            foreach ($seen_content as $existing_content) {
                $similarity = $this->calculate_similarity($content, $existing_content);
                if ($similarity >= $this->similarity_threshold) {
                    $is_duplicate = true;
                    break;
                }
            }

            if (!$is_duplicate) {
                $unique[] = $item;
                $seen_content[] = $content;
            }
        }

        return $unique;
    }

    /**
     * Calculate similarity between two strings.
     *
     * @param string $content1 First content.
     * @param string $content2 Second content.
     * @return float Similarity score (0.0-1.0).
     * @since 1.0.0
     */
    public function calculate_similarity(string $content1, string $content2): float
    {
        // Normalize content
        $norm1 = $this->normalize($content1);
        $norm2 = $this->normalize($content2);

        // Quick length check
        $len1 = strlen($norm1);
        $len2 = strlen($norm2);
        
        if ($len1 === 0 || $len2 === 0) {
            return 0.0;
        }

        // Length ratio check
        $length_ratio = min($len1, $len2) / max($len1, $len2);
        if ($length_ratio < 0.5) {
            return $length_ratio;
        }

        // Calculate multiple similarity metrics
        $scores = [];

        // 1. Similarity using similar_text
        $similar_percent = 0;
        similar_text($norm1, $norm2, $similar_percent);
        $scores['similar_text'] = $similar_percent / 100;

        // 2. Levenshtein distance
        $lev_distance = levenshtein($norm1, $norm2);
        $max_len = max($len1, $len2);
        $scores['levenshtein'] = 1 - ($lev_distance / $max_len);

        // 3. Jaccard similarity on words
        $scores['jaccard'] = $this->jaccard_similarity($norm1, $norm2);

        // 4. N-gram similarity
        $scores['ngram'] = $this->ngram_similarity($norm1, $norm2, 3);

        // Weighted average
        $weights = [
            'similar_text' => 0.25,
            'levenshtein' => 0.25,
            'jaccard' => 0.25,
            'ngram' => 0.25,
        ];

        $total = 0;
        foreach ($scores as $key => $score) {
            $total += $score * $weights[$key];
        }

        return $total;
    }

    /**
     * Find duplicates in content array.
     *
     * @param array<int, array<string, mixed>> $items Items to check.
     * @param string $content_field Field containing content.
     * @return array<int, array<string, mixed>> Groups of duplicate items.
     * @since 1.0.0
     */
    public function find_duplicates(array $items, string $content_field = 'content'): array
    {
        if (count($items) < 2) {
            return [];
        }

        $duplicate_groups = [];
        $processed = [];

        foreach ($items as $i => $item1) {
            if (isset($processed[$i])) {
                continue;
            }

            $content1 = $item1[$content_field] ?? '';
            $group = [$item1];
            $processed[$i] = true;

            foreach ($items as $j => $item2) {
                if ($i === $j || isset($processed[$j])) {
                    continue;
                }

                $content2 = $item2[$content_field] ?? '';
                $similarity = $this->calculate_similarity($content1, $content2);

                if ($similarity >= $this->similarity_threshold) {
                    $group[] = $item2;
                    $processed[$j] = true;
                }
            }

            if (count($group) > 1) {
                $duplicate_groups[] = [
                    'items' => $group,
                    'count' => count($group),
                    'representative' => $group[0],
                ];
            }
        }

        return $duplicate_groups;
    }

    /**
     * Merge similar content items.
     *
     * @param array<int, array<string, mixed>> $items Items to merge.
     * @param array<string, mixed> $options Merge options.
     * @return array<string, mixed> Merged item.
     * @since 1.0.0
     */
    public function merge_similar(array $items, array $options = []): array
    {
        if (empty($items)) {
            return [];
        }

        if (count($items) === 1) {
            return $items[0];
        }

        $content_field = $options['content_field'] ?? 'content';
        $keep_longest = $options['keep_longest'] ?? true;
        $merge_metadata = $options['merge_metadata'] ?? true;

        // Find the best content
        $best_item = $items[0];
        $best_length = strlen($best_item[$content_field] ?? '');

        foreach ($items as $item) {
            $length = strlen($item[$content_field] ?? '');
            if ($keep_longest && $length > $best_length) {
                $best_item = $item;
                $best_length = $length;
            }
        }

        if (!$merge_metadata) {
            return $best_item;
        }

        // Merge metadata
        $merged = $best_item;
        $merged['_merged_from'] = count($items);
        $merged['_merged_sources'] = [];

        foreach ($items as $item) {
            // Merge unique tags
            if (!empty($item['tags']) && is_array($item['tags'])) {
                $merged['tags'] = array_unique(
                    array_merge($merged['tags'] ?? [], $item['tags'])
                );
            }

            // Track sources
            if (!empty($item['source'])) {
                $merged['_merged_sources'][] = $item['source'];
            }

            // Use earliest date
            if (!empty($item['created_at'])) {
                if (empty($merged['created_at']) || $item['created_at'] < $merged['created_at']) {
                    $merged['created_at'] = $item['created_at'];
                }
            }
        }

        $merged['_merged_sources'] = array_unique($merged['_merged_sources']);

        return $merged;
    }

    /**
     * Compute hash for content.
     *
     * @param string $content Content to hash.
     * @return string Content hash.
     * @since 1.0.0
     */
    private function compute_hash(string $content): string
    {
        $normalized = $this->normalize($content);
        return hash('sha256', $normalized);
    }

    /**
     * Normalize content for comparison.
     *
     * @param string $content Content to normalize.
     * @return string Normalized content.
     * @since 1.0.0
     */
    private function normalize(string $content): string
    {
        // Lowercase
        $normalized = strtolower($content);
        
        // Remove extra whitespace
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        
        // Remove punctuation
        $normalized = preg_replace('/[^\p{L}\p{N}\s]/u', '', $normalized);
        
        return trim($normalized);
    }

    /**
     * Calculate Jaccard similarity on word sets.
     *
     * @param string $content1 First content.
     * @param string $content2 Second content.
     * @return float Jaccard similarity.
     * @since 1.0.0
     */
    private function jaccard_similarity(string $content1, string $content2): float
    {
        $words1 = array_unique(explode(' ', $content1));
        $words2 = array_unique(explode(' ', $content2));

        $words1 = array_filter($words1, fn($w) => strlen($w) > 2);
        $words2 = array_filter($words2, fn($w) => strlen($w) > 2);

        if (empty($words1) || empty($words2)) {
            return 0.0;
        }

        $intersection = array_intersect($words1, $words2);
        $union = array_unique(array_merge($words1, $words2));

        return count($intersection) / count($union);
    }

    /**
     * Calculate n-gram similarity.
     *
     * @param string $content1 First content.
     * @param string $content2 Second content.
     * @param int $n N-gram size.
     * @return float N-gram similarity.
     * @since 1.0.0
     */
    private function ngram_similarity(string $content1, string $content2, int $n = 3): float
    {
        $ngrams1 = $this->get_ngrams($content1, $n);
        $ngrams2 = $this->get_ngrams($content2, $n);

        if (empty($ngrams1) || empty($ngrams2)) {
            return 0.0;
        }

        $intersection = array_intersect($ngrams1, $ngrams2);
        $union = array_unique(array_merge($ngrams1, $ngrams2));

        return count($intersection) / count($union);
    }

    /**
     * Get n-grams from content.
     *
     * @param string $content Content.
     * @param int $n N-gram size.
     * @return array<int, string> N-grams.
     * @since 1.0.0
     */
    private function get_ngrams(string $content, int $n): array
    {
        $ngrams = [];
        $length = strlen($content);

        for ($i = 0; $i < $length - $n + 1; $i++) {
            $ngrams[] = substr($content, $i, $n);
        }

        return $ngrams;
    }

    /**
     * Set similarity threshold.
     *
     * @param float $threshold Threshold value (0.0-1.0).
     * @since 1.0.0
     */
    public function set_similarity_threshold(float $threshold): void
    {
        $this->similarity_threshold = max(0.0, min(1.0, $threshold));
    }

    /**
     * Set minimum content length.
     *
     * @param int $length Minimum length.
     * @since 1.0.0
     */
    public function set_min_length(int $length): void
    {
        $this->min_length = max(1, $length);
    }

    /**
     * Clear the hash cache.
     *
     * @since 1.0.0
     */
    public function clear_cache(): void
    {
        $this->hash_cache = [];
    }
}
