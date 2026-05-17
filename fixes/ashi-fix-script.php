<?php
/**
 * BITCORE REST API Blocker Fix Script
 * Run inside wordpress-app container
 */

$plugin_dir = '/var/www/html/wp-content/plugins/bitcore-core/';
$errors = [];
$fixes = [];

function log_fix($msg) {
    global $fixes;
    $fixes[] = $msg;
    echo "[FIX] $msg\n";
}

function log_error($msg) {
    global $errors;
    $errors[] = $msg;
    echo "[ERROR] $msg\n";
}

// ============================================
// FIX 1: Remove nonce checks from tasks.php
// ============================================
$tasks_file = $plugin_dir . 'rest/tasks.php';
if (file_exists($tasks_file)) {
    $content = file_get_contents($tasks_file);
    $original = $content;

    // Pattern: remove nonce block from permission callbacks
    $pattern = '/\s*\$nonce = \$request->get_header\(\s*[\'"]X-WP-Nonce[\'"]\s*\);\s*if\s*\(\s*!\s*\$nonce\s*\|\|\s*!\s*wp_verify_nonce\(\s*\$nonce,\s*[\'"]wp_rest[\'"]\s*\)\s*\)\s*\{\s*return\s+new\s+\\\\WP_Error\(\s*[\'"]rest_forbidden[\'"],\s*__\(\s*[\'"]Invalid or missing nonce\.[\'"],\s*[\'"]bitcore[\'"]\s*\),\s*array\(\s*[\'"]status[\'"]\s*=>\s*403\s*\)\s*\);\s*\}/s';

    $content = preg_replace($pattern, '', $content);

    if ($content !== $original) {
        file_put_contents($tasks_file, $content);
        log_fix("tasks.php: removed manual nonce checks from permission callbacks");
    } else {
        log_error("tasks.php: nonce pattern not found or already fixed");
    }
} else {
    log_error("tasks.php not found");
}

// ============================================
// FIX 2: Remove nonce checks from trait-rest-tasks-permissions.php
// ============================================
$trait_file = $plugin_dir . 'rest/trait-rest-tasks-permissions.php';
if (file_exists($trait_file)) {
    $content = file_get_contents($trait_file);
    $original = $content;

    $pattern = '/\s*\$nonce = \$request->get_header\([\'"]X-WP-Nonce[\'"]\);\s*if\s*\(\s*!\s*\$nonce\s*\|\|\s*!\s*wp_verify_nonce\(\s*\$nonce,\s*[\'"]wp_rest[\'"]\s*\)\s*\)\s*\{\s*return\s+new\s+\\\\WP_Error\(\s*[\'"]rest_forbidden[\'"],\s*__\(\s*[\'"]Invalid or missing nonce\.[\'"],\s*[\'"]bitcore-tasks[\'"]\s*\),\s*\[\s*[\'"]status[\'"]\s*=>\s*403\s*\]\s*\);\s*\}/s';

    $content = preg_replace($pattern, '', $content);

    if ($content !== $original) {
        file_put_contents($trait_file, $content);
        log_fix("trait-rest-tasks-permissions.php: removed manual nonce checks");
    } else {
        log_error("trait-rest-tasks-permissions.php: nonce pattern not found or already fixed");
    }
} else {
    log_error("trait-rest-tasks-permissions.php not found");
}

// ============================================
// FIX 3: Remove nonce checks from class-rest-tasks-permissions.php
// ============================================
$class_perm_file = $plugin_dir . 'rest/class-rest-tasks-permissions.php';
if (file_exists($class_perm_file)) {
    $content = file_get_contents($class_perm_file);
    $original = $content;

    $pattern = '/\s*\$nonce = \$request->get_header\([\'"]X-WP-Nonce[\'"]\);\s*if\s*\(\s*!\s*\$nonce\s*\|\|\s*!\s*wp_verify_nonce\(\s*\$nonce,\s*[\'"]wp_rest[\'"]\s*\)\s*\)\s*\{\s*return\s+new\s+\\\\WP_Error\(\s*[\'"]rest_forbidden[\'"],\s*__\(\s*[\'"]Invalid or missing nonce\.[\'"],\s*[\'"]bitcore-tasks[\'"]\s*\),\s*\[\s*[\'"]status[\'"]\s*=>\s*403\s*\]\s*\);\s*\}/s';

    $content = preg_replace($pattern, '', $content);

    if ($content !== $original) {
        file_put_contents($class_perm_file, $content);
        log_fix("class-rest-tasks-permissions.php: removed manual nonce checks");
    } else {
        log_error("class-rest-tasks-permissions.php: nonce pattern not found or already fixed");
    }
} else {
    log_error("class-rest-tasks-permissions.php not found");
}

