<?php
/**
 * Template Name: Command Center
 * Description: Central hub for agent dispatch and swarm monitoring
 * @package bitcore-librarian-theme
 * @since 1.0.0
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_title('|', true, 'right'); ?><?php bloginfo('name'); ?> - Command Center</title>
    <?php wp_head(); ?>
    <style>
        :root {
            --bg-primary: #0a0a0f;
            --bg-secondary: #12121a;
            --bg-tertiary: #1a1a25;
            --bg-elevated: #22222e;
            --text-primary: #f0f0f5;
            --text-secondary: #a0a0b0;
            --text-muted: #6a6a7a;
            --accent-primary: #00d4aa;
            --accent-secondary: #6366f1;
            --accent-warning: #f59e0b;
            --accent-danger: #ef4444;
            --border-default: #2a2a3a;
            --border-accent: #3a3a4a;
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 16px;
            --space-xs: 4px;
            --space-sm: 8px;
            --space-md: 16px;
            --space-lg: 24px;
            --space-xl: 32px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
        }
        .command-center {
            max-width: 1600px;
            margin: 0 auto;
            padding: var(--space-xl);
        }
        .cc-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-xl);
            padding: var(--space-lg);
            background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-tertiary) 100%);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-default);
        }
        .cc-title {
            display: flex;
            align-items: center;
            gap: var(--space-md);
        }
        .cc-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        .cc-title h1 { font-size: 1.75rem; font-weight: 600; }
        .cc-title p { color: var(--text-muted); font-size: 0.875rem; }
        .cc-actions { display: flex; gap: var(--space-sm); }
        .btn {
            padding: var(--space-sm) var(--space-md);
            border-radius: var(--radius-md);
            border: 1px solid var(--border-default);
            background: var(--bg-tertiary);
            color: var(--text-primary);
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn:hover { background: var(--bg-elevated); border-color: var(--border-accent); }
        .btn-primary { background: var(--accent-primary); border-color: var(--accent-primary); color: #000; }
        .btn-primary:hover { background: #00eabb; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: var(--space-md);
            margin-bottom: var(--space-xl);
        }
        .stat-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-default);
            border-radius: var(--radius-md);
            padding: var(--space-lg);
            text-align: center;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--accent-primary);
            font-family: 'SF Mono', monospace;
        }
        .stat-value.secondary { color: var(--accent-secondary); }
        .stat-value.warning { color: var(--accent-warning); }
        .stat-label {
            color: var(--text-muted);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: var(--space-xs);
        }
        .main-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: var(--space-xl);
        }
        .card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-default);
            border-radius: var(--radius-lg);
            overflow: hidden;
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--space-md) var(--space-lg);
            border-bottom: 1px solid var(--border-default);
            background: var(--bg-tertiary);
        }
        .card-title { font-size: 1rem; font-weight: 600; }
        .card-body { padding: var(--space-lg); }
        .agent-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: var(--space-md);
        }
        .agent-card {
            background: var(--bg-tertiary);
            border: 1px solid var(--border-default);
            border-radius: var(--radius-md);
            padding: var(--space-md);
            transition: all 0.2s;
            cursor: pointer;
        }
        .agent-card:hover { border-color: var(--accent-primary); transform: translateY(-2px); }
        .agent-card.active { border-color: var(--accent-primary); box-shadow: 0 0 20px rgba(0,212,170,0.2); }
        .agent-header {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            margin-bottom: var(--space-sm);
        }
        .agent-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent-secondary), var(--accent-primary));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }
        .agent-name { font-weight: 600; font-size: 0.9rem; }
        .agent-status {
            display: flex;
            align-items: center;
            gap: var(--space-xs);
            font-size: 0.75rem;
            color: var(--text-muted);
        }
        .status-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--accent-primary);
            animation: pulse 2s infinite;
        }
        .status-dot.idle { background: var(--text-muted); animation: none; }
        .status-dot.busy { background: var(--accent-warning); }
        .status-dot.error { background: var(--accent-danger); }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        .workflow-list { display: flex; flex-direction: column; gap: var(--space-sm); }
        .workflow-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: var(--space-md);
            background: var(--bg-tertiary);
            border-radius: var(--radius-md);
            border: 1px solid var(--border-default);
        }
        .workflow-info { display: flex; flex-direction: column; gap: 2px; }
        .workflow-name { font-weight: 500; font-size: 0.9rem; }
        .workflow-desc { font-size: 0.75rem; color: var(--text-muted); }
        .workflow-actions { display: flex; gap: var(--space-xs); }
        .btn-sm { padding: var(--space-xs) var(--space-sm); font-size: 0.75rem; }
        .log-container {
            background: var(--bg-primary);
            border-radius: var(--radius-md);
            padding: var(--space-md);
            max-height: 300px;
            overflow-y: auto;
            font-family: 'SF Mono', monospace;
            font-size: 0.8rem;
        }
        .log-entry {
            padding: var(--space-xs) 0;
            border-bottom: 1px solid var(--border-default);
            display: flex;
            gap: var(--space-sm);
        }
        .log-time { color: var(--text-muted); min-width: 70px; }
        .log-level { min-width: 50px; font-weight: 600; }
        .log-level.info { color: var(--accent-primary); }
        .log-level.warn { color: var(--accent-warning); }
        .log-level.error { color: var(--accent-danger); }
        .log-msg { color: var(--text-secondary); }
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .main-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: 1fr; }
            .cc-header { flex-direction: column; gap: var(--space-md); }
            .agent-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="command-center">
        <header class="cc-header">
            <div class="cc-title">
                <div class="cc-icon">🎮</div>
                <div>
                    <h1>Command Center</h1>
                    <p>Agent Dispatch & Swarm Monitoring</p>
                </div>
            </div>
            <div class="cc-actions">
                <button class="btn" id="refresh-btn">⟳ Refresh</button>
                <button class="btn btn-primary" id="dispatch-btn">⚡ Dispatch Agent</button>
            </div>
        </header>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value" id="agents-active">--</div>
                <div class="stat-label">Active Agents</div>
            </div>
            <div class="stat-card">
                <div class="stat-value secondary" id="workflows-running">--</div>
                <div class="stat-label">Running Workflows</div>
            </div>
            <div class="stat-card">
                <div class="stat-value warning" id="tasks-queued">--</div>
                <div class="stat-label">Queued Tasks</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="health-score">--</div>
                <div class="stat-label">Health Score</div>
            </div>
        </div>

        <div class="main-grid">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Agent Swarm</h3>
                    <span id="agent-count" style="color: var(--text-muted); font-size: 0.875rem;">Loading...</span>
                </div>
                <div class="card-body">
                    <div class="agent-grid" id="agent-grid">
                        <!-- Agents loaded dynamically -->
                    </div>
                </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: var(--space-xl);">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Quick Workflows</h3>
                    </div>
                    <div class="card-body">
                        <div class="workflow-list" id="workflow-list">
                            <!-- Workflows loaded dynamically -->
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Activity Log</h3>
                        <button class="btn btn-sm" id="clear-log">Clear</button>
                    </div>
                    <div class="card-body" style="padding: var(--space-sm);">
                        <div class="log-container" id="activity-log">
                            <!-- Log entries loaded dynamically -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function() {
        const restUrl = '<?php echo rest_url("bitcore/v1"); ?>';
        let agents = [];
        let workflows = [];

        async function fetchAPI(endpoint) {
            try {
                const response = await fetch(restUrl + endpoint);
                return await response.json();
            } catch (err) {
                console.error('API Error:', err);
                return null;
            }
        }

        async function loadStats() {
            const stats = await fetchAPI('command-center/stats');
            if (stats) {
                document.getElementById('agents-active').textContent = stats.active_agents || 0;
                document.getElementById('workflows-running').textContent = stats.running_workflows || 0;
                document.getElementById('tasks-queued').textContent = stats.queued_tasks || 0;
                document.getElementById('health-score').textContent = (stats.health_score || 100) + '%';
            }
        }

        async function loadAgents() {
            const data = await fetchAPI('command-center/agents');
            if (data && data.agents) {
                agents = data.agents;
                renderAgents();
            }
        }

        function renderAgents() {
            const grid = document.getElementById('agent-grid');
            document.getElementById('agent-count').textContent = agents.length + ' agents';
            
            grid.innerHTML = agents.map(agent => `
                <div class="agent-card ${agent.status === 'active' ? 'active' : ''}" data-id="${agent.id}">
                    <div class="agent-header">
                        <div class="agent-avatar">${agent.icon || '🤖'}</div>
                        <div>
                            <div class="agent-name">${agent.name}</div>
                            <div class="agent-status">
                                <span class="status-dot ${agent.status}"></span>
                                ${agent.status || 'idle'}
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        async function loadWorkflows() {
            const data = await fetchAPI('command-center/workflows');
            if (data && data.workflows) {
                workflows = data.workflows;
                renderWorkflows();
            }
        }

        function renderWorkflows() {
            const list = document.getElementById('workflow-list');
            list.innerHTML = workflows.map(wf => `
                <div class="workflow-item">
                    <div class="workflow-info">
                        <div class="workflow-name">${wf.name}</div>
                        <div class="workflow-desc">${wf.description}</div>
                    </div>
                    <div class="workflow-actions">
                        <button class="btn btn-sm btn-primary" onclick="runWorkflow('${wf.id}')">▶ Run</button>
                    </div>
                </div>
            `).join('');
        }

        function addLog(level, msg) {
            const log = document.getElementById('activity-log');
            const time = new Date().toLocaleTimeString('en-US', { hour12: false });
            log.innerHTML = `<div class="log-entry">
                <span class="log-time">${time}</span>
                <span class="log-level ${level}">${level.toUpperCase()}</span>
                <span class="log-msg">${msg}</span>
            </div>` + log.innerHTML;
        }

        window.runWorkflow = async function(id) {
            addLog('info', 'Dispatching workflow: ' + id);
            const result = await fetchAPI('workflows/' + id + '/run');
            if (result) {
                addLog('info', 'Workflow started: ' + result.run_id);
            } else {
                addLog('error', 'Failed to start workflow');
            }
        };

        document.getElementById('refresh-btn').addEventListener('click', () => {
            loadStats();
            loadAgents();
            loadWorkflows();
            addLog('info', 'Data refreshed');
        });

        document.getElementById('clear-log').addEventListener('click', () => {
            document.getElementById('activity-log').innerHTML = '';
        });

        document.getElementById('dispatch-btn').addEventListener('click', () => {
            const agentType = prompt('Enter agent type to dispatch:');
            if (agentType) {
                addLog('info', 'Dispatching agent: ' + agentType);
                fetchAPI('agents/dispatch?type=' + agentType).then(result => {
                    if (result) addLog('info', 'Agent dispatched: ' + result.agent_id);
                });
            }
        });

        // Initial load
        loadStats();
        loadAgents();
        loadWorkflows();
        addLog('info', 'Command Center initialized');

        // Auto-refresh every 30s
        setInterval(loadStats, 30000);
    })();
    </script>
    <?php wp_footer(); ?>
</body>
</html>
