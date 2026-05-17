<?php
// Wrap injected route registrations in rest_api_init action
$file = '/var/www/html/wp-content/plugins/bitcore-core/bitcore-core.php';
$content = file_get_contents($file);

// Find the top-level register_rest_route calls that need wrapping
// They start with "// Agent action endpoints (standalone)"
$search = "// Agent action endpoints (standalone)\nregister_rest_route('bitcore/v1', '/workflows/(?P<id>[a-zA-Z0-9_-]+)/run', [";

if (strpos($content, $search) !== false) {
    echo "Found top-level routes. Wrapping in rest_api_init...\n";
    
    // Replace the opening with add_action wrapper
    $content = str_replace(
        "// Agent action endpoints (standalone)\nregister_rest_route('bitcore/v1', '/workflows/(?P<id>[a-zA-Z0-9_-]+)/run', [",
        "// Agent action endpoints\nadd_action('rest_api_init', function() {\nregister_rest_route('bitcore/v1', '/workflows/(?P<id>[a-zA-Z0-9_-]+)/run', [",
        $content
    );
    
    // Find the closing of the eliza/chat route and add the action close
    $chat_close = "'permission_callback' => '__return_true',\n]);";
    // Find the third occurrence of this pattern (after workflows, agents, eliza)
    $pos = 0;
    $count = 0;
    $target_pos = 0;
    while (($pos = strpos($content, $chat_close, $pos)) !== false) {
        $count++;
        if ($count === 3) {
            $target_pos = $pos;
            break;
        }
        $pos += strlen($chat_close);
    }
    
    if ($target_pos > 0) {
        // Check what follows - if it's another register_rest or end of file
        $after = substr($content, $target_pos + strlen($chat_close), 50);
        echo "After eliza/chat close: " . substr($after, 0, 40) . "...\n";
        
        // Insert action close after the eliza chat route
        $content = substr_replace($content, $chat_close . "\n});", $target_pos, strlen($chat_close));
        echo "Added rest_api_init close after eliza/chat route\n";
    } else {
        echo "Could not find eliza/chat route close\n";
    }
} else {
    echo "Pattern not found - checking current state...\n";
    // Show what's around line 258
    $lines = explode("\n", $content);
    for ($i = 255; $i < 300 && $i < count($lines); $i++) {
        echo "Line " . ($i + 1) . ": " . $lines[$i] . "\n";
    }
}

file_put_contents($file, $content);

$out = []; $ret = 0;
exec('php -l ' . escapeshellarg($file) . ' 2>&1', $out, $ret);
echo implode("\n", $out) . "\n";
echo $ret === 0 ? "SYNTAX PASS" : "SYNTAX FAIL";
