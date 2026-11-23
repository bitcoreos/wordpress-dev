<?php
/**
 * BITCORE Timeline Feeds - Topic Clusterer
 *
 * @package BITCORE\Feeds
 * @since 1.0.0
 * @version     1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Feeds;

trait Topic_Clusterer_Internals {

    /**
     * Build TF-IDF vectors for all items.
     *
     * @param array<int, array<string, mixed>> $items Items.
     * @param string $content_field Content field.
     * @return array<int, array<string, float>> TF-IDF vectors.
     * @since 1.0.0
     */
    private function build_tfidf_vectors(array $items, string $content_field): array
    {
        $this->total_documents = count($items);
        $document_freq = [];
        $vectors = [];

        // First pass: calculate document frequency
        foreach ($items as $index => $item) {
            $content = $item[$content_field] ?? '';
            $terms = $this->extract_terms($content);
            $unique_terms = array_unique($terms);

            foreach ($unique_terms as $term) {
                $document_freq[$term] = ($document_freq[$term] ?? 0) + 1;
            }
        }

        // Calculate IDF
        foreach ($document_freq as $term => $freq) {
            $this->idf_values[$term] = log($this->total_documents / $freq);
        }

        // Second pass: build TF-IDF vectors
        foreach ($items as $index => $item) {
            $content = $item[$content_field] ?? '';
            $terms = $this->extract_terms($content);
            $vectors[$index] = $this->build_tfidf_vector($terms);
        }

        return $vectors;
    }
    /**
     * Build TF-IDF vector for a single document.
     *
     * @param array<int, string> $terms Document terms.
     * @return array<string, float> TF-IDF vector.
     * @since 1.0.0
     */
    private function build_tfidf_vector(array $terms): array
    {
        $tf = $this->calculate_term_frequency($terms);
        $vector = [];

        foreach ($tf as $term => $freq) {
            $idf = $this->idf_values[$term] ?? 1.0;
            $vector[$term] = $freq * $idf;
        }

        // Normalize
        $magnitude = sqrt(array_sum(array_map(fn($v) => $v * $v, $vector)));
        if ($magnitude > 0) {
            foreach ($vector as $term => $value) {
                $vector[$term] = $value / $magnitude;
            }
        }

        return $vector;
    }
    /**
     * Build simple vector (TF only).
     *
     * @param array<int, string> $terms Document terms.
     * @return array<string, float> TF vector.
     * @since 1.0.0
     */
    private function build_vector(array $terms): array
    {
        return $this->calculate_term_frequency($terms);
    }
    /**
     * K-means-like clustering algorithm.
     *
     * @param array<int, array<string, mixed>> $items Items.
     * @param array<int, array<string, float>> $vectors TF-IDF vectors.
     * @param array<int, array<string>> $document_terms Terms per document.
     * @return array<string, mixed> Clustering result.
     * @since 1.0.0
     */
    private function kmeans_cluster(array $items, array $vectors, array $document_terms): array
    {
        $k = min($this->num_clusters, (int) ceil(count($items) / $this->min_cluster_size));
        $k = max(1, $k);

        // Initialize centroids randomly
        $indices = array_keys($items);
        shuffle($indices);
        $centroid_indices = array_slice($indices, 0, $k);

        $centroids = [];
        foreach ($centroid_indices as $i) {
            $centroids[] = $vectors[$i] ?? [];
        }

        // Iterate
        $max_iterations = 20;
        $assignments = [];

        for ($iter = 0; $iter < $max_iterations; $iter++) {
            $new_assignments = [];

            // Assign items to nearest centroid
            foreach ($items as $index => $item) {
                $vector = $vectors[$index] ?? [];
                $best_cluster = 0;
                $best_similarity = -1;

                foreach ($centroids as $cluster_id => $centroid) {
                    $similarity = $this->cosine_similarity($vector, $centroid);
                    if ($similarity > $best_similarity) {
                        $best_similarity = $similarity;
                        $best_cluster = $cluster_id;
                    }
                }

                $new_assignments[$index] = $best_cluster;
            }

            // Check convergence
            if ($assignments === $new_assignments) {
                break;
            }
            $assignments = $new_assignments;

            // Update centroids
            $cluster_items = [];
            foreach ($assignments as $index => $cluster_id) {
                $cluster_items[$cluster_id][] = $index;
            }

            foreach ($cluster_items as $cluster_id => $item_indices) {
                $centroids[$cluster_id] = $this->calculate_centroid(
                    array_map(fn($i) => $vectors[$i] ?? [], $item_indices)
                );
            }
        }

        // Build final clusters
        $clusters = [];
        $unassigned = [];

        foreach ($assignments as $index => $cluster_id) {
            $item = $items[$index];
            $item['_index'] = $index;
            $item['_similarity'] = $this->cosine_similarity($vectors[$index] ?? [], $centroids[$cluster_id] ?? []);

            $clusters[$cluster_id]['items'][] = $item;
            $clusters[$cluster_id]['centroid'] = $centroids[$cluster_id] ?? [];
        }

        // Filter small clusters
        $final_clusters = [];
        foreach ($clusters as $cluster_id => $cluster) {
            if (count($cluster['items']) >= $this->min_cluster_size) {
                $final_clusters[] = $cluster;
            } else {
                $unassigned = array_merge($unassigned, $cluster['items']);
            }
        }

        return [
            'clusters' => $final_clusters,
            'unassigned' => $unassigned,
            'statistics' => [
                'total_items' => count($items),
                'total_clusters' => count($final_clusters),
                'iterations' => $iter ?? 0,
            ],
        ];
    }
    /**
     * Calculate centroid of vectors.
     *
     * @param array<int, array<string, float>> $vectors Vectors.
     * @return array<string, float> Centroid.
     * @since 1.0.0
     */
    private function calculate_centroid(array $vectors): array
    {
        if (empty($vectors)) {
            return [];
        }

        $sum = [];
        $count = count($vectors);

        foreach ($vectors as $vector) {
            foreach ($vector as $term => $value) {
                $sum[$term] = ($sum[$term] ?? 0) + $value;
            }
        }

        $centroid = [];
        foreach ($sum as $term => $total) {
            $centroid[$term] = $total / $count;
        }

        return $centroid;
    }
    /**
     * Calculate cosine similarity between vectors.
     *
     * @param array<string, float> $v1 First vector.
     * @param array<string, float> $v2 Second vector.
     * @return float Similarity score.
     * @since 1.0.0
     */
    private function cosine_similarity(array $v1, array $v2): float
    {
        if (empty($v1) || empty($v2)) {
            return 0.0;
        }

        $dot = 0.0;
        $mag1 = 0.0;
        $mag2 = 0.0;

        $all_terms = array_unique(array_merge(array_keys($v1), array_keys($v2)));

        foreach ($all_terms as $term) {
            $a = $v1[$term] ?? 0.0;
            $b = $v2[$term] ?? 0.0;

            $dot += $a * $b;
            $mag1 += $a * $a;
            $mag2 += $b * $b;
        }

        $magnitude = sqrt($mag1) * sqrt($mag2);
        return $magnitude > 0 ? $dot / $magnitude : 0.0;
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
        $content = preg_replace('/\s+/', ' ', $content);

        // Tokenize
        $terms = explode(' ', trim($content));

        // Filter
        $terms = array_filter($terms, function ($term) {
            return strlen($term) > 2 && !in_array($term, $this->stop_words);
        });

        return array_values($terms);
    }
    /**
     * Calculate term frequency.
     *
     * @param array<int, string> $terms Terms.
     * @return array<string, float> Term frequencies.
     * @since 1.0.0
     */
    private function calculate_term_frequency(array $terms): array
    {
        $freq = [];
        $total = count($terms);

        if ($total === 0) {
            return [];
        }

        foreach ($terms as $term) {
            $freq[$term] = ($freq[$term] ?? 0) + 1;
        }

        // Normalize by total terms
        foreach ($freq as $term => $count) {
            $freq[$term] = $count / $total;
        }

        return $freq;
    }
    /**
     * Extract topic name for a cluster.
     *
     * @param array<string, mixed> $cluster Cluster data.
     * @param array<int, array<string>> $document_terms Terms per document.
     * @return string Topic name.
     * @since 1.0.0
     */
    private function extract_cluster_topic(array $cluster, array $document_terms): string
    {
        $terms = $this->get_cluster_terms($cluster, $document_terms);
        return implode(', ', array_slice($terms, 0, 3));
    }
    /**
     * Get default English stop words.
     *
     * @return array<int, string> Stop words.
     * @since 1.0.0
     */
    private function get_default_stop_words(): array
    {
        return [
            'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
            'of', 'with', 'by', 'from', 'is', 'are', 'was', 'were', 'be', 'been',
            'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would',
            'could', 'should', 'may', 'might', 'must', 'this', 'that', 'these',
            'those', 'it', 'its', 'as', 'if', 'when', 'than', 'so', 'no', 'not',
            'only', 'own', 'same', 'into', 'also', 'just', 'which', 'who', 'what',
            'where', 'when', 'why', 'how', 'all', 'each', 'every', 'both', 'few',
            'more', 'most', 'other', 'some', 'such', 'any', 'about', 'after',
            'before', 'above', 'below', 'between', 'under', 'again', 'further',
            'then', 'once', 'here', 'there', 'their', 'they', 'them', 'he', 'she',
            'him', 'her', 'his', 'hers', 'we', 'us', 'our', 'you', 'your', 'i', 'me', 'my',
        ];
    }
}
