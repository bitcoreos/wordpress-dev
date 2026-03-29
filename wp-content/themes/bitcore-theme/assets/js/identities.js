/**
 * BITCORE Identities Page Controller
 * Manages agent identities (Coraline, Hyperion, Hermes, etc.)
 * @version 1.0.0
 */

(function(global) {
    'use strict';

    const IdentitiesPage = {
        config: {
            endpoint: '/wp-json/bitcore/v1/identities',
            nonce: null,
            container: '#identities-container',
            form: '#identity-form',
            list: '#identity-grid'
        },

        identities: [],

        init: function(config = {}) {
            this.config = { ...this.config, ...config };
            
            if (typeof bitcoreData !== 'undefined') {
                this.config.nonce = bitcoreData.nonce;
            }

            this.bindEvents();
            this.loadIdentities();
        },

        bindEvents: function() {
            const form = document.querySelector(this.config.form);
            if (form) {
                form.addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.createIdentity();
                });
            }

            const list = document.querySelector(this.config.list);
            if (list) {
                list.addEventListener('click', (e) => {
                    const target = e.target.closest('[data-action]');
                    if (target) {
                        const action = target.dataset.action;
                        const identityId = target.closest('[data-identity-id]')?.dataset.identityId;
                        if (action && identityId) {
                            this.handleAction(action, identityId);
                        }
                    }
                });
            }
        },

        loadIdentities: async function() {
            try {
                this.showLoading(true);
                const response = await this.request('GET', this.config.endpoint);
                this.identities = response || [];
                this.render();
            } catch (error) {
                console.error('Failed to load identities:', error);
                this.showError('Failed to load identities');
            } finally {
                this.showLoading(false);
            }
        },

        createIdentity: async function() {
            const identity = {
                name: document.getElementById('identity-name')?.value || '',
                type: document.getElementById('identity-type')?.value || 'agent',
                description: document.getElementById('identity-description')?.value || '',
                capabilities: document.getElementById('identity-capabilities')?.value?.split(',').map(c => c.trim()) || []
            };

            if (!identity.name) {
                this.showError('Identity name is required');
                return;
            }

            try {
                const response = await this.request('POST', this.config.endpoint, identity);
                this.identities.unshift(response);
                this.render();
                document.querySelector(this.config.form)?.reset();
                this.showSuccess('Identity created');
            } catch (error) {
                console.error('Failed to create identity:', error);
                this.showError('Failed to create identity');
            }
        },

        handleAction: async function(action, identityId) {
            switch (action) {
                case 'ping':
                    await this.pingIdentity(identityId);
                    break;
                case 'edit':
                    this.editIdentity(identityId);
                    break;
                case 'delete':
                    await this.deleteIdentity(identityId);
                    break;
            }
        },

        pingIdentity: async function(identityId) {
            const identity = this.identities.find(i => i.id == identityId);
            if (!identity) return;

            try {
                const response = await this.request('POST', `${this.config.endpoint}/${identityId}/ping`);
                this.showSuccess(`${identity.name} responded: ${response.status || 'OK'}`);
            } catch (error) {
                this.showError(`${identity.name} did not respond`);
            }
        },

        editIdentity: function(identityId) {
            const identity = this.identities.find(i => i.id == identityId);
            if (!identity) return;
            // Open edit modal or navigate to edit page
            window.location.href = `/identities/${identityId}/edit/`;
        },

        deleteIdentity: async function(identityId) {
            if (!confirm('Delete this identity?')) return;
            
            try {
                await this.request('DELETE', `${this.config.endpoint}/${identityId}`);
                this.identities = this.identities.filter(i => i.id != identityId);
                this.render();
                this.showSuccess('Identity deleted');
            } catch (error) {
                this.showError('Failed to delete identity');
            }
        },

        render: function() {
            const container = document.querySelector(this.config.list);
            if (!container) return;

            if (this.identities.length === 0) {
                container.innerHTML = '<div class="empty-state"><p>No identities registered. Add your first agent identity.</p></div>';
                return;
            }

            container.innerHTML = this.identities.map(identity => `
                <div class="identity-card" data-identity-id="${identity.id}">
                    <div class="identity-avatar">
                        <span class="avatar-icon">${this.getAvatarIcon(identity.type)}</span>
                    </div>
                    <div class="identity-info">
                        <h3 class="identity-name">${this.escapeHtml(identity.name)}</h3>
                        <span class="identity-type">${identity.type}</span>
                        ${identity.description ? `<p class="identity-description">${this.escapeHtml(identity.description)}</p>` : ''}
                        <div class="identity-status status-${identity.status || 'unknown'}">${identity.status || 'unknown'}</div>
                    </div>
                    <div class="identity-actions">
                        <button class="btn btn-sm" data-action="ping" title="Ping">Ping</button>
                        <button class="btn btn-sm btn-secondary" data-action="edit">Edit</button>
                        <button class="btn btn-sm btn-danger" data-action="delete">Delete</button>
                    </div>
                </div>
            `).join('');
        },

        getAvatarIcon: function(type) {
            const icons = {
                'agent': '🤖',
                'orchestrator': '🧠',
                'executor': '⚙️',
                'guardian': '🛡️',
                'liaison': '🔗',
                'default': '◉'
            };
            return icons[type] || icons.default;
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
        IdentitiesPage.init();
    });

    global.IdentitiesPage = IdentitiesPage;

})(typeof window !== 'undefined' ? window : global);
