/**
 * BITCORE Dashboard - Live Stats & System Health
 * @package BITCORE_Theme
 * @version 2.0.0
 */

(function() {
    'use strict';

    var Dashboard = {

        config: {
            dashboardEndpoint: '/wp-json/bitcore/v1/dashboard',
            healthEndpoint: '/wp-json/bitcore/v1/health',
            pingEndpoint: '/wp-json/bitcore/v1/ping',
            refreshInterval: 30000
        },

        state: {
            data: null,
            loading: false,
            error: null,
            lastUpdate: null
        },

        init: function() {
            if (!document.querySelector('.dash-container')) return;

            this.loadDashboard();
            this.bindEvents();
            this.initAutoRefresh();
        },

        bindEvents: function() {
            var self = this;

            // Refresh button
            var refreshBtn = document.getElementById('refresh-btn');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', function() {
                    refreshBtn.disabled = true;
                    refreshBtn.textContent = '⟳ Loading...';
                    self.loadDashboard().then(function() {
                        refreshBtn.disabled = false;
                        refreshBtn.textContent = '⟳ Refresh';
                    }).catch(function() {
                        refreshBtn.disabled = false;
                        refreshBtn.textContent = '⟳ Refresh';
                    });
                });
            }

            // Clear log button
            var clearBtn = document.getElementById('clear-log');
            if (clearBtn) {
                clearBtn.addEventListener('click', function() {
                    self.clearLog();
                });
            }

            // Quick action buttons
            var actionBtns = document.querySelectorAll('.action-btn');
            actionBtns.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var action = btn.getAttribute('onclick');
                    if (action) {
                        // Extract action name from onclick attribute
                        var match = action.match(/runAction\(['"]([^'"]+)['"]\)/);
                        if (match) {
                            self.runAction(match[1], btn);
                        }
                    }
                });
            });
        },

        loadDashboard: function() {
            var self = this;
            this.state.loading = true;

            return fetch(this.config.dashboardEndpoint)
                .then(function(response) {
                    if (!response.ok) throw new Error('Dashboard API returned ' + response.status);
                    return response.json();
                })
                .then(function(result) {
                    if (!result.success) throw new Error('Dashboard API error');
                    self.state.data = result;
                    self.state.lastUpdate = new Date();
                    self.state.loading = false;
                    self.state.error = null;
                    self.updateUI();
                })
                .catch(function(error) {
                    self.state.loading = false;
                    self.state.error = error.message;
                    console.error('[Dashboard] Load error:', error);
                    self.showError(error.message);
                });
        },

        updateUI: function() {
            if (!this.state.data) return;

            var data = this.state.data;

            // System badge
            this.updateSystemBadge(data.system);

            // Health cards
            this.updateHealthCard('db-health', data.database);
            this.updateHealthCard('cache-health', data.cache);
            this.updateHealthCard('queue-health', data.queue);

            // Database metrics
            this.updateMetric('db-connections', data.database.connections);
            this.updateMetric('db-latency', data.database.latency);

            // Cache metrics
            this.updateMetric('cache-hit', data.cache.hit_rate);
            this.updateMetric('cache-keys', data.cache.keys);

            // Queue metrics
            this.updateMetric('queue-pending', data.queue.pending);
            this.updateMetric('queue-workers', data.queue.workers);

            // Queue list
            this.updateQueueList(data.queue.items || []);

            // System stats
            if (data.system) {
                this.updateElement('sys-version', data.system.version);
                this.updateElement('sys-uptime', data.system.uptime);
                this.updateElement('sys-memory', data.system.memory);
                this.updateElement('sys-php', data.system.php);
            }

            // System log
            this.updateLog(data.logs || []);
        },

        updateSystemBadge: function(system) {
            var badge = document.getElementById('system-badge');
            if (!badge) return;

            var label = (system && system.status) || 'HEALTHY';
            var cls = (system && system.status_class) || 'healthy';

            badge.textContent = label;
            badge.className = 'system-badge ' + cls;
        },

        updateHealthCard: function(cardId, data) {
            var card = document.getElementById(cardId);
            if (!card || !data) return;

            var status = data.status || 'ok';

            // Update card border class
            card.className = 'health-card ' + status;

            // Update status span inside card header
            var statusSpan = card.querySelector('.health-status');
            if (statusSpan) {
                statusSpan.textContent = status.toUpperCase();
                statusSpan.className = 'health-status ' + status;
            }

            // Update status dot if exists
            var dot = card.querySelector('.status-dot');
            if (dot) {
                dot.className = 'status-dot ' + status;
            }
        },

        updateMetric: function(id, value) {
            var el = document.getElementById(id);
            if (!el) return;

            var formatted = (typeof value === 'number') ? value.toLocaleString() : String(value);

            if (el.textContent !== formatted) {
                el.style.transform = 'scale(1.1)';
                el.textContent = formatted;
                setTimeout(function() { el.style.transform = 'scale(1)'; }, 200);
            }
        },

        updateQueueList: function(items) {
            var container = document.getElementById('queue-list');
            if (!container) return;

            if (!items || items.length === 0) {
                container.innerHTML = '<div class="queue-item"><span class="queue-name" style="color: var(--text-muted);">No pending tasks</span></div>';
                return;
            }

            var html = '';
            items.forEach(function(item) {
                var statusDot = '<span class="status-dot ' + (item.status === 'active' ? 'ok' : 'warn') + '"></span>';
                html += '<div class="queue-item">';
                html += '  <span class="queue-name">' + statusDot + ' ' + this.escapeHtml(item.name) + '</span>';
                html += '  <span class="queue-count">' + item.count + '</span>';
                html += '</div>';
            }.bind(this));

            container.innerHTML = html;
        },

        updateLog: function(logs) {
            var container = document.getElementById('system-log');
            if (!container) return;

            if (!logs || logs.length === 0) {
                container.innerHTML = '<div class="log-entry"><span class="log-time">--:--:--</span><span class="log-level info">INFO</span><span>No recent logs</span></div>';
                return;
            }

            var html = '';
            logs.forEach(function(entry) {
                var level = entry.level || 'info';
                html += '<div class="log-entry">';
                html += '  <span class="log-time">' + this.escapeHtml(entry.time || '--:--:--') + '</span>';
                html += '  <span class="log-level ' + level + '">' + level.toUpperCase() + '</span>';
                html += '  <span>' + this.escapeHtml(entry.message || '') + '</span>';
                html += '</div>';
            }.bind(this));

            container.innerHTML = html;
        },

        clearLog: function() {
            var container = document.getElementById('system-log');
            if (container) {
                container.innerHTML = '<div class="log-entry"><span class="log-time">' + this.formatTime(new Date()) + '</span><span class="log-level info">INFO</span><span>Log cleared</span></div>';
            }
        },

        runAction: function(action, btn) {
            if (!btn) return;

            var originalText = btn.querySelector('.action-label');
            var labelEl = originalText;

            if (labelEl) {
                labelEl.textContent = 'Running...';
            }

            // Log the action
            this.addLogEntry('info', 'Action: ' + action);

            // Simulate action completion after delay
            setTimeout(function() {
                if (labelEl) {
                    labelEl.textContent = action === 'flush-cache' ? 'Flush Cache' :
                                         action === 'restart-workers' ? 'Restart Workers' :
                                         action === 'clear-queue' ? 'Clear Queue' :
                                         action === 'export-logs' ? 'Export Logs' : action;
                }
                this.addLogEntry('info', 'Completed: ' + action);
            }.bind(this), 1500);
        },

        addLogEntry: function(level, message) {
            var container = document.getElementById('system-log');
            if (!container) return;

            var entry = document.createElement('div');
            entry.className = 'log-entry';
            entry.innerHTML = '<span class="log-time">' + this.formatTime(new Date()) + '</span>' +
                '<span class="log-level ' + level + '">' + level.toUpperCase() + '</span>' +
                '<span>' + this.escapeHtml(message) + '</span>';

            container.insertBefore(entry, container.firstChild);

            // Limit log entries to 50
            while (container.children.length > 50) {
                container.removeChild(container.lastChild);
            }
        },

        showError: function(message) {
            var badge = document.getElementById('system-badge');
            if (badge) {
                badge.textContent = 'ERROR';
                badge.className = 'system-badge critical';
            }

            this.addLogEntry('error', 'Failed to load dashboard: ' + message);
        },

        initAutoRefresh: function() {
            var self = this;

            setInterval(function() {
                self.loadDashboard();
            }, this.config.refreshInterval);

            document.addEventListener('visibilitychange', function() {
                if (!document.hidden) {
                    self.loadDashboard();
                }
            });
        },

        formatTime: function(date) {
            var h = String(date.getHours()).padStart(2, '0');
            var m = String(date.getMinutes()).padStart(2, '0');
            var s = String(date.getSeconds()).padStart(2, '0');
            return h + ':' + m + ':' + s;
        },

        escapeHtml: function(str) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        },

        updateElement: function(id, value) {
            var el = document.getElementById(id);
            if (el && value !== undefined && value !== null) {
                el.textContent = String(value);
            }
        }
    };

    // Make globally accessible
    window.BITCORE_Dashboard = Dashboard;

    // Remove inline onclick handlers and use event delegation
    window.runAction = function(action) {
        Dashboard.runAction(action, null);
    };

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() { Dashboard.init(); });
    } else {
        Dashboard.init();
    }

})();
