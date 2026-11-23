<?php
declare(strict_types=1);
/**
 * BITCORE API & Tools Page
 * OpenAI-compatible API endpoints with Hermes skills
 * @package     Bitcore
 * @since       1.0.0
 * @version     1.0.0
 */

// Fetch Hermes skills
$hermes_url = get_option('bitcore_hermes_url', 'http://172.17.0.1:8787');
$hermes_skills = [];
$skills_response = wp_remote_get($hermes_url . '/skills', ['timeout' => 5]);
if (!is_wp_error($skills_response)) {
    $skills_data = json_decode(wp_remote_retrieve_body($skills_response), true);
    if (isset($skills_data['skills'])) {
        $hermes_skills = $skills_data['skills'];
    }
}
?>
<div class="wrap bitcore-admin">
    <h1>API & Tools</h1>
    
    <div class="bitcore-tabs">
        <a href="#endpoints" class="tab active">REST Endpoints</a>
        <a href="#hermes" class="tab">Hermes Skills</a>
        <a href="#testing" class="tab">API Tester</a>
    </div>
    
    <!-- REST Endpoints Tab -->
    <div id="endpoints" class="tab-content active">
        <h2>REST API Endpoints</h2>
        <p class="description">OpenAI-compatible REST endpoints for integration.</p>
        
        <table class="wp-list-table widefat fixed striped">
            <thead><tr><th width="200">Endpoint</th><th>Methods</th><th>Description</th><th>Tags</th></tr></thead>
            <tbody>
                <tr>
                    <td><code>/bitcore/v1/chat</code></td>
                    <td><span class="method post" style="background: #d4edda; padding: 2px 8px; border-radius: 3px;">POST</span></td>
                    <td>OpenAI-compatible chat completion endpoint</td>
                    <td><span style="background: #cce5ff; padding: 2px 6px; border-radius: 3px;">AI</span> <span style="background: #fff3cd; padding: 2px 6px; border-radius: 3px;">Chat</span></td>
                </tr>
                <tr>
                    <td><code>/bitcore/v1/health</code></td>
                    <td><span class="method get" style="background: #cce5ff; padding: 2px 8px; border-radius: 3px;">GET</span></td>
                    <td>System health check</td>
                    <td><span style="background: #d4edda; padding: 2px 6px; border-radius: 3px;">System</span></td>
                </tr>
                <tr>
                    <td><code>/bitcore/v1/ping</code></td>
                    <td><span class="method get" style="background: #cce5ff; padding: 2px 8px; border-radius: 3px;">GET</span></td>
                    <td>Simple ping endpoint for connectivity testing</td>
                    <td><span style="background: #d4edda; padding: 2px 6px; border-radius: 3px;">System</span></td>
                </tr>
                <tr>
                    <td><code>/bitcore/v1/agents</code></td>
                    <td><span class="method get" style="background: #cce5ff; padding: 2px 8px; border-radius: 3px;">GET</span></td>
                    <td>List available agents</td>
                    <td><span style="background: #e2e3e5; padding: 2px 6px; border-radius: 3px;">Agents</span></td>
                </tr>
                <tr>
                    <td><code>/bitcore/v1/models</code></td>
                    <td><span class="method get" style="background: #cce5ff; padding: 2px 8px; border-radius: 3px;">GET</span></td>
                    <td>List available models</td>
                    <td><span style="background: #e2e3e5; padding: 2px 6px; border-radius: 3px;">Models</span></td>
                </tr>
                <tr>
                    <td><code>/bitcore/v1/org-chart</code></td>
                    <td><span class="method get" style="background: #cce5ff; padding: 2px 8px; border-radius: 3px;">GET</span> <span class="method post" style="background: #d4edda; padding: 2px 8px; border-radius: 3px;">POST</span></td>
                    <td>Agent hierarchy management</td>
                    <td><span style="background: #f8d7da; padding: 2px 6px; border-radius: 3px;">Org</span></td>
                </tr>
                <tr>
                    <td><code>/bitcore/v1/budget</code></td>
                    <td><span class="method get" style="background: #cce5ff; padding: 2px 8px; border-radius: 3px;">GET</span> <span class="method post" style="background: #d4edda; padding: 2px 8px; border-radius: 3px;">POST</span></td>
                    <td>Budget policy management</td>
                    <td><span style="background: #fff3cd; padding: 2px 6px; border-radius: 3px;">Budget</span></td>
                </tr>
                <tr>
                    <td><code>/bitcore/v1/goals</code></td>
                    <td><span class="method get" style="background: #cce5ff; padding: 2px 8px; border-radius: 3px;">GET</span> <span class="method post" style="background: #d4edda; padding: 2px 8px; border-radius: 3px;">POST</span></td>
                    <td>Goal hierarchy management</td>
                    <td><span style="background: #cce5ff; padding: 2px 6px; border-radius: 3px;">Goals</span></td>
                </tr>
                <tr>
                    <td><code>/bitcore/v1/approvals</code></td>
                    <td><span class="method get" style="background: #cce5ff; padding: 2px 8px; border-radius: 3px;">GET</span> <span class="method post" style="background: #d4edda; padding: 2px 8px; border-radius: 3px;">POST</span></td>
                    <td>Approval queue management</td>
                    <td><span style="background: #d4edda; padding: 2px 6px; border-radius: 3px;">Governance</span></td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <!-- Hermes Skills Tab -->
    <div id="hermes" class="tab-content" style="display: none;">
        <h2>Hermes Skills</h2>
        <p class="description">Available skills from Hermes agent.</p>
        
        <?php if (empty($hermes_skills)): ?>
        <div style="background: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 5px; margin: 20px 0;">
            <h3 style="margin-top: 0;">No Skills Found</h3>
            <p>Hermes is either offline or has no skills registered.</p>
            <p><strong>Hermes URL:</strong> <code><?php echo esc_html($hermes_url); ?></code></p>
            <p>Try accessing Hermes directly or check the configuration.</p>
        </div>
        <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
            <?php foreach ($hermes_skills as $skill): ?>
            <div class="skill-card" style="background: #fff; border: 1px solid #ccc; border-radius: 8px; padding: 20px;">
                <h3 style="margin: 0 0 10px 0;"><?php echo esc_html($skill['name'] ?? 'Unknown'); ?></h3>
                <p style="color: #666; margin: 0 0 10px 0;"><?php echo esc_html($skill['description'] ?? 'No description'); ?></p>
                <?php if (!empty($skill['triggers'])): ?>
                <div style="margin-top: 10px;">
                    <strong>Triggers:</strong>
                    <code style="background: #f0f0f1; padding: 2px 6px; border-radius: 3px; font-size: 12px;"><?php echo esc_html(implode(', ', $skill['triggers'])); ?></code>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <div style="margin-top: 30px;">
            <h3>Hermes Configuration</h3>
            <table class="form-table">
                <tr>
                    <th>Endpoint</th>
                    <td><code><?php echo esc_html($hermes_url); ?></code></td>
                </tr>
                <tr>
                    <th>Skills API</th>
                    <td><code><?php echo esc_html($hermes_url); ?>/skills</code></td>
                </tr>
                <tr>
                    <th>Health Check</th>
                    <td><code><?php echo esc_html($hermes_url); ?>/health</code></td>
                </tr>
            </table>
        </div>
    </div>
    
    <!-- API Tester Tab -->
    <div id="testing" class="tab-content" style="display: none;">
        <h2>API Tester</h2>
        <p class="description">Test BITCORE REST API endpoints.</p>
        
        <div style="background: #f0f0f1; padding: 20px; border-radius: 8px; margin-top: 20px;">
            <h3>Quick Test</h3>
            <p>Use these curl commands to test the API:</p>
            <pre style="background: #23282d; color: #fff; padding: 15px; border-radius: 5px; overflow-x: auto;">
# Ping test
curl -X GET <?php echo rest_url('bitcore/v1/ping'); ?>

# Health check
curl -X GET <?php echo rest_url('bitcore/v1/health'); ?>

# List agents
curl -X GET <?php echo rest_url('bitcore/v1/agents'); ?>

# Chat completion
curl -X POST <?php echo rest_url('bitcore/v1/chat'); ?> \
  -H "Content-Type: application/json" \
  -d '{"messages": [{"role": "user", "content": "Hello!"}]}'
            </pre>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Tab switching
    $('.bitcore-tabs .tab').on('click', function(e) {
        e.preventDefault();
        var target = $(this).attr('href');
        $('.bitcore-tabs .tab').removeClass('active');
        $(this).addClass('active');
        $('.tab-content').hide();
        $(target).show();
    });
});
</script>
