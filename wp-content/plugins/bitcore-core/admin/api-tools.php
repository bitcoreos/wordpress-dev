<?php
declare(strict_types=1);
/**
 * BITCORE API & Tools Page
 * @package     Bitcore
 * @since       1.0.0
 * @version     1.0.0
 */

$eliza_url = get_option('bitcore_eliza_url', 'http://127.0.0.1:3000');
$eliza_agents = [];
$agents_response = wp_remote_get($eliza_url . '/api/agents', ['timeout' => 5]);
if (!is_wp_error($agents_response)) {
    $agents_data = json_decode(wp_remote_retrieve_body($agents_response), true);
    if (isset($agents_data['agents'])) {
        $eliza_agents = $agents_data['agents'];
    }
}
?>
<style>
.bitcore-tabs { margin: 20px 0 10px 0; border-bottom: 1px solid #ccc; }
.bitcore-tabs .tab { 
    display: inline-block; padding: 8px 16px; margin-right: 4px;
    background: #f0f0f1; border: 1px solid #ccc; border-bottom: none;
    text-decoration: none; color: #555; border-radius: 4px 4px 0 0;
}
.bitcore-tabs .tab:hover { background: #e0e0e0; }
.bitcore-tabs .tab.active { background: #fff; color: #0073aa; font-weight: 600; border-bottom: 1px solid #fff; margin-bottom: -1px; }
.tab-content { display: none; padding: 20px 0; }
.tab-content.active { display: block; }
</style>
<div class="wrap bitcore-admin">
    <h1>API & Tools</h1>
    
    <div class="bitcore-tabs">
        <a href="#endpoints" class="tab active">REST Endpoints</a>
        <a href="#eliza" class="tab">ElizaOS Agents</a>
        <a href="#testing" class="tab">API Tester</a>
    </div>
    
    <div id="endpoints" class="tab-content active">
        <h2>REST API Endpoints</h2>
        <p class="description">OpenAI-compatible REST endpoints for integration.</p>
        <table class="wp-list-table widefat fixed striped">
            <thead><tr><th width="200">Endpoint</th><th>Methods</th><th>Description</th><th>Tags</th></tr></thead>
            <tbody>
                <tr><td><code>/bitcore/v1/chat</code></td><td><span style="background: #d4edda; padding: 2px 8px; border-radius: 3px;">POST</span></td><td>OpenAI-compatible chat completion</td><td><span style="background: #cce5ff; padding: 2px 6px; border-radius: 3px;">AI</span></td></tr>
                <tr><td><code>/bitcore/v1/health</code></td><td><span style="background: #cce5ff; padding: 2px 8px; border-radius: 3px;">GET</span></td><td>System health check</td><td><span style="background: #d4edda; padding: 2px 6px; border-radius: 3px;">System</span></td></tr>
                <tr><td><code>/bitcore/v1/ping</code></td><td><span style="background: #cce5ff; padding: 2px 8px; border-radius: 3px;">GET</span></td><td>Connectivity test</td><td><span style="background: #d4edda; padding: 2px 6px; border-radius: 3px;">System</span></td></tr>
                <tr><td><code>/bitcore/v1/agents</code></td><td><span style="background: #cce5ff; padding: 2px 8px; border-radius: 3px;">GET</span></td><td>List available agents</td><td><span style="background: #e2e3e5; padding: 2px 6px; border-radius: 3px;">Agents</span></td></tr>
                <tr><td><code>/bitcore/v1/models</code></td><td><span style="background: #cce5ff; padding: 2px 8px; border-radius: 3px;">GET</span></td><td>List available models</td><td><span style="background: #e2e3e5; padding: 2px 6px; border-radius: 3px;">Models</span></td></tr>
                <tr><td><code>/bitcore/v1/org-chart</code></td><td><span style="background: #cce5ff; padding: 2px 8px; border-radius: 3px;">GET</span> <span style="background: #d4edda; padding: 2px 8px; border-radius: 3px;">POST</span></td><td>Flat agent roles</td><td><span style="background: #f8d7da; padding: 2px 6px; border-radius: 3px;">Org</span></td></tr>
                <tr><td><code>/bitcore/v1/credits/tiers</code></td><td><span style="background: #cce5ff; padding: 2px 8px; border-radius: 3px;">GET</span></td><td>Credits tiers and subscription limits</td><td><span style="background: #fff3cd; padding: 2px 6px; border-radius: 3px;">Credits</span></td></tr>
                <tr><td><code>/bitcore/v1/credits/{agent_id}</code></td><td><span style="background: #cce5ff; padding: 2px 8px; border-radius: 3px;">GET</span></td><td>Credits usage status for an agent</td><td><span style="background: #fff3cd; padding: 2px 6px; border-radius: 3px;">Credits</span></td></tr>
                <tr><td><code>/bitcore/v1/tasks</code></td><td><span style="background: #cce5ff; padding: 2px 8px; border-radius: 3px;">GET</span> <span style="background: #d4edda; padding: 2px 8px; border-radius: 3px;">POST</span></td><td>Unified tasks, goals, milestones, and sprints</td><td><span style="background: #cce5ff; padding: 2px 6px; border-radius: 3px;">Unified tasks</span></td></tr>
                <tr><td><code>/bitcore/v1/approvals</code></td><td><span style="background: #cce5ff; padding: 2px 8px; border-radius: 3px;">GET</span> <span style="background: #d4edda; padding: 2px 8px; border-radius: 3px;">POST</span></td><td>Approval queue</td><td><span style="background: #d4edda; padding: 2px 6px; border-radius: 3px;">Governance</span></td></tr>
            </tbody>
        </table>
    </div>
    
    <div id="eliza" class="tab-content">
        <h2>ElizaOS Agents</h2>
        <p class="description">Available agents from ElizaOS runtime.</p>
        <?php if (empty($eliza_agents)): ?>
        <div style="background: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 5px; margin: 20px 0;">
            <h3 style="margin-top: 0;">No Agents Found</h3>
            <p>ElizaOS is either offline or has no agents registered.</p>
            <p><strong>ElizaOS URL:</strong> <code><?php echo esc_html($eliza_url); ?></code></p>
        </div>
        <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
            <?php foreach ($eliza_agents as $agent): ?>
            <div class="skill-card" style="background: #fff; border: 1px solid #ccc; border-radius: 8px; padding: 20px;">
                <h3 style="margin: 0 0 10px 0;"><?php echo esc_html($agent['name'] ?? 'Unknown'); ?></h3>
                <p style="color: #666; margin: 0 0 10px 0;"><?php echo esc_html($agent['description'] ?? 'No description'); ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div style="margin-top: 30px;">
            <h3>ElizaOS Configuration</h3>
            <table class="form-table">
                <tr><th>Endpoint</th><td><code><?php echo esc_html($eliza_url); ?></code></td></tr>
                <tr><th>Agents API</th><td><code><?php echo esc_html($eliza_url); ?>/api/agents</code></td></tr>
                <tr><th>Health Check</th><td><code><?php echo esc_html($eliza_url); ?>/api/server/health</code></td></tr>
            </table>
        </div>
    </div>
    
    <div id="testing" class="tab-content">
        <h2>API Tester</h2>
        <p class="description">Test BITCORE REST API endpoints.</p>
        <div style="background: #f0f0f1; padding: 20px; border-radius: 8px; margin-top: 20px;">
            <h3>Quick Test Commands</h3>
            <pre style="background: #23282d; color: #fff; padding: 15px; border-radius: 5px; overflow-x: auto;">
# Ping test
curl -X GET <?php echo rest_url('bitcore/v1/ping'); ?>

# Health check
curl -X GET <?php echo rest_url('bitcore/v1/health'); ?>

# List agents
curl -X GET <?php echo rest_url('bitcore/v1/agents'); ?>
            </pre>
        </div>
    </div>
</div>
<script>
jQuery(document).ready(function($) {
    $('.bitcore-tabs .tab').on('click', function(e) {
        e.preventDefault();
        var target = $(this).attr('href');
        $('.bitcore-tabs .tab').removeClass('active');
        $(this).addClass('active');
        $('.tab-content').removeClass('active').hide();
        $(target).addClass('active').show();
    });
});
</script>
