/**
 * BITCORE Artifacts - Gallery Display
 * @package BITCORE_Theme
 * @version 1.0.0
 */

(function() {
    'use strict';
    
    const Artifacts = {
        
        config: {
            endpoint: '/wp-json/bitcore/v1/artifacts',
            container: '#bitcore-artifacts',
            filterButtons: '[data-artifact-filter]'
        },
        
        state: {
            items: [],
            filteredItems: [],
            loading: false,
            error: null,
            currentFilter: 'all'
        },
        
        init: function() {
            this.container = document.querySelector(this.config.container);
            if (!this.container) return;
            
            this.bindEvents();
            this.loadArtifacts();
        },
        
        bindEvents: function() {
            const self = this;
            
            const filterButtons = document.querySelectorAll(this.config.filterButtons);
            filterButtons.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    filterButtons.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    self.filterByType(this.dataset.artifactFilter);
                });
            });
        },
        
        loadArtifacts: function() {
            const self = this;
            this.state.loading = true;
            this.renderLoading();
            
            fetch(this.config.endpoint, {
                method: 'GET',
                headers: { 'Content-Type': 'application/json' }
            })
            .then(function(response) {
                if (!response.ok) throw new Error('Failed to load artifacts');
                return response.json();
            })
            .then(function(data) {
                self.state.items = Array.isArray(data) ? data : [];
                self.state.filteredItems = self.state.items;
                self.state.loading = false;
                self.render();
            })
            .catch(function(error) {
                self.state.error = error.message;
                self.state.loading = false;
                self.renderError();
            });
        },
        
        filterByType: function(type) {
            this.state.currentFilter = type;
            
            if (type === 'all') {
                this.state.filteredItems = this.state.items;
            } else {
                this.state.filteredItems = this.state.items.filter(function(item) {
                    const tags = item.tags || [];
                    return tags.some(function(t) { return t.slug === type; });
                });
            }
            this.render();
        },
        
        render: function() {
            const self = this;
            
            if (this.state.filteredItems.length === 0) {
                this.renderEmpty();
                return;
            }
            
            const html = this.state.filteredItems.map(function(item) {
                return self.renderCard(item);
            }).join('');
            
            this.container.innerHTML = '<div class="bitcore-grid bitcore-grid--auto">' + html + '</div>';
        },
        
        renderCard: function(item) {
            const imageHtml = item.image_url 
                ? '<div class="bitcore-card__image"><img src="' + this.escapeHtml(item.image_url) + '" alt="" loading="lazy"></div>' 
                : '<div class="bitcore-card__image bitcore-card__image--placeholder"><span class="bitcore-icon">📄</span></div>';
            
            const tagsHtml = (item.tags || []).slice(0, 3).map(function(tag) {
                return '<span class="bitcore-tag bitcore-tag--sm">' + self.escapeHtml(tag.name) + '</span>';
            }).join('');
            
            const date = item.created_at ? new Date(item.created_at) : null;
            const dateStr = date ? BITCORE.formatRelativeTime(date) : '';
            
            const url = item.slug ? 'https://hub.bitwiki.org/t/' + item.slug : '#';
            
            return '<article class="bitcore-card bitcore-card--interactive" onclick="window.open(\'' + url + '\', \'_blank\')">' +
                imageHtml +
                '<div class="bitcore-card__body">' +
                '<h3 class="bitcore-card__title">' + this.decodeHtml(item.fancy_title || item.title) + '</h3>' +
                '<p class="bitcore-card__excerpt">' + this.truncate(this.stripHtml(item.excerpt || ''), 120) + '</p>' +
                (tagsHtml ? '<div class="bitcore-card__tags">' + tagsHtml + '</div>' : '') +
                '</div>' +
                '<div class="bitcore-card__footer">' +
                '<span class="bitcore-card__meta">' + dateStr + '</span>' +
                '<span class="bitcore-card__meta">' + (item.views || 0) + ' views</span>' +
                '</div>' +
                '</article>';
        },
        
        renderLoading: function() {
            this.container.innerHTML = '<div class="bitcore-loading">' +
                '<div class="bitcore-skeleton bitcore-skeleton--card"></div>' +
                '<div class="bitcore-skeleton bitcore-skeleton--card"></div>' +
                '<div class="bitcore-skeleton bitcore-skeleton--card"></div>' +
                '</div>';
        },
        
        renderEmpty: function() {
            this.container.innerHTML = '<div class="bitcore-empty">' +
                '<div class="bitcore-empty__icon">🗂️</div>' +
                '<p class="bitcore-empty__text">No artifacts found</p>' +
                '</div>';
        },
        
        renderError: function() {
            this.container.innerHTML = '<div class="bitcore-error">' +
                '<p>Failed to load artifacts: ' + this.escapeHtml(this.state.error) + '</p>' +
                '<button class="bitcore-btn bitcore-btn--primary" onclick="BITCORE_Artifacts.loadArtifacts()">Retry</button>' +
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
            if (!str || str.length <= len) return str;
            return str.substring(0, len).trim() + '...';
        }
    };
    
    var self = Artifacts;
    window.BITCORE_Artifacts = Artifacts;
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() { Artifacts.init(); });
    } else {
        Artifacts.init();
    }
    
})();
