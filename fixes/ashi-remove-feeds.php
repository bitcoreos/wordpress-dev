<?php
$file = '/var/www/html/wp-content/plugins/bitcore-core/bitcore-core.php';
$lines = file($file);
$out = [];
$removed = 0;
foreach ($lines as $line) {
    if (strpos($line, "feeds.php") !== false || strpos($line, "Feeds_Loader") !== false) {
        $removed++;
        continue;
    }
    $out[] = $line;
}
file_put_contents($file, implode('', $out));
echo "Removed $removed lines\n";
$output = [];
exec('php -l ' . escapeshellarg($file) . ' 2>&1', $output, $return_var);
echo $return_var === 0 ? "Syntax OK\n" : "Syntax ERROR: " . implode(' ', $output) . "\n";
