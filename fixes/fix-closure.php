<?php
/**
 * Fix the bitcore-core.php route registration closure
 * Run inside wordpress-app Docker container
 */

$file = '/var/www/html/wp-content/plugins/bitcore-core/bitcore-core.php';
$content = file_get_contents($file);

// The correct closure to insert
$correct = "add_action('rest_api_init', function() {\n"
    . "    if (class_exists('BITCORE_Rest_MCP_Tools_API')) {\n"
    . "        (new BITCORE_Rest_MCP_Tools_API())->register_routes();\n"
    . "    }\n"
    . "    if (class_exists('BITCORE_Coordination_API')) {\n"
    . "        (new BITCORE_Coordination_API())->register_routes();\n"
    . "    }\n"
    . "    if (class_exists('\\BITCORE\\Rest\\Missions_Api')) {\n"
    . "        (new \\BITCORE\\Rest\\Missions_Api())->register_routes();\n"
    . "    }\n"
    . "});\n";

// Find the start marker of the broken closure
$marker = "add_action('rest_api_init', function() {";
$start = strpos($content, $marker);

if ($start === false) {
    echo "ERROR: Could not find closure start marker\n";
    exit(1);
}

// Count braces to find the matching closing });
$depth = 0;
$search_start = $start + strlen($marker);
$len = strlen($content);
$end = -1;

for ($i = $search_start; $i < $len - 2; $i++) {
    if ($content[$i] === '{') {
        $depth++;
    } elseif ($content[$i] === '}') {
        if ($depth === 0 && $content[$i+1] === ')' && $content[$i+2] === ';') {
            $end = $i + 3;
            break;
        }
        $depth--;
    }
}

if ($end === -1) {
    echo "ERROR: Could not find closure end\n";
    exit(1);
}

// Extract the broken section for inspection
$broken_section = substr($content, $start, $end - $start);
echo "Broken closure found (" . strlen($broken_section) . " chars):\n";
echo substr($broken_section, 0, 200) . "...\n\n";

// Replace with correct closure
$before = substr($content, 0, $start);
$after = substr($content, $end);
$new_content = $before . $correct . $after;

file_put_contents($file, $new_content);

// Verify syntax
$output = [];
$return_var = 0;
exec('php -l ' . escapeshellarg($file) . ' 2>&1', $output, $return_var);

echo "Syntax check: " . ($return_var === 0 ? "PASS" : "FAIL") . "\n";
if ($return_var !== 0) {
    echo implode("\n", $output) . "\n";
    // Restore from backup
    copy($file . '.bak2', $file);
    echo "Restored from backup\n";
    exit(1);
}

echo "SUCCESS: Closure replaced with correct route registrations\n";
echo "New closure:\n$correct\n";
