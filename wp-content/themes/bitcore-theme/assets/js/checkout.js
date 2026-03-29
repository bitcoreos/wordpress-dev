/**
 * BITCORE Task Checkout Controller
 * Paperclip Integration - Exclusive Execution Locks
 */
(function(global) {
    'use strict';
    const CheckoutPage = {
        config: { endpoint: '/wp-json/bitcore/v1/checkout', nonce: null },
        init: function() {
            if (typeof bitcoreData !== 'undefined') this.config.nonce = bitcoreData.nonce;
            console.log('CheckoutPage initializing...');
            this.bindEvents();
            this.loadData();
        },
        bindEvents: function() {
            var self = this;
            document.getElementById('refresh-checkout')?.addEventListener('click', function() { self.loadData(); });
        },
        loadData: async function() {
            try {
                var response = await fetch(this.config.endpoint, { headers: { 'X-WP-Nonce': this.config.nonce || '' } });
                var data = await response.json();
                this.render(data);
            } catch (error) {
                console.error('Checkout error:', error);
                document.getElementById('checkout-list').innerHTML = '<div class="empty-state">No checkout data available</div>';
                this.updateStats(0, 0, 0);
            }
        },
        render: function(data) {
            var checkouts = data.checkouts || data || [];
            var list = document.getElementById('checkout-list');
            if (!list) return;
            if (checkouts.length === 0) {
                list.innerHTML = '<div class="empty-state">No active checkouts</div>';
                this.updateStats(0, 0, 0);
                return;
            }
            var html = '';
            var locked = 0, available = 0, stale = 0;
            checkouts.forEach(function(c) {
                var isLocked = c.status === 'locked';
                var isStale = c.stale === true;
                if (isLocked) locked++;
                else available++;
                if (isStale) stale++;
                html += '<div class="checkout-item ' + (isStale ? 'stale' : '') + '">';
                html += '<span class="checkout-status">' + (isLocked ? '🔒' : '🔓') + '</span>';
                html += '<span class="checkout-task">' + (c.task_name || c.task_id) + '</span>';
                html += '<span class="checkout-agent">' + (c.agent || 'Available') + '</span>';
                if (isStale) html += '<span class="checkout-warning">⚠️ Stale</span>';
                html += '</div>';
            });
            list.innerHTML = html;
            this.updateStats(locked, available, stale);
        },
        updateStats: function(locked, available, stale) {
            if (document.getElementById('locked-tasks')) document.getElementById('locked-tasks').textContent = locked;
            if (document.getElementById('available-tasks')) document.getElementById('available-tasks').textContent = available;
            if (document.getElementById('stale-locks')) document.getElementById('stale-locks').textContent = stale;
        }
    };
    document.addEventListener('DOMContentLoaded', function() { CheckoutPage.init(); });
    global.CheckoutPage = CheckoutPage;
})(window);
