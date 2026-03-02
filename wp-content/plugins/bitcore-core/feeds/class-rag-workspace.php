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
 *
 * Personal research storage and querying with RAG capabilities.
 *
 * @since 1.0.0
 */
class RAG_Workspace
{
    /** @var int Workspace ID */
    private int $workspace_id;

    /** @var int User ID */
    private int $user_id;

    /** @var string Workspace name */
    private string $name;

    /** @var string Documents table */
    private string $documents_table;

    /** @var string Embeddings table */
    private string $embeddings_table;

    /** @var string Workspace table */
    private string $workspace_table;

    /** @var string AI API URL */
    private string $api_url;

    /** @var string AI API key */
    private string $api_key;

    /** @var string Embedding model */
    private string $embedding_model;

    /** @var int Chunk size for documents */
    private int $chunk_size;

    /** @var Chunker|null Chunker instance */
    private ?Chunker $chunker;

    /**
     * Constructor.
     *
     * @param int|null $workspace_id Optional workspace ID to load.
     * @since 1.0.0
     */
    public function __construct(?int $workspace_id = null)
    {
        global $wpdb;

        $this->workspace_table = $wpdb->prefix . 'bitcore_rag_workspaces';
        $this->documents_table = $wpdb->prefix . 'bitcore_rag_documents';
        $this->embeddings_table = $wpdb->prefix . 'bitcore_rag_embeddings';

        $this->api_url = get_option('bitcore_ai_api_url', 'https://api.openai.com/v1');
        $this->api_key = get_option('bitcore_ai_api_key', '');
        $this->embedding_model = 'text-embedding-3-small';
        $this->chunk_size = 500;

        if ($workspace_id !== null) {
            $this->load($workspace_id);
        }
    }

