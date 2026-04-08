<?php
declare(strict_types=1);
/**
 * REST API - Health and Knowledge Graph Endpoints
 * P1/P2 Feature Implementation
 * 
 * @package BitCore
 * @license MIT
 */

if (!defined('ABSPATH')) {
    exit;
}

class BITCORE_Rest_Health_Knowledge {
    
    const NAMESPACE = 'bitcore/v1';
    
    /**
     * Register REST routes
     */
    public static function register_routes(): void {
        // Health check endpoint
        register_rest_route(self::NAMESPACE, '/health', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_health'],
            'permission_callback' => '__return_true',
        ]);
        
        // Health refresh endpoint
        register_rest_route(self::NAMESPACE, '/health/refresh', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'refresh_health'],
            'permission_callback' => [__CLASS__, 'check_admin_permission'],
        ]);
        // Knowledge Graph endpoints
        register_rest_route(self::NAMESPACE, '/knowledge-graph', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_graph'],
            'permission_callback' => '__return_true',
        ]);
        
        register_rest_route(self::NAMESPACE, '/knowledge-graph/nodes', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'add_node'],
            'permission_callback' => [__CLASS__, 'check_write_permission'],
        ]);
        
        register_rest_route(self::NAMESPACE, '/knowledge-graph/nodes/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_node'],
            'permission_callback' => '__return_true',
        ]);
        
        register_rest_route(self::NAMESPACE, '/knowledge-graph/nodes/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods' => 'DELETE',
            'callback' => [__CLASS__, 'delete_node'],
            'permission_callback' => [__CLASS__, 'check_admin_permission'],
        ]);
        
        register_rest_route(self::NAMESPACE, '/knowledge-graph/edges', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'add_edge'],
            'permission_callback' => [__CLASS__, 'check_write_permission'],
        ]);
        
        register_rest_route(self::NAMESPACE, '/knowledge-graph/search', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'search_nodes'],
            'permission_callback' => '__return_true',
        ]);
        
        // Agent Identity endpoints
        register_rest_route(self::NAMESPACE, '/identities', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'list_identities'],
            'permission_callback' => '__return_true',
        ]);
        
        register_rest_route(self::NAMESPACE, '/identities', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'register_identity'],
            'permission_callback' => [__CLASS__, 'check_admin_permission'],
        ]);
        
        register_rest_route(self::NAMESPACE, '/identities/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods' => 'DELETE',
            'callback' => [__CLASS__, 'revoke_identity'],
            'permission_callback' => [__CLASS__, 'check_admin_permission'],
        ]);
        
        // Token management endpoints
        register_rest_route(self::NAMESPACE, '/token/rotate', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rotate_token'],
            'permission_callback' => [__CLASS__, 'check_admin_permission'],
        ]);
        
        // Ping endpoint for health check
        register_rest_route(self::NAMESPACE, '/ping', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'ping'],
            'permission_callback' => '__return_true',
        ]);
    }
    
    // ==================== HEALTH ENDPOINTS ====================
    
    /**
     * Get current health snapshot
     */
    public static function get_health($request) {
        $snapshot = BITCORE_Health_Snapshot::get_latest(true);
        return new WP_REST_Response([
            'success' => true,
            'data' => $snapshot,
            'status_label' => BITCORE_Health_Snapshot::get_status_label($snapshot['health_score'] ?? 0),
        ]);
    }
    
    /**
     * Force refresh health snapshot
     */
    public static function refresh_health($request) {
        $snapshot = BITCORE_Health_Snapshot::capture();
        return new WP_REST_Response([
            'success' => true,
            'data' => $snapshot,
            'status_label' => BITCORE_Health_Snapshot::get_status_label($snapshot['health_score'] ?? 0),
        ]);
    }
    
    /**
     * Ping endpoint for latency testing
     */
    public static function ping($request) {
        return new WP_REST_Response([
            'pong' => true,
            'timestamp' => current_time('mysql'),
        ]);
    }
    
    // ==================== KNOWLEDGE GRAPH ENDPOINTS ====================
    
    /**
     * Get knowledge graph JSON
     */
    public static function get_graph($request) {
        $filters = [
            'type' => $request->get_param('type'),
            'limit' => $request->get_param('limit') ? (int) $request->get_param('limit') : 500,
        ];
        
        $graph = BITCORE_Knowledge_Graph::to_json($filters);
        return new WP_REST_Response([
            'success' => true,
            'data' => $graph,
        ]);
    }
    
    /**
     * Add node to knowledge graph
     */
    public static function add_node($request) {
        $id = sanitize_text_field($request->get_param('id'));
        $type = sanitize_text_field($request->get_param('type'));
        $data = $request->get_param('data') ?: [];
        
        if (empty($id) || empty($type)) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'id and type are required',
            ], 400);
        }
        
        $result = BITCORE_Knowledge_Graph::add_node($id, $type, $data);
        return new WP_REST_Response([
            'success' => $result,
            'node_id' => $id,
        ]);
    }
    
    /**
     * Get single node
     */
    public static function get_node($request) {
        $id = sanitize_text_field($request->get_param('id'));
        $node = BITCORE_Knowledge_Graph::get_node($id);
        
        if (!$node) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'Node not found',
            ], 404);
        }
        
        return new WP_REST_Response([
            'success' => true,
            'data' => $node,
        ]);
    }
    
    /**
     * Delete node
     */
    public static function delete_node($request) {
        $id = sanitize_text_field($request->get_param('id'));
        $result = BITCORE_Knowledge_Graph::delete_node($id);
        
        return new WP_REST_Response([
            'success' => $result,
            'node_id' => $id,
        ]);
    }
    
    /**
     * Add edge to knowledge graph
     */
    public static function add_edge($request) {
        $source = sanitize_text_field($request->get_param('source'));
        $target = sanitize_text_field($request->get_param('target'));
        $type = sanitize_text_field($request->get_param('type'));
        $metadata = $request->get_param('metadata') ?: [];
        
        if (empty($source) || empty($target) || empty($type)) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'source, target, and type are required',
            ], 400);
        }
        
        $result = BITCORE_Knowledge_Graph::add_edge($source, $target, $type, $metadata);
        return new WP_REST_Response([
            'success' => $result,
            'edge' => compact('source', 'target', 'type'),
        ]);
    }
    
    /**
     * Search knowledge graph nodes
     */
    public static function search_nodes($request) {
        $query = sanitize_text_field($request->get_param('q'));
        $type = $request->get_param('type');
        $limit = $request->get_param('limit') ? (int) $request->get_param('limit') : 50;
        
        if (empty($query)) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'Query parameter q is required',
            ], 400);
        }
        
        $results = BITCORE_Knowledge_Graph::search($query, $type, $limit);
        return new WP_REST_Response([
            'success' => true,
            'data' => $results,
        ]);
    }
    
    // ==================== IDENTITY ENDPOINTS ====================
    
    /**
     * List registered identities
     */
    public static function list_identities($request) {
        $identities = BITCORE_Agent_Identity::list_identities();
        return new WP_REST_Response([
            'success' => true,
            'data' => $identities,
        ]);
    }
    
    /**
     * Register new agent identity
     */
    public static function register_identity($request) {
        $agent_id = sanitize_text_field($request->get_param('agent_id'));
        $public_key = $request->get_param('public_key');
        $algorithm = sanitize_text_field($request->get_param('algorithm')) ?: 'RSASSA-PKCS1-v1_5';
        
        if (empty($agent_id) || empty($public_key)) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'agent_id and public_key are required',
            ], 400);
        }
        
        $result = BITCORE_Agent_Identity::register_identity($agent_id, $public_key, $algorithm);
        return new WP_REST_Response([
            'success' => $result !== false,
            'agent_id' => $agent_id,
        ]);
    }
    
    /**
     * Revoke agent identity
     */
    public static function revoke_identity($request) {
        $id = sanitize_text_field($request->get_param('id'));
        $result = BITCORE_Agent_Identity::revoke_identity($id);
        
        return new WP_REST_Response([
            'success' => $result,
            'agent_id' => $id,
        ]);
    }
    
    // ==================== TOKEN ENDPOINTS ====================
    
    /**
     * Rotate API token
     */
    public static function rotate_token($request) {
        $new_state = BITCORE_Services_Token_Manager::rotate_token();
        return new WP_REST_Response([
            'success' => true,
            'token' => $new_state['token'],
            'previous_expires' => $new_state['previous_expires'],
        ]);
    }
    
    // ==================== PERMISSION CALLBACKS ====================
    
    /**
     * Check read permission
     */
    public static function check_read_permission() {
        return current_user_can('read') || self::verify_token_from_header();
    }
    
    /**
     * Check write permission
     */
    public static function check_write_permission() {
        return current_user_can('edit_posts') || self::verify_token_from_header();
    }
    
    /**
     * Check admin permission
     */
    public static function check_admin_permission() {
        return current_user_can('manage_options') || self::verify_token_from_header();
    }
    
    /**
     * Verify token from Authorization header
     */
    private static function verify_token_from_header() {
        $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.+)$/i', $auth_header, $matches)) {
            return BITCORE_Services_Token_Manager::validate_token($matches[1]);
        }
        return false;
    }
}
