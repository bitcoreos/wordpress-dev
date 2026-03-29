/**
 * BITCORE Knowledge Graph Page Controller
 * Interactive graph visualization for knowledge nodes and edges
 * @version 1.0.0
 */

(function(global) {
    'use strict';

    const KnowledgeGraphPage = {
        config: {
            nodesEndpoint: '/wp-json/bitcore/v1/knowledge-graph/nodes',
            edgesEndpoint: '/wp-json/bitcore/v1/knowledge-graph/edges',
            searchEndpoint: '/wp-json/bitcore/v1/knowledge-graph/search',
            nonce: null,
            container: '#graph-container',
            canvas: '#graph-canvas'
        },

        nodes: [],
        edges: [],
        selectedNode: null,

        init: function(config = {}) {
            this.config = { ...this.config, ...config };
            if (typeof bitcoreData !== 'undefined') {
                this.config.nonce = bitcoreData.nonce;
            }
            this.bindEvents();
            this.loadGraph();
        },

        bindEvents: function() {
            const searchInput = document.getElementById('graph-search');
            if (searchInput) {
                searchInput.addEventListener('input', (e) => this.search(e.target.value));
            }

            const addNodeBtn = document.getElementById('add-node-btn');
            if (addNodeBtn) {
                addNodeBtn.addEventListener('click', () => this.showAddNodeModal());
            }
        },

        loadGraph: async function() {
            try {
                this.showLoading(true);
                const [nodes, edges] = await Promise.all([
                    this.request('GET', this.config.nodesEndpoint),
                    this.request('GET', this.config.edgesEndpoint)
                ]);
                this.nodes = nodes || [];
                this.edges = edges || [];
                this.render();
            } catch (error) {
                console.error('Failed to load graph:', error);
                this.showError('Failed to load knowledge graph');
            } finally {
                this.showLoading(false);
            }
        },

        search: async function(query) {
            if (!query || query.length < 2) {
                this.render();
                return;
            }
            try {
                const results = await this.request('GET', `${this.config.searchEndpoint}?q=${encodeURIComponent(query)}`);
                this.renderSearchResults(results);
            } catch (error) {
                console.error('Search failed:', error);
            }
        },

        addNode: async function(nodeData) {
            try {
                const node = await this.request('POST', this.config.nodesEndpoint, nodeData);
                this.nodes.push(node);
                this.render();
                this.showSuccess('Node added');
            } catch (error) {
                this.showError('Failed to add node');
            }
        },

        render: function() {
            const container = document.querySelector(this.config.container);
            if (!container) return;

            if (this.nodes.length === 0) {
                container.innerHTML = '<div class="empty-state"><p>No nodes in the knowledge graph. Add your first node to get started.</p></div>';
                return;
            }

            // Simple list rendering - could be enhanced with D3.js or similar
            container.innerHTML = `
                <div class="graph-stats">
                    <span class="node-count">${this.nodes.length} nodes</span>
                    <span class="edge-count">${this.edges.length} connections</span>
                </div>
                <div class="graph-viewport">
                    ${this.nodes.map(node => `
                        <div class="graph-node" data-node-id="${node.id}" style="left: ${node.x || Math.random() * 80}%; top: ${node.y || Math.random() * 80}%">
                            <span class="node-label">${this.escapeHtml(node.label || node.id)}</span>
                            <span class="node-type">${node.type || 'concept'}</span>
                        </div>
                    `).join('')}
                </div>
            `;
        },

        renderSearchResults: function(results) {
            const container = document.querySelector(this.config.container);
            if (!container) return;
            container.innerHTML = `
                <div class="search-results">
                    <h3>Search Results (${results.length})</h3>
                    ${results.map(node => `
                        <div class="graph-node search-result" data-node-id="${node.id}">
                            <span class="node-label">${this.escapeHtml(node.label || node.id)}</span>
                        </div>
                    `).join('')}
                </div>
            `;
        },

        showAddNodeModal: function() {
            const modal = document.getElementById('node-modal');
            if (modal) modal.classList.add('active');
        },

        request: async function(method, url, data = null) {
            const options = {
                method: method,
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }
            };
            if (this.config.nonce) options.headers['X-WP-Nonce'] = this.config.nonce;
            if (data && method !== 'GET') options.body = JSON.stringify(data);
            const response = await fetch(url, options);
            if (!response.ok) throw new Error('Request failed');
            return await response.json();
        },

        showLoading: function(show) {
            const container = document.querySelector(this.config.container);
            if (container) container.classList.toggle('loading', show);
        },
        showError: function(msg) { this.showNotification(msg, 'error'); },
        showSuccess: function(msg) { this.showNotification(msg, 'success'); },
        showNotification: function(message, type) {
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

    document.addEventListener('DOMContentLoaded', () => KnowledgeGraphPage.init());
    global.KnowledgeGraphPage = KnowledgeGraphPage;
})(typeof window !== 'undefined' ? window : global);
