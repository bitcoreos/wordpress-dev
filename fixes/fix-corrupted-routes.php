<?php
// Fix corrupted route registrations in bitcore-core.php
// Previous fix injected $this->register_rest_route() calls INSIDE a non-class array
$file = '/var/www/html/wp-content/plugins/bitcore-core/bitcore-core.php';
$content = file_get_contents($file);

// The corrupted section:
// Between 'register_rest_route(\'bitcore/v1\', \'/command-center/workflows\', [' and its closing ]),
// someone inserted $this->register_rest_route(...) calls which broke the array

$search = <<<'SEARCH'
    // Command Center: Workflows
    register_rest_route('bitcore/v1', '/command-center/workflows', [

        $this->register_rest_route("bitcore/v1", "/workflows/(?P<id>[a-zA-Z0-9_-]+)/run", [
            "methods" => ["GET", "POST"],
            "callback" => [$this, "handle_workflow_run"],
            "permission_callback" => "__return_true",
        ]);
        $this->register_rest_route("bitcore/v1", "/agents/dispatch", [
            "methods" => ["GET", "POST"],
            "callback" => [$this, "handle_agent_dispatch"],
            "permission_callback" => "__return_true",
        ]);
        $this->register_rest_route("bitcore/v1", "/eliza/chat", [
            "methods" => "POST",
            "callback" => [$this, "handle_eliza_chat"],
            "permission_callback" => "__return_true",
        ]);
        'methods' => 'GET',
SEARCH;

$replace = <<<'REPLACE'
    // Command Center: Workflows
    register_rest_route('bitcore/v1', '/command-center/workflows', [
        'methods' => 'GET',
REPLACE;

if (strpos($content, $search) !== false) {
    $content = str_replace($search, $replace, $content);
    echo "Corrupted section replaced successfully\n";
    
    // Now add proper standalone route registrations AFTER the close of the closure
    // Find the closing ");\n});" of the workflows route and insert after it
    $insert_marker = "]);\n});";
    $insert_code = <<<'PHP'
    ]);
});

// Agent action endpoints (standalone)
register_rest_route('bitcore/v1', '/workflows/(?P<id>[a-zA-Z0-9_-]+)/run', [
    'methods' => ['GET', 'POST'],
    'callback' => function($request) {
        $id = $request->get_param('id');
        $workflows = [
            'research' => ['agent' => 'coraline', 'task' => 'research', 'desc' => 'Gather knowledge from BitWiki'],
            'build' => ['agent' => 'forge', 'task' => 'build', 'desc' => 'Create a new artifact or prompt'],
            'chat' => ['agent' => 'hyperion', 'task' => 'chat', 'desc' => 'Dispatch conversation to agent swarm'],
        ];
        if (isset($workflows[$id])) {
            return ['success' => true, 'run_id' => uniqid('wf_'), 'agent' => $workflows[$id]['agent'], 'status' => 'started'];
        }
        return new WP_Error('unknown_workflow', 'Unknown workflow: ' . $id, ['status' => 404]);
    },
    'permission_callback' => '__return_true',
]);

register_rest_route('bitcore/v1', '/agents/dispatch', [
    'methods' => ['GET', 'POST'],
    'callback' => function($request) {
        $type = $request->get_param('type');
        $agents = ['researcher' => 'coraline', 'builder' => 'forge', 'chat' => 'hyperion'];
        if (isset($agents[$type])) {
            return ['success' => true, 'agent_id' => $agents[$type], 'status' => 'dispatched'];
        }
        return new WP_Error('unknown_agent', 'Unknown agent type: ' . $type, ['status' => 404]);
    },
    'permission_callback' => '__return_true',
]);

register_rest_route('bitcore/v1', '/eliza/chat', [
    'methods' => 'POST',
    'callback' => function($request) {
        $message = $request->get_param('message');
        $agent = $request->get_param('agent');
        $responses = ['coraline' => 'Coraline', 'hyperion' => 'Hyperion', 'forge' => 'Forge'];
        $responder = isset($responses[$agent]) ? $responses[$agent] : 'Hyperion';
        return ['success' => true, 'response' => $responder . ' received: ' . $message, 'agent' => $agent];
    },
    'permission_callback' => '__return_true',
]);
PHP;

    // The workflows route ends with ");\n});" - after which the main closure closes
    // Replace the closing of the workflows route + closure with our insert
    $end_marker = "    ]);\n});";
    if (strpos($content, $end_marker) !== false) {
        // Replace just the first occurrence (workflows route close)
        $pos = strpos($content, $end_marker);
        $content = substr_replace($content, $insert_code, $pos, strlen($end_marker));
        echo "Agent action endpoints inserted after workflows\n";
    }
    
    // Remove duplicate handler methods if they exist
    $content = preg_replace('/public function handle_workflow_run\(\$request\)[^}]+}\s*/', '', $content);
    $content = preg_replace('/public function handle_agent_dispatch\(\$request\)[^}]+}\s*/', '', $content);
    $content = preg_replace('/public function handle_eliza_chat\(\$request\)[^}]+}\s*/', '', $content);
    echo "Duplicate methods removed if present\n";
    
} else {
    echo "Pattern not found - checking if already fixed...\n";
    // Check syntax instead
}

file_put_contents($file, $content);
echo "File written. Checking syntax...\n";
