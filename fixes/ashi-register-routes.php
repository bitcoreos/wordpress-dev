<?php
$file = '/var/www/html/wp-content/plugins/bitcore-core/bitcore-core.php';
$lines = file($file);
$out = [];
$inserted = false;
foreach ($lines as $line) {
    $out[] = $line;
    if (!$inserted && strpos($line, "add_action('rest_api_init', ['BITCORE_Rest_Sse_Events', 'register_routes']);") !== false) {
        $out[] = "\n// === FROM HYPERION: Additional REST API Registrations ===\n";
        $out[] = "require_once BITCORE_PLUGIN_DIR . 'rest/class-missions-api.php';\n";
        $out[] = "add_action('rest_api_init', ['Missions_Api', 'register_routes']);\n";
        $out[] = "\n";
        $out[] = "require_once BITCORE_PLUGIN_DIR . 'rest/coordination-api.php';\n";
        $out[] = "add_action('rest_api_init', function() { new BITCORE_Coordination_API(); });\n";
        $out[] = "\n";
        $out[] = "require_once BITCORE_PLUGIN_DIR . 'rest/mcp-tools-api.php';\n";
        $out[] = "add_action('rest_api_init', function() { new BITCORE_Rest_MCP_Tools_API(); });\n";
        $out[] = "\n";
        $out[] = "require_once BITCORE_PLUGIN_DIR . 'feeds/feeds.php';\n";
        $out[] = "add_action('rest_api_init', ['Feeds_Loader', 'register_rest_routes']);\n";
        $inserted = true;
    }
}
file_put_contents($file, implode('', $out));
echo "Inserted registrations: " . ($inserted ? "YES" : "NO") . "\n";
$output = [];
exec('php -l ' . escapeshellarg($file) . ' 2>&1', $output, $return_var);
echo $return_var === 0 ? "Syntax OK\n" : "Syntax ERROR: " . implode(' ', $output) . "\n";
