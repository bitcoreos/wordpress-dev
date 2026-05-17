<?php
$file = '/var/www/html/wp-content/plugins/bitcore-core/bitcore-core.php';
$content = file_get_contents($file);

$routes = <<<'PHP'
add_action('rest_api_init', function() {
    // Command Center: Stats
    register_rest_route('bitcore/v1', '/command-center/stats', [
        'methods' => 'GET',
        'callback' => function() {
            $uptime = 0;
            if (file_exists('/proc/uptime')) {
                $parts = explode(' ', file_get_contents('/proc/uptime'));
                $uptime = (int) floatval($parts[0]);
            }
            return [
                'active_agents' => 3,
                'running_workflows' => 0,
                'queued_tasks' => 0,
                'health_score' => 100,
            ];
        },
        'permission_callback' => '__return_true',
    ]);
    
    // Command Center: Agents
    register_rest_route('bitcore/v1', '/command-center/agents', [
        'methods' => 'GET',
        'callback' => function() {
            return [
                'agents' => [
                    ['id' => 'coraline', 'name' => 'Coraline', 'icon' => '🧬', 'status' => 'active'],
                    ['id' => 'hyperion', 'name' => 'Hyperion', 'icon' => '⚡', 'status' => 'active'],
                    ['id' => 'forge', 'name' => 'Forge', 'icon' => '🔨', 'status' => 'idle'],
                ]
            ];
        },
        'permission_callback' => '__return_true',
    ]);
    
    // Command Center: Workflows
    register_rest_route('bitcore/v1', '/command-center/workflows', [
        'methods' => 'GET',
        'callback' => function() {
            return [
                'workflows' => [
                    ['id' => 'research', 'name' => 'Research Topic', 'description' => 'Gather knowledge from BitWiki'],
                    ['id' => 'build', 'name' => 'Build Artifact', 'description' => 'Create a new artifact or prompt'],
                    ['id' => 'chat', 'name' => 'Chat with Agents', 'description' => 'Dispatch conversation to agent swarm'],
                ]
            ];
        },
        'permission_callback' => '__return_true',
    ]);
});
PHP;

// Find the closing PHP tag and insert before it
if (strpos($content, '?>') !== false) {
    $content = str_replace('?>', $routes . "\n?>", $content);
    file_put_contents($file, $content);
    echo "ADDED command-center routes\n";
} else {
    echo "No closing PHP tag found - appending\n";
    $content .= "\n" . $routes;
    file_put_contents($file, $content);
    echo "APPENDED command-center routes\n";
}

$out = []; $ret = 0;
exec('php -l ' . escapeshellarg($file) . ' 2>&1', $out, $ret);
echo $ret === 0 ? "SYNTAX PASS\n" : "SYNTAX FAIL: " . implode("\n", $out) . "\n";
if ($ret !== 0) {
    copy($file . '.bak2', $file);
    echo "RESTORED from bak2\n";
    exit(1);
}
echo "SUCCESS\n";
