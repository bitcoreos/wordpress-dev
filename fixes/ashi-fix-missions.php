<?php
$file = '/var/www/html/wp-content/plugins/bitcore-core/bitcore-core.php';
$content = file_get_contents($file);
$original = $content;

// Replace bad Missions_Api callback with proper instantiation
$bad = "add_action('rest_api_init', ['Missions_Api', 'register_routes']);";
$good = "add_action('rest_api_init', function() { new \\BITCORE\\Rest\\Missions_Api(); });";

$content = str_replace($bad, $good, $content);

if ($content !== $original) {
    file_put_contents($file, $content);
    echo "[FIX] Replaced Missions_Api callback\n";
} else {
    echo "[ERROR] Pattern not found\n";
}

$output = [];
exec('php -l ' . escapeshellarg($file) . ' 2>&1', $output, $return_var);
echo $return_var === 0 ? "[OK] Syntax valid\n" : "[ERROR] Syntax: " . implode(' ', $output) . "\n";
