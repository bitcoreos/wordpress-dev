<?php
$file = '/var/www/html/wp-content/plugins/bitcore-core/bitcore-core.php';
$content = file_get_contents($file);

// Find the first rest_api_init closure
$start_marker = "add_action('rest_api_init', function()";
$start = strpos($content, $start_marker);
if ($start === false) { echo "ERROR: rest_api_init closure not found\n"; exit(1); }

// Extract closure
$brace_count = 0;
$in_closure = false;
$end = $start;
$len = strlen($content);
for ($i = $start; $i < $len - 2; $i++) {
    if ($content[$i] === '{') { $brace_count++; $in_closure = true; }
    if ($content[$i] === '}') {
        $brace_count--;
        if ($in_closure && $brace_count === 0 && $content[$i+1] === ')' && $content[$i+2] === ';') {
            $end = $i + 3;
            break;
        }
    }
}

$closure = substr($content, $start, $end - $start);
echo "Current closure:\n$closure\n\n";

// Check if Status_API already registered
if (strpos($closure, 'BITCORE_Status_API') !== false) {
    echo "Status_API already in closure. Checking class file...\n";
    $class_file = '/var/www/html/wp-content/plugins/bitcore-core/rest/class-status-api.php';
    if (file_exists($class_file)) {
        echo "Class file exists. Syntax: ";
        passthru('php -l ' . escapeshellarg($class_file));
        echo "\n";
    } else {
        echo "Class file MISSING. Creating...\n";
        // Create class file
        $class_code = '<?php
class BITCORE_Status_API {
    private $namespace = "bitcore/v1";
    public function register_routes() {
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
    public function get_status() {
        $u = 0;
        if (file_exists("/proc/uptime")) {
            $p = explode(" ", file_get_contents("/proc/uptime"));
            $u = (int)floatval($p[0]);
        }
        return ["version"=>get_bloginfo("version"),"uptime"=>$u,"memory"=>round(memory_get_usage(true)/1048576,1)."MB/".ini_get("memory_limit"),"php"=>PHP_VERSION];
    }
    public function get_queue() {
        return ["queues"=>[["name"=>"email","pending"=>0]]];
    }
}
';
        file_put_contents($class_file, $class_code);
        passthru('php -l ' . escapeshellarg($class_file));
    }
    echo "\nDONE - Status_API already registered\n";
    exit(0);
}

// Not registered. Check if class file exists
$class_file = '/var/www/html/wp-content/plugins/bitcore-core/rest/class-status-api.php';
if (!file_exists($class_file)) {
    echo "Class file missing, creating...\n";
    $class_code = '<?php
class BITCORE_Status_API {
    private $namespace = "bitcore/v1";
    public function register_routes() {
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
    public function get_status() {
        $u = 0;
        if (file_exists("/proc/uptime")) {
            $p = explode(" ", file_get_contents("/proc/uptime"));
            $u = (int)floatval($p[0]);
        }
        return ["version"=>get_bloginfo("version"),"uptime"=>$u,"memory"=>round(memory_get_usage(true)/1048576,1)."MB/".ini_get("memory_limit"),"php"=>PHP_VERSION];
    }
    public function get_queue() {
        return ["queues"=>[["name"=>"email","pending"=>0]]];
    }
}
';
    file_put_contents($class_file, $class_code);
}

// Add registration before Missions_Api
$insert_before = "if (class_exists('\\\\BITCORE\\\\Rest\\\\Missions_Api'))";
$registration = "if (class_exists('BITCORE_Status_API')) {\n        require_once __DIR__ . '/rest/class-status-api.php';\n        (new BITCORE_Status_API())->register_routes();\n    }\n\n    ";

if (strpos($content, $insert_before) !== false) {
    $content = str_replace($insert_before, $registration . $insert_before, $content);
    echo "ADDED Status_API before Missions_Api\n";
} else {
    echo "Missions_Api marker not found, trying Coordination_API...\n";
    $insert = "(new BITCORE_Coordination_API())->register_routes();\n    }\n";
    if (strpos($content, $insert) !== false) {
        $content = str_replace($insert, $insert . "    " . $registration, $content);
        echo "ADDED Status_API after Coordination_API\n";
    } else {
        echo "FATAL: No insertion point found\n";
        exit(1);
    }
}

file_put_contents($file, $content);

// Syntax check
$out = []; $ret = 0;
exec('php -l ' . escapeshellarg($file) . ' 2>&1', $out, $ret);
if ($ret !== 0) {
    echo "SYNTAX FAIL: " . implode("\n", $out) . "\n";
    copy($file . '.bak2', $file);
    echo "RESTORED from bak2\n";
    exit(1);
}
echo "SYNTAX PASS - Status_API registered\n";
