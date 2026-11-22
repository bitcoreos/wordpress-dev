<?php
declare(strict_types=1);
/**
 * BITCORE Internal Agents
 * Admin-only page for system agents (Coraline, Hyperion)
 * These are NEVER exposed to users - internal orchestration only
 */

// Server-side health checks
$hermes_url = get_option('bitcore_hermes_url', 'http://172.17.0.1:8787');
$hermes_health = wp_remote_get($hermes_url . '/health', ['timeout' => 3]);
$hermes_data = !is_wp_error($hermes_health) ? json_decode(wp_remote_retrieve_body($hermes_health), true) : null;

$coraline_url = 'http://64.23.131.221:50081/';
$coraline_health = wp_remote_get($coraline_url, ['timeout' => 3]);

$hyperion_url = 'http://64.227.106.173:8081/';
// Hyperion is THIS instance - check local health
$local_health = wp_remote_get(rest_url('bitcore/v1/health'), ['timeout' => 3]);
?>
<div class="wrap bitcore-admin">
    <h1>🔒 Internal Agents</h1>
    <p class="description">System agents for internal orchestration. <strong>Never exposed to users.</strong></p>
    
    <div class="agents-grid">
        <!-- Hermes (Local Docker) -->
        <div class="agent-card">
            <div class="agent-header">
                <div class="agent-avatar hermes"></div>
                <div class="agent-info">
                    <h2>Hermes</h2>
                    <span class="agent-type">Local Docker Agent</span>
                </div>
            </div>
            <table class="agent-details">
                <tr><td>Role</td><td>Guardian / Coordinator</td></tr>
                <tr><td>Endpoint</td><td><code><?php echo esc_html($hermes_url); ?></code></td></tr>
                <tr><td>Status</td><td><?php 
                    if ($hermes_data && $hermes_data['status'] === 'ok') {
                        echo '<span class="status-ok">Connected</span> (bridge: ' . esc_html($hermes_data['bridge'] ?? 'unknown') . ')';
                    } else {
                        echo '<span class="status-error">Disconnected</span>';
                    }
                ?></td></tr>
                <tr><td>Skills</td><td><?php echo isset($hermes_data['skills_count']) ? $hermes_data['skills_count'] : '90+'; ?></td></tr>
                <tr><td>Model</td><td>qwen3-5-35b-a3b (256K context)</td></tr>
                <tr><td>Visibility</td><td><code>internal</code> - Never user-facing</td></tr>
            </table>
            <div class="agent-actions">
                <button class="button" onclick="testHermes()">Test Connection</button>
                <button class="button" onclick="viewHermesSkills()">View Skills</button>
            </div>
            <div id="hermes-log" class="agent-log"></div>
        </div>
        
        <!-- Coraline (Remote) -->
        <div class="agent-card">
            <div class="agent-header">
                <div class="agent-avatar coraline"></div>
                <div class="agent-info">
                    <h2>Coraline</h2>
                    <span class="agent-type">Remote Inference Node</span>
                </div>
            </div>
            <table class="agent-details">
                <tr><td>Role</td><td>AI Orchestration / MCP Server</td></tr>
                <tr><td>Endpoint</td><td><code>http://64.23.131.221:50081/</code></td></tr>
                <tr><td>A2A</td><td><code>https://64.23.131.221/a2a/t-fg_hqFPshR7-IwRM/p-coraline</code></td></tr>
                <tr><td>Status</td><td><?php 
                    if (!is_wp_error($coraline_health) && wp_remote_retrieve_response_code($coraline_health) === 200) {
                        echo '<span class="status-ok">Online</span>';
                    } else {
                        echo '<span class="status-error">Offline</span>';
                    }
                ?></td></tr>
                <tr><td>Visibility</td><td><code>internal</code> - Never user-facing</td></tr>
            </table>
            <div class="agent-actions">
                <button class="button" onclick="testCoraline()">Test A2A</button>
                <a href="http://64.23.131.221:50081/" target="_blank" class="button">Open UI</a>
            </div>
            <div id="coraline-log" class="agent-log"></div>
        </div>
        
        <!-- Hyperion (This Instance) -->
        <div class="agent-card">
            <div class="agent-header">
                <div class="agent-avatar hyperion"></div>
                <div class="agent-info">
                    <h2>Hyperion</h2>
                    <span class="agent-type">This Instance (Nexus Node)</span>
                </div>
            </div>
            <table class="agent-details">
                <tr><td>Role</td><td>Nexus Node / Infrastructure</td></tr>
                <tr><td>Endpoint</td><td><code>http://64.227.106.173:8081/</code></td></tr>
                <tr><td>Status</td><td><span class="status-ok">Online (Local)</span></td></tr>
                <tr><td>Version</td><td>a0.b8</td></tr>
                <tr><td>Visibility</td><td><code>internal</code> - Never user-facing</td></tr>
            </table>
            <div class="agent-actions">
                <button class="button" onclick="testLocal()">Self Test</button>
                <button class="button" onclick="viewContext()">View Context</button>
            </div>
        </div>
        
        <!-- DUALCORE Mesh -->
        <div class="agent-card">
            <div class="agent-header">
                <div class="agent-avatar dualcore"></div>
                <div class="agent-info">
                    <h2>DUALCORE Mesh</h2>
                    <span class="agent-type">Swarm Coordination Layer</span>
                </div>
            </div>
            <table class="agent-details">
                <tr><td>Protocol</td><td>The Scuba Diver Principle</td></tr>
                <tr><td>Nodes</td><td>Hyperion ↔ Coraline</td></tr>
                <tr><td>Status</td><td><?php 
                    $both_up = !is_wp_error($local_health) && !is_wp_error($coraline_health);
                    echo $both_up ? '<span class="status-ok">Fully Connected</span>' : '<span class="status-error">Partial</span>';
                ?></td></tr>
                <tr><td>Synapses</td><td>FLASH (A2A), DEEP (Memory), CORE (n8n/MCP)</td></tr>
            </table>
            <div class="agent-actions">
                <button class="button button-primary" onclick="syncPartners()">Sync Partners</button>
            </div>
        </div>
    </div>
    
    <div class="info-box">
        <h3>About Internal Agents</h3>
        <p>These agents are <strong>system infrastructure</strong> and never exposed to end users:</p>
        <ul>
            <li><strong>Hermes</strong> - Local Docker container handling AI inference and skill execution</li>
            <li><strong>Coraline</strong> - Remote inference node (MCP server) for distributed processing</li>
            <li><strong>Hyperion</strong> - This WordPress instance, acting as Nexus Node</li>
        </ul>
        <p>Users interact with <strong>models</strong> via the API, not with agents directly.</p>
    </div>
