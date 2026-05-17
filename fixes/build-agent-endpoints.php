<?php
// Build missing agent action endpoints for command center
$file = '/var/www/html/wp-content/plugins/bitcore-core/bitcore-core.php';
$content = file_get_contents($file);

$new_routes = <<<'PHP'
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
PHP;

$handlers = <<<'PHP'
    public function handle_workflow_run($request) {
        $id = $request->get_param("id");
        $workflows = [
            "research" => ["agent" => "coraline", "task" => "research", "desc" => "Gather knowledge from BitWiki"],
            "build" => ["agent" => "forge", "task" => "build", "desc" => "Create a new artifact or prompt"],
            "chat" => ["agent" => "hyperion", "task" => "chat", "desc" => "Dispatch conversation to agent swarm"],
        ];
        if (isset($workflows[$id])) {
            return ["success" => true, "run_id" => uniqid("wf_"), "agent" => $workflows[$id]["agent"], "status" => "started"];
        }
        return new WP_Error("unknown_workflow", "Unknown workflow: " . $id, ["status" => 404]);
    }

    public function handle_agent_dispatch($request) {
        $type = $request->get_param("type");
        $agents = ["researcher" => "coraline", "builder" => "forge", "chat" => "hyperion"];
        if (isset($agents[$type])) {
            return ["success" => true, "agent_id" => $agents[$type], "status" => "dispatched"];
        }
        return new WP_Error("unknown_agent", "Unknown agent type: " . $type, ["status" => 404]);
    }

    public function handle_eliza_chat($request) {
        $message = $request->get_param("message");
        $agent = $request->get_param("agent");
        if ($agent === "coraline") {
            return ["success" => true, "response" => "Coraline received: " . $message, "agent" => $agent];
        } elseif ($agent === "hyperion") {
            return ["success" => true, "response" => "Hyperion received: " . $message, "agent" => $agent];
        } elseif ($agent === "forge") {
            return ["success" => true, "response" => "Forge received: " . $message, "agent" => $agent];
        }
        // Default to hyperion
        return ["success" => true, "response" => "Hyperion received: " . $message, "agent" => "hyperion"];
    }
PHP;

// Check if routes already exist
if (strpos($content, 'workflows/(?P<id>') !== false) {
    echo "Routes already exist. Checking handlers...\n";
} else {
    // Find a good insertion point - after the last register_rest_route call
    $lines = file($file);
    $insert_line = 0;
    foreach ($lines as $i => $line) {
        if (strpos($line, 'register_rest_route') !== false) {
            $insert_line = $i + 1;
        }
    }
    array_splice($lines, $insert_line, 0, ["\n" . $new_routes . "\n"]);
    $content = implode('', $lines);
    echo "Routes inserted at line " . ($insert_line + 1) . "\n";
}

// Check if handlers exist
if (strpos($content, 'handle_workflow_run') !== false) {
    echo "Handlers already exist\n";
} else {
    // Add handlers before the last closing brace
    $pos = strrpos($content, '}');
    if ($pos !== false) {
        $pos = strrpos($content, '}', $pos - strlen($content) - 1);
        // Find the second-to-last closing brace (class close)
        $content = substr_replace($content, "\n" . $handlers . "\n", $pos, 0);
        echo "Handlers inserted\n";
    }
}

file_put_contents($file, $content);

// Verify syntax
$output = [];
$return = 0;
exec('docker exec wordpress-app php -l ' . escapeshellarg($file) . ' 2>&1', $output, $return);
if ($return !== 0) {
    echo "SYNTAX ERROR:\n" . implode("\n", $output) . "\n";
} else {
    echo "SYNTAX PASS\n";
}
