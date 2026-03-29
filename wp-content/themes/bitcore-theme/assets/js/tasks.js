/**
 * BITCORE Tasks Page Controller
 * Handles task creation, listing, and management
 * @version 1.0.0
 */

(function(global) {
    'use strict';

    const TasksPage = {
        config: {
            endpoint: '/wp-json/bitcore/v1/tasks',
            nonce: null,
            container: '#tasks-container',
            form: '#task-form',
            list: '#task-list'
        },

        tasks: [],

        init: function(config = {}) {
            this.config = { ...this.config, ...config };
            
            // Get nonce from WordPress localization if available
            if (typeof bitcoreData !== 'undefined') {
                this.config.nonce = bitcoreData.nonce;
            }

            this.bindEvents();
            this.loadTasks();
        },

        bindEvents: function() {
            const form = document.querySelector(this.config.form);
            if (form) {
                form.addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.createTask();
                });
            }

            // Delegate clicks for task actions
            const list = document.querySelector(this.config.list);
            if (list) {
                list.addEventListener('click', (e) => {
                    const target = e.target.closest('[data-action]');
                    if (target) {
                        const action = target.dataset.action;
                        const taskId = target.closest('[data-task-id]')?.dataset.taskId;
                        if (action && taskId) {
                            this.handleAction(action, taskId);
                        }
                    }
                });
            }
        },

        loadTasks: async function() {
            try {
                this.showLoading(true);
                const response = await this.request('GET', this.config.endpoint);
                this.tasks = response || [];
                this.render();
            } catch (error) {
                console.error('Failed to load tasks:', error);
                this.showError('Failed to load tasks. Please try again.');
            } finally {
                this.showLoading(false);
            }
        },

        createTask: async function() {
            const form = document.querySelector(this.config.form);
            const formData = new FormData(form);
            
            const task = {
                title: formData.get('title') || document.getElementById('new-task-name')?.value,
                description: formData.get('description') || document.getElementById('new-task-desc')?.value,
                priority: formData.get('priority') || document.getElementById('new-task-priority')?.value || 'normal'
            };

            if (!task.title) {
                this.showError('Task name is required');
                return;
            }

            try {
                const response = await this.request('POST', this.config.endpoint, task);
                this.tasks.unshift(response);
                this.render();
                form.reset();
                this.showSuccess('Task created successfully');
            } catch (error) {
                console.error('Failed to create task:', error);
                this.showError('Failed to create task. Please try again.');
            }
        },

        handleAction: async function(action, taskId) {
            const task = this.tasks.find(t => t.id == taskId);
            if (!task) return;

            switch (action) {
                case 'dispatch':
                    await this.dispatchTask(taskId);
                    break;
                case 'cancel':
                    await this.cancelTask(taskId);
                    break;
                case 'delete':
                    await this.deleteTask(taskId);
                    break;
            }
        },

        dispatchTask: async function(taskId) {
            try {
                await this.request('POST', `${this.config.endpoint}/${taskId}/dispatch`);
                this.showSuccess('Task dispatched');
                this.loadTasks();
            } catch (error) {
                this.showError('Failed to dispatch task');
            }
        },

        cancelTask: async function(taskId) {
            try {
                await this.request('POST', `${this.config.endpoint}/${taskId}/cancel`);
                this.showSuccess('Task cancelled');
                this.loadTasks();
            } catch (error) {
                this.showError('Failed to cancel task');
            }
        },

        deleteTask: async function(taskId) {
            if (!confirm('Are you sure you want to delete this task?')) return;
            
            try {
                await this.request('DELETE', `${this.config.endpoint}/${taskId}`);
                this.tasks = this.tasks.filter(t => t.id != taskId);
                this.render();
                this.showSuccess('Task deleted');
            } catch (error) {
                this.showError('Failed to delete task');
            }
        },

        render: function() {
            const container = document.querySelector(this.config.list);
            if (!container) return;

            if (this.tasks.length === 0) {
                container.innerHTML = '<div class="empty-state"><p>No tasks yet. Create your first task above.</p></div>';
                return;
            }

            container.innerHTML = this.tasks.map(task => `
                <div class="task-item" data-task-id="${task.id}">
                    <div class="task-header">
                        <span class="task-title">${this.escapeHtml(task.title)}</span>
                        <span class="task-priority priority-${task.priority || 'normal'}">${task.priority || 'normal'}</span>
                        <span class="task-status status-${task.status || 'pending'}">${task.status || 'pending'}</span>
                    </div>
                    ${task.description ? `<p class="task-description">${this.escapeHtml(task.description)}</p>` : ''}
                    <div class="task-actions">
                        <button class="btn btn-sm" data-action="dispatch">Dispatch</button>
                        <button class="btn btn-sm btn-secondary" data-action="cancel">Cancel</button>
                        <button class="btn btn-sm btn-danger" data-action="delete">Delete</button>
                    </div>
                </div>
            `).join('');
        },

        request: async function(method, url, data = null) {
            const options = {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            };

            if (this.config.nonce) {
                options.headers['X-WP-Nonce'] = this.config.nonce;
            }

            if (data && method !== 'GET') {
                options.body = JSON.stringify(data);
            }

            const response = await fetch(url, options);
            
            if (!response.ok) {
                const error = await response.json().catch(() => ({}));
                throw new Error(error.message || 'Request failed');
            }

            return await response.json();
        },

        showLoading: function(show) {
            const container = document.querySelector(this.config.container);
            if (container) {
                container.classList.toggle('loading', show);
            }
        },

        showError: function(message) {
            this.showNotification(message, 'error');
        },

        showSuccess: function(message) {
            this.showNotification(message, 'success');
        },

        showNotification: function(message, type = 'info') {
            const existing = document.querySelector('.notification');
            if (existing) existing.remove();

            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.textContent = message;
            document.body.appendChild(notification);

            setTimeout(() => notification.remove(), 5000);
        },

        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Auto-initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', () => {
        TasksPage.init();
    });

    global.TasksPage = TasksPage;

})(typeof window !== 'undefined' ? window : global);