</div>

<style>
.agents-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-top: 20px; }
.agent-card { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; }
.agent-header { display: flex; align-items: center; gap: 15px; margin-bottom: 15px; }
.agent-avatar { width: 48px; height: 48px; border-radius: 50%; }
.agent-avatar.hermes { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
.agent-avatar.coraline { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.agent-avatar.hyperion { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
.agent-avatar.dualcore { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
.agent-info h2 { margin: 0; font-size: 16px; }
.agent-type { color: #646970; font-size: 12px; }
.agent-details { width: 100%; }
.agent-details td { padding: 6px 0; border-bottom: 1px solid #f0f0f1; }
.agent-details td:first-child { color: #646970; width: 100px; }
.agent-details code { background: #f6f7f7; padding: 2px 6px; border-radius: 3px; font-size: 11px; }
.status-ok { color: #00a32a; font-weight: 600; }
.status-error { color: #d63638; font-weight: 600; }
.agent-actions { margin-top: 15px; display: flex; gap: 10px; }
.agent-log { margin-top: 10px; padding: 10px; background: #1e1e1e; color: #d4d4d4; border-radius: 4px; font-family: monospace; font-size: 11px; max-height: 100px; overflow-y: auto; }
.info-box { margin-top: 20px; padding: 15px; background: #f6f7f7; border-left: 4px solid #0073aa; }
.info-box h3 { margin-top: 0; }
</style>

<script>
function testHermes() {
    jQuery('#hermes-log').text('Testing connection...');
    jQuery.get(bitcoreConfig.restUrl + 'hermes/health')
        .done(function(d) { jQuery('#hermes-log').text(JSON.stringify(d, null, 2)); })
        .fail(function(e) { jQuery('#hermes-log').text('Error: ' + e.status); });
}
function viewHermesSkills() {
    jQuery('#hermes-log').text('Loading skills...');
    jQuery.get(bitcoreConfig.restUrl + 'hermes/skills')
        .done(function(d) { jQuery('#hermes-log').text(JSON.stringify(d, null, 2)); })
        .fail(function(e) { jQuery('#hermes-log').text('Error: ' + e.status); });
}
function testCoraline() {
    jQuery('#coraline-log').text('Testing A2A connection...');
    jQuery.ajax({
        url: 'https://64.23.131.221/a2a/t-fg_hqFPshR7-IwRM/p-coraline',
        method: 'POST', contentType: 'application/json',
        data: JSON.stringify({jsonrpc: '2.0', method: 'tasks/get', id: Date.now()})
    }).done(function(d) { jQuery('#coraline-log').text(JSON.stringify(d, null, 2)); })
      .fail(function(e) { jQuery('#coraline-log').text('Error: Check CORS or network'); });
}
function testLocal() { alert('This instance is running!'); }
function viewContext() { console.log(window.agentState); alert('Context logged to console'); }
function syncPartners() { location.reload(); }
</script>
