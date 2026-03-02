<?php
/**
 * BITCORE_Services_Token_Manager class for Bitcore
 *
 * @package    Bitcore
 * @since      2026.03.01
 * @version    1.3.0
 * @author     hyperion
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}
class BITCORE_Services_Token_Manager {
    
    const TOKEN_MAX_AGE_MS = 604800000;  // 7 days in ms
    const TOKEN_GRACE_MS = 3600000;       // 1 hour grace
    
    /**
     * Get current token state from options
     *
     * @return array Token state array
     */
    private static function get_token_state() {
        $defaults = [
            'token' => wp_generate_password(64, false),
            'created' => time(),
            'previous_token' => null,
            'previous_expires' => null
        ];
        return get_option('bitcore_token_state', $defaults);
    }
    
    /**
     * Rotate the current token, keeping previous for grace period
     *
     * @return array New token state
     */
    public static function rotate_token() {
        $old_state = self::get_token_state();
        $new_state = [
            'token' => wp_generate_password(64, false),
            'created' => time(),
            'previous_token' => $old_state['token'],
            'previous_expires' => time() + 3600 // 1 hour grace
        ];
        update_option('bitcore_token_state', $new_state);
        
        /**
         * Action hook for token rotation events
         * 
         * @param array $new_state New token state
         * @param array $old_state Previous token state
         */
        do_action('bitcore_token_rotated', $new_state, $old_state);
        
        return $new_state;
    }
    
    /**
     * Validate a token against current or previous (within grace)
     *
     * @param string $candidate Token to validate
     * @return bool True if valid
     */
    public static function validate_token($candidate) {
        if (empty($candidate)) {
            return false;
        }
        
        $state = self::get_token_state();
        
        // Check current token using timing-safe comparison
        if (hash_equals($state['token'], $candidate)) {
            return true;
        }
        
        // Check previous token within grace period
        if (!empty($state['previous_token']) && !empty($state['previous_expires'])) {
            if ($state['previous_expires'] > time()) {
                return hash_equals($state['previous_token'], $candidate);
            }
        }
        
        return false;
    }
    
    /**
     * Get current token (for internal use only)
     *
     * @return string|null Current token
     */
    public static function get_current_token() {
        $state = self::get_token_state();
        return $state['token'] ?? null;
    }
    
    /**
     * Check if token needs rotation based on age
     *
     * @return bool True if rotation needed
     */
    public static function needs_rotation() {
        $state = self::get_token_state();
        $age_ms = (time() - $state['created']) * 1000;
        return $age_ms >= self::TOKEN_MAX_AGE_MS;
    }
    
    /**
     * Auto-rotate if needed
     *
     * @return array Current token state
     */
    public static function ensure_valid_token() {
        if (self::needs_rotation()) {
            return self::rotate_token();
        }
        return self::get_token_state();
    }
}
