<?php
/**
 * Template Name: Sessions
 * Description: Active session and connection management
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_title('|', true, 'right'); ?><?php bloginfo('name'); ?></title>
    <?php wp_head(); ?>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(180deg, #0a0a0f 0%, #1a1a2e 50%, #0f0f1a 100%);
            color: #e0e0e0;
            min-height: 100vh;
            line-height: 1.6;
        }
        .cosmic-bg {
            position: relative;
            min-height: 100vh;
        }
        .cosmic-bg::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="10" cy="10" r="0.5" fill="%23ffffff" opacity="0.3"/><circle cx="30" cy="25" r="0.3" fill="%23ffffff" opacity="0.2"/><circle cx="50" cy="15" r="0.4" fill="%23ffffff" opacity="0.25"/><circle cx="70" cy="35" r="0.3" fill="%23ffffff" opacity="0.2"/><circle cx="90" cy="20" r="0.5" fill="%23ffffff" opacity="0.3"/></svg>');
            background-size: 200px 200px;
            opacity: 0.5;
            pointer-events: none;
        }
        .sessions-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
            position: relative;
            z-index: 1;
        }
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 32px;
            flex-wrap: wrap;
            gap: 20px;
        }
        .header-section h1 {
            font-size: 2rem;
            font-weight: 300;
            color: #fff;
        }
        .header-section .subtitle {
            font-size: 0.95rem;
            color: #888;
            margin-top: 4px;
        }
        .header-actions {
            display: flex;
            gap: 12px;
        }
        .action-btn {
            padding: 10px 20px;
            font-size: 0.85rem;
            font-weight: 500;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .action-btn.danger {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #ef4444;
        }
        .action-btn.danger:hover {
            background: rgba(239, 68, 68, 0.3);
        }
        .action-btn.primary {
            background: linear-gradient(135deg, #4ade80, #22c55e);
            color: #000;
        }
        .action-btn.primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(74, 222, 128, 0.4);
        }
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }
        .stat-card {
            background: rgba(17, 17, 24, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }
        .stat-card .stat-value {
            font-size: 1.75rem;
            font-weight: 600;
        }
        .stat-card .stat-label {
            font-size: 0.8rem;
            color: #888;
            margin-top: 4px;
        }
        .stat-card.green .stat-value { color: #4ade80; }
        .stat-card.blue .stat-value { color: #818cf8; }
        .stat-card.orange .stat-value { color: #fb923c; }
        .stat-card.red .stat-value { color: #ef4444; }
        .filter-bar {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        .search-input {
            padding: 10px 16px;
            font-size: 0.85rem;
            background: rgba(17, 17, 24, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: #fff;
            outline: none;
            flex: 1;
            min-width: 200px;
            transition: all 0.3s;
        }
        .search-input:focus {
            border-color: #4ade80;
        }
        .search-input::placeholder { color: #666; }
        .filter-select {
            padding: 10px 16px;
            font-size: 0.85rem;
            background: rgba(17, 17, 24, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: #fff;
            cursor: pointer;
        }
        .filter-select option { background: #111118; }
        .main-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }
        @media (max-width: 1024px) {
            .main-grid { grid-template-columns: 1fr; }
        }
        .section-card {
            background: rgba(17, 17, 24, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            overflow: hidden;
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        .section-header h2 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #fff;
        }
        .section-count {
            padding: 4px 10px;
            font-size: 0.75rem;
            background: rgba(74, 222, 128, 0.2);
            color: #4ade80;
            border-radius: 20px;
        }
        .sessions-list {
            display: flex;
            flex-direction: column;
        }
        .session-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            transition: all 0.2s;
        }
        .session-item:last-child { border-bottom: none; }
        .session-item:hover {
            background: rgba(255, 255, 255, 0.02);
        }
        .session-item.current {
            background: rgba(74, 222, 128, 0.05);
            border-left: 3px solid #4ade80;
        }
        .session-info {
            display: flex;
            align-items: center;
            gap: 16px;
            flex: 1;
        }
        .device-icon {
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
        }
        .device-icon.desktop { background: rgba(129, 140, 248, 0.2); }
        .device-icon.mobile { background: rgba(244, 114, 182, 0.2); }
        .device-icon.tablet { background: rgba(251, 146, 60, 0.2); }
        .device-icon.api { background: rgba(167, 139, 250, 0.2); }
        .session-details { flex: 1; }
        .session-title {
            font-size: 0.95rem;
            color: #fff;
            font-weight: 500;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .current-badge {
            padding: 2px 8px;
            font-size: 0.65rem;
            font-weight: 600;
            background: rgba(74, 222, 128, 0.2);
            color: #4ade80;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .session-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            font-size: 0.8rem;
            color: #888;
        }
        .session-meta span {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }
        .status-dot.active { background: #4ade80; box-shadow: 0 0 8px rgba(74, 222, 128, 0.5); }
        .status-dot.idle { background: #fbbf24; }
        .status-dot.expired { background: #ef4444; }
        .session-status {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 8px;
        }
        .status-badge {
            padding: 4px 10px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-radius: 4px;
        }
        .status-badge.active {
            background: rgba(74, 222, 128, 0.2);
            color: #4ade80;
        }
        .status-badge.idle {
            background: rgba(251, 191, 36, 0.2);
            color: #fbbf24;
        }
        .status-badge.expired {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }
        .terminate-btn {
            padding: 6px 12px;
            font-size: 0.75rem;
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            border-radius: 6px;
            color: #ef4444;
            cursor: pointer;
            transition: all 0.2s;
        }
        .terminate-btn:hover {
            background: rgba(239, 68, 68, 0.2);
        }
        .terminate-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }
        .login-history-item {
            display: flex;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        .login-history-item:last-child { border-bottom: none; }
        .history-time {
            font-size: 0.75rem;
            color: #888;
            min-width: 80px;
        }
        .history-details {
            flex: 1;
        }
        .history-action {
            font-size: 0.85rem;
            color: #e0e0e0;
        }
        .history-location {
            font-size: 0.75rem;
            color: #666;
        }
        .history-icon {
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: 0.8rem;
        }
        .history-icon.login { background: rgba(74, 222, 128, 0.2); color: #4ade80; }
        .history-icon.logout { background: rgba(129, 140, 248, 0.2); color: #818cf8; }
        .history-icon.failed { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
        .security-alert {
            display: flex;
            gap: 12px;
            padding: 12px 16px;
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            border-radius: 10px;
            margin-bottom: 12px;
        }
        .security-alert:last-child { margin-bottom: 0; }
        .security-alert.warning {
            background: rgba(251, 191, 36, 0.1);
            border-color: rgba(251, 191, 36, 0.2);
        }
        .alert-icon {
            font-size: 1.25rem;
        }
        .alert-content { flex: 1; }
        .alert-title {
            font-size: 0.85rem;
            color: #fff;
            font-weight: 500;
        }
        .alert-description {
            font-size: 0.75rem;
            color: #888;
            margin-top: 2px;
        }
        .alert-time {
            font-size: 0.7rem;
            color: #666;
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #888;
        }
        .empty-state .icon {
            font-size: 2rem;
            margin-bottom: 12px;
            opacity: 0.5;
        }
        .connection-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .connection-stat {
            padding: 12px;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 8px;
            text-align: center;
        }
        .connection-stat .value {
            font-size: 1.25rem;
            font-weight: 600;
            color: #fff;
        }
        .connection-stat .label {
            font-size: 0.7rem;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .api-key-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 8px;
            margin-bottom: 8px;
        }
        .api-key-item:last-child { margin-bottom: 0; }
        .api-key-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .api-key-name {
            font-size: 0.85rem;
            color: #fff;
        }
        .api-key-value {
            font-size: 0.75rem;
            color: #888;
            font-family: 'SF Mono', Monaco, monospace;
        }
        .revoke-btn {
            padding: 6px 12px;
            font-size: 0.75rem;
            background: rgba(239, 68, 68, 0.1);
            border: none;
            border-radius: 6px;
            color: #ef4444;
            cursor: pointer;
        }
        @media (max-width: 768px) {
            .header-section { flex-direction: column; }
            .session-item { flex-direction: column; align-items: flex-start; gap: 12px; }
            .session-status { align-items: flex-start; flex-direction: row; }
            .stats-row { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body class="cosmic-bg">
    <?php include(get_template_directory() . "/nav-header.php"); ?>
    <div id="sessions-container" class="sessions-container bitcore-container">
        <section class="header-section">
            <div>
                <h1>Session Management</h1>
                <p class="subtitle">Active sessions and connection monitoring</p>
            </div>
            <div class="header-actions">
                <button class="action-btn danger" onclick="terminateAll()">Terminate All Other</button>
                <button class="action-btn primary">New Session</button>
            </div>
        </section>

        <section class="stats-row">
            <div class="stat-card green">
                <div class="stat-value">4</div>
                <div class="stat-label">Active Sessions</div>
            </div>
            <div class="stat-card blue">
                <div class="stat-value">2</div>
                <div class="stat-label">API Connections</div>
            </div>
            <div class="stat-card orange">
                <div class="stat-value">1</div>
                <div class="stat-label">Idle Sessions</div>
            </div>
            <div class="stat-card red">
                <div class="stat-value">0</div>
                <div class="stat-label">Security Alerts</div>
            </div>
        </section>

        <div class="filter-bar">
            <input type="text" class="search-input" placeholder="Search sessions...">
            <select class="filter-select">
                <option value="">All Devices</option>
                <option value="desktop">Desktop</option>
                <option value="mobile">Mobile</option>
                <option value="tablet">Tablet</option>
                <option value="api">API</option>
            </select>
            <select class="filter-select">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="idle">Idle</option>
                <option value="expired">Expired</option>
            </select>
        </div>

        <div class="main-grid">
            <div class="section-card">
                <div class="section-header">
                    <h2>Active Sessions</h2>
                    <span class="section-count">4 active</span>
                </div>
                <div class="sessions-list">
                    <!-- Current Session -->
                    <div class="session-item current">
                        <div class="session-info">
                            <div class="device-icon desktop">💻</div>
                            <div class="session-details">
                                <div class="session-title">
                                    Chrome on macOS
                                    <span class="current-badge">Current</span>
                                </div>
                                <div class="session-meta">
                                    <span><span class="status-dot active"></span> Active</span>
                                    <span>📍 New York, USA</span>
                                    <span>IP: 192.168.1.***</span>
                                    <span>Last activity: Just now</span>
                                </div>
                            </div>
                        </div>
                        <div class="session-status">
                            <span class="status-badge active">Active</span>
                            <button class="terminate-btn" disabled>Current Session</button>
                        </div>
                    </div>

                    <!-- Session 2 -->
                    <div class="session-item">
                        <div class="session-info">
                            <div class="device-icon desktop">💻</div>
                            <div class="session-details">
                                <div class="session-title">Firefox on Windows</div>
                                <div class="session-meta">
                                    <span><span class="status-dot active"></span> Active</span>
                                    <span>📍 San Francisco, USA</span>
                                    <span>IP: 10.0.0.***</span>
                                    <span>Last activity: 5 mins ago</span>
                                </div>
                            </div>
                        </div>
                        <div class="session-status">
                            <span class="status-badge active">Active</span>
                            <button class="terminate-btn" onclick="terminateSession(2)">Terminate</button>
                        </div>
                    </div>

                    <!-- Session 3 -->
                    <div class="session-item">
                        <div class="session-info">
                            <div class="device-icon mobile">📱</div>
                            <div class="session-details">
                                <div class="session-title">Safari on iPhone</div>
                                <div class="session-meta">
                                    <span><span class="status-dot idle"></span> Idle</span>
                                    <span>📍 Boston, USA</span>
                                    <span>IP: 172.16.0.***</span>
                                    <span>Last activity: 2 hours ago</span>
                                </div>
                            </div>
                        </div>
                        <div class="session-status">
                            <span class="status-badge idle">Idle</span>
                            <button class="terminate-btn" onclick="terminateSession(3)">Terminate</button>
                        </div>
                    </div>

                    <!-- Session 4 -->
                    <div class="session-item">
                        <div class="session-info">
                            <div class="device-icon api">🔌</div>
                            <div class="session-details">
                                <div class="session-title">API Key - n8n Integration</div>
                                <div class="session-meta">
                                    <span><span class="status-dot active"></span> Active</span>
                                    <span>📍 Server</span>
                                    <span>Key: sk-***...xYz</span>
                                    <span>Last activity: 1 min ago</span>
                                </div>
                            </div>
                        </div>
                        <div class="session-status">
                            <span class="status-badge active">Active</span>
                            <button class="terminate-btn" onclick="terminateSession(4)">Revoke</button>
                        </div>
                    </div>

                    <!-- Session 5 -->
                    <div class="session-item">
                        <div class="session-info">
                            <div class="device-icon tablet">📲</div>
                            <div class="session-details">
                                <div class="session-title">Chrome on iPad</div>
                                <div class="session-meta">
                                    <span><span class="status-dot active"></span> Active</span>
                                    <span>📍 Seattle, USA</span>
                                    <span>IP: 192.168.2.***</span>
                                    <span>Last activity: 15 mins ago</span>
                                </div>
                            </div>
                        </div>
                        <div class="session-status">
                            <span class="status-badge active">Active</span>
                            <button class="terminate-btn" onclick="terminateSession(5)">Terminate</button>
                        </div>
                    </div>
                </div>
            </div>

            <aside class="sidebar">
                <!-- Security Alerts -->
                <div class="section-card">
                    <div class="section-header">
                        <h2>Security Alerts</h2>
                    </div>
                    <div class="section-content" style="padding: 16px;">
                        <div class="security-alert warning">
                            <span class="alert-icon">⚠️</span>
                            <div class="alert-content">
                                <div class="alert-title">Unusual Login Location</div>
                                <div class="alert-description">Login from Boston detected. Is this you?</div>
                            </div>
                            <span class="alert-time">2h ago</span>
                        </div>
                        <div class="empty-state" style="padding: 20px;">
                            <div class="icon">🔒</div>
                            <p style="font-size: 0.8rem;">No critical alerts</p>
                        </div>
                    </div>
                </div>

                <!-- Login History -->
                <div class="section-card">
                    <div class="section-header">
                        <h2>Login History</h2>
                    </div>
                    <div class="section-content" style="padding: 16px;">
                        <div class="login-history-item">
                            <div class="history-icon login">✓</div>
                            <div class="history-details">
                                <div class="history-action">Login - Chrome on macOS</div>
                                <div class="history-location">New York, USA • 192.168.1.***</div>
                            </div>
                            <div class="history-time">Today 10:42</div>
                        </div>
                        <div class="login-history-item">
                            <div class="history-icon login">✓</div>
                            <div class="history-details">
                                <div class="history-action">Login - Safari on iPhone</div>
                                <div class="history-location">Boston, USA • 172.16.0.***</div>
                            </div>
                            <div class="history-time">Today 08:15</div>
                        </div>
                        <div class="login-history-item">
                            <div class="history-icon logout">→</div>
                            <div class="history-details">
                                <div class="history-action">Logout - Firefox on Windows</div>
                                <div class="history-location">San Francisco, USA</div>
                            </div>
                            <div class="history-time">Yesterday 23:30</div>
                        </div>
                        <div class="login-history-item">
                            <div class="history-icon failed">✗</div>
                            <div class="history-details">
                                <div class="history-action">Failed Login Attempt</div>
                                <div class="history-location">Unknown • 203.0.113.***</div>
                            </div>
                            <div class="history-time">Yesterday 14:22</div>
                        </div>
                        <div class="login-history-item">
                            <div class="history-icon login">✓</div>
                            <div class="history-details">
                                <div class="history-action">Login - Firefox on Windows</div>
                                <div class="history-location">San Francisco, USA • 10.0.0.***</div>
                            </div>
                            <div class="history-time">Yesterday 09:00</div>
                        </div>
                    </div>
                </div>

                <!-- Connection Stats -->
                <div class="section-card">
                    <div class="section-header">
                        <h2>Connection Stats</h2>
                    </div>
                    <div class="section-content" style="padding: 16px;">
                        <div class="connection-stats">
                            <div class="connection-stat">
                                <div class="value">4</div>
                                <div class="label">Devices</div>
                            </div>
                            <div class="connection-stat">
                                <div class="value">3</div>
                                <div class="label">Locations</div>
                            </div>
                            <div class="connection-stat">
                                <div class="value">2</div>
                                <div class="label">API Keys</div>
                            </div>
                            <div class="connection-stat">
                                <div class="value">99.9%</div>
                                <div class="label">Uptime</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- API Keys -->
                <div class="section-card">
                    <div class="section-header">
                        <h2>API Keys</h2>
                    </div>
                    <div class="section-content" style="padding: 16px;">
                        <div class="api-key-item">
                            <div class="api-key-info">
                                <div class="api-key-name">n8n Integration</div>
                                <div class="api-key-value">sk-prod-***xYz</div>
                            </div>
                            <button class="revoke-btn">Revoke</button>
                        </div>
                        <div class="api-key-item">
                            <div class="api-key-info">
                                <div class="api-key-name">Development</div>
                                <div class="api-key-value">sk-dev-***aBc</div>
                            </div>
                            <button class="revoke-btn">Revoke</button>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    <script>
        function terminateSession(id) {
            const item = document.querySelector(`.session-item:nth-child(${id + 1})`);
            if (item) {
                item.style.opacity = '0.5';
                item.querySelector('.terminate-btn').textContent = 'Terminated';
                item.querySelector('.terminate-btn').disabled = true;
                item.querySelector('.status-badge').textContent = 'Terminated';
                item.querySelector('.status-badge').className = 'status-badge expired';
            }
        }

        function terminateAll() {
            const sessions = document.querySelectorAll('.session-item:not(.current)');
            sessions.forEach((session, index) => {
                setTimeout(() => {
                    session.style.opacity = '0.5';
                    const btn = session.querySelector('.terminate-btn');
                    if (btn) {
                        btn.textContent = 'Terminated';
                        btn.disabled = true;
                    }
                    const badge = session.querySelector('.status-badge');
                    if (badge) {
                        badge.textContent = 'Terminated';
                        badge.className = 'status-badge expired';
                    }
                }, index * 100);
            });
        }

        // Search functionality
        document.querySelector('.search-input').addEventListener('input', function(e) {
            const query = e.target.value.toLowerCase();
            document.querySelectorAll('.session-item').forEach(item => {
                const title = item.querySelector('.session-title').textContent.toLowerCase();
                const meta = item.querySelector('.session-meta').textContent.toLowerCase();
                item.style.display = (title.includes(query) || meta.includes(query)) ? 'flex' : 'none';
            });
        });

        // Filter functionality
        document.querySelectorAll('.filter-select').forEach(select => {
            select.addEventListener('change', function() {
                // Placeholder for filter logic
                console.log('Filter changed:', this.value);
            });
        });
    </script>
    <?php wp_footer(); ?>
</body>
</html>
