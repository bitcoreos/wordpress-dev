/**
 * BITCORE SaaS Dashboard - User Home Page
 * Fetches from /tasks, /goals, /sessions, /approvals, /dashboard, /ping
 * Uses nonce auth via bitcoreData for authenticated requests
 * @version 3.0.0
 */

(function() {
    'use strict';

    var SaaSDashboard = {

        config: {
            restBase: '/wp-json/bitcore/v1/',
            refreshInterval: 30000,
            maxTasks: 5,
            maxGoals: 5,
            maxActivities: 10
        },

        state: {
            tasks: [],
            goals: [],
            sessions: [],
            approvals: [],
            dashboard: null,
            systemHealth: null,
            lastUpdate: null,
            loading: false
        },

        init: function() {
            if (!document.querySelector('.saas-container')) return;

            // Build endpoints from bitcoreData if available
            if (typeof bitcoreData !== 'undefined' && bitcoreData.restUrl) {
                this.config.restBase = bitcoreData.restUrl;
            }

            this.bindEvents();
            this.loadAll();
            this.initAutoRefresh();
            this.startClock();
        },

        bindEvents: function() {
            var self = this;
            var refreshBtn = document.getElementById('refresh-btn');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', function() {
                    refreshBtn.disabled = true;
                    refreshBtn.textContent = '⟳ Loading...';
                    self.loadAll().then(function() {
                        refreshBtn.disabled = false;
                        refreshBtn.textContent = '⟳ Refresh';
                    }).catch(function() {
                        refreshBtn.disabled = false;
                        refreshBtn.textContent = '⟳ Refresh';
                    });
                });
            }
        },

        // ====== AUTH HELPERS ======

        getHeaders: function() {
            var headers = { 'Content-Type': 'application/json' };
            if (typeof bitcoreData !== 'undefined' && bitcoreData.nonce) {
                headers['X-WP-Nonce'] = bitcoreData.nonce;
            }
            return headers;
        },

        // ====== API FETCHES ======

        fetchJSON: function(endpoint) {
            var url = this.config.restBase + endpoint;
            var options = {
                method: 'GET',
                credentials: 'same-origin',
                headers: this.getHeaders()
            };
            return fetch(url, options)
                .then(function(response) {
                    if (!response.ok) {
                        console.warn('[SaaSDashboard] ' + endpoint + ' returned ' + response.status);
                        return null;
                    }
                    return response.json();
                })
                .catch(function(error) {
                    console.warn('[SaaSDashboard] Fetch error for ' + endpoint + ':', error.message);
                    return null;
                });
        },

        loadAll: function() {
            var self = this;
            this.state.loading = true;

            var promises = [
                this.fetchJSON('tasks').then(function(data) { self.state.tasks = data || []; }),
                this.fetchJSON('goals').then(function(data) {
                    // Handle {goals: []} wrapper
                    if (data && data.goals && Array.isArray(data.goals)) {
                        self.state.goals = data.goals;
                    } else if (Array.isArray(data)) {
                        self.state.goals = data;
                    } else {
                        self.state.goals = [];
                    }
                }),
                this.fetchJSON('sessions').then(function(data) {
                    // Handle {sessions: []} wrapper
                    if (data && data.sessions && Array.isArray(data.sessions)) {
                        self.state.sessions = data.sessions;
                    } else if (Array.isArray(data)) {
                        self.state.sessions = data;
                    } else {
                        self.state.sessions = [];
                    }
                }),
                this.fetchJSON('approvals').then(function(data) {
                    if (Array.isArray(data)) {
                        self.state.approvals = data;
                    } else if (data && data.approvals && Array.isArray(data.approvals)) {
                        self.state.approvals = data.approvals;
                    } else {
                        self.state.approvals = [];
                    }
                }),
                this.fetchJSON('dashboard').then(function(data) {
                    self.state.dashboard = data;
                    if (data && data.system) {
                        self.state.systemHealth = data.system;
                    }
                }),
                this.fetchJSON('ping').then(function(data) {
                    self.state.ping = data;
                })
            ];

            return Promise.all(promises).then(function() {
                self.state.loading = false;
                self.state.lastUpdate = new Date();
                self.renderAll();
            });
        },

        // ====== RENDERING ======

        renderAll: function() {
            this.renderHealthIndicator();
            this.renderStatsRow();
            this.renderTasks();
            this.renderGoals();
            this.renderActivity();
            this.renderAgentStatus();
            this.updateTimestamp();
        },

        // ====== 1. HEALTH INDICATOR ======

        renderHealthIndicator: function() {
            var indicator = document.getElementById('health-indicator');
            var label = document.getElementById('health-label');
            if (!indicator || !label) return;

            var health = this.state.systemHealth;
            if (!health) {
                label.textContent = 'Unknown';
                return;
            }

            var status = (health.status || 'healthy').toLowerCase();
            var statusText = health.status_label || status;

            // Map system status to classes
            if (status === 'healthy' || status === 'excellent' || health.health_score >= 80) {
                indicator.className = 'health-indicator';
                statusText = 'System Healthy';
            } else if (status === 'degraded' || health.health_score >= 50) {
                indicator.className = 'health-indicator degraded';
                statusText = 'System Degraded';
            } else {
                indicator.className = 'health-indicator critical';
                statusText = 'System Critical';
            }

            label.textContent = statusText;
        },

        // ====== 2. STATS ROW ======

        renderStatsRow: function() {
            // Active tasks count
            var tasks = this.state.tasks;
            var activeTasks = Array.isArray(tasks) ? tasks.length : 0;
            this.updateStatCount('stat-tasks-count', activeTasks);

            // Active goals count
            var goals = this.state.goals;
            var activeGoals = Array.isArray(goals) ? goals.length : 0;
            this.updateStatCount('stat-goals-count', activeGoals);

            // Sessions count
            var sessions = this.state.sessions;
            var sessionCount = Array.isArray(sessions) ? sessions.length : 0;
            this.updateStatCount('stat-sessions-count', sessionCount);

            // Approvals pending count
            var approvals = this.state.approvals;
            var approvalCount = Array.isArray(approvals) ? approvals.length : 0;
            this.updateStatCount('stat-approvals-count', approvalCount);
        },

        updateStatCount: function(id, count) {
            var el = document.getElementById(id);
            if (!el) return;
            var formatted = (typeof count === 'number') ? count.toLocaleString() : '0';
            if (el.textContent !== formatted) {
                el.style.transform = 'scale(1.15)';
                el.textContent = formatted;
                setTimeout(function() { el.style.transform = 'scale(1)'; }, 250);
            }
        },

        // ====== 3. TASKS WIDGET ======

        renderTasks: function() {
            var container = document.getElementById('tasks-list');
            if (!container) return;

            var tasks = this.state.tasks;
            if (!Array.isArray(tasks) || tasks.length === 0) {
                container.innerHTML = '<div class="empty-state"><p>No tasks yet. <a href="/tasks/">Create your first task!</a></p></div>';
                return;
            }

            var display = tasks.slice(0, this.config.maxTasks);
            var html = '';

            for (var i = 0; i < display.length; i++) {
                var task = display[i];
                var status = (task.status || 'draft').toLowerCase();
                var title = this.escapeHtml(task.title || 'Untitled Task');
                var date = task.date ? this.formatDate(task.date) : '';

                html += '<div class="task-item">';
                html += '  <div class="task-info">';
                html += '    <span class="task-status-dot ' + status + '"></span>';
                html += '    <span class="task-title">' + title + '</span>';
                html += '  </div>';
                html += '  <span class="task-meta">' + date + '</span>';
                html += '</div>';
            }

            container.innerHTML = html;
        },

        // ====== 4. GOALS WIDGET ======

        renderGoals: function() {
            var container = document.getElementById('goals-list');
            if (!container) return;

            var goals = this.state.goals;
            if (!Array.isArray(goals) || goals.length === 0) {
                container.innerHTML = '<div class="empty-state"><p>No goals defined. <a href="/goals/">Set your first goal!</a></p></div>';
                return;
            }

            var display = goals.slice(0, this.config.maxGoals);
            var html = '';

            for (var i = 0; i < display.length; i++) {
                var goal = display[i];
                var name = this.escapeHtml(goal.title || goal.name || 'Unnamed Goal');
                var progress = parseInt(goal.progress || 0, 10);
                var priority = (goal.priority || 'medium').toLowerCase();

                // Progress bar class
                var progressClass = progress >= 75 ? 'high' : (progress >= 40 ? 'mid' : 'low');

                html += '<div class="goal-item">';
                html += '  <div class="goal-name">' + name;
                if (goal.priority) {
                    html += ' <span class="goal-priority priority-' + priority + '">' + priority + '</span>';
                }
                html += '  </div>';
                html += '  <div class="progress-bar"><div class="progress-fill ' + progressClass + '" style="width:' + progress + '%"></div></div>';
                html += '  <span class="progress-text">' + progress + '% complete</span>';
                html += '</div>';
            }

            container.innerHTML = html;
        },

        // ====== 5. ACTIVITY FEED ======

        renderActivity: function() {
            var container = document.getElementById('activity-list');
            if (!container) return;

            var activities = this.buildActivityFeed();

            if (activities.length === 0) {
                container.innerHTML = '<div class="empty-state"><p>No recent activity</p></div>';
                return;
            }

            var display = activities.slice(0, this.config.maxActivities);
            var html = '';

            for (var i = 0; i < display.length; i++) {
                var act = display[i];
                html += '<div class="activity-item">';
                html += '  <div class="activity-icon">' + act.icon + '</div>';
                html += '  <div class="activity-content">';
                html += '    <div class="activity-text">' + this.escapeHtml(act.text) + '</div>';
                html += '    <div class="activity-time">' + this.escapeHtml(act.time) + '</div>';
                html += '  </div>';
                html += '</div>';
            }

            container.innerHTML = html;
        },

        buildActivityFeed: function() {
            var activities = [];

            // Add task activities
            var tasks = this.state.tasks;
            if (Array.isArray(tasks)) {
                for (var i = 0; i < tasks.length && i < 5; i++) {
                    var task = tasks[i];
                    var statusLabel = task.status === 'publish' ? 'completed' : (task.status || 'created');
                    activities.push({
                        icon: '✅',
                        text: 'Task ' + statusLabel + ': ' + (task.title || 'Untitled'),
                        time: task.date ? this.formatDate(task.date) : 'Recently',
                        timestamp: task.date ? new Date(task.date).getTime() : Date.now()
                    });
                }
            }

            // Add goal activities
            var goals = this.state.goals;
            if (Array.isArray(goals)) {
                for (var j = 0; j < goals.length && j < 3; j++) {
                    var goal = goals[j];
                    activities.push({
                        icon: '🎯',
                        text: 'Goal: ' + (goal.title || goal.name || 'Unnamed') + ' — ' + (goal.progress || 0) + '%',
                        time: 'Updated recently',
                        timestamp: Date.now() - (j * 60000)
                    });
                }
            }

            // Add session activities
            var sessions = this.state.sessions;
            if (Array.isArray(sessions)) {
                for (var k = 0; k < sessions.length && k < 3; k++) {
                    var session = sessions[k];
                    activities.push({
                        icon: '💬',
                        text: 'Session with ' + (session.agent_id || 'Agent') + ' — ' + (session.status || 'unknown'),
                        time: session.started_at ? this.formatDate(session.started_at) : 'Recently',
                        timestamp: session.started_at ? new Date(session.started_at).getTime() : Date.now()
                    });
                }
            }

            // Add system log entries from /dashboard
            var logs = this.state.dashboard && this.state.dashboard.logs;
            if (Array.isArray(logs)) {
                for (var l = 0; l < logs.length && l < 5; l++) {
                    var log = logs[l];
                    activities.push({
                        icon: '⚙️',
                        text: log.message || 'System event',
                        time: log.time || 'Recently',
                        timestamp: Date.now() - (l * 60000)
                    });
                }
            }

            // Sort by timestamp descending
            activities.sort(function(a, b) { return b.timestamp - a.timestamp; });

            return activities;
        },

        // ====== 7. AGENT STATUS ======

        renderAgentStatus: function() {
            // Check Hyperion (local ping)
            var ping = this.state.ping;
            var hyperionDot = document.getElementById('hyperion-dot');
            var hyperionTime = document.getElementById('hyperion-time');

            if (hyperionDot && hyperionTime) {
                if (ping && ping.pong === true) {
                    hyperionDot.className = 'agent-dot online';
                    hyperionTime.textContent = 'Online';
                } else {
                    hyperionDot.className = 'agent-dot offline';
                    hyperionTime.textContent = 'Offline';
                }
            }

            // Check Coraline (try to reach its endpoint)
            var coralineDot = document.getElementById('coraline-dot');
            var coralineTime = document.getElementById('coraline-time');

            if (coralineDot && coralineTime) {
                this.checkCoraline(coralineDot, coralineTime);
            }
        },

        checkCoraline: function(dotEl, timeEl) {
            // Try fetching Coraline's ping endpoint
            var controller = new AbortController();
            var timeoutId = setTimeout(function() { controller.abort(); }, 5000);

            fetch('https://64.23.131.221/a2a/t-fg_hqFPshR7-IwRM/p-coraline/sse', {
                method: 'GET',
                signal: controller.signal,
                mode: 'no-cors'
            }).then(function() {
                // no-cors means we can't read response but if no error, connection succeeded
                clearTimeout(timeoutId);
                dotEl.className = 'agent-dot online';
                timeEl.textContent = 'Online';
            }).catch(function() {
                clearTimeout(timeoutId);
                dotEl.className = 'agent-dot offline';
                timeEl.textContent = 'Offline';
            });
        },

        // ====== UTILITIES ======

        updateTimestamp: function() {
            var el = document.getElementById('activity-updated');
            if (el) {
                var now = new Date();
                var h = String(now.getHours()).padStart(2, '0');
                var m = String(now.getMinutes()).padStart(2, '0');
                el.textContent = 'Updated ' + h + ':' + m;
            }
        },

        formatDate: function(dateStr) {
            if (!dateStr) return 'N/A';
            try {
                var d = new Date(dateStr);
                if (isNaN(d.getTime())) return dateStr;
                var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                return months[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear();
            } catch (e) {
                return dateStr;
            }
        },

        escapeHtml: function(str) {
            if (!str) return '';
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(String(str)));
            return div.innerHTML;
        },

        startClock: function() {
            var self = this;
            var datetimeEl = document.getElementById('current-datetime');
            if (!datetimeEl) return;

            setInterval(function() {
                var now = new Date();
                var days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                var months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                var h = now.getHours();
                var ampm = h >= 12 ? 'PM' : 'AM';
                h = h % 12 || 12;
                var m = String(now.getMinutes()).padStart(2, '0');
                datetimeEl.textContent = days[now.getDay()] + ', ' + months[now.getMonth()] + ' ' + now.getDate() + ', ' + now.getFullYear() + ' - ' + h + ':' + m + ' ' + ampm;
            }, 30000);
        },

        initAutoRefresh: function() {
            var self = this;
            setInterval(function() {
                self.loadAll();
            }, this.config.refreshInterval);

            document.addEventListener('visibilitychange', function() {
                if (!document.hidden) {
                    self.loadAll();
                }
            });
        }
    };

    // Expose globally
    window.SaaSDashboard = SaaSDashboard;

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() { SaaSDashboard.init(); });
    } else {
        SaaSDashboard.init();
    }

})();
