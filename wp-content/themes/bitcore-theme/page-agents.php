<?php
/**
 * Template Name: Agent Directory
 * Description: ElizaOS agent selection and management interface
 * @package bitcore-librarian-theme
 * @since 1.2.0
 */
if (!is_user_logged_in()) {
    wp_redirect(wp_login_url($_SERVER['REQUEST_URI']));
    exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_title('|', true, 'right'); ?> - Agent Directory</title>
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
            --accent-success: #22c55e;
            --border-default: #2a2a3a;
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
            min-height: 100vh;
            line-height: 1.6;
        }
        .agents-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: var(--space-xl);
        }

        /* Header */
        .agents-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-lg);
            padding: var(--space-lg);
            background: linear-gradient(135deg, var(--bg-secondary), var(--bg-tertiary));
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-default);
        }
        .agents-title {
            display: flex;
            align-items: center;
            gap: var(--space-md);
        }
        .agents-icon {
            width: 48px; height: 48px;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            border-radius: var(--radius-md);
            display: flex; align-items: center; justify-content: center;
            font-size: 24px;
        }
        .agents-title h1 { font-size: 1.75rem; font-weight: 600; }
        .agents-title p { color: var(--text-muted); font-size: 0.875rem; }

        /* Status Bar */
        .status-bar {
            display: flex;
            align-items: center;
            gap: var(--space-md);
            padding: var(--space-md) var(--space-lg);
            margin-bottom: var(--space-lg);
            background: var(--bg-secondary);
            border-radius: var(--radius-md);
            border: 1px solid var(--border-default);
            font-size: 0.875rem;
        }
        .status-dot {
            width: 10px; height: 10px;
            border-radius: 50%;
            display: inline-block;
            flex-shrink: 0;
        }
        .status-dot.healthy { background: var(--accent-success); box-shadow: 0 0 6px var(--accent-success); }
        .status-dot.degraded { background: var(--accent-warning); box-shadow: 0 0 6px var(--accent-warning); }
        .status-dot.offline { background: var(--accent-danger); box-shadow: 0 0 6px var(--accent-danger); }
        .status-label { color: var(--text-secondary); }
        .status-label strong { color: var(--text-primary); }
        .status-details { margin-left: auto; color: var(--text-muted); font-family: 'SF Mono', monospace; font-size: 0.8rem; }

        /* Agent Grid */
        .agent-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: var(--space-lg);
            margin-bottom: var(--space-lg);
        }

        /* Agent Card */
        .agent-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-default);
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: border-color 0.2s, box-shadow 0.2s;
            position: relative;
        }
        .agent-card:hover {
            border-color: var(--accent-primary);
            box-shadow: 0 0 20px rgba(0, 212, 170, 0.08);
        }
        .agent-card.active {
            border-color: var(--accent-primary);
            box-shadow: 0 0 24px rgba(0, 212, 170, 0.12);
        }
        .agent-card.active::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--accent-primary), var(--accent-secondary));
        }

        .card-emoji {
            font-size: 2.5rem;
            padding: var(--space-lg) var(--space-lg) 0;
            line-height: 1;
        }
        .card-body {
            padding: var(--space-md) var(--space-lg) var(--space-lg);
        }
        .card-name {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .card-style {
            font-size: 0.8rem;
            color: var(--accent-secondary);
            font-weight: 500;
            margin-bottom: var(--space-sm);
        }
        .card-desc {
            font-size: 0.85rem;
            color: var(--text-secondary);
            line-height: 1.5;
            margin-bottom: var(--space-md);
        }
        .card-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: var(--space-md);
        }
        .card-badge.active {
            background: rgba(0, 212, 170, 0.15);
            color: var(--accent-primary);
        }
        .card-badge.inactive {
            background: var(--bg-elevated);
            color: var(--text-muted);
        }
        .card-badge .pulse {
            width: 6px; height: 6px;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        .card-badge.active .pulse { background: var(--accent-primary); }
        .card-badge.inactive .pulse { background: var(--text-muted); animation: none; }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        .card-actions {
            display: flex;
            gap: var(--space-sm);
        }
        .btn {
            padding: var(--space-sm) var(--space-md);
            border-radius: 6px;
            border: 1px solid var(--border-default);
            background: var(--bg-tertiary);
            color: var(--text-primary);
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.85rem;
            font-family: inherit;
        }
        .btn:hover { background: var(--bg-elevated); border-color: var(--accent-primary); }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-primary {
            background: var(--accent-primary);
            border-color: var(--accent-primary);
            color: #000;
            font-weight: 500;
        }
        .btn-primary:hover { opacity: 0.9; }
        .btn-primary:disabled { opacity: 0.5; }
        .btn-secondary {
            background: var(--accent-secondary);
            border-color: var(--accent-secondary);
            color: #fff;
        }
        .btn-secondary:hover { opacity: 0.9; }
        .btn-sm { padding: 4px 10px; font-size: 0.75rem; }

        /* Loading state */
        .loading-overlay {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 200px;
            color: var(--text-muted);
        }
        .spinner {
            width: 24px; height: 24px;
            border: 3px solid var(--border-default);
            border-top-color: var(--accent-primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin-right: var(--space-sm);
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Error state */
        .error-state {
            text-align: center;
            padding: var(--space-xl);
            color: var(--accent-danger);
        }
        .error-state p { color: var(--text-secondary); margin-top: var(--space-sm); }

        /* Toast notification */
        .toast {
            position: fixed;
            top: var(--space-lg);
            right: var(--space-lg);
            padding: var(--space-md) var(--space-lg);
            background: var(--bg-elevated);
            border: 1px solid var(--border-default);
            border-radius: var(--radius-md);
            color: var(--text-primary);
            font-size: 0.875rem;
            z-index: 1000;
            opacity: 0;
            transform: translateY(-10px);
            transition: all 0.3s;
            pointer-events: none;
        }
        .toast.show {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }
        .toast.success { border-left: 4px solid var(--accent-success); }
        .toast.error { border-left: 4px solid var(--accent-danger); }
        .toast.warning { border-left: 4px solid var(--accent-warning); }

        /* Mobile responsive */
        @media (max-width: 900px) {
            .agent-grid {
                grid-template-columns: 1fr;
            }
            .agents-header {
                flex-direction: column;
                gap: var(--space-md);
                text-align: center;
            }
        }
    </style>
</head>
<body <?php body_class(); ?>>
<?php include(get_template_directory() . "/nav-header.php"); ?>
    <div class="agents-container">
        <header class="agents-header">
            <div class="agents-title">
                <div class="agents-icon">🤖</div>
                <div>
                    <h1>Agent Directory</h1>
                    <p>Select and activate an AI agent</p>
                </div>
            </div>
            <div style="display: flex; gap: var(--space-sm);">
                <button class="btn" id="refresh-btn">⟳ Refresh</button>
            </div>
        </header>

        <!-- Status Bar -->
        <div class="status-bar" id="status-bar">
            <span class="status-dot" id="status-dot"></span>
            <span class="status-label" id="status-label"><strong>Checking server...</strong></span>
            <span class="status-details" id="status-details"></span>
        </div>

        <!-- Agent Grid -->
        <div class="agent-grid" id="agent-grid">
            <div class="loading-overlay">
                <div class="spinner"></div>
                Loading agents...
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div class="toast" id="toast"></div>

    <?php wp_footer(); ?>

    <script>
    (function() {
        'use strict';

        // Agent definitions
        const AGENTS = [
            {
                id: 'eac677dd-94c4-02ab-bfea-bc554e52b2a3',
                name: 'Aria',
                emoji: '🔬',
                style: 'Analytical & Precise',
                description: 'Evidence-first research agent. Cites sources, structures insights, delivers clarity.',
                slug: 'aria'
            },
            {
                id: '3ab11b75-ca36-0cee-a3d7-7bfcab3c27cd',
                name: 'Forge',
                emoji: '🔨',
                style: 'Practical & Action-Oriented',
                description: 'Gets things done. Creates tasks, tracks goals, turns ideas into completed work.',
                slug: 'forge'
            },
            {
                id: 'cf0dee2b-9fc6-041b-83a0-90a644d26362',
                name: 'Muse',
                emoji: '✨',
                style: 'Creative & Exploratory',
                description: 'Ideation and strategy. Brainstorms possibilities, connects unexpected dots.',
                slug: 'muse'
            }
        ];

        const API_BASE = '<?php echo esc_url(rest_url("bitcore/v1/eliza")); ?>';
        const NONCE = '<?php echo wp_create_nonce("wp_rest"); ?>';

        let activeAgentId = null;
        let isLoading = false;

        // ---- API Helpers ----
        async function apiGet(path) {
            const res = await fetch(API_BASE + path, {
                headers: { 'X-WP-Nonce': NONCE }
            });
            if (!res.ok) throw new Error('API error: ' + res.status);
            return res.json();
        }

        async function apiPost(path, body) {
            const res = await fetch(API_BASE + path, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': NONCE
                },
                body: JSON.stringify(body || {})
            });
            if (!res.ok) {
                const err = await res.json().catch(() => ({}));
                throw new Error(err.message || 'API error: ' + res.status);
            }
            return res.json();
        }

        // ---- Toast ----
        function showToast(message, type) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = 'toast ' + type + ' show';
            setTimeout(function() { toast.className = 'toast'; }, 3000);
        }

        // ---- Health Check ----
        async function checkHealth() {
            const dot = document.getElementById('status-dot');
            const label = document.getElementById('status-label');
            const details = document.getElementById('status-details');
            try {
                const data = await apiGet('/health');
                const healthStatus = String(data.status || '').toLowerCase();
                if (data && (healthStatus === 'healthy' || healthStatus === 'ok')) {
                    dot.className = 'status-dot healthy';
                    label.innerHTML = '<strong>Server Healthy</strong>';
                    details.textContent = data.timestamp || '';
                } else {
                    dot.className = 'status-dot degraded';
                    label.innerHTML = '<strong>Server Degraded</strong>';
                    details.textContent = data.message || 'ElizaOS unreachable';
                }
            } catch (e) {
                dot.className = 'status-dot offline';
                label.innerHTML = '<strong>Server Offline</strong>';
                details.textContent = 'Cannot connect to ElizaOS';
            }
        }

        // ---- Load Agents ----
        async function loadAgents() {
            if (isLoading) return;
            isLoading = true;

            try {
                const data = await apiGet('/agents');
                // data may be array or object with agents
                const agents = Array.isArray(data) ? data : (data.agents || []);

                // Determine active agent from user meta or agent status
                let activeId = null;
                agents.forEach(function(a) {
                    if (a.status === 'active' || a.status === 'running') {
                        activeId = a.id;
                    }
                });
                activeAgentId = activeId;
                renderAgents(agents);
            } catch (e) {
                // Fallback: render static agents without server status
                activeAgentId = null;
                renderAgents(AGENTS.map(function(a) { return Object.assign({}, a, { status: 'unknown' }); }));
                showToast('Could not load agent status', 'warning');
            }

            isLoading = false;
        }

        // ---- Render Agents ----
        function renderAgents(serverAgents) {
            const grid = document.getElementById('agent-grid');
            grid.innerHTML = '';

            AGENTS.forEach(function(agent) {
                // Merge server data if available
                const server = serverAgents.find(function(a) { return a.id === agent.id; }) || {};
                const isActive = activeAgentId === agent.id;
                const hasActiveAgent = activeAgentId !== null;
                const primaryActionLabel = hasActiveAgent ? 'Switch Agent' : 'Start Agent';
                const status = server ? (server.status || 'inactive') : 'inactive';

                const card = document.createElement('div');
                card.className = 'agent-card' + (isActive ? ' active' : '');
                card.setAttribute('data-agent-id', agent.id);

                card.innerHTML =
                    '<div class="card-emoji">' + agent.emoji + '</div>' +
                    '<div class="card-body">' +
                        '<div class="card-name">' + agent.name + '</div>' +
                        '<div class="card-style">' + agent.style + '</div>' +
                        '<div class="card-desc">' + agent.description + '</div>' +
                        '<div class="card-badge ' + (isActive ? 'active' : 'inactive') + '">' +
                            '<span class="pulse"></span>' +
                            (isActive ? 'Active' : 'Inactive') +
                        '</div>' +
                        '<div class="card-actions">' +
                            (isActive
                                ? '<button class="btn btn-sm" disabled>✓ Running</button>' +
                                  '<a href="/chat/?agent=' + agent.slug + '" class="btn btn-secondary btn-sm">💬 Open Chat</a>' +
                                  '<button class="btn btn-sm" onclick="deactivateAgent(\'' + agent.id + '\')">Stop</button>'
                                : '<button class="btn btn-primary btn-sm" onclick="activateAgent(\'' + agent.id + '\')">⚡ ' + primaryActionLabel + '</button>' +
                                  '<a href="/chat/?agent=' + agent.slug + '" class="btn btn-sm">💬 Open Chat</a>'
                            ) +
                        '</div>' +
                    '</div>';

                grid.appendChild(card);
            });
        }

        // ---- Activate Agent ----
        async function activateAgent(agentId) {
            const agent = AGENTS.find(function(a) { return a.id === agentId; });
            try {
                // Server enforces single-active-agent policy
                await apiPost('/agents/' + agentId + '/activate');
                activeAgentId = agentId;
                renderAgents(await getAgentList());
                showToast(agent.name + ' activated', 'success');
            } catch (e) {
                showToast('Failed to activate ' + agent.name + ': ' + e.message, 'error');
            }
        }

        // ---- Deactivate Agent ----
        async function deactivateAgent(agentId) {
            const agent = AGENTS.find(function(a) { return a.id === agentId; });
            try {
                await apiPost('/agents/' + agentId + '/deactivate');
                activeAgentId = null;
                renderAgents(await getAgentList());
                showToast(agent.name + ' stopped', 'success');
            } catch (e) {
                showToast('Failed to stop ' + agent.name + ': ' + e.message, 'error');
            }
        }

        // ---- Helper: Get Agent List ----
        async function getAgentList() {
            try {
                const data = await apiGet('/agents');
                return Array.isArray(data) ? data : (data.agents || []);
            } catch (e) {
                return AGENTS.map(function(a) { return Object.assign({}, a, { status: 'unknown' }); });
            }
        }

        // ---- Expose to window ----
        window.activateAgent = activateAgent;
        window.deactivateAgent = deactivateAgent;

        // ---- Init ----
        document.getElementById('refresh-btn').addEventListener('click', function() {
            checkHealth();
            loadAgents();
        });

        checkHealth();
        loadAgents();
    })();
    </script>
</body>
</html>
