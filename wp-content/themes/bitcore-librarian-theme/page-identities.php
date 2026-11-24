<?php
/**
 * Template Name: Agent Identities
 * Description: Agent identity management and status dashboard
 * @package bitcore-librarian-theme
 * @since 1.0.0
 * @version    1.0.0
 * @author     coraline
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_title('|', true, 'right'); ?> - Agent Identities</title>
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
            --radius-md: 10px;
            --radius-lg: 16px;
            --space-sm: 8px;
            --space-md: 16px;
            --space-lg: 24px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
        }
        .id-container { max-width: 1400px; margin: 0 auto; padding: var(--space-lg); }
        .id-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-lg);
            padding: var(--space-lg);
            background: linear-gradient(135deg, var(--bg-secondary), var(--bg-tertiary));
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-default);
        }
        .id-title { display: flex; align-items: center; gap: var(--space-md); }
        .id-icon {
            width: 48px; height: 48px;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            border-radius: var(--radius-md);
            display: flex; align-items: center; justify-content: center;
            font-size: 24px;
        }
        .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: var(--space-md); margin-bottom: var(--space-lg); }
        .stat-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-default);
            border-radius: var(--radius-md);
            padding: var(--space-md);
            text-align: center;
        }
        .stat-value { font-size: 1.75rem; font-weight: 700; color: var(--accent-primary); font-family: 'SF Mono', monospace; }
        .stat-label { color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; margin-top: 4px; }
        .identity-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: var(--space-lg); }
        .identity-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-default);
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: all 0.2s;
        }
        .identity-card:hover { border-color: var(--accent-primary); transform: translateY(-2px); }
        .identity-header {
            padding: var(--space-lg);
            background: var(--bg-tertiary);
            display: flex;
            align-items: center;
            gap: var(--space-md);
            border-bottom: 1px solid var(--border-default);
        }
        .identity-avatar {
            width: 56px; height: 56px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 28px;
            background: linear-gradient(135deg, var(--accent-secondary), var(--accent-primary));
        }
        .identity-info { flex: 1; }
        .identity-name { font-size: 1.1rem; font-weight: 600; margin-bottom: 4px; }
        .identity-node { font-size: 0.75rem; color: var(--text-muted); font-family: 'SF Mono', monospace; }
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-badge.online { background: var(--accent-primary); color: #000; }
        .status-badge.offline { background: var(--bg-elevated); color: var(--text-muted); }
        .status-badge.busy { background: var(--accent-warning); color: #000; }
        .status-badge.error { background: var(--accent-danger); color: #fff; }
        .identity-body { padding: var(--space-md); }
        .capability-list { display: flex; flex-wrap: wrap; gap: var(--space-xs); margin-bottom: var(--space-md); }
        .capability-badge {
            padding: 4px 10px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border-default);
            border-radius: 12px;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        .identity-meta {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: var(--space-sm);
            font-size: 0.8rem;
        }
        .meta-item { display: flex; flex-direction: column; gap: 2px; }
        .meta-label { color: var(--text-muted); font-size: 0.7rem; }
        .meta-value { color: var(--text-primary); font-family: 'SF Mono', monospace; }
        .identity-actions {
            padding: var(--space-md);
            border-top: 1px solid var(--border-default);
            display: flex; gap: var(--space-sm);
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
        }
        .btn:hover { background: var(--bg-elevated); border-color: var(--accent-primary); }
        .btn-primary { background: var(--accent-primary); border-color: var(--accent-primary); color: #000; }
        .space-xs { --space-xs: 4px; }
        @media (max-width: 768px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); }
            .identity-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="id-container">
        <header class="id-header">
            <div class="id-title">
                <div class="id-icon">🤖</div>
                <div>
                    <h1>Agent Identities</h1>
                    <p style="color: var(--text-muted);">Identity & Capability Management</p>
                </div>
            </div>
            <div style="display: flex; gap: var(--space-sm);">
                <button class="btn" id="refresh-btn">⟳ Refresh</button>
                <button class="btn btn-primary" id="register-btn">+ Register Agent</button>
            </div>
        </header>

        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-value" id="total-agents">--</div>
                <div class="stat-label">Total Agents</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="online-count">--</div>
                <div class="stat-label">Online</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="busy-count">--</div>
                <div class="stat-label">Busy</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="offline-count">--</div>
                <div class="stat-label">Offline</div>
            </div>
        </div>

        <div class="identity-grid" id="identity-grid">
            <div style="text-align: center; padding: 40px; color: var(--text-muted); grid-column: 1/-1;">Loading identities...</div>
        </div>
    </div>

    <script>
    (function() {
        const restUrl = '<?php echo rest_url("bitcore/v1"); ?>';
        let identities = [];

        async function fetchAPI(endpoint) {
            try {
                const res = await fetch(restUrl + endpoint);
                return await res.json();
            } catch (e) { console.error(e); return null; }
        }

        async function loadIdentities() {
            const data = await fetchAPI('identities');
            identities = data && data.identities ? data.identities : [];
            updateStats();
            renderIdentities();
        }

        function updateStats() {
            const total = identities.length;
            const online = identities.filter(i => i.status === 'online').length;
            const busy = identities.filter(i => i.status === 'busy').length;
            const offline = identities.filter(i => i.status === 'offline' || !i.status).length;
            
            document.getElementById('total-agents').textContent = total;
            document.getElementById('online-count').textContent = online;
            document.getElementById('busy-count').textContent = busy;
            document.getElementById('offline-count').textContent = offline;
        }

        function renderIdentities() {
            const grid = document.getElementById('identity-grid');
            if (identities.length === 0) {
                grid.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--text-muted); grid-column: 1/-1;">No identities registered</div>';
                return;
            }
            grid.innerHTML = identities.map(id => `
                <div class="identity-card" data-id="${id.id}">
                    <div class="identity-header">
                        <div class="identity-avatar">${id.icon || '🤖'}</div>
                        <div class="identity-info">
                            <div class="identity-name">${id.name}</div>
                            <div class="identity-node">${id.node_id || id.id}</div>
                        </div>
                        <span class="status-badge ${id.status || 'offline'}">${id.status || 'offline'}</span>
                    </div>
                    <div class="identity-body">
                        <div class="capability-list">
                            ${(id.capabilities || []).map(c => `<span class="capability-badge">${c}</span>`).join('')}
                            ${(id.capabilities || []).length === 0 ? '<span class="capability-badge" style="color: var(--text-muted);">No capabilities</span>' : ''}
                        </div>
                        <div class="identity-meta">
                            <div class="meta-item">
                                <span class="meta-label">Created</span>
                                <span class="meta-value">${formatDate(id.created_at)}</span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Last Active</span>
                                <span class="meta-value">${formatDate(id.last_active)}</span>
                            </div>
                        </div>
                    </div>
                    <div class="identity-actions">
                        <button class="btn" onclick="viewIdentity('${id.id}')">View</button>
                        <button class="btn" onclick="pingIdentity('${id.id}')">Ping</button>
                    </div>
                </div>
            `).join('');
        }

        function formatDate(dateStr) {
            if (!dateStr) return 'N/A';
            const d = new Date(dateStr);
            return d.toLocaleDateString();
        }

        window.viewIdentity = function(id) {
            const identity = identities.find(i => i.id === id);
            if (identity) {
                alert('Identity: ' + identity.name + '\nNode: ' + identity.node_id + '\nCapabilities: ' + (identity.capabilities || []).join(', '));
            }
        };

        window.pingIdentity = async function(id) {
            const result = await fetchAPI('identities/' + id + '/ping');
            if (result && result.ok) {
                alert('Ping successful: ' + result.latency + 'ms');
            } else {
                alert('Ping failed');
            }
        };

        document.getElementById('refresh-btn').addEventListener('click', loadIdentities);
        document.getElementById('register-btn').addEventListener('click', async () => {
            const name = prompt('Enter agent name:');
            if (!name) return;
            const nodeId = prompt('Enter node ID:', name.toLowerCase().replace(/\s+/g, '-'));
            
            await fetch(restUrl + 'identities', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name, node_id: nodeId, capabilities: [] })
            });
            loadIdentities();
        });

        loadIdentities();
    })();
    </script>
    <?php wp_footer(); ?>
</body>
</html>
