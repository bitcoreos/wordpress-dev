<?php
declare(strict_types=1);
/**
 * REST API - Research Intelligence Endpoints
 * Workspace, Context Store, Learning Loop, Activity Feed
 *
 * @package BitCore
 * @since 1.1.0
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
trait EpisodeEndpoints {
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
}
