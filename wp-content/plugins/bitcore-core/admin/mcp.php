<?php
declare(strict_types=1);
/**
 * BITCORE Admin MCP Page - Functional Version
 * Dynamically discovers and tests MCP tools
 */
?>
<div class="wrap bitcore-admin">
    <h1>MCP Tools</h1>

    <div class="bitcore-mcp-grid">
        <div class="bitcore-card">
            <h2>🔌 Available Tools</h2>
            <p class="description">Tools are discovered dynamically from the REST API.</p>
            <table class="wp-list-table widefat fixed striped" id="mcp-tools-table">
                <thead>
                    <tr>
                        <th>Tool Name</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="mcp-tools-body">
                    <tr><td colspan="4">Loading tools...</td></tr>
                </tbody>
            </table>
        </div>

        <div class="bitcore-card">
            <h2>🧪 Tool Tester</h2>
            <div class="tool-tester">
                <label for="mcp-tool-select">Select Tool:</label>
                <select id="mcp-tool-select" class="regular-text">
                    <option value="">Loading...</option>
                </select>
                
                <label for="mcp-tool-args">Arguments (JSON):</label>
                <textarea id="mcp-tool-args" class="large-text" rows="5" placeholder='{"query": "test"}'></textarea>
                
                <button class="button button-primary" id="mcp-test-btn">Execute Tool</button>
                <button class="button" id="mcp-clear-btn">Clear</button>
                
                <h3>Result:</h3>
                <div id="mcp-result" class="mcp-result">Select a tool and click Execute.</div>
            </div>
        </div>
    </div>
    
    <div class="bitcore-card" style="margin-top: 20px;">
        <h2>📊 REST API Endpoints</h2>
        <p class="description">All BITCORE REST endpoints available for integration.</p>
        <div id="rest-endpoints" style="max-height: 400px; overflow-y: auto;">
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th>Endpoint</th><th>Methods</th><th>Test</th></tr></thead>
                <tbody id="endpoints-body">
                    <tr><td colspan="3">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.bitcore-mcp-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 20px; }
.status-active { color: #00a32a; font-weight: 500; }
.status-inactive { color: #d63638; }
.tool-tester { display: flex; flex-direction: column; gap: 12px; }
.tool-tester label { font-weight: 600; margin-top: 10px; }
.tool-tester select, .tool-tester textarea { width: 100%; }
.mcp-result { background: #1e1e1e; color: #d4d4d4; border-radius: 4px; padding: 15px; min-height: 150px; font-family: 'Monaco', 'Menlo', monospace; font-size: 12px; white-space: pre-wrap; overflow-x: auto; }
.endpoint-methods { display: flex; gap: 5px; }
.method-badge { padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; }
.method-get { background: #e7f5ff; color: #1971c2; }
.method-post { background: #fff3e0; color: #e65100; }
.method-delete { background: #ffebee; color: #c62828; }
</style>

<script>
jQuery(document).ready(function($) {
    var tools = [];
    var endpoints = [];
    
    // Discover tools and endpoints from REST API
    function discoverAPI() {
        $.get(bitcoreConfig.restUrl)
            .done(function(data) {
                // Parse routes
                if (data.routes) {
                    endpoints = Object.keys(data.routes).filter(function(r) {
                        return r !== '/bitcore/v1' && !r.includes('(?P');
                    }).map(function(route) {
                        var routeData = data.routes[route];
                        return {
                            path: route,
                            methods: routeData.methods || ['GET']
                        };
                    });
                    
                    renderEndpoints();
                    
                    // Extract MCP-like tools from routes
                    tools = endpoints.filter(function(e) {
                        return e.path.includes('eliza') || e.path.includes('mcp') || e.path.includes('knowledge') || e.path.includes('context');
                    }).map(function(e) {
                        return {
                            name: e.path.replace('/bitcore/v1/', '').replace(/\//g, '_'),
                            endpoint: e.path,
                            methods: e.methods,
                            description: 'REST endpoint: ' + e.path
                        };
                    });
                    
                    renderTools();
                }
            })
            .fail(function(xhr) {
                $('#mcp-tools-body').html('<tr><td colspan="4">Error loading tools: ' + xhr.statusText + '</td></tr>');
            });
    }
    
    function renderTools() {
        var html = '';
        tools.forEach(function(tool) {
            html += '<tr>';
            html += '<td><code>' + tool.name + '</code></td>';
            html += '<td>' + tool.description + '</td>';
            html += '<td><span class="status-active">Available</span></td>';
            html += '<td><button class="button button-small test-tool" data-endpoint="' + tool.endpoint + '">Test</button></td>';
            html += '</tr>';
        });
        
        if (tools.length === 0) {
            html = '<tr><td colspan="4">No tools discovered</td></tr>';
        }
        
        $('#mcp-tools-body').html(html);
        
        // Update select
        var selectHtml = '<option value="">Select a tool...</option>';
        tools.forEach(function(tool) {
            selectHtml += '<option value="' + tool.endpoint + '">' + tool.name + '</option>';
        });
        $('#mcp-tool-select').html(selectHtml);
    }
    
    function renderEndpoints() {
        var html = '';
        endpoints.forEach(function(ep) {
            html += '<tr>';
            html += '<td><code>' + ep.path + '</code></td>';
            html += '<td><div class="endpoint-methods">';
            ep.methods.forEach(function(m) {
                var cls = 'method-' + m.toLowerCase();
                html += '<span class="method-badge ' + cls + '">' + m + '</span>';
            });
            html += '</div></td>';
            html += '<td><button class="button button-small test-endpoint" data-path="' + ep.path + '">GET</button></td>';
            html += '</tr>';
        });
        $('#endpoints-body').html(html);
    }
    
    // Execute tool
    $('#mcp-test-btn').on('click', function() {
        var endpoint = $('#mcp-tool-select').val();
        var args = $('#mcp-tool-args').val();
        
        if (!endpoint) {
            alert('Please select a tool');
            return;
        }
        
        $('#mcp-result').text('Executing...');
        
        var method = 'GET';
        var data = null;
        
        if (args && args.trim()) {
            method = 'POST';
            try {
                data = JSON.parse(args);
            } catch (e) {
                $('#mcp-result').text('Invalid JSON: ' + e.message);
                return;
            }
        }
        
        $.ajax({
            url: bitcoreConfig.restUrl.replace('/bitcore/v1/', endpoint),
            method: method,
            data: data ? JSON.stringify(data) : null,
            contentType: 'application/json',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', bitcoreConfig.nonce);
            }
        })
        .done(function(response) {
            $('#mcp-result').text(JSON.stringify(response, null, 2));
        })
        .fail(function(xhr) {
            $('#mcp-result').text('Error ' + xhr.status + ': ' + xhr.responseText);
        });
    });
    
    $('#mcp-clear-btn').on('click', function() {
        $('#mcp-result').text('');
        $('#mcp-tool-args').val('');
    });
    
    // Quick test buttons
    $(document).on('click', '.test-tool, .test-endpoint', function() {
        var path = $(this).data('endpoint') || $(this).data('path');
        $('#mcp-tool-select').val(path);
        $('#mcp-result').text('Testing ' + path + '...');
        
        $.get(bitcoreConfig.restUrl.replace('/bitcore/v1/', path))
            .done(function(r) { $('#mcp-result').text(JSON.stringify(r, null, 2)); })
            .fail(function(x) { $('#mcp-result').text('Error: ' + x.status); });
    });
    
    // Initialize
    discoverAPI();
});
</script>
