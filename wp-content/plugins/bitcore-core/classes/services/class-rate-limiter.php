<?php
declare(strict_types=1);
/**
 * Rate Limiter - Request rate limiting
 * Pattern extracted from clawd-control
 * 
 * @package BitCore
 * @license MIT
 */

if (!defined('ABSPATH')) {
    exit;
}

class BITCORE_Services_Rate_Limiter {
    
    const WINDOW_SECONDS = 60;
    const MAX_ATTEMPTS = 5;
    
    /**
     * Get attempt timestamps for identifier
     *
     * @param string $identifier Unique identifier (IP, user_id, etc)
     * @return array Array of timestamps
     */
    private static function get_attempts($identifier) {
        $key = "bitcore_rate_limit_{$identifier}";
        $attempts = get_transient($key);
        return is_array($attempts) ? $attempts : [];
    }
    
    /**
     * Check if request is within rate limit
     *
     * @param string $identifier Unique identifier
     * @return bool True if allowed, false if rate limited
     */
    public static function check_rate_limit($identifier) {
        $attempts = self::get_attempts($identifier);
        $now = time();
        
        // Filter to only recent attempts within window
        $recent = array_filter($attempts, function($t) use ($now) {
            return ($now - $t) < self::WINDOW_SECONDS;
        });
        
        // Check if limit exceeded
        if (count($recent) >= self::MAX_ATTEMPTS) {
            return false; // Rate limited
        }
        
        // Record this attempt
        $recent[] = $now;
        $key = "bitcore_rate_limit_{$identifier}";
        set_transient($key, $recent, self::WINDOW_SECONDS);
        
        return true;
    }
    
    /**
     * Get remaining attempts for identifier
     *
     * @param string $identifier Unique identifier
     * @return int Number of remaining attempts
     */
    public static function get_remaining_attempts($identifier) {
        $attempts = self::get_attempts($identifier);
        $now = time();
        $recent = array_filter($attempts, function($t) use ($now) {
            return ($now - $t) < self::WINDOW_SECONDS;
        });
        return max(0, self::MAX_ATTEMPTS - count($recent));
    }
    
    /**
     * Get seconds until rate limit resets
     *
     * @param string $identifier Unique identifier
     * @return int Seconds until reset
     */
    public static function get_reset_time($identifier) {
        $attempts = self::get_attempts($identifier);
        if (empty($attempts)) {
            return 0;
        }
        
        $now = time();
        $recent = array_filter($attempts, function($t) use ($now) {
            return ($now - $t) < self::WINDOW_SECONDS;
        });
        
        if (empty($recent)) {
            return 0;
        }
        
        $oldest = min($recent);
        return max(0, self::WINDOW_SECONDS - ($now - $oldest));
    }
    
    /**
     * Clear rate limit for identifier
     *
     * @param string $identifier Unique identifier
     */
    public static function clear_rate_limit($identifier) {
        delete_transient("bitcore_rate_limit_{$identifier}");
    }
    
    /**
     * Configure rate limit parameters
     *
     * @param int $max_attempts Maximum attempts per window
     * @param int $window_seconds Window duration in seconds
     */
    public static function configure($max_attempts = 5, $window_seconds = 60) {
        // These could be stored in options for dynamic configuration
        // For now, constants are used but this allows future extension
    }
}