    /**
     * Store a document in the workspace.
     *
     * @param string $content Document content.
     * @param array<string, mixed> $metadata Document metadata.
     * @return int|\WP_Error Document ID or WP_Error.
     * @since 1.0.0
     */
    public function store_document(string $content, array $metadata = [])
    {
        if (empty($content)) {
            return new \WP_Error('empty_content', __('Document content cannot be empty.', 'bitcore'));
        }

        if (!$this->workspace_id) {
            return new \WP_Error('no_workspace', __('No workspace loaded.', 'bitcore'));
        }

        global $wpdb;

        // Store document
        $result = $wpdb->insert($this->documents_table, [
            'workspace_id' => $this->workspace_id,
            'title' => $metadata['title'] ?? 'Untitled',
            'content' => $content,
            'source_url' => $metadata['url'] ?? '',
            'source_type' => $metadata['type'] ?? 'text',
            'metadata' => wp_json_encode($metadata),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);

        if ($result === false) {
            return new \WP_Error('db_error', __('Failed to store document.', 'bitcore'));
        }

        $document_id = $wpdb->insert_id;

        // Build index (create chunks and embeddings)
        $this->build_index($document_id, $content);

        return $document_id;
    }

    /**
     * Query the workspace using natural language.
     *
     * @param string $query Query string.
     * @param array<string, mixed> $options Query options.
     * @return array<string, mixed>|\WP_Error Query results or WP_Error.
     * @since 1.0.0
     */
    public function query(string $query, array $options = [])
    {
        if (empty($query)) {
            return new \WP_Error('empty_query', __('Query cannot be empty.', 'bitcore'));
        }

        if (!$this->workspace_id) {
            return new \WP_Error('no_workspace', __('No workspace loaded.', 'bitcore'));
        }

        $top_k = $options['top_k'] ?? 5;
        $min_score = $options['min_score'] ?? 0.7;
        $include_content = $options['include_content'] ?? true;

        // Get query embedding
        $query_embedding = $this->get_embedding($query);
        
        if (is_wp_error($query_embedding)) {
            return $query_embedding;
        }

        // Find similar chunks
        $results = $this->find_similar_chunks($query_embedding, $top_k, $min_score);

        // Build context from results
        $context = $this->build_context_from_results($results, $include_content);

        return [
            'query' => $query,
            'results' => $results,
            'context' => $context,
            'total_chunks' => count($results),
            'workspace_id' => $this->workspace_id,
        ];
    }

    /**
     * Get relevant context for a query.
     *
     * @param string $query Query string.
     * @param int $max_chunks Maximum chunks to include.
     * @return string Context string.
     * @since 1.0.0
     */
    public function get_context(string $query, int $max_chunks = 5): string
    {
        $result = $this->query($query, ['top_k' => $max_chunks, 'include_content' => true]);

        if (is_wp_error($result)) {
            return '';
        }

        return $result['context'] ?? '';
    }

    /**
     * Build index for a document.
     *
     * @param int $document_id Document ID.
     * @param string $content Document content.
     * @return bool|\WP_Error Success or WP_Error.
     * @since 1.0.0
     */
    public function build_index(int $document_id, string $content)
    {
        // Chunk the content
        $chunker = $this->get_chunker();
        $chunks = $chunker->chunk($content, [
            'chunk_size' => $this->chunk_size,
            'method' => 'auto',
        ]);

        if (empty($chunks)) {
            return new \WP_Error('chunking_failed', __('Failed to chunk document.', 'bitcore'));
        }

        global $wpdb;

        foreach ($chunks as $index => $chunk) {
            $chunk_content = $chunk['content'];
            
            // Get embedding for chunk
            $embedding = $this->get_embedding($chunk_content);
            
            if (is_wp_error($embedding)) {
                continue; // Skip failed embeddings
            }

            // Store chunk with embedding
            $wpdb->insert($this->embeddings_table, [
                'document_id' => $document_id,
                'workspace_id' => $this->workspace_id,
                'chunk_index' => $index,
                'chunk_content' => $chunk_content,
                'embedding' => wp_json_encode($embedding),
                'metadata' => wp_json_encode($chunk),
                'created_at' => current_time('mysql'),
            ]);
        }

        return true;
    }

    /**
     * Create a new workspace.
     *
     * @param string $name Workspace name.
     * @param array<string, mixed> $options Workspace options.
     * @return int|\WP_Error Workspace ID or WP_Error.
     * @since 1.0.0
     */
    public function create_workspace(string $name, array $options = []): int
    {
        if (empty($name)) {
            return new \WP_Error('empty_name', __('Workspace name cannot be empty.', 'bitcore'));
        }

        global $wpdb;

        $result = $wpdb->insert($this->workspace_table, [
            'name' => $name,
            'user_id' => get_current_user_id(),
            'description' => $options['description'] ?? '',
            'settings' => wp_json_encode($options['settings'] ?? []),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);

        if ($result === false) {
            return new \WP_Error('db_error', __('Failed to create workspace.', 'bitcore'));
        }

        $this->workspace_id = $wpdb->insert_id;
        $this->name = $name;
        $this->user_id = get_current_user_id();

        return $this->workspace_id;
    }

    /**
     * Get all workspaces for a user.
     *
     * @param int|null $user_id User ID or current user.
     * @return array<int, array<string, mixed>> Workspaces.
     * @since 1.0.0
     */
    public static function get_user_workspaces(?int $user_id = null): array
    {
        global $wpdb;
        $user_id = $user_id ?? get_current_user_id();
        $table_name = $wpdb->prefix . 'bitcore_rag_workspaces';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE user_id = %d ORDER BY created_at DESC",
            $user_id
        ));

        return array_map(function ($row) {
            return [
                'id' => (int) $row->id,
                'name' => $row->name,
                'description' => $row->description,
                'document_count' => self::get_document_count((int) $row->id),
                'created_at' => $row->created_at,
            ];
        }, $results ?? []);
    }

