<?php
declare(strict_types=1);
/**
 * Workspace Endpoints Trait
 *
 * @package BitCore
 * @since 1.1.0
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait WorkspaceEndpoints {
    
    public static function list_workspaces($request) {
        $workspace = new BITCORE_Research_Workspace();
        $filters = [
            'org_id' => sanitize_text_field($request->get_param('org_id')),
            'agent_id' => sanitize_text_field($request->get_param('agent_id')),
            'status' => sanitize_text_field($request->get_param('status')),
            'limit' => $request->get_param('limit') ? (int) $request->get_param('limit') : 50,
        ];
        
        $results = $workspace->list($filters);
        
        return rest_ensure_response([
            'success' => true,
            'data' => $results,
        ]);
    }
    
    public static function create_workspace($request) {
        $workspace = new BITCORE_Research_Workspace();
        
        $data = [
            'name' => sanitize_text_field($request->get_param('name')),
            'org_id' => sanitize_text_field($request->get_param('org_id')),
            'agent_id' => sanitize_text_field($request->get_param('agent_id')),
            'config' => $request->get_param('config') ?: [],
        ];
        
        if (empty($data['name'])) {
            return new WP_Error('missing_name', 'Workspace name is required', ['status' => 400]);
        }
        
        $id = $workspace->create($data);
        
        if (is_wp_error($id)) {
            return $id;
        }
        
        return rest_ensure_response([
            'success' => true,
            'data' => ['id' => $id],
        ]);
    }
    
    public static function get_workspace($request) {
        $id = sanitize_text_field($request->get_param('id'));
        $workspace = new BITCORE_Research_Workspace();
        
        $result = $workspace->get($id);
        
        if (!$result) {
            return new WP_Error('not_found', 'Workspace not found', ['status' => 404]);
        }
        
        return rest_ensure_response([
            'success' => true,
            'data' => $result,
        ]);
    }
    
    public static function delete_workspace($request) {
        $id = sanitize_text_field($request->get_param('id'));
        $workspace = new BITCORE_Research_Workspace();
        
        $deleted = $workspace->delete($id);
        
        if (!$deleted) {
            return new WP_Error('delete_failed', 'Failed to delete workspace', ['status' => 500]);
        }
        
        return rest_ensure_response([
            'success' => true,
            'message' => 'Workspace deleted',
        ]);
    }
    
    public static function archive_workspace($request) {
        $id = sanitize_text_field($request->get_param('id'));
        $workspace = new BITCORE_Research_Workspace();
        
        $archived = $workspace->archive($id);
        
        if (!$archived) {
            return new WP_Error('archive_failed', 'Failed to archive workspace', ['status' => 500]);
        }
        
        return rest_ensure_response([
            'success' => true,
            'message' => 'Workspace archived',
        ]);
    }
}
