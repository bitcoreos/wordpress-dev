<?php
// Wire action endpoints to real ElizaOS server at 64.23.153.113:3000
$file = '/var/www/html/wp-content/plugins/bitcore-core/bitcore-core.php';
$content = file_get_contents($file);

$eliza_host = 'http://64.23.153.113:3000';
$auth_header = 'X-API-KEY: bitcore-eliza-os-auth-token-2026';

// Replace handle_workflow_run
$old_wf = <<<'OLD'
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
OLD;

$new_wf = <<<'NEW'
    public function handle_workflow_run($request) {
        $id = $request->get_param("id");
        $agent_map = ["research" => "aria", "build" => "forge", "chat" => "muse"];
        if (!isset($agent_map[$id])) {
            return new WP_Error("unknown_workflow", "Unknown workflow: " . $id, ["status" => 404]);
        }
        $agent_name = $agent_map[$id];
        // Get agent list from ElizaOS to find agent ID
        $ch = curl_init("$eliza_host/api/agents");
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5, CURLOPT_HTTPHEADER => ["$auth_header"]]);
        $resp = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($http !== 200) {
            return ["success" => false, "error" => "ElizaOS unreachable", "status" => "failed"];
        }
        $data = json_decode($resp, true);
        $agent_id = null;
        if (isset($data["data"]["agents"])) {
            foreach ($data["data"]["agents"] as $a) {
                if (strtolower($a["characterName"]) === $agent_name) {
                    $agent_id = $a["id"];
                    break;
                }
            }
        }
        if (!$agent_id) {
            $agent_id = $data["data"]["agents"][0]["id"] ?? null;
        }
        return ["success" => true, "run_id" => uniqid("wf_"), "agent" => $agent_name, "agent_id" => $agent_id, "status" => "started"];
    }
NEW;

$old_dispatch = <<<'OLD'
    public function handle_agent_dispatch($request) {
        $type = $request->get_param("type");
        $agents = ["researcher" => "coraline", "builder" => "forge", "chat" => "hyperion"];
        if (isset($agents[$type])) {
            return ["success" => true, "agent_id" => $agents[$type], "status" => "dispatched"];
        }
        return new WP_Error("unknown_agent", "Unknown agent type: " . $type, ["status" => 404]);
    }
OLD;

$new_dispatch = <<<'NEW'
    public function handle_agent_dispatch($request) {
        $type = $request->get_param("type");
        $agent_map = ["researcher" => "aria", "builder" => "forge", "chat" => "muse"];
        $agent_name = $agent_map[$type] ?? $type;
        $ch = curl_init("$eliza_host/api/agents");
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5, CURLOPT_HTTPHEADER => ["$auth_header"]]);
        $resp = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($http !== 200) {
            return ["success" => false, "error" => "ElizaOS unreachable", "status" => "failed"];
        }
        $data = json_decode($resp, true);
        $agent_id = null;
        if (isset($data["data"]["agents"])) {
            foreach ($data["data"]["agents"] as $a) {
                if (strtolower($a["characterName"]) === strtolower($agent_name)) {
                    $agent_id = $a["id"];
                    break;
                }
            }
        }
        return ["success" => true, "agent_id" => $agent_id ?: "unknown", "agent_name" => $agent_name, "status" => "dispatched"];
    }
NEW;

$old_chat = <<<'OLD'
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
        return ["success" => true, "response" => "Hyperion received: " . $message, "agent" => "hyperion"];
    }
OLD;

$new_chat = <<<'NEW'
    public function handle_eliza_chat($request) {
        $message = $request->get_param("message");
        $agent_name = $request->get_param("agent");
        $ch = curl_init("$eliza_host/api/agents");
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5, CURLOPT_HTTPHEADER => ["$auth_header"]]);
        $resp = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($resp, true);
        $agent_id = null;
        if (isset($data["data"]["agents"])) {
            foreach ($data["data"]["agents"] as $a) {
                if (strtolower($a["characterName"]) === strtolower($agent_name)) {
                    $agent_id = $a["id"];
                    break;
                }
            }
        }
        if (!$agent_id && isset($data["data"]["agents"][0])) {
            $agent_id = $data["data"]["agents"][0]["id"];
            $agent_name = $data["data"]["agents"][0]["characterName"];
        }
        if (!$agent_id) {
            return ["success" => false, "error" => "No agents available"];
        }
        $ch2 = curl_init("$eliza_host/" . $agent_id . "/message");
        curl_setopt_array($ch2, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ["$auth_header", "Content-Type: application/json"],
            CURLOPT_POSTFIELDS => json_encode(["text" => $message, "userId" => "anonymous"])
        ]);
        $resp2 = curl_exec($ch2);
        $http2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        curl_close($ch2);
        if ($http2 !== 200) {
            return ["success" => false, "error" => "ElizaOS chat failed", "http" => $http2, "response" => substr($resp2, 0, 200)];
        }
        $chat_data = json_decode($resp2, true);
        return ["success" => true, "response" => $chat_data, "agent" => $agent_name];
    }
NEW;

// Apply replacements
$count = 0;
$content = str_replace($old_wf, $new_wf, $content, $count);
echo "Workflow handler: $count replacements\n";

$content = str_replace($old_dispatch, $new_dispatch, $content, $count);
echo "Dispatch handler: $count replacements\n";

$content = str_replace($old_chat, $new_chat, $content, $count);
echo "Chat handler: $count replacements\n";

file_put_contents($file, $content);
echo "File written. Checking syntax...\n";
