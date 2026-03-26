<?php
/**
 * BITCORE Research Intelligence - Contexts & Episodes
 *
 * @package    Bitcore
 * @since      2026.03.01
 * @version    1.3.0
 * @author     coraline
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;

class BITCORE_Research_Intelligence_Contexts {

}
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