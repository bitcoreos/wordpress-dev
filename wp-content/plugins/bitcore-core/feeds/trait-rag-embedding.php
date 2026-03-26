<?php

declare(strict_types=1);

/**
 * BITCORE Timeline Feeds - RAG Workspace
 *
 * @package BITCORE\Feeds
 * @since 1.0.0
 */

namespace BITCORE\Feeds;

/**
 * RAG Workspace
trait RAG_Embedding {
    private function get_embedding(string $text)
    {
        if (empty($this->api_key)) {
            return new \WP_Error('no_api_key', __('AI API key not configured.', 'bitcore'));
        }

        // Check cache first
        $cache_key = 'bitcore_emb_' . md5($text);
        $cached = get_transient($cache_key);
        
        if ($cached !== false && is_array($cached)) {
            return $cached;
        }

        $response = wp_remote_post(rtrim($this->api_url, '/') . '/embeddings', [
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key,
            ],
            'body' => wp_json_encode([
                'model' => $this->embedding_model,
                'input' => $text,
            ]),
        ]);

        if (is_wp_error($response)) {
            return new \WP_Error('api_error', $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return new \WP_Error('api_error', "API returned code {$code}");
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($data['data'][0]['embedding'])) {
            return new \WP_Error('invalid_response', __('Invalid embedding response.', 'bitcore'));
        }

        $embedding = $data['data'][0]['embedding'];

        // Cache for 7 days
        set_transient($cache_key, $embedding, 7 * DAY_IN_SECONDS);

        return $embedding;
    }

    /**
     * Find similar chunks using embedding.
     *
     * @param array<float> $query_embedding Query embedding.
     * @param int $top_k Number of results.
     * @param float $min_score Minimum similarity score.
     * @return array<int, array<string, mixed>> Similar chunks.
     * @since 1.0.0
     */
    private function find_similar_chunks(array $query_embedding, int $top_k, float $min_score): array
    {
        global $wpdb;

        // Get all embeddings for workspace
        $embeddings = $wpdb->get_results($wpdb->prepare(
            "SELECT e.*, d.title as document_title, d.source_url 
            FROM {$this->embeddings_table} e 
            LEFT JOIN {$this->documents_table} d ON e.document_id = d.id 
            WHERE e.workspace_id = %d",
            $this->workspace_id
        ));

        $results = [];

        foreach ($embeddings as $row) {
            $chunk_embedding = json_decode($row->embedding, true);
            
            if (!is_array($chunk_embedding)) {
                continue;
            }

            $similarity = $this->cosine_similarity($query_embedding, $chunk_embedding);

            if ($similarity >= $min_score) {
                $results[] = [
                    'chunk_id' => (int) $row->id,
                    'document_id' => (int) $row->document_id,
                    'document_title' => $row->document_title,
                    'source_url' => $row->source_url,
                    'chunk_index' => (int) $row->chunk_index,
                    'content' => $row->chunk_content,
                    'similarity' => $similarity,
                ];
            }
        }

        // Sort by similarity
        usort($results, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

        return array_slice($results, 0, $top_k);
    }

    /**
     * Build context string from results.
     *
     * @param array<int, array<string, mixed>> $results Query results.
     * @param bool $include_content Include content.
     * @return string Context string.
     * @since 1.0.0
     */
    private function build_context_from_results(array $results, bool $include_content): string
    {
        if (empty($results)) {
            return '';
        }

        $context_parts = [];

        foreach ($results as $result) {
            $part = sprintf(
                "[%s]\n%s",
                $result['document_title'] ?? 'Untitled',
                $include_content ? $result['content'] : ''
            );
            $context_parts[] = $part;
        }

        return implode("\n\n---\n\n", $context_parts);
    }

    /**
     * Calculate cosine similarity between vectors.
     *
     * @param array<float> $v1 First vector.
     * @param array<float> $v2 Second vector.
     * @return float Similarity score.
     * @since 1.0.0
     */
    private function cosine_similarity(array $v1, array $v2): float
    {
        if (empty($v1) || empty($v2) || count($v1) !== count($v2)) {
            return 0.0;
        }

        $dot = 0.0;
        $mag1 = 0.0;
        $mag2 = 0.0;

        $count = count($v1);
        for ($i = 0; $i < $count; $i++) {
            $a = (float) $v1[$i];
            $b = (float) $v2[$i];

            $dot += $a * $b;
            $mag1 += $a * $a;
            $mag2 += $b * $b;
        }

        $magnitude = sqrt($mag1) * sqrt($mag2);
        return $magnitude > 0 ? $dot / $magnitude : 0.0;
    }

    /**
     * Get chunker instance.
     *
     * @return Chunker Chunker instance.
     * @since 1.0.0
}
