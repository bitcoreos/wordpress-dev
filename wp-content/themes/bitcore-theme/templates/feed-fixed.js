/**
 * BITCORE Feed - BIThub Content Display (v2.0)
 * Fixed scoping issues, improved design
 */

(function() {
    'use strict';
    
    const Feed = {
        config: {
            endpoint: '/wp-json/bitcore/v1/feed',
            container: '#bitcore-feed',
            searchInput: '#feed-search'
        },
        
        state: {
            items: [],
            filteredItems: [],
            loading: false,
            error: null
        },
        
        init: function() {
            const container = document.querySelector(this.config.container);
            if (!container) {
                console.log('Feed container not found');
                return;
            }
            this.container = container;
            this.bindEvents();
            this.loadFeed();
        },
        
        bindEvents: function() {
            const searchInput = document.querySelector(this.config.searchInput);
            if (searchInput && window.BITCORE && window.BITCORE.debounce) {
                const self = this;
                searchInput.addEventListener('input', window.BITCORE.debounce(function(e) {
                    self.filterFeed(e.target.value);
                }, 300));
            }
        },
        
        loadFeed: function() {
            const self = this;
            self.state.loading = true;
            self.renderLoading();
            
            fetch(self.config.endpoint, {
                method: 'GET',
                headers: { 'Content-Type': 'application/json' }
            })
            .then(function(response) {
                if (!response.ok) throw new Error('Failed to load feed');
                return response.json();
            })
            .then(function(data) {
                self.state.items = Array.isArray(data) ? data : [];
                self.state.filteredItems = self.state.items;
                self.state.loading = false;
                self.render();
            })
            .catch(function(error) {
                console.error('Feed error:', error);
                self.state.error = error.message;
                self.state.loading = false;
                self.renderError();
            });
        },
        
        filterFeed: function(query) {
            const q = (query || '').toLowerCase().trim();
            if (!q) {
                this.state.filteredItems = this.state.items;
            } else {
                this.state.filteredItems = this.state.items.filter(function(item) {
                    return (item.title || '').toLowerCase().includes(q) ||
                           (item.excerpt || '').toLowerCase().includes(q);
                });
            }
            this.render();
        },
        
        render: function() {
            if (!this.container) return;
            
            if (this.state.filteredItems.length === 0) {
                this.renderEmpty();
                return;
            }
            
            const self = this;
            const cardsHtml = this.state.filteredItems.map(function(item) {
                return self.renderCard(item);
            }).join('');
            
            this.container.innerHTML = '<div class="bitcore-feed-grid">' + cardsHtml + '</div>';
        },
        
        renderCard: function(item) {
            const self = this;
            const imageHtml = item.image_url 
                ? '<div class="bitcore-card__image"><img src="' + self.escapeHtml(item.image_url) + '" alt="" loading="lazy"></div>'
                : '';
            
            const tags = item.tags || [];
            const tagsHtml = tags.map(function(tag) {
                return '<span class="bitcore-tag">' + self.escapeHtml(tag.name || '') + '</span>';
            }).join('');
            
            const date = item.created_at ? new Date(item.created_at) : null;
            const dateStr = date ? self.formatDate(date) : '';
            const title = self.decodeHtml(item.fancy_title || item.title || 'Untitled');
            const excerpt = self.truncate(self.stripHtml(item.excerpt || ''), 150);
            const url = 'https://hub.bitwiki.org/t/' + (item.slug || item.id);
            
            return '<article class="bitcore-card bitcore-feed-card" onclick="window.open(\'' + self.escapeHtml(url) + '\', \'_blank\')">' +
                imageHtml +
                '<div class="bitcore-card__body">' +
                '<h3 class="bitcore-card__title">' + title + '</h3>' +
                '<p class="bitcore-card__excerpt">' + excerpt + '</p>' +
                (tagsHtml ? '<div class="bitcore-card__tags">' + tagsHtml + '</div>' : '') +
                '</div>' +
                '<div class="bitcore-card__footer">' +
                '<span class="bitcore-card__meta">' + dateStr + '</span>' +
                '<span class="bitcore-card__meta">💬 ' + (item.posts_count || 0) + '</span>' +
                '</div>' +
                '</article>';
        },
        
        renderLoading: function() {
            if (!this.container) return;
            this.container.innerHTML = 
                '<div class="bitcore-loading">' +
                '<div class="bitcore-spinner"></div>' +
                '<p>Loading feed...</p>' +
                '</div>';
        },
        
        renderEmpty: function() {
            if (!this.container) return;
            this.container.innerHTML = 
                '<div class="bitcore-empty">' +
                '<div class="bitcore-empty__icon">📭</div>' +
                '<p class="bitcore-empty__text">No content found</p>' +
                '</div>';
        },
        
        renderError: function() {
            if (!this.container) return;
            this.container.innerHTML = 
                '<div class="bitcore-error">' +
                '<p>⚠️ Failed to load feed: ' + this.escapeHtml(this.state.error) + '</p>' +
                '<button class="bitcore-btn bitcore-btn--primary" onclick="BITCORE_Feed.loadFeed()">Retry</button>' +
                '</div>';
        },
        
        escapeHtml: function(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
        
        decodeHtml: function(html) {
            if (!html) return '';
            const txt = document.createElement('textarea');
            txt.innerHTML = html;
            return txt.value;
        },
        
        stripHtml: function(html) {
            if (!html) return '';
            return html.replace(/<[^>]*>/g, '');
        },
        
        truncate: function(str, len) {
            if (!str || str.length <= len) return str || '';
            return str.substring(0, len).trim() + '...';
        },
        
        formatDate: function(date) {
            if (!date) return '';
            const now = new Date();
            const diff = Math.floor((now - date) / 1000);
            
            if (diff < 60) return 'just now';
            if (diff < 3600) return Math.floor(diff / 60) + ' min ago';
            if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
            if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
            
            return date.toLocaleDateString();
        }
    };
    
    window.BITCORE_Feed = Feed;
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() { Feed.init(); });
    } else {
        Feed.init();
    }
    
})();
