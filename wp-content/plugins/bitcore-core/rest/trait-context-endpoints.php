<?php
declare(strict_types=1);
/**
 * REST API - Research Intelligence Endpoints
 * Workspace, Context Store, Learning Loop, Activity Feed
 *
 * @package BitCore
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait ContextEndpoints {
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
}
