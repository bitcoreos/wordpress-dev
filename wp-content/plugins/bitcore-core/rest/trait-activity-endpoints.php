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
trait ActivityEndpoints {
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
}
