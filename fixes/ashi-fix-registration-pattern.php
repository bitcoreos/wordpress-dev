<?php
$file = '/var/www/html/wp-content/plugins/bitcore-core/bitcore-core.php';
$content = file_get_contents($file);
$original = $content;

// Fix coordination: directly call register_routes
$content = str_replace(
    "add_action('rest_api_init', function() { new BITCORE_Coordination_API(); });",
    "add_action('rest_api_init', function() { (new BITCORE_Coordination_API())->register_routes(); });",
    $content
);

// Fix MCP: directly call register_routes
$content = str_replace(
    "add_action('rest_api_init', function() { new BITCORE_Rest_MCP_Tools_API(); });",
    "add_action('rest_api_init', function() { (new BITCORE_Rest_MCP_Tools_API())->register_routes(); });",
    $content
);

if ($content !== $original) {
    file_put_contents($file, $content);
    echo "[FIX] Updated registration patterns\n";
} else {
    echo "[WARN] Patterns not found or already fixed\n";
}

$output = [];
exec('php -l ' . escapeshellarg($file) . ' 2>&1', $output, $return_var);
echo $return_var === 0 ? "[OK] Syntax valid\n" : "[ERROR] Syntax: " . implode(' ', $output) . "\n";
