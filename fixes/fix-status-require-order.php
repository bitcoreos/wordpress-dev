<?php
$f = '/var/www/html/wp-content/plugins/bitcore-core/bitcore-core.php';
$c = file_get_contents($f);

$old = "if (class_exists('BITCORE_Status_API')) {
        require_once __DIR__ . '/rest/class-status-api.php';
        (new BITCORE_Status_API())->register_routes();
    }";

$new = "require_once __DIR__ . '/rest/class-status-api.php';
    if (class_exists('BITCORE_Status_API')) {
        (new BITCORE_Status_API())->register_routes();
    }";

if (strpos($c, $old) !== false) {
    $c = str_replace($old, $new, $c);
    file_put_contents($f, $c);
    echo "FIXED: require_once moved before class_exists\n";
} else {
    echo "PATTERN NOT FOUND\n";
    exit(1);
}

$o = []; $r = 0;
exec('php -l ' . escapeshellarg($f) . ' 2>&1', $o, $r);
echo $r === 0 ? "SYNTAX PASS\n" : "SYNTAX FAIL: " . implode("\n", $o) . "\n";
if ($r !== 0) { copy($f . '.bak2', $f); echo "RESTORED\n"; exit(1); }
echo "DONE\n";
