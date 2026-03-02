<?php
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
    public static function list_episodes($request) {
        $learning = new BITCORE_Learning_Loop();
        $org_id = sanitize_text_field($request->get_param('org_id')) ?: 'org_default';
        $filters = [
            'agent_id' => sanitize_text_field($request->get_param('agent_id')),
            'action_type' => sanitize_text_field($request->get_param('action_type')),
            'limit' => $request->get_param('limit') ? (int) $request->get_param('limit') : 100,
        ];
        
        $results = $learning->list_episodes($org_id, $filters);
        return new WP_REST_Response([
            'success' => true,
            'data' => $results,
            'count' => count($results),
        ]);
    }
    
    /**
     * Record episode
     */
    public static function record_episode($request) {
        $learning = new BITCORE_Learning_Loop();
        
        $org_id = sanitize_text_field($request->get_param('org_id')) ?: 'org_default';
        $source = [
            'action_id' => sanitize_text_field($request->get_param('action_id')),
            'agent_id' => sanitize_text_field($request->get_param('agent_id')),
            'action_type' => sanitize_text_field($request->get_param('action_type')),
            'status' => sanitize_text_field($request->get_param('status')),
            'risk_score' => $request->get_param('risk_score') ? (float) $request->get_param('risk_score') : 0,
            'reversible' => $request->get_param('reversible') !== false,
            'confidence' => $request->get_param('confidence') ? (float) $request->get_param('confidence') : 50,
            'duration_ms' => $request->get_param('duration_ms') ? (int) $request->get_param('duration_ms') : null,
            'cost_estimate' => $request->get_param('cost_estimate') ? (float) $request->get_param('cost_estimate') : 0,
            'invalidated_assumptions' => $request->get_param('invalidated_assumptions') ? (int) $request->get_param('invalidated_assumptions') : 0,
            'open_loops' => $request->get_param('open_loops') ? (int) $request->get_param('open_loops') : 0,
            'recommendation_id' => sanitize_text_field($request->get_param('recommendation_id')),
            'recommendation_applied' => $request->get_param('recommendation_applied') ? 1 : 0,
            'timestamp_start' => sanitize_text_field($request->get_param('timestamp_start')),
        ];
        
        $scored = [
            'outcome_label' => sanitize_text_field($request->get_param('outcome_label')),
            'score' => $request->get_param('score') ? (float) $request->get_param('score') : 0,
            'breakdown' => $request->get_param('score_breakdown'),
        ];
        
        if (empty($source['action_id']) || empty($source['agent_id']) || empty($source['action_type'])) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'action_id, agent_id, and action_type are required',
            ], 400);
        }
        
        $result = $learning->upsert_episode($org_id, $source, $scored);
        
        if (!$result) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'Failed to record episode',
            ], 500);
        }
        
        return new WP_REST_Response([
            'success' => true,
            'data' => $result,
        ], 201);
    }
    
    /**
     * Get episode
     */
    public static function get_episode($request) {
        $id = sanitize_text_field($request->get_param('id'));
        $learning = new BITCORE_Learning_Loop();
        $result = $learning->get_episode($id);
        
        if (!$result) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'Episode not found',
            ], 404);
        }
        
        return new WP_REST_Response([
            'success' => true,
            'data' => $result,
        ]);
    }
    
    /**
     * Compute score for actions
     */
    public static function compute_score($request) {
        $org_id = sanitize_text_field($request->get_param('org_id')) ?: 'org_default';
        $action_ids = $request->get_param('action_ids');
        
        if (empty($action_ids) || !is_array($action_ids)) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'action_ids array is required',
            ], 400);
        }
        
        // Score computation logic
        $scores = [];
        foreach ($action_ids as $action_id) {
            $scores[$action_id] = [
                'action_id' => sanitize_text_field($action_id),
                'computed_score' => rand(0, 100) / 100, // Placeholder - implement actual scoring logic
                'computed_at' => current_time('mysql'),
            ];
        }
        
        return new WP_REST_Response([
            'success' => true,
            'data' => $scores,
            'count' => count($scores),
        ]);
    }
    
    /**
     * Get knowledge retention report
     */
    public static function get_retention($request) {
        $learning = new BITCORE_Learning_Loop();
        $org_id = sanitize_text_field($request->get_param('org_id')) ?: 'org_default';
        $filters = [
            'agent_id' => sanitize_text_field($request->get_param('agent_id')),
        ];
        
        $episodes = $learning->list_episodes($org_id, ['limit' => 1000] + $filters);
        $recommendations = $learning->list_recommendations($org_id, $filters);
        
        // Calculate retention metrics
        $total_episodes = count($episodes);
        $avg_score = 0;
        $score_sum = 0;
        $by_outcome = [];
        
        foreach ($episodes as $episode) {
            $score_sum += $episode->score ?? 0;
            $outcome = $episode->outcome_label ?? 'unknown';
            $by_outcome[$outcome] = ($by_outcome[$outcome] ?? 0) + 1;
        }
        
        $avg_score = $total_episodes > 0 ? $score_sum / $total_episodes : 0;
        
        return new WP_REST_Response([
            'success' => true,
            'data' => [
                'org_id' => $org_id,
                'total_episodes' => $total_episodes,
                'average_score' => round($avg_score, 4),
                'by_outcome' => $by_outcome,
                'recommendations_count' => count($recommendations),
                'recommendations' => $recommendations,
                'generated_at' => current_time('mysql'),
            ],
        ]);
    }
    
    // ==================== ACTIVITY FEED ENDPOINTS ====================
    
    /**
     * Get activity feed
     */
    public static function get_activity_feed($request) {
        $activity = new BITCORE_Activity_Feed();
        $org_id = sanitize_text_field($request->get_param('org_id')) ?: 'org_default';
        
        $options = [
            'agent_id' => sanitize_text_field($request->get_param('agent_id')),
            'date' => sanitize_text_field($request->get_param('date')),
        ];
        
        $results = $activity->fetch_digest($org_id, $options);
        
        // Calculate totals
        $totals = [];
        foreach ($results as $key => $items) {
            $totals[$key] = count($items);
        }
        
        return new WP_REST_Response([
            'success' => true,
            'data' => $results,
            'totals' => $totals,
        ]);
    }
    
    /**
     * Get digest
     */
    public static function get_digest($request) {
        $activity = new BITCORE_Activity_Feed();
        $org_id = sanitize_text_field($request->get_param('org_id')) ?: 'org_default';
        
        $date = sanitize_text_field($request->get_param('date'));
        if (!$date) {
            $date = date('Y-m-d');
        }
        
        $options = [
            'agent_id' => sanitize_text_field($request->get_param('agent_id')),
            'date' => $date,
        ];
        
        $results = $activity->fetch_digest($org_id, $options);
        
        return new WP_REST_Response([
            'success' => true,
            'data' => $results,
            'date' => $date,
            'org_id' => $org_id,
        ]);
    }
    
    /**
     * Trigger aggregation
     */
    public static function trigger_aggregation($request) {
        $activity = new BITCORE_Activity_Feed();
        
        $data = [
            'org_id' => sanitize_text_field($request->get_param('org_id')) ?: 'org_default',
            'agent_id' => sanitize_text_field($request->get_param('agent_id')),
            'action_type' => sanitize_text_field($request->get_param('action_type')),
            'declared_goal' => sanitize_textarea_field($request->get_param('declared_goal')),
            'status' => sanitize_text_field($request->get_param('status')) ?: 'pending',
            'risk_score' => $request->get_param('risk_score') ? (float) $request->get_param('risk_score') : 0,
            'reversible' => $request->get_param('reversible') !== false,
            'confidence' => $request->get_param('confidence') ? (float) $request->get_param('confidence') : 50,
        ];
        
        if (empty($data['agent_id']) || empty($data['action_type'])) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'agent_id and action_type are required',
            ], 400);
        }
        
        $action_id = $activity->log_activity($data);
        
        if (!$action_id) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'Failed to log activity',
            ], 500);
        }
        
        return new WP_REST_Response([
            'success' => true,
            'action_id' => $action_id,
        ], 201);
    }
    
    /**
     * Get activity statistics
     */
    public static function get_activity_stats($request) {
        $activity = new BITCORE_Activity_Feed();
        $org_id = sanitize_text_field($request->get_param('org_id')) ?: 'org_default';
        
        $start_date = sanitize_text_field($request->get_param('start_date')) ?: date('Y-m-d', strtotime('-7 days'));
        $end_date = sanitize_text_field($request->get_param('end_date')) ?: date('Y-m-d');
        
        $options = [
            'agent_id' => sanitize_text_field($request->get_param('agent_id')),
        ];
        
        $summary = $activity->get_summary($org_id, $start_date, $end_date, $options);
        
        return new WP_REST_Response([
            'success' => true,
            'data' => $summary,
            'period' => [
                'start_date' => $start_date,
                'end_date' => $end_date,
            ],
            'org_id' => $org_id,
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
