/**
 * BITCORE Agent Sessions Controller
 * Paperclip Integration - Heartbeat Session History
 */
(function(global) {
    'use strict';
    const SessionsPage = {
        config: { endpoint: '/wp-json/bitcore/v1/sessions', nonce: null },
        init: function() {
            if (typeof bitcoreData !== 'undefined') this.config.nonce = bitcoreData.nonce;
            console.log('SessionsPage initializing...');
            this.bindEvents();
            this.loadData();
        },
        bindEvents: function() {
            var self = this;
            document.getElementById('refresh-sessions')?.addEventListener('click', function() { self.loadData(); });
        },
        loadData: async function() {
            try {
                var response = await fetch(this.config.endpoint, { headers: { 'X-WP-Nonce': this.config.nonce || '' } });
                var data = await response.json();
                this.render(data);
            } catch (error) {
                console.error('Sessions error:', error);
                document.getElementById('session-list').innerHTML = '<div class="empty-state">No session data available</div>';
                this.updateStats(0, 0, '--');
            }
        },
        render: function(data) {
            var sessions = data.sessions || data || [];
            var list = document.getElementById('session-list');
            if (!list) return;
            if (sessions.length === 0) {
                list.innerHTML = '<div class="empty-state">No active sessions</div>';
                this.updateStats(0, 0, '--');
                return;
            }
            var html = '';
            var active = 0, heartbeats = 0, totalResponse = 0;
            sessions.forEach(function(s) {
                if (s.active) active++;
                heartbeats += s.heartbeat_count || 1;
                totalResponse += s.response_time || 0;
                html += '<div class="session-item">';
                html += '<span class="session-status">' + (s.active ? '🟢' : '🔴') + '</span>';
                html += '<span class="session-agent">' + (s.agent || 'Unknown') + '</span>';
                html += '<span class="session-time">' + (s.last_heartbeat || 'N/A') + '</span>';
                html += '</div>';
            });
            list.innerHTML = html;
            var avgResponse = sessions.length > 0 ? Math.round(totalResponse / sessions.length) : '--';
            this.updateStats(active, heartbeats, avgResponse);
        },
        updateStats: function(active, heartbeats, avgResponse) {
            if (document.getElementById('active-sessions')) document.getElementById('active-sessions').textContent = active;
            if (document.getElementById('total-heartbeats')) document.getElementById('total-heartbeats').textContent = heartbeats;
            if (document.getElementById('avg-response')) document.getElementById('avg-response').textContent = avgResponse;
        }
    };
    document.addEventListener('DOMContentLoaded', function() { SessionsPage.init(); });
    global.SessionsPage = SessionsPage;
})(window);
