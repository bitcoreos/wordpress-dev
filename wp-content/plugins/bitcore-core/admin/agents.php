<?php
/**
 * agents
 *
 * @package    Bitcore
 * @since      2026.03.01
 * @version    1.3.0
 * @author     hyperion
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * BITCORE Admin Agents Page
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
                    <span class="stat-value online">Online</span>
                </div>
                <div class="agent-stat">
                    <span class="stat-label">Tasks Today</span>
                    <span class="stat-value">42</span>
                </div>
                <div class="agent-stat">
                    <span class="stat-label">Success Rate</span>
                    <span class="stat-value">98.5%</span>
                </div>
            </div>
            <div class="agent-actions">
                <button class="button">Test Connection</button>
                <button class="button">View Logs</button>
            </div>
        </div>

        <!-- Hyperion -->
        <div class="bitcore-card agent-card">
            <div class="agent-header">
                <div class="agent-avatar hyperion"></div>
                <div class="agent-info">
                    <h2>Hyperion</h2>
                    <span class="agent-role">Validation Layer</span>
                </div>
            </div>
            <div class="agent-stats">
                <div class="agent-stat">
                    <span class="stat-label">Status</span>
                    <span class="stat-value online">Online</span>
                </div>
                <div class="agent-stat">
                    <span class="stat-label">Validations</span>
                    <span class="stat-value">156</span>
                </div>
                <div class="agent-stat">
                    <span class="stat-label">Accuracy</span>
                    <span class="stat-value">99.2%</span>
                </div>
            </div>
            <div class="agent-actions">
                <button class="button">Test Connection</button>
                <button class="button">View Logs</button>
            </div>
        </div>
    </div>
</div>

<style>
.bitcore-agents-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}
.agent-card {
    padding: 24px;
}
.agent-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 20px;
}
.agent-avatar {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
.agent-avatar.hyperion {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}
.agent-info h2 {
    margin: 0 0 4px 0;
}
.agent-role {
    color: #646970;
    font-size: 13px;
}
.agent-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin-bottom: 20px;
}
.agent-stat {
    text-align: center;
    padding: 12px;
    background: #f6f7f7;
    border-radius: 4px;
}
.agent-stat .stat-label {
    display: block;
    font-size: 12px;
    color: #646970;
    margin-bottom: 4px;
}
.agent-stat .stat-value {
    font-size: 18px;
    font-weight: 600;
}
.agent-stat .stat-value.online {
    color: #00a32a;
}
.agent-actions {
    display: flex;
    gap: 12px;
}
</style>
