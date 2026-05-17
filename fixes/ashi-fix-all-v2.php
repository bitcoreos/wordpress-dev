<?php
/**
 * ASHI Comprehensive Fix All Script - v2
 * Runs inside wordpress-app Docker container on ASHI server
 * Patches all 6 April 23 fixes that were wiped by failed deploys
 */

$plugin_dir = '/var/www/html/wp-content/plugins/bitcore-core/';
$errors = [];
$fixes = [];

function log_fix($msg) { global $fixes; $fixes[] = $msg; echo "[FIX] $msg\n"; }
function log_error($msg) { global $errors; $errors[] = $msg; echo "[ERROR] $msg\n"; }

// ============================================
// FIX 1: core.php - Comment out broken services
// ============================================
echo "=== FIX 1: core.php broken services ===\n";
$core_file = $plugin_dir . 'classes/core.php';
if (file_exists($core_file)) {
    $content = file_get_contents($core_file);
    $original = $content;
    
    // Remove the broken service registrations (lines 85-99 equivalent)
    // Pattern: everything from "// === Hyperion: Infrastructure Services ===" 
    // through the last broken registration before webhooks
    
    $search_start = "        // === Hyperion: Infrastructure Services ===";
    $search_end = "        require_once BITCORE_PATH . 'infrastructure/webhooks/WebhookManager.php';";
    
    $pos_start = strpos($content, $search_start);
    $pos_end = strpos($content, $search_end);
    
    if ($pos_start !== false && $pos_end !== false) {
        // Everything between these two markers is broken - remove it
        $before = substr($content, 0, $pos_start);
        $after = substr($content, $pos_end);
        
        $replacement = "        // === Hyperion: Infrastructure Services ===\n"
                     . "        // DISABLED: 6 infrastructure files MISSING - commented out until built\n"
                     . "        // Missing: Auth/AuthManager.php, Auth/CapabilityManager.php, MultiTenancy/TenantResolver.php,\n"
                     . "        //          Cache/CacheManager.php, Logging/Logger.php, RateLimit/RateLimitManager.php\n"
                     . "        // QueueWorker + Logger chain also disabled (depends on missing files)\n"
                     . "        // Cron hooks bitcore_queue_worker + bitcore_health_check also disabled in bitcore-core.php\n";
        
        $content = $before . $replacement . $after;
        
        if ($content !== $original) {
            file_put_contents($core_file, $content);
            log_fix("core.php: commented out broken service registrations");
        } else {
            log_error("core.php: no changes applied");
        }
    } else {
        log_error("core.php: markers not found (pos_start=" . ($pos_start===false?'false':$pos_start) . " pos_end=" . ($pos_end===false?'false':$pos_end) . ")");
    }
} else {
    log_error("core.php not found at $core_file");
}

