<?php
$plugin_dir = '/var/www/html/wp-content/plugins/bitcore-core';
$class_file = $plugin_dir . '/rest/class-status-api.php';

echo "Class file exists: " . (file_exists($class_file) ? 'YES' : 'NO') . "\n";
echo "Class file path: $class_file\n";

require_once $class_file;
echo "Class loaded: " . (class_exists('BITCORE_Status_API') ? 'YES' : 'NO') . "\n";

$api = new BITCORE_Status_API();
echo "Instance created: " . (is_object($api) ? 'YES' : 'NO') . "\n";
echo "Namespace: " . $api->namespace . "\n";

echo "\n--- Testing route registration ---\n";
$api->register_routes();

echo "\n--- Registered routes ---\n";
$routes = rest_get_server()->get_routes('bitcore/v1');
foreach ($routes as $route => $handlers) {
    if (strpos($route, 'status') !== false || strpos($route, 'queue') !== false || strpos($route, 'health') !== false) {
        echo "$route\n";
        foreach ($handlers as $handler) {
            echo "  methods: " . json_encode($handler['methods']) . "\n";
        }
    }
}
