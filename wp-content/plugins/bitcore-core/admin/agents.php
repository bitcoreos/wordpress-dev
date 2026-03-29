<?php
declare(strict_types=1);
/**
 * BITCORE Admin Agents Page - Functional Version
 * Connects to actual REST endpoints for live status
 */
?>
<div class="wrap bitcore-admin">
    <h1>Agent Management</h1>

    <div class="bitcore-agents-grid">
        <!-- Coraline -->
        <div class="bitcore-card agent-card">
            <div class="agent-header">
                <div class="agent-avatar coraline"></div>
                <div class="agent-info">
                    <h2>Coraline</h2>
                    <span class="agent-role">Orchestration Layer</span>
                </div>
            </div>
            <div class="agent-stats">
                <div class="agent-stat">
                    <span class="stat-label">Status</span>
                    <span class="stat-value" id="coraline-status">Checking...</span>
                </div>
                <div class="agent-stat">
                    <span class="stat-label">Bridge</span>
                    <span class="stat-value" id="coraline-bridge">-</span>
                </div>
                <div class="agent-stat">
                    <span class="stat-label">Endpoint</span>
                    <span class="stat-value small">http://64.23.131.221:50081/</span>
                </div>
            </div>
            <div class="agent-actions">
                <button class="button" id="test-coraline">Test Connection</button>
                <button class="button" id="ping-coraline">Ping</button>
            </div>
            <div class="agent-log" id="coraline-log"></div>
        </div>

        <!-- Hyperion -->
        <div class="bitcore-card agent-card">
            <div class="agent-header">
                <div class="agent-avatar hyperion"></div>
                <div class="agent-info">
                    <h2>Hyperion</h2>
                    <span class="agent-role">Nexus Node (This Instance)</span>
                </div>
            </div>
            <div class="agent-stats">
                <div class="agent-stat">
                    <span class="stat-label">Status</span>
                    <span class="stat-value online">Online (Local)</span>
                </div>
                <div class="agent-stat">
                    <span class="stat-label">Endpoint</span>
                    <span class="stat-value small">http://64.227.106.173:8081/</span>
                </div>
                <div class="agent-stat">
                    <span class="stat-label">Version</span>
                    <span class="stat-value">a0.b8</span>
                </div>
            </div>
            <div class="agent-actions">
                <button class="button" id="test-hyperion">Self Test</button>
                <button class="button" id="view-context">View Context</button>
            </div>
        </div>

        <!-- Hermes -->
        <div class="bitcore-card agent-card">
            <div class="agent-header">
                <div class="agent-avatar hermes"></div>
                <div class="agent-info">
                    <h2>Hermes</h2>
                    <span class="agent-role">AI Agent Bridge</span>
                </div>
            </div>
            <div class="agent-stats">
                <div class="agent-stat">
                    <span class="stat-label">Status</span>
                    <span class="stat-value" id="hermes-status">Checking...</span>
                </div>
                <div class="agent-stat">
                    <span class="stat-label">Bridge</span>
                    <span class="stat-value" id="hermes-bridge">-</span>
                </div>
                <div class="agent-stat">
                    <span class="stat-label">Skills</span>
                    <span class="stat-value" id="hermes-skills">-</span>
                </div>
            </div>
            <div class="agent-actions">
                <button class="button" id="test-hermes">Test Connection</button>
                <button class="button" id="list-skills">List Skills</button>
            </div>
            <div class="agent-log" id="hermes-log"></div>
        </div>

        <!-- DUALCORE Status -->
        <div class="bitcore-card agent-card">
            <div class="agent-header">
                <div class="agent-avatar dualcore"></div>
                <div class="agent-info">
                    <h2>DUALCORE Network</h2>
                    <span class="agent-role">Swarm Coordination</span>
                </div>
            </div>
            <div class="agent-stats">
                <div class="agent-stat full-width">
                    <span class="stat-label">Mesh Status</span>
                    <span class="stat-value" id="mesh-status">Checking...</span>
                </div>
            </div>
            <div class="agent-actions">
                <button class="button button-primary" id="sync-partners">Sync Partners</button>
                <button class="button" id="view-registry">View Registry</button>
            </div>
        </div>
    </div>
</div>

