/**
 * BITCORE SSE Client
 * EventSource connection manager for real-time updates
 * @version 1.0.0
 */

(function(global) {
    'use strict';

    const SSEClient = {
        /**
         * EventSource instance
         */        connection: null,

        /**
         * Connection URL
         */
        url: null,

        /**
         * Reconnection settings
         */
        reconnectAttempts: 0,
        maxReconnectAttempts: 5,
        reconnectDelay: 1000,
        maxReconnectDelay: 30000,

        /**
         * Connection state
         */
        isConnected: false,
        isConnecting: false,

        /**
         * Event handlers
         */
        handlers: new Map(),

        /**
         * Buffer for missed events
         */
        eventBuffer: [],
        maxBufferSize: 100,

        /**
         * Initialize SSE connection
         * @param {string} streamUrl - SSE endpoint URL
         * @param {Object} options - Configuration options
         */
        init: function(streamUrl, options = {}) {
            this.url = streamUrl;
            this.maxReconnectAttempts = options.maxReconnectAttempts || 5;
            this.reconnectDelay = options.reconnectDelay || 1000;
            this.maxReconnectDelay = options.maxReconnectDelay || 30000;
            this.maxBufferSize = options.maxBufferSize || 100;

            // Set up global handlers
            if (options.handlers) {
                Object.entries(options.handlers).forEach(([event, handler]) => {
                    this.on(event, handler);
                });
            }

            this.connect();
            return this;
        },

        /**
         * Establish SSE connection
         */
        connect: function() {
            if (this.connection || this.isConnecting) {
                return;
            }

            this.isConnecting = true;
            this.trigger('connecting', { url: this.url });

            try {
                this.connection = new EventSource(this.url);

                this.connection.onopen = () => {
                    this.isConnected = true;
                    this.isConnecting = false;
                    this.reconnectAttempts = 0;
                    this.reconnectDelay = 1000;
                    this.trigger('connected', { time: new Date().toISOString() });
                };

                this.connection.onerror = (error) => {
                    this.isConnected = false;
                    this.isConnecting = false;
                    this.trigger('error', { error, reconnectAttempts: this.reconnectAttempts });

                    if (this.connection.readyState === EventSource.CLOSED) {
                        this.handleDisconnect();
                    }
                };

                this.connection.onmessage = (event) => {
                    this.handleMessage(event);
                };

                // Register custom event listeners
                this.handlers.forEach((handler, eventName) => {
                    if (eventName !== 'message' && eventName !== 'connected' && 
                        eventName !== 'disconnected' && eventName !== 'error' &&
                        eventName !== 'connecting') {
                        this.connection.addEventListener(eventName, (e) => {
                            this.handleMessage(e);
                        });
                    }
                });

            } catch (error) {
                this.isConnecting = false;
                this.trigger('error', { error, fatal: true });
            }
        },

        /**
         * Handle incoming message
         */
        handleMessage: function(event) {
            const data = this.parseEvent(event);
            
            // Buffer the event
            this.bufferEvent(data);
            
            // Trigger handlers
            this.trigger(data.type || 'message', data);
        },

        /**
         * Parse SSE event data
         */
        parseEvent: function(event) {
            let data;
            try {
                data = JSON.parse(event.data);
            } catch (e) {
                data = { raw: event.data };
            }

            return {
                type: event.type || 'message',
                data: data,
                id: event.lastEventId || null,
                timestamp: new Date().toISOString()
            };
        },

        /**
         * Buffer event for replay
         */
        bufferEvent: function(event) {
            this.eventBuffer.push(event);
            if (this.eventBuffer.length > this.maxBufferSize) {
                this.eventBuffer.shift();
            }
        },

        /**
         * Handle disconnection with auto-reconnect
         */
        handleDisconnect: function() {
            this.isConnected = false;
            this.connection = null;

            this.trigger('disconnected', { 
                reconnectAttempts: this.reconnectAttempts,
                willReconnect: this.reconnectAttempts < this.maxReconnectAttempts
            });

            if (this.reconnectAttempts < this.maxReconnectAttempts) {
                const delay = Math.min(
                    this.reconnectDelay * Math.pow(2, this.reconnectAttempts),
                    this.maxReconnectDelay
                );

                setTimeout(() => {
                    this.reconnectAttempts++;
                    this.connect();
                }, delay);
            }
        },

        /**
         * Register event handler
         */
        on: function(eventName, handler) {
            if (!this.handlers.has(eventName)) {
                this.handlers.set(eventName, []);
            }
            this.handlers.get(eventName).push(handler);
            return this;
        },

        /**
         * Remove event handler
         */
        off: function(eventName, handler) {
            if (!handler) {
                this.handlers.delete(eventName);
            } else {
                const handlers = this.handlers.get(eventName);
                if (handlers) {
                    const index = handlers.indexOf(handler);
                    if (index > -1) {
                        handlers.splice(index, 1);
                    }
                }
            }
            return this;
        },

        /**
         * Trigger event handlers
         */
        trigger: function(eventName, data) {
            const handlers = this.handlers.get(eventName);
            if (handlers) {
                handlers.forEach(handler => {
                    try {
                        handler(data);
                    } catch (e) {
                        console.error('SSE handler error:', e);
                    }
                });
            }
        },

        /**
         * Close connection
         */
        close: function() {
            if (this.connection) {
                this.connection.close();
                this.connection = null;
                this.isConnected = false;
                this.trigger('disconnected', { manual: true });
            }
        },

        /**
         * Get buffered events
         */
        getBuffer: function() {
            return [...this.eventBuffer];
        },

        /**
         * Clear buffer
         */
        clearBuffer: function() {
            this.eventBuffer = [];
        },

        /**
         * Get connection state
         */
        getState: function() {
            return {
                connected: this.isConnected,
                connecting: this.isConnecting,
                reconnectAttempts: this.reconnectAttempts,
                bufferLength: this.eventBuffer.length
            };
        }
    };

    // Factory function for creating instances
    function createSSEClient(url, options) {
        const client = Object.create(SSEClient);
        client.handlers = new Map();
        client.eventBuffer = [];
        client.reconnectAttempts = 0;
        client.isConnected = false;
        client.isConnecting = false;
        return client.init(url, options);
    }

    // Export
    global.SSEClient = SSEClient;
    global.createSSEClient = createSSEClient;

})(typeof window !== 'undefined' ? window : global);
