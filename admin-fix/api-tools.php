<?php
declare(strict_types=1);
/**
 * BITCORE API & Tools Page
 * OpenAI-compatible API endpoints with descriptions and tags
 */
?>
<div class="wrap bitcore-admin">
    <h1>🔧 API & Tools</h1>
    
    <div class="bitcore-tabs">
        <a href="#endpoints" class="tab active">REST Endpoints</a>
        <a href="#hermes" class="tab">Hermes Skills</a>
        <a href="#testing" class="tab">API Tester</a>
    </div>
    
    <div id="endpoints" class="tab-content">
        <h2>REST API Endpoints</h2>
        <p class="description">OpenAI-compatible REST endpoints for integration.</p>
        
        <table class="wp-list-table widefat fixed striped">
            <thead><tr><th width="200">Endpoint</th><th>Methods</th><th>Description</th><th>Tags</th><th>Access</th></tr></thead>
            <tbody>
                <!-- Chat Completion -->
                <tr>
                    <td><code>/hermes/chat</code></td>
                    <td><span class="method post">POST</span></td>
                    <td>OpenAI-compatible chat completion endpoint. Accepts messages array, returns streaming or JSON response.</td>
                    <td><span class="tag ai">AI</span> <span class="tag chat">Chat</span></td>
                    <td>User</td>
                </tr>
                <!-- Health -->
                <tr>
                    <td><code>/health</code></td>
                    <td><span class="method get">GET</span></td>
                    <td>System health check. Returns status, version, and component states.</td>
                    <td><span class="tag system">System</span></td>
                    <td>Public</td>
                </tr>
                <tr>
                    <td><code>/hermes/health</code></td>
                    <td><span class="method get">GET</span></td>
                    <td>Hermes agent health. Returns bridge status and timestamp.</td>
                    <td><span class="tag system">System</span> <span class="tag hermes">Hermes</span></td>
                    <td>Public</td>
                </tr>
                <!-- Tasks -->
                <tr>
                    <td><code>/hermes/task</code></td>
                    <td><span class="method post">POST</span> <span class="method get">GET</span></td>
                    <td>Create or list async tasks. Tasks run background jobs with status tracking.</td>
                    <td><span class="tag ai">AI</span> <span class="tag async">Async</span></td>
                    <td>User</td>
                </tr>
                <tr>
                    <td><code>/hermes/task/{id}</code></td>
                    <td><span class="method get">GET</span> <span class="method delete">DEL</span></td>
                    <td>Get task status or cancel running task.</td>
                    <td><span class="tag ai">AI</span> <span class="tag async">Async</span></td>
                    <td>User</td>
                </tr>
                <!-- Skills -->
                <tr>
                    <td><code>/hermes/skills</code></td>
                    <td><span class="method get">GET</span></td>
                    <td>List available Hermes skills (tools/capabilities).</td>
                    <td><span class="tag hermes">Hermes</span> <span class="tag skills">Skills</span></td>
                    <td>User</td>
                </tr>
                <tr>
                    <td><code>/hermes/skill/{name}/run</code></td>
                    <td><span class="method post">POST</span></td>
                    <td>Execute a specific Hermes skill by name.</td>
                    <td><span class="tag hermes">Hermes</span> <span class="tag skills">Skills</span></td>
                    <td>User</td>
                </tr>
                <!-- Knowledge Graph -->
                <tr>
                    <td><code>/knowledge-graph</code></td>
                    <td><span class="method get">GET</span> <span class="method post">POST</span></td>
                    <td>Query or modify the knowledge graph. Stores entities and relationships.</td>
                    <td><span class="tag kg">Knowledge</span> <span class="tag graph">Graph</span></td>
                    <td>Admin</td>
                </tr>
                <tr>
                    <td><code>/knowledge-graph/search</code></td>
                    <td><span class="method post">POST</span></td>
                    <td>Semantic search across knowledge graph nodes.</td>
                    <td><span class="tag kg">Knowledge</span> <span class="tag search">Search</span></td>
                    <td>User</td>
                </tr>
                <!-- Context -->
                <tr>
                    <td><code>/context</code></td>
                    <td><span class="method get">GET</span> <span class="method post">POST</span></td>
                    <td>Manage conversation contexts. Store and retrieve session state.</td>
                    <td><span class="tag ctx">Context</span> <span class="tag session">Session</span></td>
                    <td>User</td>
                </tr>
                <!-- Workspaces -->
                <tr>
                    <td><code>/workspaces</code></td>
                    <td><span class="method get">GET</span> <span class="method post">POST</span></td>
                    <td>Manage research workspaces. Isolated environments for projects.</td>
                    <td><span class="tag ws">Workspace</span></td>
                    <td>Admin</td>
                </tr>
                <!-- Token -->
                <tr>
                    <td><code>/token/rotate</code></td>
                    <td><span class="method post">POST</span></td>
                    <td>Rotate API authentication token. Invalidates previous token.</td>
                    <td><span class="tag auth">Auth</span> <span class="tag admin">Admin</span></td>
                    <td>Admin</td>
                </tr>
                <!-- Episodes -->
                <tr>
                    <td><code>/episodes</code></td>
                    <td><span class="method get">GET</span> <span class="method post">POST</span></td>
                    <td>Manage learning episodes for agent training.</td>
                    <td><span class="tag ml">ML</span> <span class="tag training">Training</span></td>
                    <td>Admin</td>
                </tr>
                <!-- Activity -->
                <tr>
                    <td><code>/activity</code></td>
                    <td><span class="method get">GET</span></td>
                    <td>Retrieve activity feed. Logs of API calls and system events.</td>
                    <td><span class="tag logs">Logs</span> <span class="tag monitor">Monitor</span></td>
                    <td>Admin</td>
                </tr>
                <!-- Stats -->
                <tr>
                    <td><code>/stats</code></td>
                    <td><span class="method get">GET</span></td>
                    <td>Usage statistics. API calls, tokens, costs aggregated.</td>
                    <td><span class="tag stats">Stats</span> <span class="tag usage">Usage</span></td>
                    <td>Admin</td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <div id="testing" class="tab-content" style="display:none;">
        <h2>API Tester</h2>
        <div class="tester-form">
            <label>Endpoint: 
                <select id="api-endpoint">
                    <option value="hermes/chat">/hermes/chat (POST)</option>
                    <option value="health">/health (GET)</option>
                    <option value="hermes/health">/hermes/health (GET)</option>
                    <option value="hermes/skills">/hermes/skills (GET)</option>
                    <option value="stats">/stats (GET)</option>
                </select>
            </label>
            <label>Request Body (JSON):
                <textarea id="api-body" rows="6" placeholder='{"messages": [{"role": "user", "content": "Hello"}]}'></textarea>
            </label>
            <button class="button button-primary" id="api-test-btn">Send Request</button>
        </div>
        <h3>Response:</h3>
        <pre id="api-response" class="response-box">Click "Send Request" to test an endpoint.</pre>
    </div>
