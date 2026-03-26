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

// Load trait files
require_once __DIR__ . '/trait-workspace-endpoints.php';
require_once __DIR__ . '/trait-context-endpoints.php';
require_once __DIR__ . '/trait-episode-endpoints.php';
require_once __DIR__ . '/trait-learning-endpoints.php';
require_once __DIR__ . '/trait-activity-endpoints.php';
require_once __DIR__ . '/trait-permissions.php';

class BITCORE_Rest_Research_Intelligence {
    
    use WorkspaceEndpoints;
    use ContextEndpoints;
    use EpisodeEndpoints;
    use LearningEndpoints;
    use ActivityEndpoints;
    use Permissions;
    
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
        
        // ==================== LEARNING LOOP ENDPOINTS ====================
        
        register_rest_route(self::NAMESPACE, '/episodes', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'list_episodes'],
            'permission_callback' => [__CLASS__, 'check_read_permission'],
        ]);
        
        register_rest_route(self::NAMESPACE, '/episodes', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'record_episode'],
            'permission_callback' => [__CLASS__, 'check_write_permission'],
        ]);
        
        register_rest_route(self::NAMESPACE, '/episodes/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_episode'],
            'permission_callback' => [__CLASS__, 'check_read_permission'],
        ]);
        
        register_rest_route(self::NAMESPACE, '/score', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'compute_score'],
            'permission_callback' => [__CLASS__, 'check_write_permission'],
        ]);
        
        register_rest_route(self::NAMESPACE, '/retention', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_retention'],
            'permission_callback' => [__CLASS__, 'check_read_permission'],
        ]);
        
        // ==================== ACTIVITY FEED ENDPOINTS ====================
        
        register_rest_route(self::NAMESPACE, '/activity', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_activity_feed'],
            'permission_callback' => [__CLASS__, 'check_read_permission'],
        ]);
        
        register_rest_route(self::NAMESPACE, '/digest', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_digest'],
            'permission_callback' => [__CLASS__, 'check_read_permission'],
        ]);
        
        register_rest_route(self::NAMESPACE, '/aggregate', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'trigger_aggregation'],
            'permission_callback' => [__CLASS__, 'check_admin_permission'],
        ]);
        
        register_rest_route(self::NAMESPACE, '/stats', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_activity_stats'],
            'permission_callback' => [__CLASS__, 'check_read_permission'],
        ]);
    }
}
