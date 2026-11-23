<?php

declare(strict_types=1);

/**
 * BITCORE Timeline Feeds - Topic Clusterer
 *
 * @package BITCORE\Feeds
 * @since 1.0.0
 * @version     1.0.0
 */

namespace BITCORE\Feeds;

require_once __DIR__ . '/trait-topic-clusterer-internals.php';

/**
 * Topic Clusterer
 *
 * Groups content by topics using clustering algorithms.
 *
 * @since 1.0.0
 */
class Topic_Clusterer
{

    use Topic_Clusterer_Internals;
    /** @var int Number of clusters */
    private int $num_clusters;

    /** @var int Minimum items per cluster */
    private int $min_cluster_size;

    /** @var array<string, int> Term frequency cache */
    private array $term_cache = [];

    /** @var array<string, float> IDF values */
    private array $idf_values = [];

    /** @var int Total documents processed */
    private int $total_documents = 0;

    /** @var array<string> Stop words */
    private array $stop_words;

    /**
     * Constructor.
     *
     * @param int $num_clusters Default number of clusters.
     * @since 1.0.0
     */
    public function __construct(int $num_clusters = 10)
    {
        $this->num_clusters = $num_clusters;
        $this->min_cluster_size = 2;
        $this->stop_words = $this->get_default_stop_words();
    }

    /**
     * Cluster content items by topic.
     *
     * @param array<int, array<string, mixed>> $items Items to cluster.
     * @param string $content_field Field containing content.
     * @return array<int, array<string, mixed>> Clustered items.
     * @since 1.0.0
     */
    public function cluster(array $items, string $content_field = 'content'): array
    {
        if (count($items) < $this->min_cluster_size) {
            return [
                'clusters' => [],
                'unassigned' => $items,
                'statistics' => [
                    'total_items' => count($items),
                    'total_clusters' => 0,
                ],
            ];
        }

        // Build TF-IDF vectors
        $vectors = $this->build_tfidf_vectors($items, $content_field);

        // Extract terms for each document
        $document_terms = [];
        foreach ($items as $index => $item) {
            $content = $item[$content_field] ?? '';
            $document_terms[$index] = $this->extract_terms($content);
        }

        // Perform clustering using K-means-like algorithm
        $clusters = $this->kmeans_cluster($items, $vectors, $document_terms);

        // Extract topics for each cluster
        foreach ($clusters['clusters'] as &$cluster) {
            $cluster['topic'] = $this->extract_cluster_topic($cluster, $document_terms);
            $cluster['terms'] = $this->get_cluster_terms($cluster, $document_terms);
        }

        return $clusters;
    }

    /**
     * Extract topics from content.
     *
     * @param string $content Content to analyze.
     * @param int $max_topics Maximum topics to return.
     * @return array<int, array<string, mixed>> Extracted topics.
     * @since 1.0.0
     */
    public function extract_topics(string $content, int $max_topics = 5): array
    {
        $terms = $this->extract_terms($content);
        $term_freq = $this->calculate_term_frequency($terms);

        arsort($term_freq);

        $topics = [];
        $count = 0;

        foreach ($term_freq as $term => $freq) {
            if ($count >= $max_topics) {
                break;
            }

            $topics[] = [
                'term' => $term,
                'frequency' => $freq,
                'weight' => $this->idf_values[$term] ?? 1.0,
            ];
            $count++;
        }

        return $topics;
    }

    /**
     * Assign an item to an existing cluster.
     *
     * @param array<string, mixed> $item Item to assign.
     * @param array<int, array<string, mixed>> $clusters Existing clusters.
     * @param string $content_field Content field name.
     * @return array<string, mixed> Assignment result.
     * @since 1.0.0
     */
    public function assign_to_cluster(array $item, array $clusters, string $content_field = 'content'): array
    {
        if (empty($clusters)) {
            return [
                'cluster_id' => null,
                'similarity' => 0.0,
            ];
        }

        $content = $item[$content_field] ?? '';
        $terms = $this->extract_terms($content);
        $vector = $this->build_vector($terms);

        $best_cluster = null;
        $best_similarity = 0.0;

        foreach ($clusters as $cluster_id => $cluster) {
            if (!isset($cluster['centroid'])) {
                continue;
            }

            $similarity = $this->cosine_similarity($vector, $cluster['centroid']);

            if ($similarity > $best_similarity) {
                $best_similarity = $similarity;
                $best_cluster = $cluster_id;
            }
        }

        return [
            'cluster_id' => $best_cluster,
            'similarity' => $best_similarity,
            'threshold_met' => $best_similarity >= 0.3,
        ];
    }

    /**
     * Get top terms for a cluster.
     *
     * @param array<string, mixed> $cluster Cluster data.
     * @param array<int, array<string>> $document_terms Terms per document.
     * @return array<int, string> Top terms.
     * @since 1.0.0
     */
    public function get_cluster_terms(array $cluster, array $document_terms): array
    {
        $all_terms = [];

        foreach ($cluster['items'] ?? [] as $item) {
            $index = $item['_index'] ?? null;
            if ($index !== null && isset($document_terms[$index])) {
                foreach ($document_terms[$index] as $term) {
                    $all_terms[$term] = ($all_terms[$term] ?? 0) + 1;
                }
            }
        }

        arsort($all_terms);

        return array_slice(array_keys($all_terms), 0, 10);
    }











    /**
     * Set number of clusters.
     *
     * @param int $num Number of clusters.
     * @since 1.0.0
     */
    public function set_num_clusters(int $num): void
    {
        $this->num_clusters = max(1, $num);
    }

    /**
     * Set minimum cluster size.
     *
     * @param int $size Minimum size.
     * @since 1.0.0
     */
    public function set_min_cluster_size(int $size): void
    {
        $this->min_cluster_size = max(1, $size);
    }
}
