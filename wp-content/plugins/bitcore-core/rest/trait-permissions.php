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
trait Permissions {
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