// ============================================
// FIX 2: bitcore-core.php - Disable broken crons
// ============================================
echo "=== FIX 2: bitcore-core.php broken crons ===\n";
$bitcore_file = $plugin_dir . 'bitcore-core.php';
if (file_exists($bitcore_file)) {
    $content = file_get_contents($bitcore_file);
    $original = $content;
    
    // Fix queue worker cron
    $old_qw = "function bitcore_queue_worker_cron(): void {";
    if (strpos($content, $old_qw) !== false) {
        // Comment out the function by wrapping its body in /* */
        $content = str_replace(
            "function bitcore_queue_worker_cron(): void {\n    if (isset(\$GLOBALS['bitcore']) && \$GLOBALS['bitcore']->container) {\n        \$worker = \$GLOBALS['bitcore']->container->get('queue.worker');\n        if (\$worker) {\n            \$worker->process(10);\n        }\n    }\n}",
            "// DISABLED: depends on missing services\n/* function bitcore_queue_worker_cron(): void {\n    if (isset(\$GLOBALS['bitcore']) && \$GLOBALS['bitcore']->container) {\n        \$worker = \$GLOBALS['bitcore']->container->get('queue.worker');\n        if (\$worker) {\n            \$worker->process(10);\n        }\n    }\n} */",
            $content
        );
        // Also comment out the add_action
        $content = str_replace(
            "add_action('bitcore_queue_worker', 'bitcore_queue_worker_cron');",
            "// add_action('bitcore_queue_worker', 'bitcore_queue_worker_cron'); // DISABLED",
            $content
        );
    }
    
    // Fix health check cron
    $old_hc = "function bitcore_health_check_cron(): void {";
    if (strpos($content, $old_hc) !== false) {
        $content = str_replace(
            "function bitcore_health_check_cron(): void {\n    if (isset(\$GLOBALS['bitcore']) && \$GLOBALS['bitcore']->container) {\n        \$logger = \$GLOBALS['bitcore']->container->get('logger');\n        if (\$logger) {\n            \$health = [\n                'timestamp' => time(),\n                'memory'    => memory_get_usage(true),\n                'peak'      => memory_get_peak_usage(true),\n                'queue'     => \$GLOBALS['wpdb']->get_var(\"SELECT COUNT(*) FROM {\$GLOBALS['wpdb']->prefix}bitcore_jobs WHERE status = 'pending'\"),\n            ];\n            \$logger->info('Health check completed', \$health);\n        }\n    }\n}",
            "// DISABLED: depends on missing services\n/* function bitcore_health_check_cron(): void {\n    if (isset(\$GLOBALS['bitcore']) && \$GLOBALS['bitcore']->container) {\n        \$logger = \$GLOBALS['bitcore']->container->get('logger');\n        if (\$logger) {\n            \$health = [\n                'timestamp' => time(),\n                'memory'    => memory_get_usage(true),\n                'peak'      => memory_get_peak_usage(true),\n                'queue'     => \$GLOBALS['wpdb']->get_var(\"SELECT COUNT(*) FROM {\$GLOBALS['wpdb']->prefix}bitcore_jobs WHERE status = 'pending'\"),\n            ];\n            \$logger->info('Health check completed', \$health);\n        }\n    }\n} */",
            $content
        );
        $content = str_replace(
            "add_action('bitcore_health_check', 'bitcore_health_check_cron');",
            "// add_action('bitcore_health_check', 'bitcore_health_check_cron'); // DISABLED",
            $content
        );
    }
    
    if ($content !== $original) {
        file_put_contents($bitcore_file, $content);
        log_fix("bitcore-core.php: disabled broken cron hooks");
    } else {
        log_fix("bitcore-core.php: cron hooks already disabled (no changes needed)");
    }
    
    // Now add missing route registrations if not present
    $content2 = file_get_contents($bitcore_file);
    $original2 = $content2;
    
    if (strpos($content2, "BITCORE_Rest_MCP_Tools_API") === false) {
        // Add route registrations after the last rest_api_init hook
        $insert_after = "add_action('rest_api_init', ['BITCORE_Rest_Sse_Events', 'register_routes']);";
        $insert_code = "\n// === ADDED by ashi-fix-all-v2.php: Route registrations ===\n"
                     . "add_action('rest_api_init', function() {\n"
                     . "    if (class_exists('BITCORE_Rest_MCP_Tools_API')) {\n"
                     . "        (new BITCORE_Rest_MCP_Tools_API())->register_routes();\n"
                     . "    }\n"
                     . "    if (class_exists('BITCORE_Coordination_API')) {\n"
                     . "        (new BITCORE_Coordination_API())->register_routes();\n"
                     . "    }\n"
                     . "});\n";
        
        $content2 = str_replace($insert_after, $insert_after . $insert_code, $content2);
        
        if ($content2 !== $original2) {
            file_put_contents($bitcore_file, $content2);
            log_fix("bitcore-core.php: added MCP Tools + Coordination route registrations");
        }
    } else {
        log_fix("bitcore-core.php: route registrations already present");
    }
} else {
    log_error("bitcore-core.php not found");
}

// ============================================
// FIX 3: class-missions-api.php - Remove return types
// ============================================
echo "=== FIX 3: missions-api.php return types ===\n";
$missions_file = $plugin_dir . 'rest/class-missions-api.php';
if (file_exists($missions_file)) {
    $content = file_get_contents($missions_file);
    $original = $content;
    
    // Remove all : \WP_REST_Response return types from methods
    $content = str_replace(': \\WP_REST_Response {', ' {', $content);
    
    if ($content !== $original) {
        file_put_contents($missions_file, $content);
        log_fix("class-missions-api.php: removed strict return types");
    } else {
        log_fix("class-missions-api.php: return types already fixed");
    }
} else {
    log_error("class-missions-api.php not found");
}