// ============================================
// FIX 4: missions-api.php - Fix return type mismatch
// ============================================
$missions_file = $plugin_dir . 'rest/class-missions-api.php';
if (file_exists($missions_file)) {
    $content = file_get_contents($missions_file);
    $original = $content;

    // Fix get_mission: remove return type or change to mixed/ WP_REST_Response|WP_Error
    // Safer: remove strict return type from methods that return WP_Error
    $content = str_replace(
        'public function get_mission(\\WP_REST_Request $request): \\WP_REST_Response {',
        'public function get_mission(\\WP_REST_Request $request) {',
        $content
    );
    $content = str_replace(
        'public function create_mission(\\WP_REST_Request $request): \\WP_REST_Response {',
        'public function create_mission(\\WP_REST_Request $request) {',
        $content
    );
    $content = str_replace(
        'public function update_mission(\\WP_REST_Request $request): \\WP_REST_Response {',
        'public function update_mission(\\WP_REST_Request $request) {',
        $content
    );
    $content = str_replace(
        'public function delete_mission(\\WP_REST_Request $request): \\WP_REST_Response {',
        'public function delete_mission(\\WP_REST_Request $request) {',
        $content
    );
    $content = str_replace(
        'public function get_statuses(\\WP_REST_Request $request): \\WP_REST_Response {',
        'public function get_statuses(\\WP_REST_Request $request) {',
        $content
    );

    if ($content !== $original) {
        file_put_contents($missions_file, $content);
        log_fix("class-missions-api.php: removed strict return types from methods that return WP_Error");
    } else {
        log_error("class-missions-api.php: no changes made (patterns not found)");
    }
} else {
    log_error("class-missions-api.php not found");
}

// ============================================
// FIX 5: sse-events.php - Fix class reference
// ============================================
$sse_file = $plugin_dir . 'rest/sse-events.php';
if (file_exists($sse_file)) {
    $content = file_get_contents($sse_file);
    $original = $content;

    // Fix class name reference
    $content = str_replace('BitCore_Auth_Filter::validate_api_key', 'BITCORE_Rest_Auth_Filter::validate_api_key', $content);

    if ($content !== $original) {
        file_put_contents($sse_file, $content);
        log_fix("sse-events.php: fixed BitCore_Auth_Filter -> BITCORE_Rest_Auth_Filter reference");
    } else {
        log_error("sse-events.php: class pattern not found");
    }
} else {
    log_error("sse-events.php not found");
}

// ============================================
// FIX 6: bitcore-core.php - Register missing REST routes
// ============================================
$core_file = $plugin_dir . 'bitcore-core.php';
if (file_exists($core_file)) {
    $content = file_get_contents($core_file);
    $original = $content;

    // Find the section with existing rest_api_init hooks and add missing ones
    $missing_hooks = [
        "add_action('rest_api_init', ['BITCORE_Rest_Mcp_Tools', 'register_routes']);",
        "add_action('rest_api_init', ['BITCORE_Rest_Coordination', 'register_routes']);",
    ];

    foreach ($missing_hooks as $hook) {
        if (strpos($content, $hook) === false) {
            // Insert after the last add_action('rest_api_init', ...)
            $content = preg_replace(
                "/(add_action\('rest_api_init',\s*\['BITCORE_Rest_Sse_Events',\s*'register_routes'\]\);)/",
                "$1\n" . $hook,
                $content,
                1
            );
            log_fix("bitcore-core.php: added $hook");
        } else {
            log_fix("bitcore-core.php: $hook already present");
        }
    }

    // Check for feeds registration
    if (strpos($content, "BITCORE_Feeds") === false && strpos($content, "feeds.php") !== false) {
        // Feeds class might need different handling - check feeds.php
        $feeds_file = $plugin_dir . 'feeds/feeds.php';
        if (file_exists($feeds_file)) {
            $feeds_content = file_get_contents($feeds_file);
            if (preg_match('/class\s+(\w+)\s+.*register_rest_routes/', $feeds_content, $matches)) {
                $feeds_class = $matches[1];
                $feeds_hook = "add_action('rest_api_init', ['$feeds_class', 'register_rest_routes']);";
                if (strpos($content, $feeds_hook) === false) {
                    $content = preg_replace(
                        "/(add_action\('rest_api_init',\s*\['BITCORE_Rest_Sse_Events',\s*'register_routes'\]\);)/",
                        "$1\n" . $feeds_hook,
                        $content,
                        1
                    );
                    log_fix("bitcore-core.php: added feeds registration for $feeds_class");
                }
            }
        }
    }

    if ($content !== $original) {
        file_put_contents($core_file, $content);
    }
} else {
    log_error("bitcore-core.php not found");
}

// ============================================
// VALIDATION: php -l syntax check
// ============================================
echo "\n=== SYNTAX VALIDATION ===\n";
$files_to_check = [
    $tasks_file,
    $trait_file,
    $class_perm_file,
    $missions_file,
    $sse_file,
    $core_file,
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
    echo "Errors: " . count($errors) . "\n";
    foreach ($errors as $e) echo "  - $e\n";
    exit(1);
} else {
    echo "All fixes applied successfully.\n";
    exit(0);
}
