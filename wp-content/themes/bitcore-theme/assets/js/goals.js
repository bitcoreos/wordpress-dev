/**
 * BITCORE Goals Hierarchy Controller
 * Paperclip Integration - Mission → Objective → Task
 */
(function(global) {
    'use strict';
    const GoalsPage = {
        config: { endpoint: '/wp-json/bitcore/v1/goals', nonce: null },
        init: function() {
            if (typeof bitcoreData !== 'undefined') this.config.nonce = bitcoreData.nonce;
            console.log('GoalsPage initializing...');
            this.bindEvents();
            this.loadData();
        },
        bindEvents: function() {
            var self = this;
            document.getElementById('refresh-goals')?.addEventListener('click', function() { self.loadData(); });
        },
        loadData: async function() {
            try {
                var response = await fetch(this.config.endpoint, { headers: { 'X-WP-Nonce': this.config.nonce || '' } });
                var data = await response.json();
                this.render(data);
            } catch (error) {
                console.error('Goals error:', error);
                document.getElementById('goal-tree').innerHTML = '<div class="empty-state">No goals data available</div>';
                this.updateStats(0, 0, 0);
            }
        },
        render: function(data) {
            var goals = data.goals || data || [];
            var tree = document.getElementById('goal-tree');
            if (!tree) return;
            if (goals.length === 0) {
                tree.innerHTML = '<div class="empty-state">No goals defined</div>';
                return;
            }
            var html = '<div class="goal-list">';
            var missions = 0, objectives = 0, tasks = 0;
            goals.forEach(function(g) {
                html += '<div class="goal-node level-' + (g.level || 'mission') + '">';
                html += '<span class="goal-icon">' + (g.level === 'mission' ? '🎯' : g.level === 'objective' ? '📋' : '✅') + '</span>';
                html += '<span class="goal-title">' + (g.title || g.name) + '</span>';
                if (g.description) html += '<span class="goal-desc">' + g.description + '</span>';
                html += '</div>';
                if (g.level === 'mission') missions++;
                else if (g.level === 'objective') objectives++;
                else tasks++;
            });
            html += '</div>';
            tree.innerHTML = html;
            this.updateStats(missions, objectives, tasks);
        },
        updateStats: function(missions, objectives, tasks) {
            if (document.getElementById('total-missions')) document.getElementById('total-missions').textContent = missions;
            if (document.getElementById('total-objectives')) document.getElementById('total-objectives').textContent = objectives;
            if (document.getElementById('total-goal-tasks')) document.getElementById('total-goal-tasks').textContent = tasks;
        }
    };
    document.addEventListener('DOMContentLoaded', function() { GoalsPage.init(); });
    global.GoalsPage = GoalsPage;
})(window);
