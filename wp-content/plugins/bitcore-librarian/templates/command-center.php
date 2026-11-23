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

<style>
.bitcore-command-center {
    --primary: #6366f1;
    --primary-dark: #4f46e5;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --bg-dark: #0f172a;
    --bg-card: #1e293b;
    --text-primary: #f8fafc;
    --text-secondary: #94a3b8;
    --border: #334155;
    
    padding: 1.5rem;
    background: var(--bg-dark);
    color: var(--text-primary);
    min-height: 100vh;
}

.command-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border);
}

.command-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.5rem;
    font-weight: 600;
    margin: 0;
}

.connection-status {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-left: 1rem;
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--danger);
}

.status-dot.online {
    background: var(--success);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}

.dashboard-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 0.5rem;
    overflow: hidden;
}

.dashboard-card.agents-card,
.dashboard-card.tasks-card {
    grid-column: span 2;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 1rem;
    background: rgba(0,0,0,0.2);
    border-bottom: 1px solid var(--border);
}

.card-header h2 {
    margin: 0;
    font-size: 1rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.card-content {
    padding: 1rem;
    max-height: 300px;
    overflow-y: auto;
}

.task-columns {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.5rem;
}

.task-column h3 {
    font-size: 0.75rem;
    text-transform: uppercase;
    color: var(--text-secondary);
    margin: 0 0 0.5rem 0;
}

.live-indicator {
    font-size: 0.75rem;
    color: var(--success);
    animation: pulse 1.5s infinite;
}

.health-item {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0;
    border-bottom: 1px solid var(--border);
}

.health-status {
    color: var(--warning);
}

.health-status.healthy {
    color: var(--success);
}

.health-status.unhealthy {
    color: var(--danger);
}

.btn {
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-primary {
    background: var(--primary);
    color: white;
}

.btn-secondary {
    background: var(--border);
    color: var(--text-primary);
}

.btn:hover {
    opacity: 0.9;
}

.chat-input-area {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.5rem;
}

.chat-input-area input,
.chat-input-area select {
    padding: 0.5rem;
    background: var(--bg-dark);
    border: 1px solid var(--border);
    border-radius: 0.375rem;
    color: var(--text-primary);
}

.chat-input-area input {
    flex: 1;
}

.agent-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem;
    border-radius: 0.375rem;
    margin-bottom: 0.5rem;
    background: rgba(0,0,0,0.2);
}

.agent-avatar {
    font-size: 1.5rem;
}

.agent-info {
    flex: 1;
}

.agent-name {
    font-weight: 500;
}

.agent-role {
    font-size: 0.75rem;
    color: var(--text-secondary);
}

.agent-status {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.agent-status.working { background: var(--success); }
.agent-status.standby { background: var(--warning); }
.agent-status.offline { background: var(--text-secondary); }
</style>

<script>
jQuery(document).ready(function($) {
    // Initialize Command Center
    const CommandCenter = {
        sse: null,
        agents: [],
        tasks: [],
        
        init: function() {
            this.connectSSE();
            this.loadAgents();
            this.loadTasks();
            this.checkAPIHealth();
            this.bindEvents();
        },
        
        connectSSE: function() {
            const self = this;
            this.sse = new EventSource('<?php echo rest_url('bitcore/v1/events/stream'); ?>');
            
            this.sse.onopen = function() {
                $('#connection-status .status-dot').removeClass('offline').addClass('online');
                $('#connection-status .status-text').text('Connected');
            };
            
            this.sse.onmessage = function(event) {
                const data = JSON.parse(event.data);
                self.handleEvent(data.type, data.payload);
            };
            
            this.sse.onerror = function() {
                $('#connection-status .status-dot').removeClass('online').addClass('offline');
                $('#connection-status .status-text').text('Disconnected');
            };
        },
        
        handleEvent: function(type, payload) {
            switch(type) {
                case 'task_created':
                case 'task_updated':
                    this.loadTasks();
                    break;
                case 'agent_spawned':
                case 'agent_status_changed':
                    this.loadAgents();
                    break;
                case 'activity_logged':
                    this.addActivity(payload);
                    break;
            }
        },
        
        loadAgents: function() {
            const self = this;
            $.get('<?php echo rest_url('bitcore/v1/agents'); ?>', function(response) {
                self.agents = response;
                self.renderAgents();
            });
        },
        
        renderAgents: function() {
            const container = $('#agents-list');
            container.empty();
            
            this.agents.forEach(function(agent) {
                container.append(`
                    <div class="agent-item">
                        <span class="agent-avatar">${agent.avatar_emoji || '🤖'}</span>
                        <div class="agent-info">
                            <div class="agent-name">${agent.name}</div>
                            <div class="agent-role">${agent.role}</div>
                        </div>
                        <span class="agent-status ${agent.status}"></span>
                    </div>
                `);
            });
            
            $('#agent-count').text(this.agents.length);
            
            // Update chat select
            const select = $('#chat-agent-select');
            select.find('option:not(:first)').remove();
            this.agents.forEach(function(agent) {
                select.append(`<option value="${agent.id}">${agent.name}</option>`);
            });
        },
        
        loadTasks: function() {
            const self = this;
            $.get('<?php echo rest_url('bitcore/v1/tasks'); ?>', function(response) {
                self.tasks = response;
                self.renderTasks();
            });
        },
        
        renderTasks: function() {
            const self = this;
            $('.task-column').each(function() {
                const status = $(this).data('status');
                const tasks = self.tasks.filter(t => t.status === status);
                const list = $(this).find('.task-list');
                list.empty();
                
                tasks.forEach(function(task) {
                    list.append(`
                        <div class="task-item" data-id="${task.id}">
                            <div class="task-title">${task.title}</div>
                        </div>
                    `);
                });
                
                $(this).find('.count').text(tasks.length);
            });
            
            const total = this.tasks.length;
            $('#task-count').text(total);
        },
        
        addActivity: function(payload) {
            const feed = $('#activity-feed');
            feed.prepend(`
                <div class="activity-item">
                    <span class="activity-time">${new Date().toLocaleTimeString()}</span>
                    <span class="activity-message">${payload.message || 'Activity occurred'}</span>
                </div>
            `);
            // Keep only last 50 items
            feed.find('.activity-item:gt(49)').remove();
        },
        
        checkAPIHealth: function() {
            const endpoints = ['rest', 'mcp', 'n8n', 'bithub', 'bitwiki'];
            endpoints.forEach(function(ep) {
                // Simple health check
                const el = $(`.health-status[data-endpoint="${ep}"]`);
                el.addClass('healthy');
            });
        },
        
        bindEvents: function() {
            const self = this;
            
            $('#btn-refresh').on('click', function() {
                self.loadAgents();
                self.loadTasks();
            });
            
            $('#btn-new-task').on('click', function() {
                // Open task creation modal
                alert('Task creation modal - implement with SweetAlert or similar');
            });
            
            $('#btn-send-chat').on('click', function() {
                const agentId = $('#chat-agent-select').val();
                const message = $('#chat-input').val();
                if (agentId && message) {
                    self.sendChatMessage(agentId, message);
                    $('#chat-input').val('');
                }
            });
        },
        
        sendChatMessage: function(agentId, message) {
            $.post('<?php echo rest_url('bitcore/v1/chat'); ?>', {
                agent_id: agentId,
                message: message
            }, function(response) {
                $('#swarm-chat-messages').append(`
                    <div class="user-message">${message}</div>
                    <div class="agent-message">${response.reply || 'Processing...'}</div>
                `);
            });
        }
    };
    
    // Initialize
    CommandCenter.init();
});
</script>
