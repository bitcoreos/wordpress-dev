<?php
declare(strict_types=1);

use BITCORE\Feeds\Chunker;
use BITCORE\Feeds\RAG_Embedding;

/**
 * RAG Workspace - Retrieval Augmented Generation
 *
 * @package BITCORE\Feeds
 * @since 1.0.0
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/trait-rag-embedding.php';

class RAG_Workspace
{
    use RAG_Embedding;
    
    private ?int $workspace_id = null;
    private string $name = '';
    private array $options = [];
    private ?int $user_id = null;
    private string $embedding_model = 'text-embedding-3-small';
    private int $chunk_size = 500;
    private ?Chunker $chunker = null;

    public function __construct(?int $workspace_id = null)
    {
        global $wpdb;
        
        $this->user_id = get_current_user_id();
        
        if ($workspace_id) {
            $this->load($workspace_id);
        }
    }

    public function store_document(string $content, array $metadata = [])
    {
        global $wpdb;
        
        if (!$this->workspace_id) {
            return new WP_Error('no_workspace', 'No workspace loaded');
        }
        
        $chunks = $this->get_chunker()->chunk($content, $this->chunk_size);
        
        $wpdb->insert(
            $wpdb->prefix . 'bitcore_rag_documents',
            [
                'workspace_id' => $this->workspace_id,
                'content' => $content,
                'metadata' => json_encode($metadata),
                'chunk_count' => count($chunks),
                'created_at' => current_time('mysql'),
            ]
        );
        
        $document_id = $wpdb->insert_id;
        
        $this->build_index($document_id, $content);
        
        return $document_id;
    }

    public function query(string $query, array $options = [])
    {
        $top_k = $options['top_k'] ?? 5;
        $min_score = $options['min_score'] ?? 0.7;
        $include_content = $options['include_content'] ?? true;
        
        $query_embedding = $this->get_embedding($query);
        
        if (is_wp_error($query_embedding)) {
            return $query_embedding;
        }
        
        $results = $this->find_similar_chunks($query_embedding, $top_k, $min_score);
        
        return $results;
    }

    public function get_context(string $query, int $max_chunks = 5): string
    {
        $results = $this->query($query, ['top_k' => $max_chunks]);
        
        if (is_wp_error($results) || empty($results)) {
            return '';
        }
        
        return $this->build_context_from_results($results, true);
    }

    public function build_index(int $document_id, string $content)
    {
        global $wpdb;
        
        $chunks = $this->get_chunker()->chunk($content, $this->chunk_size);
        
        foreach ($chunks as $index => $chunk) {
            $embedding = $this->get_embedding($chunk);
            
            if (is_wp_error($embedding)) {
                continue;
            }
            
            $wpdb->insert(
                $wpdb->prefix . 'bitcore_rag_embeddings',
                [
                    'document_id' => $document_id,
                    'workspace_id' => $this->workspace_id,
                    'chunk_index' => $index,
                    'content' => $chunk,
                    'embedding' => json_encode($embedding),
                    'created_at' => current_time('mysql'),
                ]
            );
        }
    }

    public function create_workspace(string $name, array $options = []): int
    {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'bitcore_rag_workspaces',
            [
                'name' => $name,
                'user_id' => $this->user_id,
                'options' => json_encode($options),
                'created_at' => current_time('mysql'),
            ]
        );
        
        $this->workspace_id = $wpdb->insert_id;
        $this->name = $name;
        $this->options = $options;
        
        return $this->workspace_id;
    }

    public static function get_user_workspaces(?int $user_id = null): array
    {
        global $wpdb;
        
        $user_id = $user_id ?? get_current_user_id();
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bitcore_rag_workspaces WHERE user_id = %d ORDER BY created_at DESC",
            $user_id
        ), ARRAY_A);
        
        return array_map(function ($row) {
            return [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'document_count' => self::get_document_count((int) $row['id']),
                'created_at' => $row['created_at'],
            ];
        }, $results);
    }

    private static function get_document_count(int $workspace_id): int
    {
        global $wpdb;
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}bitcore_rag_documents WHERE workspace_id = %d",
            $workspace_id
        ));
    }

    private function get_chunker(): Chunker
    {
        if (!$this->chunker) {
            $this->chunker = new Chunker();
        }
        return $this->chunker;
    }

    private function load(int $id): bool
    {
        global $wpdb;
        
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bitcore_rag_workspaces WHERE id = %d",
            $id
        ), ARRAY_A);
        
        if (!$row) {
            return false;
        }
        
        $this->workspace_id = (int) $row['id'];
        $this->name = $row['name'];
        $this->user_id = (int) $row['user_id'];
        $this->options = json_decode($row['options'], true) ?: [];
        
        return true;
    }

    public function delete()
    {
        global $wpdb;
        
        if (!$this->workspace_id) {
            return new WP_Error('no_workspace', 'No workspace loaded');
        }
        
        $wpdb->delete(
            $wpdb->prefix . 'bitcore_rag_embeddings',
            ['workspace_id' => $this->workspace_id]
        );
        
        $wpdb->delete(
            $wpdb->prefix . 'bitcore_rag_documents',
            ['workspace_id' => $this->workspace_id]
        );
        
        $wpdb->delete(
            $wpdb->prefix . 'bitcore_rag_workspaces',
            ['id' => $this->workspace_id]
        );
        
        $this->workspace_id = null;
        
        return true;
    }

    public function delete_document(int $document_id): bool
    {
        global $wpdb;
        
        $wpdb->delete(
            $wpdb->prefix . 'bitcore_rag_embeddings',
            ['document_id' => $document_id]
        );
        
        $wpdb->delete(
            $wpdb->prefix . 'bitcore_rag_documents',
            ['id' => $document_id]
        );
        
        return true;
    }

    public function get_documents(int $limit = 50): array
    {
        global $wpdb;
        
        if (!$this->workspace_id) {
            return [];
        }
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT id, content, metadata, chunk_count, created_at FROM {$wpdb->prefix}bitcore_rag_documents WHERE workspace_id = %d ORDER BY created_at DESC LIMIT %d",
            $this->workspace_id,
            $limit
        ), ARRAY_A);
        
        return array_map(function ($row) {
            return [
                'id' => (int) $row['id'],
                'content_preview' => substr($row['content'], 0, 200),
                'metadata' => json_decode($row['metadata'], true) ?: [],
                'chunk_count' => (int) $row['chunk_count'],
                'created_at' => $row['created_at'],
            ];
        }, $results);
    }

    public function set_embedding_model(string $model): void
    {
        $this->embedding_model = $model;
    }

    public function set_chunk_size(int $size): void
    {
        $this->chunk_size = $size;
    }

    public function get_id(): ?int
    {
        return $this->workspace_id;
    }

    public function get_name(): string
    {
        return $this->name;
    }
}
