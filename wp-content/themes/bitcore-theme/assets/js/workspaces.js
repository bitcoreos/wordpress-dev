/**
 * BITCORE Workspaces Page Controller
 * Handles workspace creation, listing, and RAG document management
 * @version 1.0.0
 */

(function(global) {
    'use strict';

    const WorkspacesPage = {
        config: {
            endpoint: '/wp-json/bitcore/v1/workspaces',
            nonce: null,
            container: '#workspaces-container',
            form: '#workspace-form',
            list: '#workspace-list',
            modal: '#workspace-modal'
        },

        workspaces: [],
        currentWorkspace: null,

        init: function(config = {}) {
            this.config = { ...this.config, ...config };
            
            if (typeof bitcoreData !== 'undefined') {
                this.config.nonce = bitcoreData.nonce;
            }

            this.bindEvents();
            this.loadWorkspaces();
        },

        bindEvents: function() {
            // Create workspace form
            const form = document.querySelector(this.config.form);
            if (form) {
                form.addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.createWorkspace();
                });
            }

            // Open modal button
            const openModalBtn = document.querySelector('[data-action="open-workspace-modal"]');
            if (openModalBtn) {
                openModalBtn.addEventListener('click', () => this.openModal());
            }

            // Close modal
            const closeModalBtn = document.querySelector('[data-action="close-modal"]');
            if (closeModalBtn) {
                closeModalBtn.addEventListener('click', () => this.closeModal());
            }

            // Delegate workspace actions
            const list = document.querySelector(this.config.list);
            if (list) {
                list.addEventListener('click', (e) => {
                    const target = e.target.closest('[data-action]');
                    if (target) {
                        const action = target.dataset.action;
                        const workspaceId = target.closest('[data-workspace-id]')?.dataset.workspaceId;
                        if (action && workspaceId) {
                            this.handleAction(action, workspaceId);
                        }
                    }
                });
            }
        },

        loadWorkspaces: async function() {
            try {
                this.showLoading(true);
                const response = await this.request('GET', this.config.endpoint);
                this.workspaces = response || [];
                this.render();
            } catch (error) {
                console.error('Failed to load workspaces:', error);
                this.showError('Failed to load workspaces');
            } finally {
                this.showLoading(false);
            }
        },

        createWorkspace: async function() {
            const nameInput = document.getElementById('workspace-name');
            const descInput = document.getElementById('workspace-description');
            
            const workspace = {
                name: nameInput?.value || '',
                description: descInput?.value || ''
            };

            if (!workspace.name) {
                this.showError('Workspace name is required');
                return;
            }

            try {
                const response = await this.request('POST', this.config.endpoint, workspace);
                this.workspaces.unshift(response);
                this.render();
                this.closeModal();
                document.querySelector(this.config.form)?.reset();
                this.showSuccess('Workspace created');
            } catch (error) {
                console.error('Failed to create workspace:', error);
                this.showError('Failed to create workspace');
            }
        },

        handleAction: async function(action, workspaceId) {
            switch (action) {
                case 'view':
                    this.viewWorkspace(workspaceId);
                    break;
                case 'archive':
                    await this.archiveWorkspace(workspaceId);
                    break;
                case 'delete':
                    await this.deleteWorkspace(workspaceId);
                    break;
            }
        },

        viewWorkspace: function(workspaceId) {
            const workspace = this.workspaces.find(w => w.id == workspaceId);
            if (!workspace) return;
            
            this.currentWorkspace = workspace;
            // Navigate to workspace detail or open detail panel
            window.location.href = `/workspaces/${workspaceId}/`;
        },

        archiveWorkspace: async function(workspaceId) {
            try {
                await this.request('POST', `${this.config.endpoint}/${workspaceId}/archive`);
                this.showSuccess('Workspace archived');
                this.loadWorkspaces();
            } catch (error) {
                this.showError('Failed to archive workspace');
            }
        },

        deleteWorkspace: async function(workspaceId) {
            if (!confirm('Delete this workspace? All documents will be lost.')) return;
            
            try {
                await this.request('DELETE', `${this.config.endpoint}/${workspaceId}`);
                this.workspaces = this.workspaces.filter(w => w.id != workspaceId);
                this.render();
                this.showSuccess('Workspace deleted');
            } catch (error) {
                this.showError('Failed to delete workspace');
            }
        },

        render: function() {
            const container = document.querySelector(this.config.list);
            if (!container) return;

            if (this.workspaces.length === 0) {
                container.innerHTML = '<div class="empty-state"><p>No workspaces yet. Create your first workspace.</p></div>';
                return;
            }

            container.innerHTML = this.workspaces.map(ws => `
                <div class="workspace-card" data-workspace-id="${ws.id}">
                    <div class="workspace-header">
                        <h3 class="workspace-name">${this.escapeHtml(ws.name)}</h3>
                        <span class="workspace-status status-${ws.status || 'active'}">${ws.status || 'active'}</span>
                    </div>
                    ${ws.description ? `<p class="workspace-description">${this.escapeHtml(ws.description)}</p>` : ''}
                    <div class="workspace-meta">
                        <span class="document-count">${ws.document_count || 0} documents</span>
                        <span class="updated-at">Updated: ${ws.updated_at || 'N/A'}</span>
                    </div>
                    <div class="workspace-actions">
                        <button class="btn btn-sm" data-action="view">View</button>
                        <button class="btn btn-sm btn-secondary" data-action="archive">Archive</button>
                        <button class="btn btn-sm btn-danger" data-action="delete">Delete</button>
                    </div>
                </div>
            `).join('');
        },

        openModal: function() {
            const modal = document.querySelector(this.config.modal);
            if (modal) modal.classList.add('active');
        },

        closeModal: function() {
            const modal = document.querySelector(this.config.modal);
            if (modal) modal.classList.remove('active');
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
            if (container) container.classList.toggle('loading', show);
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

    document.addEventListener('DOMContentLoaded', () => {
        WorkspacesPage.init();
    });

    global.WorkspacesPage = WorkspacesPage;

})(typeof window !== 'undefined' ? window : global);
