<?php
/**
 * Distillation_Engine implementation
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
 * BITCORE Accelerate - Distillation Engine
 *
 * @package BITCORE\Accelerate
 * @since 1.0.0
 */

namespace BITCORE\Accelerate;

use BITCORE\Artifacts\Artifact_Repository;
use WP_Error;

/**
 * Distillation Engine
 *
 * Processes and condenses knowledge artifacts into distilled forms.
 *
 * @since 1.0.0
 */
class Distillation_Engine
{
    /** @var Artifact_Repository Artifact repository */
    private Artifact_Repository $repository;

    /** @var array<string, callable> Registered distillation strategies */
    private array $strategies = [];

    /**
     * Constructor.
     *
     * @param Artifact_Repository|null $repository Optional repository.
     * @since 1.0.0
     */
    public function __construct(?Artifact_Repository $repository = null)
    {
        $this->repository = $repository ?? new Artifact_Repository();
        $this->register_default_strategies();
    }

    /**
     * Register default distillation strategies.
     *
     * @since 1.0.0
     */
    private function register_default_strategies(): void
    {
        $this->strategies = [
            'extract_key_points' => [$this, 'extract_key_points'],
            'summarize' => [$this, 'summarize'],
            'compress' => [$this, 'compress'],
            'extract_patterns' => [$this, 'extract_patterns'],
            'generate_outline' => [$this, 'generate_outline'],
        ];
    }

    /**
     * Distill an artifact using specified strategy.
     *
     * @param int $artifact_id The artifact to distill.
     * @param string $strategy The distillation strategy.
     * @param array<string, mixed> $options Strategy options.
     * @return array<string, mixed>|WP_Error Distilled result or error.
     * @since 1.0.0
     */
    public function distill(int $artifact_id, string $strategy = 'summarize', array $options = []): array|WP_Error
    {
        $artifact = $this->repository->get($artifact_id);

        if (!$artifact) {
            return new WP_Error(
                'artifact_not_found',
                'Artifact not found.',
                ['status' => 404]
            );
        }

        if (!isset($this->strategies[$strategy])) {
            return new WP_Error(
                'invalid_strategy',
                sprintf('Unknown strategy: %s', $strategy),
                ['status' => 400]
            );
        }

        $content = $artifact['content'];
        $result = call_user_func($this->strategies[$strategy], $content, $options);

        return [
            'success' => true,
            'source_artifact' => $artifact_id,
            'strategy' => $strategy,
            'result' => $result,
            'metadata' => [
                'original_length' => strlen($content),
                'distilled_length' => strlen($result['content'] ?? ''),
                'compression_ratio' => $this->calculate_compression_ratio($content, $result['content'] ?? ''),
                'processed_at' => current_time('mysql', true),
            ],
        ];
    }

    /**
     * Distill multiple artifacts.
     *
     * @param array<int> $artifact_ids Artifact IDs to distill.
     * @param string $strategy The distillation strategy.
     * @param array<string, mixed> $options Strategy options.
     * @return array<string, mixed> Results keyed by artifact ID.
     * @since 1.0.0
     */
    public function distill_batch(array $artifact_ids, string $strategy = 'summarize', array $options = []): array
    {
        $results = [];

        foreach ($artifact_ids as $id) {
            $result = $this->distill($id, $strategy, $options);
            $results[$id] = [
                'success' => !is_wp_error($result),
                'data' => is_wp_error($result) ? ['error' => $result->get_error_message()] : $result,
            ];
        }

        return $results;
    }

    /**
     * Extract key points from content.
     *
     * @param string $content The content.
     * @param array<string, mixed> $options Options.
     * @return array<string, mixed> Extracted points.
     @since 1.0.0
     */
    private function extract_key_points(string $content, array $options = []): array
    {
        $max_points = $options['max_points'] ?? 5;
        $sentences = preg_split('/[.!?]+/', $content, -1, PREG_SPLIT_NO_EMPTY);
        $sentences = array_filter(array_map('trim', $sentences));

        // Score sentences by key indicators
        $scored = [];
        $keywords = ['important', 'key', 'critical', 'essential', 'must', 'should', 'note', 'remember'];

        foreach ($sentences as $sentence) {
            $score = 0;
            $lower = strtolower($sentence);

            foreach ($keywords as $keyword) {
                if (str_contains($lower, $keyword)) {
                    $score += 2;
                }
            }

            // Longer sentences often contain more information
            $score += min(3, floor(str_word_count($sentence) / 10));
            $scored[] = ['sentence' => $sentence, 'score' => $score];
        }

        // Sort by score and take top N
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
        $top = array_slice($scored, 0, $max_points);

        return [
            'content' => implode("\n- ", array_column($top, 'sentence')),
            'points' => array_column($top, 'sentence'),
            'count' => count($top),
        ];
    }

    /**
     * Summarize content.
     *
     * @param string $content The content.
     * @param array<string, mixed> $options Options.
     * @return array<string, mixed> Summary.
     * @since 1.0.0
     */
    private function summarize(string $content, array $options = []): array
    {
        $max_length = $options['max_length'] ?? 500;
        $sentences = preg_split('/[.!?]+/', $content, -1, PREG_SPLIT_NO_EMPTY);
        $sentences = array_filter(array_map('trim', $sentences));

        if (empty($sentences)) {
            return ['content' => '', 'word_count' => 0];
        }

        // Take first and last sentences + middle summary
        $summary_parts = [];
        $total = count($sentences);

        if ($total <= 3) {
            $summary = implode('. ', $sentences) . '.';
        } else {
            $summary_parts[] = $sentences[0];
            $summary_parts[] = $sentences[(int) floor($total / 2)];
            $summary_parts[] = $sentences[$total - 1];
            $summary = implode('. ', $summary_parts) . '.';
        }

        // Truncate if needed
        if (strlen($summary) > $max_length) {
            $summary = substr($summary, 0, $max_length - 3) . '...';
        }

        return [
            'content' => $summary,
            'word_count' => str_word_count($summary),
            'sentence_count' => $total,
        ];
    }

