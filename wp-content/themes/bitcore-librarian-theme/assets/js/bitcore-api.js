/**
 * BITCORE API Client
 * Unified REST API wrapper for WordPress integration
 * @version 1.0.0
 */

(function(global) {
    'use strict';

    const BitcoreAPI = {
        /**
         * Base REST URL - will be set from WordPress localization
         */
        baseUrl: null,

        /**
         * Nonce for WordPress REST API authentication
         */
        nonce: null,

        /**
         * Default headers
         */
        defaultHeaders: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },

        /**
         * Initialize the API client
         * @param {Object} config - Configuration options
         */
        init: function(config) {
            this.baseUrl = config.restUrl || '/wp-json/bitcore/v1/';
            this.nonce = config.nonce || null;
            
            if (this.nonce) {
                this.defaultHeaders['X-WP-Nonce'] = this.nonce;
            }

            return this;
        },

        /**
         * Make an API request
         * @param {string} endpoint - API endpoint (without base URL)
         * @param {Object} options - Fetch options
         * @returns {Promise} - Response promise
         */
        request: async function(endpoint, options = {}) {
            const url = this.baseUrl + endpoint.replace(/^\//, '');
            
            const config = {
                ...options,
                headers: {
                    ...this.defaultHeaders,
                    ...(options.headers || {})
                }
            };

            if (options.body && typeof options.body === 'object') {
                config.body = JSON.stringify(options.body);
            }

            try {
                const response = await fetch(url, config);
                
                if (!response.ok) {
                    const error = await response.json().catch(() => ({}));
                    throw new APIError(response.status, error.message || 'Request failed', error);
                }

                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return await response.json();
                }
                
                return await response.text();
            } catch (error) {
                if (error instanceof APIError) {
                    throw error;
                }
                throw new APIError(0, error.message, error);
            }
        },

        /**
         * GET request
         */
        get: function(endpoint, params = {}) {
            const queryString = new URLSearchParams(params).toString();
            const url = queryString ? `${endpoint}?${queryString}` : endpoint;
            return this.request(url, { method: 'GET' });
        },

        /**
         * POST request
         */
        post: function(endpoint, data = {}) {
            return this.request(endpoint, {
                method: 'POST',
                body: data
            });
        },

        /**
         * PUT request
         */
        put: function(endpoint, data = {}) {
            return this.request(endpoint, {
                method: 'PUT',
                body: data
            });
        },

        /**
         * DELETE request
         */
        delete: function(endpoint) {
            return this.request(endpoint, { method: 'DELETE' });
        },

        // ============================================
        // Command Center API
        // ============================================
        commandCenter: {
            getStats: function() {
                return BitcoreAPI.get('command-center/stats');
            },

            getAgents: function() {
                return BitcoreAPI.get('command-center/agents');
            },

            getWorkflows: function() {
                return BitcoreAPI.get('command-center/workflows');
            },

            dispatchAgent: function(type, config = {}) {
                return BitcoreAPI.post('agents/dispatch', { type, config });
            },

            runWorkflow: function(workflowId) {
                return BitcoreAPI.post(`workflows/${workflowId}/run`);
            }
        },

        // ============================================
        // MCP Tools API
        // ============================================
        mcp: {
            getTools: function() {
                return BitcoreAPI.get('mcp/tools');
            },

            getTool: function(toolId) {
                return BitcoreAPI.get(`mcp/tools/${toolId}`);
            },

            callTool: function(toolId, params = {}) {
                return BitcoreAPI.post('mcp/call', {
                    tool_id: toolId,
                    parameters: params
                });
            }
        },

        // ============================================
        // Events API
        // ============================================
        events: {
            getEvents: function(params = {}) {
                return BitcoreAPI.get('events', params);
            },

            subscribe: function(channels = []) {
                return BitcoreAPI.post('events/subscribe', { channels });
            }
        },

        // ============================================
        // Webhooks API
        // ============================================
        webhooks: {
            list: function() {
                return BitcoreAPI.get('webhooks');
            },

            get: function(id) {
                return BitcoreAPI.get(`webhooks/${id}`);
            },

            create: function(data) {
                return BitcoreAPI.post('webhooks', data);
            },

            update: function(id, data) {
                return BitcoreAPI.put(`webhooks/${id}`, data);
            },

            delete: function(id) {
                return BitcoreAPI.delete(`webhooks/${id}`);
            },

            test: function(id) {
                return BitcoreAPI.post(`webhooks/${id}/test`);
            },

            getLogs: function(id) {
                return BitcoreAPI.get(`webhooks/${id}/logs`);
            }
        },

        // ============================================
        // Knowledge Graph API
        // ============================================
        graph: {
            getNodes: function(params = {}) {
                return BitcoreAPI.get('graph/nodes', params);
            },

            getNode: function(id) {
                return BitcoreAPI.get(`graph/nodes/${id}`);
            },

            createNode: function(data) {
                return BitcoreAPI.post('graph/nodes', data);
            },

            updateNode: function(id, data) {
                return BitcoreAPI.put(`graph/nodes/${id}`, data);
            },

            deleteNode: function(id) {
                return BitcoreAPI.delete(`graph/nodes/${id}`);
            },

            getEdges: function(params = {}) {
                return BitcoreAPI.get('graph/edges', params);
            },

            createEdge: function(data) {
                return BitcoreAPI.post('graph/edges', data);
            },

            deleteEdge: function(id) {
                return BitcoreAPI.delete(`graph/edges/${id}`);
            },

            search: function(query) {
                return BitcoreAPI.get('graph/search', { q: query });
            }
        },

        // ============================================
        // RAG Workspaces API
        // ============================================
        rag: {
            getWorkspaces: function() {
                return BitcoreAPI.get('rag/workspaces');
            },

            getWorkspace: function(id) {
                return BitcoreAPI.get(`rag/workspaces/${id}`);
            },

            createWorkspace: function(data) {
                return BitcoreAPI.post('rag/workspaces', data);
            },

            deleteWorkspace: function(id) {
                return BitcoreAPI.delete(`rag/workspaces/${id}`);
            },

            getDocuments: function(workspaceId) {
                return BitcoreAPI.get(`rag/workspaces/${workspaceId}/documents`);
            },

            addDocument: function(workspaceId, document) {
                return BitcoreAPI.post(`rag/workspaces/${workspaceId}/documents`, document);
            },

            query: function(workspaceId, query, options = {}) {
                return BitcoreAPI.post(`rag/workspaces/${workspaceId}/query`, {
                    query,
                    ...options
                });
            }
        },

        // ============================================
        // Identities API
        // ============================================
        identities: {
            list: function() {
                return BitcoreAPI.get('identities');
            },

            get: function(id) {
                return BitcoreAPI.get(`identities/${id}`);
            },

            create: function(data) {
                return BitcoreAPI.post('identities', data);
            },

            update: function(id, data) {
                return BitcoreAPI.put(`identities/${id}`, data);
            },

            delete: function(id) {
                return BitcoreAPI.delete(`identities/${id}`);
            },

            ping: function(id) {
                return BitcoreAPI.post(`identities/${id}/ping`);
            }
        },

        // ============================================
        // Tasks API
        // ============================================
        tasks: {
            list: function(params = {}) {
                return BitcoreAPI.get('tasks', params);
            },

            get: function(id) {
                return BitcoreAPI.get(`tasks/${id}`);
            },

            create: function(data) {
                return BitcoreAPI.post('tasks', data);
            },

            update: function(id, data) {
                return BitcoreAPI.put(`tasks/${id}`, data);
            },

            delete: function(id) {
                return BitcoreAPI.delete(`tasks/${id}`);
            },

            dispatch: function(id) {
                return BitcoreAPI.post(`tasks/${id}/dispatch`);
            },

            cancel: function(id) {
                return BitcoreAPI.post(`tasks/${id}/cancel`);
            }
        },

        // ============================================
        // System API
        // ============================================
        system: {
            getHealth: function() {
                return BitcoreAPI.get('health');
            },

            getStatus: function() {
                return BitcoreAPI.get('status');
            },

            getQueue: function() {
                return BitcoreAPI.get('queue');
            },

            runAction: function(action) {
                return BitcoreAPI.post(`actions/${action}`);
            }
        }
    };

    /**
     * Custom API Error class
     */
    class APIError extends Error {
        constructor(status, message, data = {}) {
            super(message);
            this.name = 'APIError';
            this.status = status;
            this.data = data;
        }
    }

    BitcoreAPI.APIError = APIError;

    // Export to global scope
    global.BitcoreAPI = BitcoreAPI;

})(typeof window !== 'undefined' ? window : global);