// ============================================
// FIX 4: sse-events.php - Fix class reference
// ============================================
echo "=== FIX 4: sse-events.php class reference ===\n";
$sse_file = $plugin_dir . 'rest/sse-events.php';
if (file_exists($sse_file)) {
    $content = file_get_contents($sse_file);
    $original = $content;
    
    $content = str_replace('BitCore_Auth_Filter::validate_api_key', 'BITCORE_Rest_Auth_Filter::validate_api_key', $content);
    
    if ($content !== $original) {
        file_put_contents($sse_file, $content);
        log_fix("sse-events.php: fixed class reference");
    } else {
        log_fix("sse-events.php: class reference already fixed");
    }
} else {
    log_error("sse-events.php not found");
}

// ============================================
// FIX 5: auth-filter.php - Make public static
// ============================================
echo "=== FIX 5: auth-filter.php visibility ===\n";
$auth_file = $plugin_dir . 'rest/auth-filter.php';
if (file_exists($auth_file)) {
    $content = file_get_contents($auth_file);
    $original = $content;
    
    $content = str_replace(
        'private function validate_api_key( $api_key ) {',
        'public static function validate_api_key( $api_key ) {',
        $content
    );
    
    if ($content !== $original) {
        file_put_contents($auth_file, $content);
        log_fix("auth-filter.php: made validate_api_key public static");
    } else {
        log_fix("auth-filter.php: already public static");
    }
} else {
    log_error("auth-filter.php not found");
}

// ============================================
// FIX 6: Remove nonce checks from permission files
// ============================================
echo "=== FIX 6: permission files nonce removals ===\n";
$perm_files = [
    $plugin_dir . 'rest/tasks.php',
    $plugin_dir . 'rest/trait-rest-tasks-permissions.php',
    $plugin_dir . 'rest/class-rest-tasks-permissions.php',
];

foreach ($perm_files as $perm_file) {
    if (file_exists($perm_file)) {
        $content = file_get_contents($perm_file);
        $original = $content;
        
        // Match and remove nonce blocks
        $pattern = '/\s*\$nonce = \$request->get_header\s*\(\s*[\'"]X-WP-Nonce[\'"]\s*\);\s*if\s*\(\s*!\s*\$nonce\s*\|\|\s*!\s*wp_verify_nonce\s*\(\s*\$nonce\s*,\s*[\'"]wp_rest[\'"]\s*\)\s*\)\s*\{\s*return\s+new\s+\\WP_Error\s*\(\s*[\'"]rest_forbidden[\'"],\s*__\s*\(\s*[\'"](?:Invalid or missing nonce\.|You do not have permission)[^\'"]*[\'"]\s*(?:,\s*[\'"][^\'"]*[\'"]\s*)?\),\s*(?:array\s*\(|\[)\s*[\'"]status[\'"]\s*=>\s*403\s*(?:\)|\])\s*\);\s*\}/s';
        
        $content = preg_replace($pattern, '', $content);
        
        if ($content !== $original) {
            file_put_contents($perm_file, $content);
            log_fix(basename($perm_file) . ": removed nonce checks");
        } else {
            log_fix(basename($perm_file) . ": nonce checks already removed (no changes)");
        }
    } else {
        log_fix(basename($perm_file) . ": file not found - skipping");
    }
}

// ============================================
// VALIDATION: php -l syntax check
// ============================================
echo "\n=== SYNTAX VALIDATION ===\n";
$files_to_check = [
    $plugin_dir . 'classes/core.php',
    $plugin_dir . 'bitcore-core.php',
    $plugin_dir . 'rest/class-missions-api.php',
    $plugin_dir . 'rest/sse-events.php',
    $plugin_dir . 'rest/auth-filter.php',
    $plugin_dir . 'rest/tasks.php',
    $plugin_dir . 'rest/trait-rest-tasks-permissions.php',
    $plugin_dir . 'rest/class-rest-tasks-permissions.php',
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        $output = [];
        $return_var = 0;
        exec('php -l ' . escapeshellarg($file) . ' 2>&1', $output, $return_var);
        if ($return_var === 0) {
            echo "[OK] " . basename($file) . "\n";
        } else {
            log_error(basename($file) . " syntax error: " . implode(" ", $output));
        }
    }
}

// ============================================
// SUMMARY
// ============================================
echo "\n=== FIX SUMMARY ===\n";
echo "Fixes applied: " . count($fixes) . "\n";
foreach ($fixes as $f) echo "  - $f\n";

if (count($errors) > 0) {
    echo "\nErrors: " . count($errors) . "\n";
    foreach ($errors as $e) echo "  - $e\n";
    exit(1);
} else {
    echo "\nAll fixes applied successfully.\n";
    exit(0);
}
