/**
 * BITCORE Tasks Page Controller
 * Handles task creation, listing, and management
 * @version 1.0.1 - Fixed selectors to match HTML
 */

(function(global) {
    'use strict';

    const TasksPage = {
        config: {
            endpoint: '/wp-json/bitcore/v1/tasks',
            nonce: null,
            container: '.task-container',      // FIXED: was #tasks-container
            form: '.card-body form',           // FIXED: was #task-form
            list: '#task-list',
            createBtn: '#submit-task-btn',
            refreshBtn: '#refresh-btn',
            newTaskName: '#new-task-name',
            newTaskDesc: '#new-task-desc',
            newTaskPriority: '#new-task-priority'
        },

        tasks: [],
        currentFilter: 'all',

        init: function(config = {}) {
            this.config = { ...this.config, ...config };
            
            // Get nonce from WordPress localization if available
            if (typeof bitcoreData !== 'undefined') {
                this.config.nonce = bitcoreData.nonce;
            }

            console.log('TasksPage initializing...');
            this.bindEvents();
            this.loadTasks();
        },

        bindEvents: function() {
            const self = this;

            // Create task button
            const createBtn = document.querySelector(this.config.createBtn);
            if (createBtn) {
                createBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    self.createTask();
                });
            }

            // Refresh button
            const refreshBtn = document.querySelector(this.config.refreshBtn);
            if (refreshBtn) {
                refreshBtn.addEventListener('click', function() {
                    self.loadTasks();
                });
            }

            // Filter buttons
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    self.currentFilter = this.dataset.filter;
                    self.renderTasks();
                });
            });
        },

        loadTasks: async function() {
            const self = this;
            const listEl = document.querySelector(this.config.list);
            
            if (listEl) {
                listEl.innerHTML = '<div class="empty-state">Loading tasks...</div>';
            }

            try {
                const response = await fetch(this.config.endpoint, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': this.config.nonce || ''
                    }
                });

                if (!response.ok) {
                    throw new Error('API returned ' + response.status);
                }

                const data = await response.json();
                this.tasks = data.tasks || data || [];
                this.renderTasks();
                this.updateStats();
            } catch (error) {
                console.error('Load tasks error:', error);
                if (listEl) {
                    listEl.innerHTML = '<div class="empty-state">No tasks found. Create your first task!</div>';
                }
                this.updateStats(0, 0, 0, 0);
            }
        },

        renderTasks: function() {
            const listEl = document.querySelector(this.config.list);
            if (!listEl) return;

            let filtered = this.tasks;
            if (this.currentFilter !== 'all') {
                filtered = this.tasks.filter(t => t.status === this.currentFilter);
            }

            if (filtered.length === 0) {
                listEl.innerHTML = '<div class="empty-state">No tasks found</div>';
                return;
            }

            let html = '';
            filtered.forEach(task => {
                html += `
                    <div class="task-item" data-task-id="${task.id}" onclick="TasksPage.selectTask('${task.id}')">
                        <div class="task-item-header">
                            <span class="task-name">${this.escapeHtml(task.name || task.title)}</span>
                            <span class="task-status ${task.status}">${task.status}</span>
                        </div>
                        <div class="task-desc">${this.escapeHtml(task.description || '')}</div>
                    </div>
                `;
            });
            listEl.innerHTML = html;

            document.getElementById('task-count').textContent = filtered.length + ' tasks';
        },

        updateStats: function(total, pending, running, completed) {
            if (total === undefined) {
                total = this.tasks.length;
                pending = this.tasks.filter(t => t.status === 'pending').length;
                running = this.tasks.filter(t => t.status === 'running').length;
                completed = this.tasks.filter(t => t.status === 'completed').length;
            }

            const totalEl = document.getElementById('total-tasks');
            const pendingEl = document.getElementById('pending-tasks');
            const runningEl = document.getElementById('running-tasks');
            const completedEl = document.getElementById('completed-tasks');

            if (totalEl) totalEl.textContent = total;
            if (pendingEl) pendingEl.textContent = pending;
            if (runningEl) runningEl.textContent = running;
            if (completedEl) completedEl.textContent = completed;
        },

        createTask: async function() {
            const nameEl = document.querySelector(this.config.newTaskName);
            const descEl = document.querySelector(this.config.newTaskDesc);
            const priorityEl = document.querySelector(this.config.newTaskPriority);

            const name = nameEl ? nameEl.value.trim() : '';
            const desc = descEl ? descEl.value.trim() : '';
            const priority = priorityEl ? priorityEl.value : 'normal';

            if (!name) {
                alert('Task name required');
                return;
            }

            try {
                const response = await fetch(this.config.endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': this.config.nonce || ''
                    },
                    body: JSON.stringify({ name: name, description: desc, priority: priority })
                });

                if (!response.ok) {
                    throw new Error('Failed to create task: ' + response.status);
                }

                // Clear form
                if (nameEl) nameEl.value = '';
                if (descEl) descEl.value = '';

                // Reload tasks
                this.loadTasks();
                this.showNotification('Task created!', 'success');
            } catch (error) {
                console.error('Create task error:', error);
                this.showNotification('Error: ' + error.message, 'error');
            }
        },

        selectTask: function(taskId) {
            const task = this.tasks.find(t => t.id == taskId);
            if (!task) return;

            const detailCard = document.getElementById('task-detail-card');
            const detailBody = document.getElementById('task-detail-body');

            if (detailCard && detailBody) {
                detailCard.style.display = 'block';
                detailBody.innerHTML = `
                    <p><strong>Name:</strong> ${this.escapeHtml(task.name)}</p>
                    <p><strong>Status:</strong> ${task.status}</p>
                    <p><strong>Description:</strong> ${this.escapeHtml(task.description || 'None')}</p>
                    <p><strong>Priority:</strong> ${task.priority || 'normal'}</p>
                    <button class="btn btn-danger btn-sm" onclick="TasksPage.deleteTask('${task.id}')">Delete</button>
                `;
            }
        },

        deleteTask: async function(taskId) {
            if (!confirm('Delete this task?')) return;

            try {
                await fetch(this.config.endpoint + '/' + taskId, {
                    method: 'DELETE',
                    headers: { 'X-WP-Nonce': this.config.nonce || '' }
                });
                document.getElementById('task-detail-card').style.display = 'none';
                this.loadTasks();
            } catch (error) {
                console.error('Delete error:', error);
            }
        },

        showNotification: function(message, type) {
            const existing = document.querySelector('.notification');
            if (existing) existing.remove();

            const notification = document.createElement('div');
            notification.className = 'notification notification-' + type;
            notification.textContent = message;
            notification.style.cssText = 'position:fixed;top:20px;right:20px;padding:10px 20px;border-radius:4px;z-index:9999;background:' + (type === 'success' ? '#4caf50' : '#f44336') + ';color:white;';
            document.body.appendChild(notification);

            setTimeout(function() { notification.remove(); }, 3000);
        },

        escapeHtml: function(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Auto-initialize
    document.addEventListener('DOMContentLoaded', function() {
        TasksPage.init();
    });

    global.TasksPage = TasksPage;

})(typeof window !== 'undefined' ? window : this);
