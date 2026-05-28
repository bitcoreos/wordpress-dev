/**
 * BITCORE Credits Dashboard Controller
 * Canonical Credits API - Per-Agent Usage
 */
(function(global) {
    'use strict';
    const BudgetPage = {
        config: { endpoint: '/wp-json/bitcore/v1/credits', nonce: null, agentId: 'eliza' },
        init: function() {
            if (typeof bitcoreData !== 'undefined') {
                this.config.nonce = bitcoreData.nonce;
                this.config.agentId = bitcoreData.agentId || this.config.agentId;
            }
            console.log('BudgetPage initializing...');
            this.bindEvents();
            this.loadData();
        },
        bindEvents: function() {
            var self = this;
            document.getElementById('refresh-budget')?.addEventListener('click', function() { self.loadData(); });
        },
        loadData: async function() {
            try {
                var url = this.config.endpoint + '/' + encodeURIComponent(this.config.agentId || 'eliza');
                var response = await fetch(url, { headers: { 'X-WP-Nonce': this.config.nonce || '' } });
                var data = await response.json();
                this.render(data);
            } catch (error) {
                console.error('Budget error:', error);
                document.getElementById('budget-list').innerHTML = '<div class="empty-state">No budget data available</div>';
                this.updateStats(0, 0, 0);
            }
        },
        render: function(data) {
            var budgets = data.budgets || data.items || (data.agent_id ? [data] : []);
            var list = document.getElementById('budget-list');
            if (!list) return;
            if (budgets.length === 0) {
                list.innerHTML = '<div class="empty-state">No budget allocations</div>';
                return;
            }
            var html = '';
            var totalBudget = 0, totalSpent = 0;
            budgets.forEach(function(b) {
                var spent = b.used_today || b.spent || 0;
                var limit = b.daily_limit || b.limit || b.budget || 100;
                var percent = Math.round((spent / limit) * 100);
                totalBudget += limit; totalSpent += spent;
                html += '<div class="budget-item"><div class="budget-header"><span class="agent-name">' + (b.agent_id || b.agent || b.name || 'eliza') + '</span><span class="budget-amount">' + spent + ' / ' + limit + ' credits</span></div>';
                html += '<div class="budget-bar"><div class="budget-fill" style="width:' + percent + '%"></div></div></div>';
            });
            list.innerHTML = html;
            this.updateStats(totalBudget, totalSpent, totalBudget - totalSpent);
        },
        updateStats: function(total, spent, remaining) {
            if (document.getElementById('total-budget')) document.getElementById('total-budget').textContent = '$' + total;
            if (document.getElementById('total-spent')) document.getElementById('total-spent').textContent = '$' + spent;
            if (document.getElementById('remaining-budget')) document.getElementById('remaining-budget').textContent = '$' + remaining;
        }
    };
    document.addEventListener('DOMContentLoaded', function() { BudgetPage.init(); });
    global.BudgetPage = BudgetPage;
})(window);