    /**
     * Compress content by removing redundancy.
     *
     * @param string $content The content.
     * @param array<string, mixed> $options Options.
     * @return array<string, mixed> Compressed content.
     * @since 1.0.0
     */
    private function compress(string $content, array $options = []): array
    {
        // Remove extra whitespace
        $compressed = preg_replace('/\s+/', ' ', $content);

        // Remove duplicate words in sequence
        $compressed = preg_replace('/\b(\w+)\s+\1\b/i', '$1', $compressed);

        // Remove common filler phrases
        $fillers = [
            '/\b(in order to)\b/i' => 'to',
            '/\b(due to the fact that)\b/i' => 'because',
            '/\b(at this point in time)\b/i' => 'now',
            '/\b(in the event that)\b/i' => 'if',
        ];

        foreach ($fillers as $pattern => $replacement) {
            $compressed = preg_replace($pattern, $replacement, $compressed);
        }

        $compressed = trim($compressed);

        return [
            'content' => $compressed,
            'original_length' => strlen($content),
            'compressed_length' => strlen($compressed),
            'reduction' => strlen($content) - strlen($compressed),
        ];
    }

    /**
     * Extract patterns from content.
     *
     * @param string $content The content.
     * @param array<string, mixed> $options Options.
     * @return array<string, mixed> Found patterns.
     * @since 1.0.0
     */
    private function extract_patterns(string $content, array $options = []): array
    {
        $patterns = [];

        // Extract URLs
        preg_match_all('/https?:\/\/[^\s]+/', $content, $urls);
        if (!empty($urls[0])) {
            $patterns['urls'] = array_unique($urls[0]);
        }

        // Extract email addresses
        preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $content, $emails);
        if (!empty($emails[0])) {
            $patterns['emails'] = array_unique($emails[0]);
        }

        // Extract code blocks
        preg_match_all('/```([\s\S]*?)```/', $content, $code_blocks);
        if (!empty($code_blocks[1])) {
            $patterns['code_blocks'] = $code_blocks[1];
        }

        // Extract key-value pairs
        preg_match_all('/([A-Z][A-Za-z_]+):\s*([^\n]+)/', $content, $kv_pairs);
        if (!empty($kv_pairs[1])) {
            $patterns['key_values'] = array_combine($kv_pairs[1], $kv_pairs[2]);
        }

        $output = "Found patterns:\n";
        foreach ($patterns as $type => $items) {
            $output .= sprintf("\n## %s (%d)\n", ucfirst($type), count($items));
            if (is_array($items)) {
                foreach (array_slice($items, 0, 5) as $item) {
                    $output .= "- " . (is_string($item) ? substr($item, 0, 100) : json_encode($item)) . "\n";
                }
            }
        }

        return [
            'content' => $output,
            'patterns' => $patterns,
            'pattern_count' => array_sum(array_map('count', $patterns)),
        ];
    }

    /**
     * Generate outline from content.
     *
     * @param string $content The content.
     * @param array<string, mixed> $options Options.
     * @return array<string, mixed> Generated outline.
     * @since 1.0.0
     */
    private function generate_outline(string $content, array $options = []): array
    {
        $max_depth = $options['max_depth'] ?? 3;
        $lines = explode("\n", $content);
        $outline = [];
        $current_level = 0;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Detect headings (markdown style)
            if (preg_match('/^(#{1,6})\s+(.+)$/', $trimmed, $matches)) {
                $level = strlen($matches[1]);
                $text = $matches[2];

                if ($level <= $max_depth) {
                    $outline[] = [
                        'level' => $level,
                        'text' => $text,
                        'type' => 'heading',
                    ];
                }
                continue;
            }

            // Detect list items
            if (preg_match('/^[-*]\s+(.+)$/', $trimmed, $matches)) {
                $outline[] = [
                    'level' => $current_level + 1,
                    'text' => $matches[1],
                    'type' => 'list_item',
                ];
            }
        }

        $output = "# Outline\n\n";
        foreach ($outline as $item) {
            $indent = str_repeat('  ', $item['level'] - 1);
            $prefix = $item['type'] === 'heading' ? '#' . $item['level'] : '-';
            $output .= "{$indent}{$prefix} {$item['text']}\n";
        }

        return [
            'content' => $output,
            'outline' => $outline,
            'item_count' => count($outline),
        ];
    }

    /**
     * Calculate compression ratio.
     *
     * @param string $original Original content.
     * @param string $distilled Distilled content.
     * @return float Compression ratio.
     * @since 1.0.0
     */
    private function calculate_compression_ratio(string $original, string $distilled): float
    {
        $orig_len = strlen($original);
        $dist_len = strlen($distilled);

        if ($orig_len === 0) {
            return 0.0;
        }

        return round((1 - ($dist_len / $orig_len)) * 100, 1);
    }

    /**
     * Get available strategies.
     *
     * @return array<string> Strategy names.
     * @since 1.0.0
     */
    public function get_strategies(): array
    {
        return array_keys($this->strategies);
    }

    /**
     * Register custom strategy.
     *
     * @param string $name Strategy name.
     * @param callable $callback Strategy callback.
     * @since 1.0.0
     */
    public function register_strategy(string $name, callable $callback): void
    {
        $this->strategies[$name] = $callback;
    }
}
