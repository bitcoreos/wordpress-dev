<?php
/**
 * Template Name: MCP Tools Dashboard
 * Description: MCP (Model Context Protocol) tool browser and executor interface
 * 
 * @package BITCORE_Librarian
 * @version 2.0.0
 * @since      2026.11.23
 * @author     coraline
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<div class="bitcore-dashboard" id="mcp-tools-dashboard">
    <!-- Dashboard Header -->
    <header class="dashboard-header">
        <div class="dashboard-title">
            <div class="dashboard-icon">🔧</div>
            <div>
                <h1>MCP Tools</h1>
                <p style="color: var(--text-muted); margin: 0; font-size: 0.875rem;">Model Context Protocol Tool Browser</p>
            </div>
        </div>
        <div class="dashboard-actions">
            <button class="btn btn-secondary" id="refresh-tools">
                <span>↻</span> Refresh
            </button>
        </div>
    </header>

    <!-- Stats Row -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Available Tools</div>
            <div class="stat-value accent" id="tools-count">--</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Executions Today</div>
            <div class="stat-value secondary" id="executions-count">--</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Success Rate</div>
            <div class="stat-value" id="success-rate">--</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Avg Response</div>
            <div class="stat-value warning" id="avg-response">--</div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div style="display: grid; grid-template-columns: 350px 1fr; gap: var(--space-xl);">
        <!-- Tools List Panel -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Tool Library</h3>
                <input type="search" class="form-input" id="tool-search" placeholder="Search tools..." style="width: 150px; padding: 6px 12px;">
            </div>
            <div class="card-body" style="max-height: 600px; overflow-y: auto; padding: var(--space-md);">
                <div id="tools-list">
                    <!-- Tools populated via JS -->
                    <div class="empty-state">
                        <div class="spinner" style="margin: 0 auto;"></div>
                        <p style="margin-top: var(--space-md);">Loading tools...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tool Details Panel -->
        <div class="card" id="tool-details-panel">
            <div class="card-header">
                <h3 class="card-title" id="selected-tool-name">Select a Tool</h3>
                <span class="badge badge-info" id="selected-tool-category">--</span>
            </div>
            <div class="card-body">
                <div id="tool-details-content">
                    <div class="empty-state">
                        <div class="empty-state-icon">🔧</div>
                        <p>Select a tool from the library to view details and execute</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Execution History -->
    <div class="card" style="margin-top: var(--space-xl);">
        <div class="card-header">
            <h3 class="card-title">Recent Executions</h3>
            <button class="btn btn-sm btn-secondary" id="clear-history">Clear</button>
        </div>
        <div class="card-body" style="padding: 0;">
            <table class="data-table" id="execution-history">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Tool</th>
                        <th>Status</th>
                        <th>Duration</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="history-body">
                    <tr>
                        <td colspan="5" style="text-align: center; color: var(--text-muted);">No executions yet</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function() {
    const restUrl = '<?php echo rest_url("bitcore/v1"); ?>';
    let tools = [];
    let selectedTool = null;
    let executionHistory = [];

    // Fetch tools on load
    async function loadTools() {
        try {
            const response = await fetch(restUrl + '/mcp/tools');
            const data = await response.json();
            tools = data.tools || data || [];
            renderToolsList();
            updateStats();
        } catch (error) {
            console.error('Failed to load tools:', error);
            document.getElementById('tools-list').innerHTML = 
                '<div class="empty-state"><p style="color: var(--accent-danger);">Failed to load tools</p></div>';
        }
    }

    // Render tools list
    function renderToolsList(filter = '') {
        const filtered = tools.filter(t => 
            (t.name || t).toLowerCase().includes(filter.toLowerCase())
        );
        
        if (filtered.length === 0) {
            document.getElementById('tools-list').innerHTML = 
                '<div class="empty-state"><p>No tools found</p></div>';
            return;
        }

        const html = filtered.map(tool => {
            const name = tool.name || tool;
            const desc = tool.description || 'No description available';
            const category = tool.category || 'general';
            return `
                <div class="tool-card" data-tool-name="${name}" onclick="selectTool('${name}')">
                    <div class="tool-name">${escapeHtml(name)}</div>
                    <div class="tool-description">${escapeHtml(desc.substring(0, 80))}${desc.length > 80 ? '...' : ''}</div>
                    <span class="badge badge-info" style="margin-top: 8px;">${escapeHtml(category)}</span>
                </div>
            `;
        }).join('');

        document.getElementById('tools-list').innerHTML = html;
    }

    // Select a tool
    window.selectTool = async function(toolName) {
        selectedTool = tools.find(t => (t.name || t) === toolName);
        if (!selectedTool) return;

        // Highlight selected
        document.querySelectorAll('.tool-card').forEach(el => el.classList.remove('selected'));
        document.querySelector(`[data-tool-name="${toolName}"]`)?.classList.add('selected');

        // Update header
        document.getElementById('selected-tool-name').textContent = selectedTool.name || selectedTool;
        document.getElementById('selected-tool-category').textContent = selectedTool.category || 'general';

        // Build form
        const parameters = selectedTool.parameters || selectedTool.inputSchema?.properties || {};
        let formHtml = `
            <p style="color: var(--text-secondary); margin-bottom: var(--space-lg);">
                ${escapeHtml(selectedTool.description || 'Execute this MCP tool')}
            </p>
            <form id="tool-execution-form">
        `;

        if (Object.keys(parameters).length > 0) {
            formHtml += '<div class="form-group"><label class="form-label">Parameters</label>';
            for (const [key, param] of Object.entries(parameters)) {
                const type = param.type || 'string';
                const required = selectedTool.required?.includes(key);
                formHtml += `
                    <div style="margin-bottom: var(--space-md);">
                        <label style="font-size: 0.8rem; color: var(--text-muted);">
                            ${escapeHtml(key)}
                            ${required ? '<span style="color: var(--accent-danger);">*</span>' : ''}
                        </label>
                        ${type === 'boolean' ? 
                            `<select class="form-input" name="${key}"><option value="true">true</option><option value="false">false</option></select>` :
                          type === 'number' || type === 'integer' ?
                            `<input type="number" class="form-input" name="${key}" placeholder="${param.description || ''}">` :
                          type === 'object' || type === 'array' ?
                            `<textarea class="form-input" name="${key}" rows="3" placeholder="${param.description || 'JSON'}"></textarea>` :
                            `<input type="text" class="form-input" name="${key}" placeholder="${param.description || ''}">`
                        }
                    </div>
                `;
            }
            formHtml += '</div>';
        }

        formHtml += `
            <div style="display: flex; gap: var(--space-md);">
                <button type="submit" class="btn btn-primary">▶ Execute</button>
                <button type="button" class="btn btn-secondary" onclick="resetForm()">Reset</button>
            </div>
            </form>
            <div id="execution-result" style="margin-top: var(--space-lg);"></div>
        `;

        document.getElementById('tool-details-content').innerHTML = formHtml;

        // Bind form submission
        document.getElementById('tool-execution-form').addEventListener('submit', executeTool);
    };

    // Execute tool
    async function executeTool(e) {
        e.preventDefault();
        if (!selectedTool) return;

        const formData = new FormData(e.target);
        const params = {};
        formData.forEach((value, key) => {
            // Try to parse JSON for objects/arrays
            try {
                params[key] = JSON.parse(value);
            } catch {
                params[key] = value;
            }
        });

        const resultDiv = document.getElementById('execution-result');
        resultDiv.innerHTML = '<div class="spinner"></div><p>Executing...</p>';

        const startTime = Date.now();
        try {
            const response = await fetch(restUrl + '/mcp/call', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    tool: selectedTool.name || selectedTool,
                    arguments: params
                })
            });
            const result = await response.json();
            const duration = Date.now() - startTime;

            // Add to history
            addToHistory({
                timestamp: new Date().toISOString(),
                tool: selectedTool.name || selectedTool,
                status: response.ok ? 'success' : 'error',
                duration: duration,
                result: result
            });

            resultDiv.innerHTML = `
                <div class="badge ${response.ok ? 'badge-success' : 'badge-danger'}" style="margin-bottom: var(--space-md);">
                    ${response.ok ? '✓ Success' : '✗ Error'} • ${duration}ms
                </div>
                <div class="code-block">
                    <pre>${escapeHtml(JSON.stringify(result, null, 2))}</pre>
                </div>
            `;
        } catch (error) {
            resultDiv.innerHTML = `
                <div class="badge badge-danger" style="margin-bottom: var(--space-md);">✗ Failed</div>
                <p style="color: var(--accent-danger);">${escapeHtml(error.message)}</p>
            `;
        }
    }

    // History management
    function addToHistory(entry) {
        executionHistory.unshift(entry);
        if (executionHistory.length > 50) executionHistory.pop();
        renderHistory();
    }

    function renderHistory() {
        const tbody = document.getElementById('history-body');
        if (executionHistory.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: var(--text-muted);">No executions yet</td></tr>';
            return;
        }

        tbody.innerHTML = executionHistory.slice(0, 10).map(entry => `
            <tr>
                <td>${new Date(entry.timestamp).toLocaleTimeString()}</td>
                <td><code>${escapeHtml(entry.tool)}</code></td>
                <td><span class="badge ${entry.status === 'success' ? 'badge-success' : 'badge-danger'}">${entry.status}</span></td>
                <td>${entry.duration}ms</td>
                <td><button class="btn btn-sm btn-secondary" onclick="showResult('${entry.timestamp}')">View</button></td>
            </tr>
        `).join('');
    }

    // Update stats
    function updateStats() {
        document.getElementById('tools-count').textContent = tools.length;
        document.getElementById('executions-count').textContent = executionHistory.length;
        const successes = executionHistory.filter(e => e.status === 'success').length;
        const rate = executionHistory.length > 0 ? Math.round((successes / executionHistory.length) * 100) : 0;
        document.getElementById('success-rate').textContent = rate + '%';
        const avgDuration = executionHistory.length > 0 ? 
            Math.round(executionHistory.reduce((a, b) => a + b.duration, 0) / executionHistory.length) : 0;
        document.getElementById('avg-response').textContent = avgDuration + 'ms';
    }

    // Helpers
    function escapeHtml(str) {
        if (typeof str !== 'string') return str;
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    window.resetForm = function() {
        if (selectedTool) selectTool(selectedTool.name || selectedTool);
    };

    window.showResult = function(timestamp) {
        const entry = executionHistory.find(e => e.timestamp === timestamp);
        if (entry) {
            alert(JSON.stringify(entry.result, null, 2));
        }
    };

    // Event listeners
    document.getElementById('tool-search').addEventListener('input', (e) => {
        renderToolsList(e.target.value);
    });

    document.getElementById('refresh-tools').addEventListener('click', loadTools);

    document.getElementById('clear-history').addEventListener('click', () => {
        executionHistory = [];
        renderHistory();
        updateStats();
    });

    // Initialize
    loadTools();
})();
</script>

<style>
/* MCP Tools specific overrides */
#mcp-tools-dashboard .tool-card {
    margin-bottom: var(--space-sm);
}

#mcp-tools-dashboard .tool-card.selected {
    border-color: var(--accent-primary);
    background: rgba(74,222,128,0.08);
}

#mcp-tools-dashboard .code-block pre {
    margin: 0;
    white-space: pre-wrap;
    word-break: break-word;
}
</style>

<?php get_footer(); ?>
