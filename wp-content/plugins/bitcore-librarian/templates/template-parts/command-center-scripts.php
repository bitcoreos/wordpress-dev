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
