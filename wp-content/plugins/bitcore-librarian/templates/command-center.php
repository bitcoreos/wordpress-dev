<?php
/**
 * Command Center Template
 * 
 * Pattern extracted from mission-control (agent orchestration dashboard)
 * Pattern extracted from flux (knowledge graph, data bridge)
 * Pattern extracted from OpenClaw-OPS-Suite (modular dashboard cards)
 * 
 * @package BitCore
 * @since 1.0.0
 * @license MIT
 * @version     1.0.0
 */

defined('ABSPATH') || exit;

// Get current user and settings
$current_user = wp_get_current_user();
$api_key = get_option('bitcore_api_key', '');
?>

<div id="bitcore-command-center" class="bitcore-command-center">
    <!-- Header -->
    <header class="command-header">
        <div class="header-left">
            <h1 class="command-title">
                <span class="title-icon">🎯</span>
                Command Center
            </h1>
            <span class="connection-status" id="connection-status">
                <span class="status-dot offline"></span>
                <span class="status-text">Disconnected</span>
            </span>
        </div>
        <div class="header-right">
            <button class="btn btn-secondary" id="btn-refresh">
                <span class="icon">🔄</span> Refresh
            </button>
            <button class="btn btn-primary" id="btn-new-task">
                <span class="icon">➕</span> New Task
            </button>
        </div>
    </header>

    <!-- Main Dashboard Grid -->
    <div class="dashboard-grid">
        <!-- Agent Status Panel -->
        <section class="dashboard-card agents-card">
            <header class="card-header">
                <h2><span class="icon">🤖</span> Agents</h2>
                <span class="badge" id="agent-count">0</span>
            </header>
            <div class="card-content" id="agents-list">
                <!-- Populated by JS -->
                <div class="loading">Loading agents...</div>
            </div>
        </section>

        <!-- Task Kanban Panel -->
        <section class="dashboard-card tasks-card">
            <header class="card-header">
                <h2><span class="icon">📋</span> Tasks</h2>
                <span class="badge" id="task-count">0</span>
            </header>
            <div class="card-content">
                <div class="task-columns" id="task-board">
                    <div class="task-column" data-status="planning">
                        <h3>Planning <span class="count">0</span></h3>
                        <div class="task-list"></div>
                    </div>
                    <div class="task-column" data-status="in_progress">
                        <h3>In Progress <span class="count">0</span></h3>
                        <div class="task-list"></div>
                    </div>
                    <div class="task-column" data-status="review">
                        <h3>Review <span class="count">0</span></h3>
                        <div class="task-list"></div>
                    </div>
                    <div class="task-column" data-status="done">
                        <h3>Done <span class="count">0</span></h3>
                        <div class="task-list"></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Activity Feed Panel -->
        <section class="dashboard-card activity-card">
            <header class="card-header">
                <h2><span class="icon">⚡</span> Activity</h2>
                <span class="live-indicator">● LIVE</span>
            </header>
            <div class="card-content" id="activity-feed">
                <!-- Populated by JS -->
            </div>
        </section>

        <!-- API Health Panel -->
        <section class="dashboard-card health-card">
            <header class="card-header">
                <h2><span class="icon">💚</span> API Health</h2>
            </header>
            <div class="card-content" id="api-health">
                <div class="health-item">
                    <span class="health-label">WordPress REST</span>
                    <span class="health-status" data-endpoint="rest">●</span>
                </div>
                <div class="health-item">
                    <span class="health-label">MCP Tools</span>
                    <span class="health-status" data-endpoint="mcp">●</span>
                </div>
                <div class="health-item">
                    <span class="health-label">n8n Workflows</span>
                    <span class="health-status" data-endpoint="n8n">●</span>
                </div>
                <div class="health-item">
                    <span class="health-label">BIThub</span>
                    <span class="health-status" data-endpoint="bithub">●</span>
                </div>
                <div class="health-item">
                    <span class="health-label">BITwiki</span>
                    <span class="health-status" data-endpoint="bitwiki">●</span>
                </div>
            </div>
        </section>

        <!-- Swarm Chat Panel -->
        <section class="dashboard-card chat-card">
            <header class="card-header">
                <h2><span class="icon">💬</span> Swarm Chat</h2>
            </header>
            <div class="card-content">
                <div class="chat-messages" id="swarm-chat-messages">
                    <div class="system-message">
                        Welcome to Swarm Chat. Select an agent to begin.
                    </div>
                </div>
                <div class="chat-input-area">
                    <select id="chat-agent-select">
                        <option value="">Select Agent...</option>
                    </select>
                    <input type="text" id="chat-input" placeholder="Type a message..." />
                    <button class="btn btn-primary" id="btn-send-chat">Send</button>
                </div>
            </div>
        </section>

        <!-- Knowledge Graph Panel -->
        <section class="dashboard-card knowledge-card">
            <header class="card-header">
                <h2><span class="icon">🔮</span> Knowledge Graph</h2>
            </header>
            <div class="card-content" id="knowledge-graph">
                <!-- Visual graph rendered by JS -->
                <div class="graph-placeholder">
                    <span class="icon">🔗</span>
                    <p>Active Projects & Connections</p>
                </div>
            </div>
        </section>
    </div>
</div>

<?php include BITCORE_LIBRARIAN_PATH . 'templates/template-parts/command-center-styles.php'; ?>

<?php include BITCORE_LIBRARIAN_PATH . 'templates/template-parts/command-center-scripts.php'; ?>
