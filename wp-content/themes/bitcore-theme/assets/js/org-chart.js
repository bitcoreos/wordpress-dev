/**
 * BITCORE Org Chart Controller
 * Paperclip Integration - Agent Hierarchy
 */
(function(global) {
    'use strict';
    const OrgChartPage = {
        config: {
            endpoint: '/wp-json/bitcore/v1/org-chart',
            nonce: null
        },
        init: function() {
            if (typeof bitcoreData !== 'undefined') this.config.nonce = bitcoreData.nonce;
            console.log('OrgChartPage initializing...');
            this.bindEvents();
            this.loadData();
        },
        bindEvents: function() {
            var self = this;
            document.getElementById('refresh-org')?.addEventListener('click', function() { self.loadData(); });
        },
        loadData: async function() {
            try {
                var response = await fetch(this.config.endpoint, { headers: { 'X-WP-Nonce': this.config.nonce || '' } });
                var data = await response.json();
                this.render(data);
            } catch (error) {
                console.error('Org chart error:', error);
                document.getElementById('org-tree').innerHTML = '<div class="empty-state">No org chart data available</div>';
                this.updateStats(0, 0, 0);
            }
        },
        render: function(data) {
            var agents = data.agents || data || [];
            var tree = document.getElementById('org-tree');
            if (!tree) return;
            if (agents.length === 0) {
                tree.innerHTML = '<div class="empty-state">No agents configured</div>';
                this.updateStats(0, 0, 0);
                return;
            }
            var html = '<div class="org-level">';
            agents.forEach(function(agent) {
                html += '<div class="org-node"><span class="node-name">' + (agent.name || agent) + '</span>';
                if (agent.role) html += '<span class="node-role">' + agent.role + '</span>';
                html += '</div>';
            });
            html += '</div>';
            tree.innerHTML = html;
            this.updateStats(agents.length, agents.filter(function(a) { return a.status === 'active'; }).length, 1);
        },
        updateStats: function(total, active, teams) {
            if (document.getElementById('total-agents')) document.getElementById('total-agents').textContent = total;
            if (document.getElementById('active-agents')) document.getElementById('active-agents').textContent = active;
            if (document.getElementById('teams-count')) document.getElementById('teams-count').textContent = teams;
        }
    };
    document.addEventListener('DOMContentLoaded', function() { OrgChartPage.init(); });
    global.OrgChartPage = OrgChartPage;
})(window);
