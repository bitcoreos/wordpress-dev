/**
 * BitFlux Avatar Controller
 * Enterprise-grade mascot state management for BITCORE Office Suite
 * @version 1.0.0
 */

(function(window, document) {
    'use strict';

    const BitFluxAvatar = function(containerId, options) {
        this.container = document.getElementById(containerId);
        if (!this.container) {
            console.error('[BitFlux] Container not found:', containerId);
            return;
        }

        // Configuration
        this.config = Object.assign({
            defaultState: 'welcome',
            autoTransition: true,
            transitionDelay: 5000,
            size: 128,
            interactive: true,
            soundEnabled: false,
            debug: false
        }, options || {});

        // State definitions with metadata
        this.states = {
            welcome: {
                label: 'Welcome',
                description: 'Greeting state for new sessions',
                animation: 'gentle-float',
                mood: 'friendly'
            },
            listen: {
                label: 'Listening',
                description: 'Waiting for user input',
                animation: 'attentive-lean',
                mood: 'focused'
            },
            'chat-ready': {
                label: 'Ready',
                description: 'Agent available for chat',
                animation: 'portal-wave',
                mood: 'open'
            },
            'focused-shield': {
                label: 'Protected',
                description: 'Secure mode active',
                animation: 'shield-pulse',
                mood: 'guarded'
            },
            'agent-task': {
                label: 'Working',
                description: 'Processing assigned task',
                animation: 'chase-lights',
                mood: 'busy'
            },
            process: {
                label: 'Thinking',
                description: 'Analyzing data',
                animation: 'dash-rotate',
                mood: 'processing'
            },
            'swarm-guard': {
                label: 'Coordinating',
                description: 'Multi-agent swarm active',
                animation: 'symmetric-pulse',
                mood: 'collaborative'
            },
            'crown-mode': {
                label: 'Admin',
                description: 'Power user mode',
                animation: 'elevated',
                mood: 'authoritative'
            },
            'portal-enter': {
                label: 'Onboarding',
                description: 'New feature or user',
                animation: 'portal-open',
                mood: 'inviting'
            },
            'error-404': {
                label: 'Error',
                description: 'Something went wrong',
                animation: 'shiver-flash',
                mood: 'alert'
            }
        };

        this.currentState = this.config.defaultState;
        this.stateHistory = [];
        this.eventListeners = {};
        this.transitionTimer = null;

        this.init();
    };

    BitFluxAvatar.prototype.init = function() {
        this.log('Initializing BitFlux Avatar');
        this.render();
        this.bindEvents();
        this.setState(this.currentState, { silent: true });

        // Emit ready event
        this.emit('ready', { state: this.currentState });
    };

    BitFluxAvatar.prototype.render = function() {
        // Create SVG container
        const wrapper = document.createElement('div');
        wrapper.className = 'bitflux-avatar-wrapper';
        wrapper.innerHTML = `
            <div class="bitflux-avatar-container" data-state="${this.currentState}">
                <object type="image/svg+xml" 
                        data="${this.getAssetPath('bitflux.svg')}" 
                        class="bitflux-svg"
                        id="bitflux-svg-${this.container.id}"
                        width="${this.config.size}" 
                        height="${this.config.size}">
                </object>
                <div class="bitflux-status-indicator" data-mood="${this.states[this.currentState].mood}"></div>
            </div>
            <div class="bitflux-tooltip">
                <span class="bitflux-label">${this.states[this.currentState].label}</span>
                <span class="bitflux-desc">${this.states[this.currentState].description}</span>
            </div>
        `;

        this.container.appendChild(wrapper);
        this.wrapper = wrapper;
        this.svgContainer = wrapper.querySelector('.bitflux-avatar-container');
        this.tooltip = wrapper.querySelector('.bitflux-tooltip');

        // Load SVG and apply initial state
        this.loadSVG();
    };

    BitFluxAvatar.prototype.loadSVG = function() {
        const obj = this.wrapper.querySelector('object');

        obj.addEventListener('load', () => {
            this.svgDoc = obj.contentDocument;
            if (this.svgDoc) {
                this.applyStateClass(this.currentState);
                this.log('SVG loaded successfully');
            }
        });

        // Fallback for cached SVG
        if (obj.contentDocument && obj.contentDocument.readyState === 'complete') {
            this.svgDoc = obj.contentDocument;
            this.applyStateClass(this.currentState);
        }
    };

    BitFluxAvatar.prototype.getAssetPath = function(filename) {
        // WordPress asset path
        if (window.bitcoreData && window.bitcoreData.assetsUrl) {
            return window.bitcoreData.assetsUrl + '/svg/' + filename;
        }
        return '/wp-content/themes/bitcore-librarian-theme/assets/svg/' + filename;
    };

    BitFluxAvatar.prototype.setState = function(stateName, options) {
        options = options || {};

        if (!this.states[stateName]) {
            console.error('[BitFlux] Unknown state:', stateName);
            return false;
        }

        const previousState = this.currentState;
        this.currentState = stateName;

        // Update history
        this.stateHistory.push({
            state: stateName,
            timestamp: Date.now(),
            trigger: options.trigger || 'manual'
        });

        // Keep history limited
        if (this.stateHistory.length > 50) {
            this.stateHistory.shift();
        }

        // Apply visual state
        this.applyStateClass(stateName);
        this.updateTooltip(stateName);
        this.updateStatusIndicator(stateName);

        // Emit event
        if (!options.silent) {
            this.emit('stateChange', {
                previous: previousState,
                current: stateName,
                state: this.states[stateName]
            });
        }

        this.log('State changed:', previousState, '→', stateName);

        // Auto-transition if enabled
        if (this.config.autoTransition && this.states[stateName].autoTransition) {
            this.scheduleTransition();
        }

        return true;
    };

    BitFluxAvatar.prototype.applyStateClass = function(stateName) {
        if (!this.svgDoc) return;

        const svg = this.svgDoc.getElementById('bitflux-avatar');
        if (!svg) return;

        // Remove all state classes
        Object.keys(this.states).forEach(state => {
            svg.classList.remove('bitflux-active-' + state);
        });

        // Add new state class
        svg.classList.add('bitflux-active-' + stateName);

        // Update container data attribute
        if (this.svgContainer) {
            this.svgContainer.setAttribute('data-state', stateName);
        }
    };

    BitFluxAvatar.prototype.updateTooltip = function(stateName) {
        if (!this.tooltip) return;

        const state = this.states[stateName];
        const label = this.tooltip.querySelector('.bitflux-label');
        const desc = this.tooltip.querySelector('.bitflux-desc');

        if (label) label.textContent = state.label;
        if (desc) desc.textContent = state.description;
    };

    BitFluxAvatar.prototype.updateStatusIndicator = function(stateName) {
        const indicator = this.wrapper.querySelector('.bitflux-status-indicator');
        if (indicator) {
            indicator.setAttribute('data-mood', this.states[stateName].mood);
        }
    };

    BitFluxAvatar.prototype.scheduleTransition = function() {
        if (this.transitionTimer) {
            clearTimeout(this.transitionTimer);
        }

        this.transitionTimer = setTimeout(() => {
            this.emit('autoTransition', { current: this.currentState });
        }, this.config.transitionDelay);
    };

    BitFluxAvatar.prototype.bindEvents = function() {
        if (!this.config.interactive) return;

        // Click handler
        this.svgContainer.addEventListener('click', (e) => {
            e.preventDefault();
            this.emit('click', { state: this.currentState });
            this.showQuickActions();
        });

        // Hover handlers
        this.svgContainer.addEventListener('mouseenter', () => {
            this.tooltip.classList.add('visible');
            this.emit('hover', { state: this.currentState });
        });

        this.svgContainer.addEventListener('mouseleave', () => {
            this.tooltip.classList.remove('visible');
        });

        // Keyboard accessibility
        this.svgContainer.setAttribute('tabindex', '0');
        this.svgContainer.setAttribute('role', 'button');
        this.svgContainer.setAttribute('aria-label', 'BitFlux Avatar - Click for actions');

        this.svgContainer.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.emit('click', { state: this.currentState });
            }
        });
    };

    BitFluxAvatar.prototype.showQuickActions = function() {
        // Create or show quick actions menu
        let menu = this.wrapper.querySelector('.bitflux-quick-actions');

        if (!menu) {
            menu = document.createElement('div');
            menu.className = 'bitflux-quick-actions';
            menu.innerHTML = `
                <button data-action="chat">💬 Chat</button>
                <button data-action="research">🔍 Research</button>
                <button data-action="settings">⚙️ Settings</button>
            `;

            menu.addEventListener('click', (e) => {
                if (e.target.dataset.action) {
                    this.emit('action', { action: e.target.dataset.action });
                    menu.classList.remove('visible');
                }
            });

            this.wrapper.appendChild(menu);
        }

        menu.classList.toggle('visible');
    };

    BitFluxAvatar.prototype.on = function(event, handler) {
        if (!this.eventListeners[event]) {
            this.eventListeners[event] = [];
        }
        this.eventListeners[event].push(handler);
        return this;
    };

    BitFluxAvatar.prototype.emit = function(event, data) {
        this.log('Event:', event, data);

        if (this.eventListeners[event]) {
            this.eventListeners[event].forEach(handler => {
                try {
                    handler.call(this, data);
                } catch (e) {
                    console.error('[BitFlux] Event handler error:', e);
                }
            });
        }

        // Global event
        window.dispatchEvent(new CustomEvent('bitflux:' + event, {
            detail: data,
            bubbles: true
        }));
    };

    BitFluxAvatar.prototype.getState = function() {
        return {
            current: this.currentState,
            history: this.stateHistory,
            metadata: this.states[this.currentState]
        };
    };

    BitFluxAvatar.prototype.getHistory = function() {
        return this.stateHistory;
    };

    BitFluxAvatar.prototype.destroy = function() {
        if (this.transitionTimer) {
            clearTimeout(this.transitionTimer);
        }

        if (this.wrapper && this.wrapper.parentNode) {
            this.wrapper.parentNode.removeChild(this.wrapper);
        }

        this.emit('destroy');
    };

    BitFluxAvatar.prototype.log = function(...args) {
        if (this.config.debug) {
            console.log('[BitFlux]', ...args);
        }
    };

    // Expose to global scope
    window.BitFluxAvatar = BitFluxAvatar;

    // Auto-initialize if data attributes present
    document.addEventListener('DOMContentLoaded', () => {
        const containers = document.querySelectorAll('[data-bitflux-avatar]');
        containers.forEach(container => {
            const options = {
                defaultState: container.dataset.bitfluxState || 'welcome',
                size: parseInt(container.dataset.bitfluxSize) || 128,
                interactive: container.dataset.bitfluxInteractive !== 'false'
            };

            new BitFluxAvatar(container.id, options);
        });
    });

})(window, document);
