<?php
/**
 * BITCORE_Health_Snapshot class for Bitcore
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
class BITCORE_Health_Snapshot {
    
    const TRANSIENT_KEY = 'bitcore_health_snapshot';
    const CACHE_TTL = 60; // 60 seconds
    
    /**
     * Capture current system health snapshot
     *
     * @return array Health snapshot data
     */
    public static function capture() {
        $snapshot = [
            'timestamp' => current_time('mysql'),
            'timestamp_utc' => gmdate('Y-m-d H:i:s'),
            'health_score' => self::calculate_score(),
            'active_agents' => self::count_active_agents(),
            'pending_tasks' => self::count_pending_tasks(),
            'api_response_time' => self::measure_api_latency(),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'db_queries' => get_num_queries(),
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version'),
            'plugins_active' => count(get_option('active_plugins', [])),
        ];
        
        // Store snapshot
        set_transient(self::TRANSIENT_KEY, $snapshot, self::CACHE_TTL);
        
        /**
         * Action hook for health snapshot captured
         *
         * @param array $snapshot Health snapshot data
         */
        do_action('bitcore_health_snapshot_captured', $snapshot);
        
        return $snapshot;
    }
    
    /**
     * Get latest health snapshot
     *
     * @param bool $refresh Force new capture
     * @return array|null Snapshot data or null
     */
    public static function get_latest($refresh = false) {
        if ($refresh) {
            return self::capture();
        }
        return get_transient(self::TRANSIENT_KEY);
    }
    
    /**
     * Calculate overall health score (0-100)
     *
     * @return int Health score
     */
    public static function calculate_score() {
        $score = 100;
        
        // Deduct for memory pressure
        $memory_usage = memory_get_usage(true);
        $memory_limit = self::parse_memory_limit(ini_get('memory_limit'));
        if ($memory_limit > 0) {
            $memory_percent = ($memory_usage / $memory_limit) * 100;
            if ($memory_percent > 90) {
                $score -= 30;
            } elseif ($memory_percent > 75) {
                $score -= 15;
            }
        }
        
        // Deduct for pending tasks backlog
        $pending = self::count_pending_tasks();
        if ($pending > 100) {
            $score -= 20;
        } elseif ($pending > 50) {
            $score -= 10;
        }
        
        // Deduct for API latency
        $latency = self::measure_api_latency();
        if ($latency > 1000) {
            $score -= 20;
        } elseif ($latency > 500) {
            $score -= 10;
        }
        
        return max(0, min(100, $score));
    }
    
    /**
     * Count active agents
     *
     * @return int Number of active agents
     */
    private static function count_active_agents() {
        global $wpdb;
        $table = $wpdb->prefix . 'bitcore_agents';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table;
        if (!$table_exists) {
            return 0;
        }
        
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE status = 'active' AND last_heartbeat > DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
        );
    }
    
    /**
     * Count pending tasks
     *
     * @return int Number of pending tasks
     */
    private static function count_pending_tasks() {
        global $wpdb;
        $table = $wpdb->prefix . 'bitcore_tasks';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table;
        if (!$table_exists) {
            return 0;
        }
        
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE status = 'pending'"
        );
    }
    
    /**
     * Measure API latency
     *
     * @return int Latency in milliseconds
     */
    private static function measure_api_latency() {
        $start = microtime(true);
        
        // Simple internal API ping
        $rest_url = rest_url('bitcore/v1/ping');
        $response = wp_remote_get($rest_url, [
            'timeout' => 5,
            'sslverify' => false,
        ]);
        
        $end = microtime(true);
        
        if (is_wp_error($response)) {
            return -1; // Error indicator
        }
        
        return (int) (($end - $start) * 1000);
    }
    
    /**
     * Parse PHP memory limit string to bytes
     *
     * @param string $limit Memory limit string (e.g., '256M')
     * @return int Bytes
     */
    private static function parse_memory_limit($limit) {
        if ($limit === '-1') {
            return PHP_INT_MAX;
        }
        
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $value = (int) $limit;
        
        switch ($last) {
            case 'g':
                $value *= 1024;
                // fall through
            case 'm':
                $value *= 1024;
                // fall through
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }
    
    /**
     * Get health status label
     *
     * @param int $score Health score
     * @return string Status label
     */
    public static function get_status_label($score) {
        if ($score >= 90) {
            return 'excellent';
        } elseif ($score >= 75) {
            return 'good';
        } elseif ($score >= 50) {
            return 'fair';
        } elseif ($score >= 25) {
            return 'poor';
        }
        return 'critical';
    }
}
