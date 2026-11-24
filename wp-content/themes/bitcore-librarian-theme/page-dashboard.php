<?php
/**
 * Template Name: System Dashboard
 * Description: System health monitoring and status overview
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
    <title><?php wp_title('|', true, 'right'); ?> - System Dashboard</title>
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
        .dash-container { max-width: 1400px; margin: 0 auto; padding: var(--space-lg); }
        .dash-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-lg);
            padding: var(--space-lg);
            background: linear-gradient(135deg, var(--bg-secondary), var(--bg-tertiary));
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-default);
        }
        .dash-title { display: flex; align-items: center; gap: var(--space-md); }
        .dash-icon {
            width: 48px; height: 48px;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            border-radius: var(--radius-md);
            display: flex; align-items: center; justify-content: center;
            font-size: 24px;
        }
        .system-badge {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .system-badge.healthy { background: var(--accent-success); color: #000; }
        .system-badge.degraded { background: var(--accent-warning); color: #000; }
        .system-badge.critical { background: var(--accent-danger); color: #fff; }
        .health-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--space-md); margin-bottom: var(--space-lg); }
        .health-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-default);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
        }
        .health-card.ok { border-left: 4px solid var(--accent-success); }
        .health-card.warn { border-left: 4px solid var(--accent-warning); }
        .health-card.error { border-left: 4px solid var(--accent-danger); }
        .health-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-md); }
        .health-name { font-weight: 600; }
        .health-status { font-size: 0.75rem; padding: 4px 10px; border-radius: 12px; }
        .health-status.ok { background: rgba(34, 197, 94, 0.2); color: var(--accent-success); }
        .health-status.warn { background: rgba(245, 158, 11, 0.2); color: var(--accent-warning); }
        .health-status.error { background: rgba(239, 68, 68, 0.2); color: var(--accent-danger); }
        .health-metrics { display: grid; grid-template-columns: repeat(2, 1fr); gap: var(--space-sm); }
        .metric { text-align: center; padding: var(--space-sm); background: var(--bg-tertiary); border-radius: 6px; }
        .metric-value { font-size: 1.25rem; font-weight: 700; font-family: 'SF Mono', monospace; }
        .metric-label { font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; }
        .main-grid { display: grid; grid-template-columns: 2fr 1fr; gap: var(--space-lg); }
        .card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-default);
            border-radius: var(--radius-lg);
            overflow: hidden;
        }
        .card-header {
            padding: var(--space-md) var(--space-lg);
            background: var(--bg-tertiary);
            border-bottom: 1px solid var(--border-default);
            display: flex; justify-content: space-between; align-items: center;
        }
        .card-title { font-weight: 600; }
        .card-body { padding: var(--space-md); }
        .queue-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: var(--space-sm) var(--space-md);
            background: var(--bg-tertiary);
            border-radius: 6px;
            margin-bottom: var(--space-sm);
        }
        .queue-name { font-size: 0.9rem; }
        .queue-count { font-family: 'SF Mono', monospace; font-size: 0.85rem; color: var(--accent-primary); }
        .quick-actions { display: grid; grid-template-columns: repeat(2, 1fr); gap: var(--space-sm); }
        .action-btn {
            padding: var(--space-md);
            background: var(--bg-tertiary);
            border: 1px solid var(--border-default);
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
        }
        .action-btn:hover { border-color: var(--accent-primary); transform: translateY(-2px); }
        .action-icon { font-size: 1.5rem; margin-bottom: var(--space-xs); }
        .action-label { font-size: 0.85rem; color: var(--text-secondary); }
        .log-container {
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
        .status-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: var(--space-xs);
        }
        .status-dot.ok { background: var(--accent-success); }
        .status-dot.warn { background: var(--accent-warning); }
        .status-dot.error { background: var(--accent-danger); }
        @media (max-width: 1024px) {
            .health-grid { grid-template-columns: repeat(2, 1fr); }
            .main-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .health-grid { grid-template-columns: 1fr; }
            .quick-actions { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="dash-container">
        <header class="dash-header">
            <div class="dash-title">
                <div class="dash-icon">📊</div>
                <div>
                    <h1>System Dashboard</h1>
                    <p style="color: var(--text-muted);">Health & Status Overview</p>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: var(--space-md);">
                <span class="system-badge healthy" id="system-badge">HEALTHY</span>
                <button class="btn" id="refresh-btn">⟳ Refresh</button>
            </div>
        </header>

        <div class="health-grid">
            <div class="health-card ok" id="db-health">
                <div class="health-header">
                    <span class="health-name">🗄️ Database</span>
                    <span class="health-status ok">OK</span>
                </div>
                <div class="health-metrics">
                    <div class="metric">
                        <div class="metric-value" id="db-connections">--</div>
                        <div class="metric-label">Connections</div>
                    </div>
                    <div class="metric">
                        <div class="metric-value" id="db-latency">--</div>
                        <div class="metric-label">Latency</div>
                    </div>
                </div>
            </div>

            <div class="health-card ok" id="cache-health">
                <div class="health-header">
                    <span class="health-name">💾 Cache</span>
                    <span class="health-status ok">OK</span>
                </div>
                <div class="health-metrics">
                    <div class="metric">
                        <div class="metric-value" id="cache-hit">--</div>
                        <div class="metric-label">Hit Rate</div>
                    </div>
                    <div class="metric">
                        <div class="metric-value" id="cache-keys">--</div>
                        <div class="metric-label">Keys</div>
                    </div>
                </div>
            </div>

            <div class="health-card ok" id="queue-health">
                <div class="health-header">
                    <span class="health-name">📨 Queue</span>
                    <span class="health-status ok">OK</span>
                </div>
                <div class="health-metrics">
                    <div class="metric">
                        <div class="metric-value" id="queue-pending">--</div>
                        <div class="metric-label">Pending</div>
                    </div>
                    <div class="metric">
                        <div class="metric-value" id="queue-workers">--</div>
                        <div class="metric-label">Workers</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="main-grid">
            <div>
                <div class="card">
                    <div class="card-header">
                        <span class="card-title">Queue Monitor</span>
                    </div>
                    <div class="card-body" id="queue-list">
                        <div class="queue-item">
                            <span class="queue-name">Loading...</span>
                        </div>
                    </div>
                </div>

                <div class="card" style="margin-top: var(--space-lg);">
                    <div class="card-header">
                        <span class="card-title">System Log</span>
                        <button class="btn" id="clear-log">Clear</button>
                    </div>
                    <div class="card-body">
                        <div class="log-container" id="system-log">
                            <div class="log-entry">
                                <span class="log-time">--:--:--</span>
                                <span class="log-level info">INFO</span>
                                <span>Initializing...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <div class="card">
                    <div class="card-header">
                        <span class="card-title">Quick Actions</span>
                    </div>
                    <div class="card-body">
                        <div class="quick-actions">
                            <div class="action-btn" onclick="runAction('flush-cache')">
                                <div class="action-icon">🗑️</div>
                                <div class="action-label">Flush Cache</div>
                            </div>
                            <div class="action-btn" onclick="runAction('restart-workers')">
                                <div class="action-icon">🔄</div>
                                <div class="action-label">Restart Workers</div>
                            </div>
                            <div class="action-btn" onclick="runAction('clear-queue')">
                                <div class="action-icon">🧹</div>
                                <div class="action-label">Clear Queue</div>
                            </div>
                            <div class="action-btn" onclick="runAction('export-logs')">
                                <div class="action-icon">📥</div>
                                <div class="action-label">Export Logs</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card" style="margin-top: var(--space-lg);">
                    <div class="card-header">
                        <span class="card-title">System Stats</span>
                    </div>
                    <div class="card-body">
                        <div style="display: flex; flex-direction: column; gap: var(--space-sm);">
                            <div style="display: flex; justify-content: space-between;">
                                <span style="color: var(--text-muted);">Version</span>
                                <span id="sys-version">1.0.0</span>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span style="color: var(--text-muted);">Uptime</span>
                                <span id="sys-uptime">--</span>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span style="color: var(--text-muted);">Memory</span>
                                <span id="sys-memory">--</span>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span style="color: var(--text-muted);">PHP</span>
                                <span id="sys-php"><?php echo phpversion(); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function() {
        const restUrl = '<?php echo rest_url("bitcore/v1"); ?>';

        async function fetchAPI(endpoint) {
            try {
                const res = await fetch(restUrl + endpoint);
                return await res.json();
            } catch (e) { console.error(e); return null; }
        }

        async function loadHealth() {
            const health = await fetchAPI('health');
            if (!health) return;

            // Database
            if (health.database) {
                document.getElementById('db-connections').textContent = health.database.connections || 0;
                document.getElementById('db-latency').textContent = (health.database.latency || 0) + 'ms';
                updateHealthCard('db-health', health.database.status);
            }

            // Cache
            if (health.cache) {
                document.getElementById('cache-hit').textContent = (health.cache.hit_rate || 0) + '%';
                document.getElementById('cache-keys').textContent = health.cache.keys || 0;
                updateHealthCard('cache-health', health.cache.status);
            }

            // Queue
            if (health.queue) {
                document.getElementById('queue-pending').textContent = health.queue.pending || 0;
                document.getElementById('queue-workers').textContent = health.queue.workers || 0;
                updateHealthCard('queue-health', health.queue.status);
            }

            // System badge
            const badge = document.getElementById('system-badge');
            if (health.overall === 'healthy') {
                badge.className = 'system-badge healthy';
                badge.textContent = 'HEALTHY';
            } else if (health.overall === 'degraded') {
                badge.className = 'system-badge degraded';
                badge.textContent = 'DEGRADED';
            } else {
                badge.className = 'system-badge critical';
                badge.textContent = 'CRITICAL';
            }

            addLog('info', 'Health check completed');
        }

        function updateHealthCard(id, status) {
            const card = document.getElementById(id);
            const statusEl = card.querySelector('.health-status');
            card.className = 'health-card ' + status;
            statusEl.className = 'health-status ' + status;
            statusEl.textContent = status.toUpperCase();
        }

        async function loadQueue() {
            const queue = await fetchAPI('queue');
            if (!queue || !queue.queues) return;

            const list = document.getElementById('queue-list');
            list.innerHTML = queue.queues.map(q => `
                <div class="queue-item">
                    <span class="queue-name"><span class="status-dot ${q.pending > 100 ? 'warn' : 'ok'}"></span>${q.name}</span>
                    <span class="queue-count">${q.pending} pending</span>
                </div>
            `).join('');
        }

        async function loadStatus() {
            const status = await fetchAPI('status');
            if (!status) return;

            document.getElementById('sys-version').textContent = status.version || '--';
            document.getElementById('sys-uptime').textContent = formatUptime(status.uptime);
            document.getElementById('sys-memory').textContent = status.memory || '--';
        }

        function formatUptime(seconds) {
            if (!seconds) return '--';
            const d = Math.floor(seconds / 86400);
            const h = Math.floor((seconds % 86400) / 3600);
            const m = Math.floor((seconds % 3600) / 60);
            return d + 'd ' + h + 'h ' + m + 'm';
        }

        function addLog(level, msg) {
            const log = document.getElementById('system-log');
            const time = new Date().toLocaleTimeString('en-US', { hour12: false });
            log.innerHTML = `<div class="log-entry">
                <span class="log-time">${time}</span>
                <span class="log-level ${level}">${level.toUpperCase()}</span>
                <span>${msg}</span>
            </div>` + log.innerHTML;
        }

        window.runAction = async function(action) {
            addLog('info', 'Running: ' + action);
            const result = await fetchAPI('actions/' + action, { method: 'POST' });
            if (result && result.success) {
                addLog('info', action + ' completed');
            } else {
                addLog('error', action + ' failed');
            }
        };

        document.getElementById('refresh-btn').addEventListener('click', () => {
            loadHealth();
            loadQueue();
            loadStatus();
        });

        document.getElementById('clear-log').addEventListener('click', () => {
            document.getElementById('system-log').innerHTML = '';
        });

        // Initial load
        loadHealth();
        loadQueue();
        loadStatus();

        // Auto-refresh every 30s
        setInterval(loadHealth, 30000);
    })();
    </script>
    <?php wp_footer(); ?>
</body>
</html>
