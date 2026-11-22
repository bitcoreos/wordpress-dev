<?php
$file = '/var/www/html/wp-content/plugins/bitcore-core/bitcore-core.php';
$content = file_get_contents($file);

$insert_before = "if (class_exists('\\\\BITCORE\\\\Rest\\\\Missions_Api'))";
$registration = "if (class_exists('BITCORE_Status_API')) {\n        require_once __DIR__ . '/rest/class-status-api.php';\n        (new BITCORE_Status_API())->register_routes();\n    }\n\n    ";

if (strpos($content, $insert_before) !== false) {
    $content = str_replace($insert_before, $registration . $insert_before, $content);
    echo "ADDED Status_API registration before Missions_Api\n";
} else {
    echo "Marker not found. Checking for alternatives...\n";
    $alt = "class_exists('BITCORE_Coordination_API')";
    if (strpos($content, $alt) !== false) {
        $insert_after = "(new BITCORE_Coordination_API())->register_routes();\n    }\n";
        $with_status = $insert_after . "    " . $registration;
        $content = str_replace($insert_after, $with_status, $content);
        echo "ADDED Status_API registration after Coordination_API\n";
    } else {
        echo "FATAL: Could not find insertion point\n";
        exit(1);
    }
}

file_put_contents($file, $content);

$out = [];
$ret = 0;
exec('php -l ' . escapeshellarg($file) . ' 2>&1', $out, $ret);
echo $ret === 0 ? "SYNTAX PASS\n" : "SYNTAX FAIL: " . implode("\n", $out) . "\n";
if ($ret !== 0) {
    copy($file . '.bak2', $file);
    echo "RESTORED FROM BACKUP\n";
    exit(1);
}
echo "SUCCESS\n";
