/**
 * BITCORE SSE Client
 * 
 * Pattern extracted from mission-control (events.ts broadcasting)
 * Pattern extracted from tenacitOS (SSE streaming)
 * 
 * @package BitCore
 * @since 1.0.0
 * @license MIT
 */

class BitCoreSSE {
    /**
     * Initialize SSE client
     * @param {Object} options - Configuration options
     * @param {string} options.endpoint - SSE endpoint URL
     * @param {number} options.reconnectDelay - Reconnection delay in ms
     * @param {number} options.heartbeatTimeout - Heartbeat timeout in ms
     */
    constructor(options = {}) {
        this.endpoint = options.endpoint || '/wp-json/bitcore/v1/events/stream';
        this.reconnectDelay = options.reconnectDelay || 3000;
        this.heartbeatTimeout = options.heartbeatTimeout || 30000;
        this.eventSource = null;
        this.listeners = new Map();
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 10;
        this.isConnected = false;
        this.lastHeartbeat = null;
        this.heartbeatCheckInterval = null;
    }
    
    /**
     * Connect to SSE stream
     */
    connect() {
        if (this.eventSource) {
            this.disconnect();
        }
        
        try {
            this.eventSource = new EventSource(this.endpoint);
            
            this.eventSource.onopen = () => {
                console.log('[BitCoreSSE] Connected');
                this.isConnected = true;
                this.reconnectAttempts = 0;
                this.lastHeartbeat = Date.now();
                this.startHeartbeatCheck();
                this.emit('_connected', { timestamp: new Date().toISOString() });
            };
            
            this.eventSource.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    this.handleEvent(data.type, data.payload);
                } catch (e) {
                    console.warn('[BitCoreSSE] Parse error:', e);
                }
            };
            
            this.eventSource.onerror = (error) => {
                console.error('[BitCoreSSE] Error:', error);
                this.isConnected = false;
                this.emit('_disconnected', { error });
                this.scheduleReconnect();
            };
            
        } catch (error) {
            console.error('[BitCoreSSE] Connection error:', error);
            this.scheduleReconnect();
        }
    }
    
    /**
     * Disconnect from SSE stream
     */
    disconnect() {
        if (this.heartbeatCheckInterval) {
            clearInterval(this.heartbeatCheckInterval);
        }
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }
        this.isConnected = false;
        console.log('[BitCoreSSE] Disconnected');
    }
    
    /**
     * Schedule reconnection attempt
     */
    scheduleReconnect() {
        if (this.reconnectAttempts >= this.maxReconnectAttempts) {
            console.error('[BitCoreSSE] Max reconnection attempts reached');
            this.emit('_max_reconnect_failed', { attempts: this.reconnectAttempts });
            return;
        }
        
        this.reconnectAttempts++;
        const delay = this.reconnectDelay * Math.pow(1.5, this.reconnectAttempts - 1);
        
        console.log(`[BitCoreSSE] Reconnecting in ${delay}ms (attempt ${this.reconnectAttempts})`);
        
        setTimeout(() => this.connect(), delay);
    }
    
    /**
     * Start heartbeat check
     */
    startHeartbeatCheck() {
        this.heartbeatCheckInterval = setInterval(() => {
            if (this.lastHeartbeat && Date.now() - this.lastHeartbeat > this.heartbeatTimeout) {
                console.warn('[BitCoreSSE] Heartbeat timeout, reconnecting');
                this.disconnect();
                this.connect();
            }
        }, 10000);
    }
    
    /**
     * Handle incoming event
     * @param {string} type - Event type
     * @param {Object} payload - Event payload
     */
    handleEvent(type, payload) {
        this.lastHeartbeat = Date.now();
        
        // Update heartbeat for connection events
        if (type === 'connected') {
            this.clientId = payload.client_id;
            return;
        }
        
        // Emit to listeners
        this.emit(type, payload);
    }
    
    /**
     * Subscribe to event type
     * @param {string} eventType - Event type to subscribe to
     * @param {Function} callback - Callback function
     * @returns {Function} Unsubscribe function
     */
    on(eventType, callback) {
        if (!this.listeners.has(eventType)) {
            this.listeners.set(eventType, []);
        }
        this.listeners.get(eventType).push(callback);
        
        // Return unsubscribe function
        return () => {
            const callbacks = this.listeners.get(eventType);
            const index = callbacks.indexOf(callback);
            if (index > -1) {
                callbacks.splice(index, 1);
            }
        };
    }
    
    /**
     * Subscribe to event type (once)
     * @param {string} eventType - Event type
     * @param {Function} callback - Callback function
     */
    once(eventType, callback) {
        const wrapper = (payload) => {
            callback(payload);
            this.off(eventType, wrapper);
        };
        this.on(eventType, wrapper);
    }
    
    /**
     * Unsubscribe from event type
     * @param {string} eventType - Event type
     * @param {Function} callback - Callback to remove
     */
    off(eventType, callback) {
        if (!this.listeners.has(eventType)) return;
        
        if (callback) {
            const callbacks = this.listeners.get(eventType);
            const index = callbacks.indexOf(callback);
            if (index > -1) {
                callbacks.splice(index, 1);
            }
        } else {
            this.listeners.delete(eventType);
        }
    }
    
    /**
     * Emit event to listeners
     * @param {string} eventType - Event type
     * @param {Object} payload - Event payload
     */
    emit(eventType, payload) {
        const callbacks = this.listeners.get(eventType) || [];
        callbacks.forEach(callback => {
            try {
                callback(payload);
            } catch (e) {
                console.error(`[BitCoreSSE] Callback error for ${eventType}:`, e);
            }
        });
    }
    
    /**
     * Get connection status
     * @returns {Object} Connection status
     */
    getStatus() {
        return {
            isConnected: this.isConnected,
            clientId: this.clientId,
            reconnectAttempts: this.reconnectAttempts,
            lastHeartbeat: this.lastHeartbeat,
            listenerCount: this.listeners.size
        };
    }
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = BitCoreSSE;
}

// Make globally available
window.BitCoreSSE = BitCoreSSE;
