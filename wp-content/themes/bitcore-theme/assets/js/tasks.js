/**
 * BITCORE Tasks Page Controller
 * Handles task creation, listing, and management
 * @version 2.0.0 - Fixed field mapping to match REST API
 */

(function(global) {
    'use strict';

    const TasksPage = {
        config: {
            endpoint: '/wp-json/bitcore/v1/tasks',
            nonce: null,
            container: '.task-container',
            form: '.card-body form',
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
            if (typeof bitcoreData !== 'undefined' && bitcoreData.nonce) {
                this.config.nonce = bitcoreData.nonce;
            }

            console.log('TasksPage initializing with nonce:', this.config.nonce ? 'present' : 'missing');
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

            // Close detail button
            const closeBtn = document.getElementById('close-detail');
            if (closeBtn) {
                closeBtn.addEventListener('click', function() {
                    const detailCard = document.getElementById('task-detail-card');
                    if (detailCard) detailCard.style.display = 'none';
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

        getHeaders: function(method) {
            const headers = {
                'Content-Type': 'application/json'
            };
            if (this.config.nonce) {
                headers['X-WP-Nonce'] = this.config.nonce;
            }
            return headers;
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
                    headers: this.getHeaders()
                });

                if (!response.ok) {
                    throw new Error('API returned ' + response.status);
                }

                const data = await response.json();
                this.tasks = Array.isArray(data) ? data : (data.tasks || []);
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

        getTaskStatus: function(task) {
            // task_status from post meta takes priority, fallback to post status
            return task.task_status || task.status || 'pending';
        },

        renderTasks: function() {
            const listEl = document.querySelector(this.config.list);
            if (!listEl) return;

            let filtered = this.tasks;
            if (this.currentFilter !== 'all') {
                filtered = this.tasks.filter(t => this.getTaskStatus(t) === this.currentFilter);
            }

            if (filtered.length === 0) {
                listEl.innerHTML = '<div class="empty-state">No tasks found</div>';
                return;
            }

            let html = '';
            filtered.forEach(task => {
                const status = this.getTaskStatus(task);
                html += `
                    <div class="task-item" data-task-id="${task.id}" onclick="TasksPage.selectTask(${task.id})">
                        <div class="task-item-header">
                            <span class="task-name">${this.escapeHtml(task.title || 'Untitled')}</span>
                            <span class="task-status ${status}">${status}</span>
                        </div>
                        <div class="task-desc">${this.escapeHtml(task.content || '').substring(0, 100)}</div>
                    </div>
                `;
            });
            listEl.innerHTML = html;

            const countEl = document.getElementById('task-count');
            if (countEl) countEl.textContent = filtered.length + ' tasks';
        },

        updateStats: function(total, pending, running, completed) {
            if (total === undefined) {
                total = this.tasks.length;
                pending = this.tasks.filter(t => this.getTaskStatus(t) === 'pending').length;
                running = this.tasks.filter(t => this.getTaskStatus(t) === 'running').length;
                const completedCount = this.tasks.filter(t => this.getTaskStatus(t) === 'completed').length;
                completed = completedCount;
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

            const title = nameEl ? nameEl.value.trim() : '';
            const content = descEl ? descEl.value.trim() : '';

            if (!title) {
                this.showNotification('Task name required', 'error');
                return;
            }

            try {
                const response = await fetch(this.config.endpoint, {
                    method: 'POST',
                    headers: this.getHeaders(),
                    body: JSON.stringify({ title: title, content: content })
                });

                if (!response.ok) {
                    let errorMsg = 'Failed to create task: ' + response.status;
                    if (response.status === 401 || response.status === 403) {
                        errorMsg = 'Permission denied. Please log in and try again.';
                    }
                    throw new Error(errorMsg);
                }

                const result = await response.json();

                // Clear form
                if (nameEl) nameEl.value = '';
                if (descEl) descEl.value = '';

                // Reload tasks
                this.loadTasks();
                this.showNotification('Task created!', 'success');
            } catch (error) {
                console.error('Create task error:', error);
                this.showNotification(error.message, 'error');
            }
        },

        selectTask: function(taskId) {
            const task = this.tasks.find(t => t.id == taskId);
            if (!task) return;

            const status = this.getTaskStatus(task);
            const detailCard = document.getElementById('task-detail-card');
            const detailBody = document.getElementById('task-detail-body');

            if (detailCard && detailBody) {
                detailCard.style.display = 'block';
                detailBody.innerHTML = `
                    <p><strong>Title:</strong> ${this.escapeHtml(task.title || 'Untitled')}</p>
                    <p><strong>Status:</strong> ${status}</p>
                    <p><strong>Content:</strong> ${this.escapeHtml(task.content || 'None')}</p>
                    <p><strong>Date:</strong> ${task.date || 'Unknown'}</p>
                    <div style="margin-top: 12px; display: flex; gap: 8px;">
                        <button class="btn btn-danger btn-sm" onclick="TasksPage.deleteTask(${task.id})">Delete Task</button>
                    </div>
                `;
            }
        },

        deleteTask: async function(taskId) {
            if (!confirm('Delete this task?')) return;

            try {
                const response = await fetch(this.config.endpoint + '/' + taskId, {
                    method: 'DELETE',
                    headers: this.getHeaders()
                });

                if (!response.ok) {
                    throw new Error('Failed to delete: ' + response.status);
                }

                const detailCard = document.getElementById('task-detail-card');
                if (detailCard) detailCard.style.display = 'none';
                this.showNotification('Task deleted', 'success');
                this.loadTasks();
            } catch (error) {
                console.error('Delete error:', error);
                this.showNotification('Delete failed: ' + error.message, 'error');
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
