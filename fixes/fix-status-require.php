<?php
/**
 * Fix Status API require path in bitcore-core.php
 * BITCORE_PLUGIN_DIR -> __DIR__  
 */

$core_file = '/var/www/html/wp-content/plugins/bitcore-core/bitcore-core.php';
$content = file_get_contents($core_file);

$old = "require_once BITCORE_PLUGIN_DIR . 'rest/class-status-api.php';";
$new = "require_once __DIR__ . '/rest/class-status-api.php';";

if (strpos($content, $old) !== false) {
    $content = str_replace($old, $new, $content);
    file_put_contents($core_file, $content);
    echo "REPLACED: BITCORE_PLUGIN_DIR -> __DIR__\n";
} else {
    echo "NOT FOUND - checking current state:\n";
    $lines = file($core_file);
    for ($i = 0; $i < count($lines); $i++) {
        if (strpos($lines[$i], "require_once") !== false && strpos($lines[$i], "status") !== false) {
            echo "  Line " . ($i+1) . ": " . $lines[$i];
        }
    }
    exit(1);
}

// Verify syntax
$output = [];
$ret = 0;
exec("php -l " . escapeshellarg($core_file) . " 2>&1", $output, $ret);
echo "Syntax: " . ($ret === 0 ? "PASS" : implode("\n", $output)) . "\n";