    /**
     * Get document count for workspace.
     *
     * @param int $workspace_id Workspace ID.
     * @return int Document count.
     * @since 1.0.0
     */
    private static function get_document_count(int $workspace_id): int
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bitcore_rag_documents';
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE workspace_id = %d",
            $workspace_id
        ));
    }

    /**
     * Get embedding for text.
     *
     * @param string $text Text to embed.
     * @return array<float>|\WP_Error Embedding vector or WP_Error.
     * @since 1.0.0
     */
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
     */
    private function get_chunker(): Chunker
    {
        if (!isset($this->chunker)) {
            $this->chunker = new Chunker();
        }
        return $this->chunker;
    }

    /**
     * Load workspace from database.
     *
     * @param int $id Workspace ID.
     * @return bool Success.
     * @since 1.0.0
     */
    private function load(int $id): bool
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->workspace_table} WHERE id = %d",
            $id
        ));

        if (!$row) {
            return false;
        }

        $this->workspace_id = (int) $row->id;
        $this->user_id = (int) $row->user_id;
        $this->name = $row->name;

        return true;
    }

    /**
     * Delete workspace and all associated data.
     *
     * @return bool|\WP_Error Success or WP_Error.
     * @since 1.0.0
     */
    public function delete()
    {
        if (!$this->workspace_id) {
            return new \WP_Error('no_workspace', __('No workspace loaded.', 'bitcore'));
        }

        global $wpdb;

        // Delete embeddings
        $wpdb->delete($this->embeddings_table, ['workspace_id' => $this->workspace_id]);

        // Delete documents
        $wpdb->delete($this->documents_table, ['workspace_id' => $this->workspace_id]);

        // Delete workspace
        $result = $wpdb->delete($this->workspace_table, ['id' => $this->workspace_id]);

        if ($result === false) {
            return new \WP_Error('db_error', __('Failed to delete workspace.', 'bitcore'));
        }

        $this->workspace_id = 0;
        return true;
    }

    /**
     * Delete a document from workspace.
     *
     * @param int $document_id Document ID.
     * @return bool Success.
     * @since 1.0.0
     */
    public function delete_document(int $document_id): bool
    {
        global $wpdb;

        // Delete embeddings
        $wpdb->delete($this->embeddings_table, ['document_id' => $document_id]);

        // Delete document
        $wpdb->delete($this->documents_table, ['id' => $document_id]);

        return true;
    }

    /**
     * Get workspace documents.
     *
     * @param int $limit Maximum documents.
     * @return array<int, array<string, mixed>> Documents.
     * @since 1.0.0
     */
    public function get_documents(int $limit = 50): array
    {
        if (!$this->workspace_id) {
            return [];
        }

        global $wpdb;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT id, title, source_url, source_type, created_at, 
            (SELECT COUNT(*) FROM {$this->embeddings_table} WHERE document_id = {$this->documents_table}.id) as chunk_count
            FROM {$this->documents_table} 
            WHERE workspace_id = %d 
            ORDER BY created_at DESC 
            LIMIT %d",
            $this->workspace_id,
            $limit
        ));

        return array_map(function ($row) {
            return [
                'id' => (int) $row->id,
                'title' => $row->title,
                'source_url' => $row->source_url,
                'source_type' => $row->source_type,
                'chunk_count' => (int) $row->chunk_count,
                'created_at' => $row->created_at,
            ];
        }, $results ?? []);
    }

    /**
     * Set embedding model.
     *
     * @param string $model Model identifier.
     * @since 1.0.0
     */
    public function set_embedding_model(string $model): void
    {
        $this->embedding_model = $model;
    }

    /**
     * Set chunk size.
     *
     * @param int $size Chunk size in characters.
     * @since 1.0.0
     */
    public function set_chunk_size(int $size): void
    {
        $this->chunk_size = max(100, $size);
    }

    /**
     * Get workspace ID.
     *
     * @return int|null Workspace ID.
     * @since 1.0.0
     */
    public function get_id(): ?int
    {
        return $this->workspace_id ?? null;
    }

    /**
     * Get workspace name.
     *
     * @return string Name.
     * @since 1.0.0
     */
    public function get_name(): string
    {
        return $this->name ?? '';
    }
}
