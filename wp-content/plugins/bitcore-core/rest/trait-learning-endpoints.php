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
trait LearningEndpoints {
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
}
