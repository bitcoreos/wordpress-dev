<?php
/**
 * BITCORE Research Intelligence - Workspaces
 *
 * @package    Bitcore
 * @since      2026.03.01
 * @version    1.3.0
 * @author     coraline
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}
class BITCORE_Rest_Research_Intelligence {
    
    const NAMESPACE = 'bitcore/v1';
    
    /**
     * Register REST routes
     */
    public static function register_routes() {
        
        // ==================== WORKSPACE ENDPOINTS ====================
        
        register_rest_route(self::NAMESPACE, '/workspaces', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'list_workspaces'],
            'permission_callback' => [__CLASS__, 'check_read_permission'],
        ]);
        
        register_rest_route(self::NAMESPACE, '/workspaces', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'create_workspace'],
            'permission_callback' => [__CLASS__, 'check_write_permission'],
        ]);
        
        register_rest_route(self::NAMESPACE, '/workspaces/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_workspace'],
            'permission_callback' => [__CLASS__, 'check_read_permission'],
        ]);
        
        register_rest_route(self::NAMESPACE, '/workspaces/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods' => 'DELETE',
            'callback' => [__CLASS__, 'delete_workspace'],
            'permission_callback' => [__CLASS__, 'check_admin_permission'],
        ]);
        
        register_rest_route(self::NAMESPACE, '/workspaces/(?P<id>[a-zA-Z0-9_-]+)/archive', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'archive_workspace'],
            'permission_callback' => [__CLASS__, 'check_write_permission'],
        ]);
        
        // ==================== CONTEXT STORE ENDPOINTS ====================
        
        register_rest_route(self::NAMESPACE, '/context', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'list_contexts'],
            'permission_callback' => [__CLASS__, 'check_read_permission'],
        ]);
        
        register_rest_route(self::NAMESPACE, '/context', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'store_context'],
            'permission_callback' => [__CLASS__, 'check_write_permission'],
        ]);
        
        register_rest_route(self::NAMESPACE, '/context/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_context'],
            'permission_callback' => [__CLASS__, 'check_read_permission'],
        ]);
        
        register_rest_route(self::NAMESPACE, '/context/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods' => 'DELETE',
            'callback' => [__CLASS__, 'delete_context'],
            'permission_callback' => [__CLASS__, 'check_admin_permission'],
        ]);
        
        register_rest_route(self::NAMESPACE, '/context/search', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'search_contexts'],
            'permission_callback' => [__CLASS__, 'check_read_permission'],
        ]);
        
        // ==================== LEARNING LOOP ENDPOINTS ====================
        
        register_rest_route(self::NAMESPACE, '/learning/episodes', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'list_episodes'],
            'permission_callback' => [__CLASS__, 'check_read_permission'],
        ]);
        
        register_rest_route(self::NAMESPACE, '/learning/episodes', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'record_episode'],
            'permission_callback' => [__CLASS__, 'check_write_permission'],
        ]);
        
        register_rest_route(self::NAMESPACE, '/learning/episodes/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_episode'],
            'permission_callback' => [__CLASS__, 'check_read_permission'],
        ]);
        
        register_rest_route(self::NAMESPACE, '/learning/score', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'compute_score'],
            'permission_callback' => [__CLASS__, 'check_write_permission'],
        ]);
        
        register_rest_route(self::NAMESPACE, '/learning/retention', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_retention'],
            'permission_callback' => [__CLASS__, 'check_read_permission'],
        ]);
        
        // ==================== ACTIVITY FEED ENDPOINTS ====================
        
        register_rest_route(self::NAMESPACE, '/activity/feed', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_activity_feed'],
            'permission_callback' => [__CLASS__, 'check_read_permission'],
        ]);
        
        register_rest_route(self::NAMESPACE, '/activity/digest', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_digest'],
            'permission_callback' => [__CLASS__, 'check_read_permission'],
        ]);
        
        register_rest_route(self::NAMESPACE, '/activity/aggregate', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'trigger_aggregation'],
            'permission_callback' => [__CLASS__, 'check_write_permission'],
        ]);
        
        register_rest_route(self::NAMESPACE, '/activity/stats', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_activity_stats'],
            'permission_callback' => [__CLASS__, 'check_read_permission'],
        ]);
    }
    
    // ==================== WORKSPACE ENDPOINTS ====================
    
    /**
     * List workspaces
     */
    public static function list_workspaces($request) {
        $workspace = new BITCORE_Research_Workspace();
        $filters = [
            'org_id' => sanitize_text_field($request->get_param('org_id')),
            'agent_id' => sanitize_text_field($request->get_param('agent_id')),
            'status' => sanitize_text_field($request->get_param('status')),
            'limit' => $request->get_param('limit') ? (int) $request->get_param('limit') : 50,
        ];
        
        $results = $workspace->list($filters);
        return new WP_REST_Response([
            'success' => true,
            'data' => $results,
            'count' => count($results),
        ]);
    }
    
    /**
     * Create workspace
     */
    public static function create_workspace($request) {
        $workspace = new BITCORE_Research_Workspace();
        
        $data = [
            'org_id' => sanitize_text_field($request->get_param('org_id')) ?: 'org_default',
            'agent_id' => sanitize_text_field($request->get_param('agent_id')),
            'name' => sanitize_text_field($request->get_param('name')),
            'summary' => sanitize_textarea_field($request->get_param('summary')),
            'config' => $request->get_param('config'),
        ];
        
        if (empty($data['agent_id']) || empty($data['name'])) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'agent_id and name are required',
            ], 400);
        }
        
        $result = $workspace->create($data);
        
        if (!$result) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'Failed to create workspace',
            ], 500);
        }
        
        return new WP_REST_Response([
            'success' => true,
            'data' => $result,
        ], 201);
    }
    
    /**
     * Get workspace
     */
    public static function get_workspace($request) {
        $id = sanitize_text_field($request->get_param('id'));
        $workspace = new BITCORE_Research_Workspace();
        $result = $workspace->get($id);
        
        if (!$result) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'Workspace not found',
            ], 404);
        }
        
        return new WP_REST_Response([
            'success' => true,
            'data' => $result,
        ]);
    }
    
    /**
     * Delete workspace
     */
    public static function delete_workspace($request) {
        $id = sanitize_text_field($request->get_param('id'));
        $workspace = new BITCORE_Research_Workspace();
        
        // Archive first if not already archived
        $existing = $workspace->get($id);
        if (!$existing) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'Workspace not found',
            ], 404);
        }
        
        $result = $workspace->update($id, ['status' => 'deleted']);
        
        return new WP_REST_Response([
            'success' => $result !== false,
            'workspace_id' => $id,
        ]);
    }
    
    /**
     * Archive workspace
     */
    public static function archive_workspace($request) {
        $id = sanitize_text_field($request->get_param('id'));
        $workspace = new BITCORE_Research_Workspace();
        
        $result = $workspace->archive($id);
        
        if (!$result) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'Failed to archive workspace',
            ], 500);
        }
        
        return new WP_REST_Response([
            'success' => true,
            'workspace_id' => $id,
            'status' => 'archived',
        ]);
    }
    
    // ==================== CONTEXT STORE ENDPOINTS ====================
    
    /**
     * List contexts
     */
    public static function list_contexts($request) {
        $context = new BITCORE_Context_Store();
        $filters = [
            'org_id' => sanitize_text_field($request->get_param('org_id')),
            'agent_id' => sanitize_text_field($request->get_param('agent_id')),
            'thread_id' => sanitize_text_field($request->get_param('thread_id')),
            'content_type' => sanitize_text_field($request->get_param('content_type')),
            'limit' => $request->get_param('limit') ? (int) $request->get_param('limit') : 50,
        ];
        
        $results = $context->search('', $filters);
        return new WP_REST_Response([
            'success' => true,
            'data' => $results,
            'count' => count($results),
        ]);
    }
    
    /**
     * Store context
     */
    public static function store_context($request) {
        $context = new BITCORE_Context_Store();
        
        $data = [
            'org_id' => sanitize_text_field($request->get_param('org_id')) ?: 'org_default',
            'agent_id' => sanitize_text_field($request->get_param('agent_id')),
            'thread_id' => sanitize_text_field($request->get_param('thread_id')),
            'content_type' => sanitize_text_field($request->get_param('content_type')) ?: 'text',
            'content' => $request->get_param('content'),
            'embedding' => $request->get_param('embedding'),
            'metadata' => $request->get_param('metadata'),
            'relevance_score' => $request->get_param('relevance_score') ? (float) $request->get_param('relevance_score') : 0,
        ];
        
        if (empty($data['agent_id']) || empty($data['content'])) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'agent_id and content are required',
            ], 400);
        }
        
        $result = $context->store($data);
        
        if (!$result) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'Failed to store context',
            ], 500);
        }
        
        return new WP_REST_Response([
            'success' => true,
            'data' => $result,
        ], 201);
    }
    
    /**
     * Get context
     */
    public static function get_context($request) {
        $id = sanitize_text_field($request->get_param('id'));
        $context = new BITCORE_Context_Store();
        $result = $context->get($id);
        
        if (!$result) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'Context not found',
            ], 404);
        }
        
        return new WP_REST_Response([
            'success' => true,
            'data' => $result,
        ]);
    }
    
    /**
     * Delete context
     */
    public static function delete_context($request) {
        $id = sanitize_text_field($request->get_param('id'));
        $context = new BITCORE_Context_Store();
        
        $result = $context->delete($id);
        
        return new WP_REST_Response([
            'success' => $result,
            'context_id' => $id,
        ]);
    }
    
    /**
     * Search contexts (for RAG)
     */
    public static function search_contexts($request) {
        $context = new BITCORE_Context_Store();
        
        $query = sanitize_text_field($request->get_param('query'));
        $filters = [
            'org_id' => sanitize_text_field($request->get_param('org_id')),
            'agent_id' => sanitize_text_field($request->get_param('agent_id')),
            'thread_id' => sanitize_text_field($request->get_param('thread_id')),
            'content_type' => sanitize_text_field($request->get_param('content_type')),
            'limit' => $request->get_param('limit') ? (int) $request->get_param('limit') : 20,
        ];
        
        $results = $context->search($query, $filters);
        
        return new WP_REST_Response([
            'success' => true,
            'data' => $results,
            'count' => count($results),
            'query' => $query,
        ]);
    }
    
    // ==================== LEARNING LOOP ENDPOINTS ====================
    
    /**
     * List episodes
     */
