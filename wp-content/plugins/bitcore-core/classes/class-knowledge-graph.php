<?php
declare(strict_types=1);
/**
 * Knowledge Graph - Entity relationship tracking
 * Pattern extracted from flux
 * 
 * @package BitCore
 * @license MIT
 * @since       1.0.0
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class BITCORE_Knowledge_Graph {
    
    /**
     * Add a node to the knowledge graph
     *
     * @param string $id Unique node identifier
     * @param string $type Node type (agent, task, document, etc)
     * @param array $data Additional node data
     * @return bool Success
     */
    public static function add_node($id, $type, $data = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'bitcore_knowledge_nodes';
        
        $node_data = [
            'node_id' => sanitize_text_field($id),
            'type' => sanitize_text_field($type),
            'data' => wp_json_encode($data),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ];
        
        return $wpdb->replace($table, $node_data) !== false;
    }
    
    /**
     * Add an edge between two nodes
     *
     * @param string $source Source node ID
     * @param string $target Target node ID
     * @param string $type Edge type (created, depends_on, references, etc)
     * @param array $metadata Additional edge metadata
     * @return bool Success
     */
    public static function add_edge($source, $target, $type, $metadata = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'bitcore_knowledge_edges';
        
        $edge_data = [
            'source_id' => sanitize_text_field($source),
            'target_id' => sanitize_text_field($target),
            'type' => sanitize_text_field($type),
            'metadata' => wp_json_encode($metadata),
            'created_at' => current_time('mysql'),
        ];
        
        return $wpdb->replace($table, $edge_data) !== false;
    }
    
    /**
     * Get neighbors of a node
     *
     * @param string $node_id Node identifier
     * @param string $direction Direction: 'outgoing', 'incoming', or 'both'
     * @param string|null $type Filter by edge type
     * @return array Array of neighbor nodes with edge info
     */
    public static function get_neighbors($node_id, $direction = 'both', $type = null) {
        global $wpdb;
        $nodes_table = $wpdb->prefix . 'bitcore_knowledge_nodes';
        $edges_table = $wpdb->prefix . 'bitcore_knowledge_edges';
        $node_id = sanitize_text_field($node_id);
        
        $results = [];
        
        if ($direction === 'outgoing' || $direction === 'both') {
            $query = "SELECT n.*, e.type as edge_type, e.metadata as edge_metadata
                      FROM {$edges_table} e
                      JOIN {$nodes_table} n ON e.target_id = n.node_id
                      WHERE e.source_id = %s";
            $params = [$node_id];
            
            if ($type) {
                $query .= " AND e.type = %s";
                $params[] = sanitize_text_field($type);
            }
            
            $outgoing = $wpdb->get_results($wpdb->prepare($query, ...$params));
            $results = array_merge($results, $outgoing);
        }
        
        if ($direction === 'incoming' || $direction === 'both') {
            $query = "SELECT n.*, e.type as edge_type, e.metadata as edge_metadata
                      FROM {$edges_table} e
                      JOIN {$nodes_table} n ON e.source_id = n.node_id
                      WHERE e.target_id = %s";
            $params = [$node_id];
            
            if ($type) {
                $query .= " AND e.type = %s";
                $params[] = sanitize_text_field($type);
            }
            
            $incoming = $wpdb->get_results($wpdb->prepare($query, ...$params));
            $results = array_merge($results, $incoming);
        }
        
        return $results;
    }
    
    /**
     * Get a single node by ID
     *
     * @param string $node_id Node identifier
     * @return object|null Node data or null
     */
    public static function get_node($node_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'bitcore_knowledge_nodes';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE node_id = %s",
            sanitize_text_field($node_id)
        ));
    }
    
    /**
     * Delete a node and all its edges
     *
     * @param string $node_id Node identifier
     * @return bool Success
     */
    public static function delete_node($node_id) {
        global $wpdb;
        $node_id = sanitize_text_field($node_id);
        $nodes_table = $wpdb->prefix . 'bitcore_knowledge_nodes';
        $edges_table = $wpdb->prefix . 'bitcore_knowledge_edges';
        
        // Delete edges
        $wpdb->delete($edges_table, ['source_id' => $node_id], ['%s']);
        $wpdb->delete($edges_table, ['target_id' => $node_id], ['%s']);
        
        // Delete node
        return $wpdb->delete($nodes_table, ['node_id' => $node_id], ['%s']) !== false;
    }
    
    /**
     * Export graph as JSON for D3.js visualization
     *
     * @param array $filters Optional filters for type, date range, etc
     * @return array Graph in D3.js format {nodes: [], links: []}
     */
    public static function to_json($filters = []) {
        global $wpdb;
        $nodes_table = $wpdb->prefix . 'bitcore_knowledge_nodes';
        $edges_table = $wpdb->prefix . 'bitcore_knowledge_edges';
        
        $graph = [
            'nodes' => [],
            'links' => [],
        ];
        
        // Get nodes
        $where = '1=1';
        if (!empty($filters['type'])) {
            $where .= $wpdb->prepare(' AND type = %s', sanitize_text_field($filters['type']));
        }
        if (!empty($filters['limit'])) {
            $limit = (int) $filters['limit'];
        } else {
            $limit = 1000;
        }
        
        $nodes = $wpdb->get_results(
            "SELECT node_id as id, type, data, created_at, updated_at 
             FROM {$nodes_table} WHERE {$where} ORDER BY updated_at DESC LIMIT {$limit}"
        );
        
        foreach ($nodes as $node) {
            $graph['nodes'][] = [
                'id' => $node->id,
                'type' => $node->type,
                'data' => json_decode($node->data, true),
                'created' => $node->created_at,
                'updated' => $node->updated_at,
            ];
        }
        
        // Get edges for these nodes
        $node_ids = wp_list_pluck($nodes, 'id');
        if (!empty($node_ids)) {
            $node_ids_sql = "'" . implode("','", array_map('esc_sql', $node_ids)) . "'";
            $edges = $wpdb->get_results(
                "SELECT source_id, target_id, type, metadata 
                 FROM {$edges_table} 
                 WHERE source_id IN ({$node_ids_sql}) AND target_id IN ({$node_ids_sql})"
            );
            
            foreach ($edges as $edge) {
                $graph['links'][] = [
                    'source' => $edge->source_id,
                    'target' => $edge->target_id,
                    'type' => $edge->type,
                    'metadata' => json_decode($edge->metadata, true),
                ];
            }
        }
        
        return $graph;
    }
    
    /**
     * Search nodes by content
     *
     * @param string $query Search query
     * @param string|null $type Filter by type
     * @param int $limit Maximum results
     * @return array Matching nodes
     */
    public static function search($query, $type = null, $limit = 50) {
        global $wpdb;
        $table = $wpdb->prefix . 'bitcore_knowledge_nodes';
        
        $search_term = '%' . $wpdb->esc_like($query) . '%';
        $sql = "SELECT * FROM {$table} WHERE data LIKE %s";
        $params = [$search_term];
        
        if ($type) {
            $sql .= " AND type = %s";
            $params[] = sanitize_text_field($type);
        }
        
        $sql .= " ORDER BY updated_at DESC LIMIT %d";
        $params[] = (int) $limit;
        
        return $wpdb->get_results($wpdb->prepare($sql, ...$params));
    }
}
