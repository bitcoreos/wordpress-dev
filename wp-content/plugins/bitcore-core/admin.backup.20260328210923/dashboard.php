<?php
declare(strict_types=1);
/**
 * BITCORE Admin Dashboard
 */
?>
<div class="wrap bitcore-admin">
    <h1>BITCORE Intelligence Factory</h1>

    <div class="bitcore-dashboard-grid">
        <!-- Status Cards -->
        <div class="bitcore-card">
            <h2>System Status</h2>
            <div class="status-grid">
                <div class="status-item">
                    <span class="status-label">n8n Connection</span>
                    <span class="status-value" id="n8n-status">Checking...</span>
                </div>
                <div class="status-item">
                    <span class="status-label">BIThub API</span>
                    <span class="status-value" id="bithub-status">Checking...</span>
                </div>
                <div class="status-item">
                    <span class="status-label">BITwiki API</span>
                    <span class="status-value" id="bitwiki-status">Checking...</span>
                </div>
            </div>
        </div>

        <!-- Usage Stats -->
        <div class="bitcore-card">
            <h2>Usage Today</h2>
            <div class="stats-grid">
                <div class="stat-item">
                    <span class="stat-number" id="today-calls">0</span>
                    <span class="stat-label">API Calls</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number" id="today-tokens">0</span>
                    <span class="stat-label">Tokens</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number" id="today-cost">$0.00</span>
                    <span class="stat-label">Cost</span>
                </div>
            </div>
        </div>

        <!-- Active Modules -->
        <div class="bitcore-card">
            <h2>Active Modules</h2>
            <ul class="module-list">
                <li class="module-item active">
                    <span class="module-icon">💬</span>
                    <span class="module-name">Chatbot</span>
                    <span class="module-status">Active</span>
                </li>
                <li class="module-item active">
                    <span class="module-icon">🧠</span>
                    <span class="module-name">MCP</span>
                    <span class="module-status">Active</span>
                </li>
                <li class="module-item active">
                    <span class="module-icon">🔍</span>
                    <span class="module-name">Search</span>
                    <span class="module-status">Active</span>
                </li>
                <li class="module-item active">
                    <span class="module-icon">📝</span>
                    <span class="module-name">Forms</span>
                    <span class="module-status">Active</span>
                </li>
            </ul>
        </div>

        <!-- Quick Actions -->
        <div class="bitcore-card">
            <h2>Quick Actions</h2>
            <div class="action-buttons">
                <a href="<?php echo admin_url('admin.php?page=bitcore-agents'); ?>" class="button button-primary">Manage Agents</a>
declare(strict_types=1);
                <a href="<?php echo admin_url('admin.php?page=bitcore-mcp'); ?>" class="button">MCP Tools</a>
                <a href="<?php echo admin_url('admin.php?page=bitcore-settings'); ?>" class="button">Settings</a>
            </div>
        </div>
    </div>
</div>

<style>
.bitcore-admin {
    max-width: 1200px;
}
.bitcore-admin h1 {
    color: #1a1a2e;
    margin-bottom: 24px;
}
.bitcore-dashboard-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}
.bitcore-card {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 20px;
}
.bitcore-card h2 {
    margin-top: 0;
    font-size: 16px;
    color: #1d2327;
}
.status-grid, .stats-grid {
    display: grid;
    gap: 12px;
}
.status-item, .stat-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    background: #f6f7f7;
    border-radius: 4px;
}
.stat-number {
    font-size: 24px;
    font-weight: 600;
    color: #2271b1;
}
.module-list {
    list-style: none;
    padding: 0;
    margin: 0;
}
.module-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border-bottom: 1px solid #f0f0f1;
}
.module-item:last-child {
    border-bottom: none;
}
.module-icon {
    font-size: 20px;
}
.module-name {
    flex: 1;
}
.module-status {
    color: #00a32a;
    font-weight: 500;
}
.action-buttons {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}
</style>
