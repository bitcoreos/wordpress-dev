/**
 * BITCORE Goals Hierarchy Controller
 * Paperclip Integration - Mission → Objective → Task
 */
(function(global) {
    'use strict';
    const GoalsPage = {
        config: { endpoint: '/wp-json/bitcore/v1/tasks?task_type=goal', nonce: null },
        init: function() {
            if (typeof bitcoreData !== 'undefined') {
                this.config.nonce = bitcoreData.nonce;
                if (bitcoreData.restUrl) this.config.endpoint = bitcoreData.restUrl + 'tasks?task_type=goal';
            }
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
            var goals = this.normalizeTaskCollection(data);
            var tree = document.getElementById('goal-tree');
            if (!tree) return;
            if (goals.length === 0) {
                tree.innerHTML = '<div class="empty-state">No goals defined</div>';
                this.updateStats(0, 0, 0);
                return;
            }
            var html = '<div class="goal-list">';
            var missions = 0, objectives = 0, tasks = 0;
            goals.forEach(function(g) {
                var level = GoalsPage.getGoalLevel(g);
                var description = g.description || g.content || '';
                html += '<div class="goal-node level-' + level + '">';
                html += '<span class="goal-icon">' + (level === 'mission' ? '🎯' : level === 'objective' ? '📋' : '✅') + '</span>';
                html += '<span class="goal-title">' + (g.title || g.name || 'Untitled goal') + '</span>';
                if (description) html += '<span class="goal-desc">' + description + '</span>';
                html += '</div>';
                if (level === 'mission') missions++;
                else if (level === 'objective') objectives++;
                else tasks++;
            });
            html += '</div>';
            tree.innerHTML = html;
            this.updateStats(missions, objectives, tasks);
        },
        normalizeTaskCollection: function(data) {
            if (Array.isArray(data)) return data;
            if (data && Array.isArray(data.data)) return data.data;
            if (data && Array.isArray(data.tasks)) return data.tasks;
            if (data && Array.isArray(data.goals)) return data.goals;
            if (data && Array.isArray(data.items)) return data.items;
            return [];
        },
        getGoalLevel: function(goal) {
            if (goal.level) return goal.level;
            if (goal.task_type === 'milestone') return 'mission';
            if (goal.task_type === 'goal') return 'objective';
            return 'task';
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