</div>

<style>
.bitcore-tabs { display: flex; gap: 5px; margin-bottom: 20px; border-bottom: 1px solid #c3c4c7; padding-bottom: 10px; }
.tab { padding: 8px 16px; background: #f6f7f7; border-radius: 4px 4px 0 0; text-decoration: none; color: #50575e; }
.tab.active { background: #fff; border: 1px solid #c3c4c7; border-bottom-color: #fff; color: #0073aa; font-weight: 600; }
.method { padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: 600; margin-right: 4px; }
.method.get { background: #e7f5ff; color: #1971c2; }
.method.post { background: #fff3e0; color: #e65100; }
.method.delete { background: #ffebee; color: #c62828; }
.tag { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; background: #f0f0f1; margin: 1px; }
.tag.ai { background: #e3f2fd; color: #1565c0; }
.tag.system { background: #f3e5f5; color: #7b1fa2; }
.tag.hermes { background: #e8f5e9; color: #2e7d32; }
.tag.kg { background: #fff8e1; color: #f57f17; }
.tester-form { display: flex; flex-direction: column; gap: 15px; max-width: 600px; }
.response-box { background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 4px; overflow-x: auto; min-height: 150px; }
</style>

<script>
jQuery(document).ready(function($) {
    $('.tab').on('click', function(e) {
        e.preventDefault();
        $('.tab').removeClass('active');
        $(this).addClass('active');
        $('.tab-content').hide();
        $($(this).attr('href')).show();
    });
    
    $('#api-test-btn').on('click', function() {
        var endpoint = $('#api-endpoint').val();
        var body = $('#api-body').val();
        var method = endpoint.includes('health') || endpoint.includes('skills') || endpoint.includes('stats') ? 'GET' : 'POST';
        
        $('#api-response').text('Loading...');
        
        $.ajax({
            url: bitcoreConfig.restUrl + endpoint,
            method: method,
            contentType: 'application/json',
            data: method === 'POST' && body ? body : null,
            beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', bitcoreConfig.nonce); }
        })
        .done(function(r) { $('#api-response').text(JSON.stringify(r, null, 2)); })
        .fail(function(x) { $('#api-response').text('Error ' + x.status + ': ' + x.responseText); });
    });
});
</script>
