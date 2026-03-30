<?php
declare(strict_types=1);
/**
 * BITCORE Admin MCP Page
 */
?>
<div class="wrap bitcore-admin">
    <h1>MCP Tools</h1>

    <div class="bitcore-mcp-grid">
        <div class="bitcore-card">
            <h2>Available Tools</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Tool Name</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>search_bitwiki</code></td>
                        <td>Search BITwiki knowledge base</td>
                        <td><span class="status-active">Active</span></td>
                        <td><button class="button button-small">Test</button></td>
                    </tr>
                    <tr>
                        <td><code>get_artifacts</code></td>
                        <td>Get available artifacts from BIThub</td>
                        <td><span class="status-active">Active</span></td>
                        <td><button class="button button-small">Test</button></td>
                    </tr>
                    <tr>
                        <td><code>submit_research_intent</code></td>
                        <td>Submit research intent to librarian</td>
                        <td><span class="status-active">Active</span></td>
                        <td><button class="button button-small">Test</button></td>
                    </tr>
                    <tr>
                        <td><code>get_queue_status</code></td>
                        <td>Get current research queue status</td>
                        <td><span class="status-active">Active</span></td>
                        <td><button class="button button-small">Test</button></td>
                    </tr>
                    <tr>
                        <td><code>dispatch_to_swarm</code></td>
                        <td>Dispatch task to agent swarm</td>
                        <td><span class="status-active">Active</span></td>
                        <td><button class="button button-small">Test</button></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="bitcore-card">
            <h2>Tool Tester</h2>
            <div class="tool-tester">
                <select id="mcp-tool-select" class="regular-text">
                    <option value="">Select a tool...</option>
                    <option value="search_bitwiki">search_bitwiki</option>
                    <option value="get_artifacts">get_artifacts</option>
                    <option value="submit_research_intent">submit_research_intent</option>
                    <option value="get_queue_status">get_queue_status</option>
                    <option value="dispatch_to_swarm">dispatch_to_swarm</option>
                </select>
                <textarea id="mcp-tool-args" class="large-text" rows="5" placeholder='{"query": "test"}'></textarea>
                <button class="button button-primary" id="mcp-test-btn">Execute Tool</button>
                <div id="mcp-result" class="mcp-result"></div>
            </div>
        </div>
    </div>
</div>

<style>
.bitcore-mcp-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
}
.status-active {
    color: #00a32a;
    font-weight: 500;
}
.tool-tester {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.tool-tester select,
.tool-tester textarea {
    width: 100%;
}
.mcp-result {
    background: #f6f7f7;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 12px;
    min-height: 100px;
    font-family: monospace;
    font-size: 12px;
    white-space: pre-wrap;
}
</style>

<script>
jQuery(document).ready(function($) {
    $('#mcp-test-btn').on('click', function() {
        var tool = $('#mcp-tool-select').val();
        var args = $('#mcp-tool-args').val();

        if (!tool) {
            alert('Please select a tool');
            return;
        }

        $('#mcp-result').text('Executing...');

        $.ajax({
            url: bitcoreConfig.restUrl + 'mcp/call',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', bitcoreConfig.nonce);
            },
            data: JSON.stringify({
                tool: tool,
                args: args ? JSON.parse(args) : {}
            }),
            contentType: 'application/json',
            success: function(response) {
                $('#mcp-result').text(JSON.stringify(response, null, 2));
            },
            error: function(xhr) {
                $('#mcp-result').text('Error: ' + xhr.responseText);
            }
        });
    });
});
</script>
