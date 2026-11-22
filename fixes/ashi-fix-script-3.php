<?php
/**
 * BITCORE REST API Blocker Fix Script Part 3
 * Handles trait/class permission files with array() syntax and no type hints
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
// FIX 1: trait-rest-tasks-permissions.php
// ============================================
$trait_file = $plugin_dir . 'rest/trait-rest-tasks-permissions.php';
if (file_exists($trait_file)) {
    $content = file_get_contents($trait_file);
    $original = $content;

    // Flexible regex: handles both array() and [] syntax, optional type hints on $request
    $pattern = '/\s*\$nonce = \$request->get_header\([\'"]X-WP-Nonce[\'"]\);\s*if\s*\(\s*!\s*\$nonce\s*\|\|\s*!\s*wp_verify_nonce\(\s*\$nonce,\s*[\'"]wp_rest[\'"]\s*\)\s*\)\s*\{\s*return\s+new\s+\\\\WP_Error\(\s*[\'"]rest_forbidden[\'"],\s*__\(\s*[\'"]Invalid or missing nonce\.[\'"],\s*[\'"]bitcore-tasks[\'"]\s*\),\s*(?:array\(|\[)\s*[\'"]status[\'"]\s*=>\s*403\s*(?:\)|\])\s*\);\s*\}/s';
    $content = preg_replace($pattern, '', $content);

    if ($content !== $original) {
        file_put_contents($trait_file, $content);
        log_fix("trait-rest-tasks-permissions.php: removed manual nonce checks");
    } else {
        log_error("trait-rest-tasks-permissions.php: nonce pattern still not found");
    }
} else {
    log_error("trait-rest-tasks-permissions.php not found");
}

// ============================================
// FIX 2: class-rest-tasks-permissions.php
// ============================================
$class_perm_file = $plugin_dir . 'rest/class-rest-tasks-permissions.php';
if (file_exists($class_perm_file)) {
    $content = file_get_contents($class_perm_file);
    $original = $content;

    $pattern = '/\s*\$nonce = \$request->get_header\([\'"]X-WP-Nonce[\'"]\);\s*if\s*\(\s*!\s*\$nonce\s*\|\|\s*!\s*wp_verify_nonce\(\s*\$nonce,\s*[\'"]wp_rest[\'"]\s*\)\s*\)\s*\{\s*return\s+new\s+\\\\WP_Error\(\s*[\'"]rest_forbidden[\'"],\s*__\(\s*[\'"]Invalid or missing nonce\.[\'"],\s*[\'"]bitcore-tasks[\'"]\s*\),\s*(?:array\(|\[)\s*[\'"]status[\'"]\s*=>\s*403\s*(?:\)|\])\s*\);\s*\}/s';
    $content = preg_replace($pattern, '', $content);

    if ($content !== $original) {
        file_put_contents($class_perm_file, $content);
        log_fix("class-rest-tasks-permissions.php: removed manual nonce checks");
    } else {
        log_error("class-rest-tasks-permissions.php: nonce pattern still not found");
    }
} else {
    log_error("class-rest-tasks-permissions.php not found");
}

// ============================================
// VALIDATION: php -l syntax check
// ============================================
echo "\n=== SYNTAX VALIDATION ===\n";
$files_to_check = [
    $trait_file,
    $class_perm_file,
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
