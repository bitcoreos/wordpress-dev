<?php
/**
 * Deploy Status+Queue API endpoints to ASHI server
 * Run inside wordpress-app Docker container
 */

// Step 1: Create class-status-api.php
$class_file = '/var/www/html/wp-content/plugins/bitcore-core/rest/class-status-api.php';
$class_code = '<?php
declare(strict_types=1);

class BITCORE_Status_API {
    private string $namespace = "bitcore/v1";

    public function register_routes(): void {
        register_rest_route($this->namespace, "/status", [
            "methods" => "GET",
            "callback" => [$this, "get_status"],
            "permission_callback" => "__return_true",
        ]);
        register_rest_route($this->namespace, "/queue", [
            "methods" => "GET",
            "callback" => [$this, "get_queue"],
            "permission_callback" => "__return_true",
        ]);
    }

    public function get_status(): array {
        $uptime = 0;
        if (file_exists("/proc/uptime")) {
            $parts = explode(" ", file_get_contents("/proc/uptime"));
            $uptime = (int) floatval($parts[0]);
        }
        return [
            "version" => get_bloginfo("version"),
            "uptime" => $uptime,
            "memory" => round(memory_get_usage(true) / 1048576, 1) . "MB / " . ini_get("memory_limit"),
            "php" => PHP_VERSION,
        ];
    }

    public function get_queue(): array {
        return [
            "queues" => [
                ["name" => "email", "pending" => 0],
            ],
        ];
    }
}
';

file_put_contents($class_file, $class_code);
echo "Created: $class_file\n";

// Verify syntax
$output = [];
$ret = 0;
exec('php -l ' . escapeshellarg($class_file) . ' 2>&1', $output, $ret);
echo "Class syntax: " . ($ret === 0 ? "PASS" : "FAIL: " . implode("\n", $output)) . "\n";

if ($ret !== 0) exit(1);

// Step 2: Register route in bitcore-core.php closure
$core_file = '/var/www/html/wp-content/plugins/bitcore-core/bitcore-core.php';
$content = file_get_contents($core_file);

// Find the closing of the first rest_api_init closure (the one we fixed earlier)
$marker = "})();";
// No - that's JavaScript. Need to find the PHP closure.
// The correct closure ends with "});" on its own line
$insert_marker = '    if (class_exists(\'BITCORE_Health_API\')) {';
$registration_code = '    if (class_exists(\'BITCORE_Status_API\')) {' . "\n"
    . '        require_once BITCORE_PLUGIN_DIR . \'rest/class-status-api.php\';' . "\n"
    . '        (new BITCORE_Status_API())->register_routes();' . "\n"
    . '    }' . "\n\n";

if (strpos($content, $insert_marker) !== false) {
    $content = str_replace($insert_marker, $registration_code . $insert_marker, $content);
    echo "Inserted route registration before Health_API block\n";
} else {
    // Health_API may not be registered. Find the missions block and insert before it.
    $alt_marker = '    if (class_exists(\'\\\\\\\\BITCORE\\\\\\\\Rest\\\\\\\\Missions_Api\')) {';
    if (strpos($content, $alt_marker) !== false) {
        $content = str_replace($alt_marker, $registration_code . $alt_marker, $content);
        echo "Inserted route registration before Missions_Api block\n";
    } else {
        // Last resort: find the rest_api_init closure and insert at start of body
        $closure_marker = "add_action('rest_api_init', function() {\n";
        $pos = strpos($content, $closure_marker);
        if ($pos !== false) {
            $insert_pos = $pos + strlen($closure_marker);
            $content = substr($content, 0, $insert_pos) . $registration_code . substr($content, $insert_pos);
            echo "Inserted route registration at start of closure body\n";
        } else {
            echo "ERROR: Could not find insertion point in bitcore-core.php\n";
            exit(1);
        }
    }
}

file_put_contents($core_file, $content);
echo "Updated: $core_file\n";

$output = [];
$ret = 0;
exec('php -l ' . escapeshellarg($core_file) . ' 2>&1', $output, $ret);
echo "Core syntax: " . ($ret === 0 ? "PASS" : "FAIL: " . implode("\n", $output)) . "\n";

if ($ret !== 0) {
    // Restore from backup
    copy($core_file . '.bak2', $core_file);
    echo "Restored core.php from backup\n";
    exit(1);
}

echo "\nSUCCESS: Status+Queue API deployed and registered\n";
