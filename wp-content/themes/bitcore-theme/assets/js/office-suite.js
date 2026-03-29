/**
 * BITCORE FILENAME Controller
 * @version 1.0.0
 */
(function(global) {
    'use strict';
    const Page = {
        config: { endpoint: '/wp-json/bitcore/v1/ENDPOINT', nonce: null },
        items: [],
        init: function(config = {}) {
            this.config = { ...this.config, ...config };
            if (typeof bitcoreData !== 'undefined') this.config.nonce = bitcoreData.nonce;
            this.bindEvents();
            this.load();
        },
        bindEvents: function() {
            const form = document.querySelector('form[data-action="create"]');
            if (form) form.addEventListener('submit', (e) => { e.preventDefault(); this.create(); });
        },
        load: async function() {
            try {
                this.items = await this.request('GET', this.config.endpoint) || [];
                this.render();
            } catch (e) { console.error('Load failed:', e); }
        },
        create: async function() { /* form handling */ },
        render: function() {
            const container = document.querySelector('[data-list]');
            if (!container) return;
            container.innerHTML = this.items.length ? 
                this.items.map(i => `<div class="item">${i.name || i.id}</div>`).join('') :
                '<div class="empty-state"><p>No items yet.</p></div>';
        },
        request: async function(method, url, data = null) {
            const opts = { method, headers: { 'Content-Type': 'application/json' } };
            if (this.config.nonce) opts.headers['X-WP-Nonce'] = this.config.nonce;
            if (data) opts.body = JSON.stringify(data);
            const res = await fetch(url, opts);
            if (!res.ok) throw new Error('Failed');
            return await res.json();
        }
    };
    document.addEventListener('DOMContentLoaded', () => Page.init());
    global.Page = Page;
})(typeof window !== 'undefined' ? window : global);