<style>
.bitcore-agents-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
.agent-card { padding: 24px; }
.agent-header { display: flex; align-items: center; gap: 16px; margin-bottom: 20px; }
.agent-avatar { width: 64px; height: 64px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.agent-avatar.hyperion { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
.agent-avatar.hermes { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
.agent-avatar.dualcore { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
.agent-info h2 { margin: 0 0 4px 0; }
.agent-role { color: #646970; font-size: 13px; }
.agent-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 20px; }
.agent-stat { text-align: center; padding: 12px; background: #f6f7f7; border-radius: 4px; }
.agent-stat.full-width { grid-column: span 3; }
.agent-stat .stat-label { display: block; font-size: 12px; color: #646970; margin-bottom: 4px; }
.agent-stat .stat-value { font-size: 18px; font-weight: 600; }
.agent-stat .stat-value.small { font-size: 11px; font-weight: normal; word-break: break-all; }
.agent-stat .stat-value.online { color: #00a32a; }
.agent-stat .stat-value.offline { color: #d63638; }
.agent-actions { display: flex; gap: 12px; flex-wrap: wrap; }
.agent-log { margin-top: 15px; padding: 10px; background: #f0f0f1; border-radius: 4px; font-family: monospace; font-size: 11px; max-height: 150px; overflow-y: auto; white-space: pre-wrap; }
</style>

<script>
jQuery(document).ready(function($) {
    // Check Hermes health
    function checkHermes() {
        $('#hermes-status').html('<span class="checking">Checking...</span>');
        $.get(bitcoreConfig.restUrl + 'hermes/health')
            .done(function(data) {
                $('#hermes-status').html('<span class="online">' + data.status + '</span>');
                $('#hermes-bridge').text(data.bridge || 'unknown');
            })
            .fail(function() {
                $('#hermes-status').html('<span class="offline">Offline</span>');
                $('#hermes-bridge').text('disconnected');
            });
    }
    
    // Get Hermes skills count
    function getHermesSkills() {
        $.get(bitcoreConfig.restUrl + 'hermes/skills')
            .done(function(data) {
                var count = Array.isArray(data) ? data.length : (data.count || 0);
                $('#hermes-skills').text(count);
            })
            .fail(function() {
                $('#hermes-skills').text('error');
            });
    }
    
    // Test Coraline (A2A)
    function testCoraline() {
        $('#coraline-status').html('<span class="checking">Checking...</span>');
        $('#coraline-log').text('Connecting to Coraline...');
        
        $.ajax({
            url: 'http://64.23.131.221:50081/a2a',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                jsonrpc: '2.0',
                method: 'tasks/get',
                id: Date.now()
            }),
            timeout: 5000
        })
        .done(function(data) {
            $('#coraline-status').html('<span class="online">Online</span>');
            $('#coraline-bridge').text('a2a');
            $('#coraline-log').text(JSON.stringify(data, null, 2));
        })
        .fail(function(xhr, status) {
            $('#coraline-status').html('<span class="offline">Offline</span>');
            $('#coraline-bridge').text(status);
            $('#coraline-log').text('Error: ' + status);
        });
    }
    
    // Check mesh status
    function checkMesh() {
        $('#mesh-status').html('<span class="checking">Checking...</span>');
        
        var checks = [
            { name: 'Hermes', url: bitcoreConfig.restUrl + 'hermes/health' },
            { name: 'Health', url: bitcoreConfig.restUrl + 'health' }
        ];
        
        var results = [];
        var completed = 0;
        
        checks.forEach(function(check) {
            $.get(check.url)
                .done(function() { results.push(check.name + ': OK'); completed++; if (completed === checks.length) updateMesh(); })
                .fail(function() { results.push(check.name + ': FAIL'); completed++; if (completed === checks.length) updateMesh(); });
        });
        
        function updateMesh() {
            var ok = results.filter(function(r) { return r.includes('OK'); }).length;
            if (ok === checks.length) {
                $('#mesh-status').html('<span class="online">Fully Connected (' + ok + '/' + checks.length + ')</span>');
            } else {
                $('#mesh-status').html('<span class="offline">Partial (' + ok + '/' + checks.length + ')</span>');
            }
        }
    }
    
    // Initialize
    checkHermes();
    getHermesSkills();
    checkMesh();
    testCoraline();
    
    // Button handlers
    $('#test-coraline').on('click', testCoraline);
    $('#ping-coraline').on('click', function() {
        $('#coraline-log').text('Sending ping...');
        $.get('http://64.23.131.221:50081/').done(function() { $('#coraline-log').text('PONG'); }).fail(function(e) { $('#coraline-log').text('Ping failed: ' + e.statusText); });
    });
    
    $('#test-hermes').on('click', function() { checkHermes(); getHermesSkills(); });
    $('#list-skills').on('click', function() {
        $.get(bitcoreConfig.restUrl + 'hermes/skills').done(function(d) { $('#hermes-log').text(JSON.stringify(d, null, 2)); });
    });
    
    $('#sync-partners').on('click', checkMesh);
});
</script>
