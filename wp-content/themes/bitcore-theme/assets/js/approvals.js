/**
 * BITCORE Approvals Queue Controller
 * Paperclip Integration - Pending Approvals
 */
(function(global) {
    'use strict';
    const ApprovalsPage = {
        config: { endpoint: '/wp-json/bitcore/v1/approvals', nonce: null },
        init: function() {
            if (typeof bitcoreData !== 'undefined') this.config.nonce = bitcoreData.nonce;
            console.log('ApprovalsPage initializing...');
            this.bindEvents();
            this.loadData();
        },
        bindEvents: function() {
            var self = this;
            document.getElementById('refresh-approvals')?.addEventListener('click', function() { self.loadData(); });
        },
        loadData: async function() {
            try {
                var response = await fetch(this.config.endpoint, { headers: { 'X-WP-Nonce': this.config.nonce || '' } });
                var data = await response.json();
                this.render(data);
            } catch (error) {
                console.error('Approvals error:', error);
                document.getElementById('approval-list').innerHTML = '<div class="empty-state">No approval data available</div>';
                this.updateStats(0, 0, 0);
            }
        },
        render: function(data) {
            var approvals = data.approvals || data || [];
            var list = document.getElementById('approval-list');
            if (!list) return;
            var pending = approvals.filter(function(a) { return a.status === 'pending'; });
            if (pending.length === 0) {
                list.innerHTML = '<div class="empty-state">No pending approvals</div>';
                this.updateStats(0, 0, 0);
                return;
            }
            var html = '';
            pending.forEach(function(a) {
                html += '<div class="approval-card"><div class="approval-header"><span class="approval-type">' + (a.type || 'Request') + '</span><span class="approval-agent">' + (a.agent || 'System') + '</span></div>';
                html += '<div class="approval-body">' + (a.description || a.message || 'No description') + '</div>';
                html += '<div class="approval-actions"><button class="btn btn-success btn-sm" onclick="ApprovalsPage.respond(\'' + a.id + '\', \'approve\')">✓ Approve</button>';
                html += '<button class="btn btn-danger btn-sm" onclick="ApprovalsPage.respond(\'' + a.id + '\', \'reject\')">✗ Reject</button></div></div>';
            });
            list.innerHTML = html;
            this.updateStats(pending.length, 0, 0);
        },
        respond: async function(id, action) {
            try {
                await fetch(this.config.endpoint + '/' + id, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': this.config.nonce || '' },
                    body: JSON.stringify({ action: action })
                });
                this.loadData();
            } catch (error) { console.error('Response error:', error); }
        },
        updateStats: function(pending, approved, rejected) {
            if (document.getElementById('pending-approvals')) document.getElementById('pending-approvals').textContent = pending;
            if (document.getElementById('approved-count')) document.getElementById('approved-count').textContent = approved;
            if (document.getElementById('rejected-count')) document.getElementById('rejected-count').textContent = rejected;
        }
    };
    document.addEventListener('DOMContentLoaded', function() { ApprovalsPage.init(); });
    global.ApprovalsPage = ApprovalsPage;
})(window);
