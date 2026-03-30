/**
 * BITCORE Dashboard - Live Stats & Info
 * @package BITCORE_Theme
 * @version 1.1.0
 */

(function() {
    'use strict';
    
    const Dashboard = {
        
        config: {
            feedEndpoint: '/wp-json/bitcore/v1/feed',
            artifactsEndpoint: '/wp-json/bitcore/v1/artifacts',
            abilitiesEndpoint: '/wp-json/wp-abilities/v1/abilities',
            refreshInterval: 60000
        },
        
        state: {
            stats: {
                topics: 0,
                artifacts: 0,
                lastUpdate: null
            },
            loading: false,
            error: null
        },
        
        init: function() {
            if (!document.querySelector('.bitcore-dashboard')) return;
            
            this.loadStats();
            this.initAutoRefresh();
        },
        
        loadStats: function() {
            const self = this;
            this.state.loading = true;
            
            // Fetch feed count
            Promise.all([
                fetch(this.config.feedEndpoint).then(function(r) { return r.ok ? r.json() : []; }).catch(function() { return []; }),
                fetch(this.config.artifactsEndpoint).then(function(r) { return r.ok ? r.json() : []; }).catch(function() { return []; })
            ])
            .then(function(results) {
                const feed = Array.isArray(results[0]) ? results[0] : [];
                const artifacts = Array.isArray(results[1]) ? results[1] : [];
                
                self.state.stats.topics = feed.length;
                self.state.stats.artifacts = artifacts.length;
                self.state.stats.lastUpdate = new Date();
                self.state.loading = false;
                
                self.updateUI();
            })
            .catch(function(error) {
                self.state.loading = false;
                self.state.error = error.message;
                console.error('Dashboard load error:', error);
            });
        },
        
        updateUI: function() {
            // Update stat cards
            this.updateStat('stat-topics', this.state.stats.topics);
            this.updateStat('stat-artifacts', this.state.stats.artifacts);
            
            // Update timestamp
            const timestamp = document.querySelector('.bitcore-dashboard__timestamp');
            if (timestamp && this.state.stats.lastUpdate) {
                timestamp.textContent = 'Last updated: ' + this.state.stats.lastUpdate.toLocaleTimeString();
            }
        },
        
        updateStat: function(id, value) {
            const el = document.getElementById(id);
            if (!el) return;
            
            const formatted = BITCORE.formatNumber ? BITCORE.formatNumber(value) : value.toLocaleString();
            
            if (el.textContent !== formatted) {
                el.style.transform = 'scale(1.1)';
                el.textContent = formatted;
                setTimeout(function() { el.style.transform = 'scale(1)'; }, 200);
            }
        },
        
        initAutoRefresh: function() {
            const self = this;
            
            setInterval(function() {
                self.loadStats();
            }, this.config.refreshInterval);
            
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden) {
                    self.loadStats();
                }
            });
        },
        
        // Site info via abilities API (requires auth)
        loadSiteInfo: function() {
            const self = this;
            
            fetch(this.config.abilitiesEndpoint + '/core~get-site-info/run', {
                method: 'GET',
                headers: { 'Content-Type': 'application/json' }
            })
            .then(function(response) {
                if (response.status === 401) {
                    console.log('Site info requires authentication');
                    return null;
                }
                if (!response.ok) throw new Error('Failed to load site info');
                return response.json();
            })
            .then(function(data) {
                if (data) {
                    self.updateStat('stat-site-name', data.name || 'BITCORE');
                    self.updateStat('stat-site-url', data.url || window.location.hostname);
                }
            })
            .catch(function(error) {
                console.log('Site info not available:', error.message);
            });
        }
    };
    
    window.BITCORE_Dashboard = Dashboard;
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() { Dashboard.init(); });
    } else {
        Dashboard.init();
    }
    
})();
