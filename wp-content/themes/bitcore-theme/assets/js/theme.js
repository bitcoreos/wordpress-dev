/**
 * BITCORE Theme - Core JavaScript
 *
 * @package BITCORE_Theme
 * @version 1.0.0
 */

(function() {
    'use strict';
    
    const BITCORE = {
        
        /**
         * Initialize theme
         */
        init: function() {
            this.initSidebar();
            this.initNavigation();
            this.initAccessibility();
            this.initNotifications();
        },
        
        /**
         * Sidebar toggle functionality
         */
        initSidebar: function() {
            const sidebar = document.querySelector('.bitcore-sidebar');
            const layout = document.querySelector('.bitcore-layout');
            const toggle = document.querySelector('[data-sidebar-toggle]');
            
            if (!sidebar || !layout) return;
            
            // Load saved state
            const isCollapsed = localStorage.getItem('bitcore-sidebar-collapsed') === 'true';
            if (isCollapsed) {
                layout.classList.add('collapsed');
            }
            
            if (toggle) {
                toggle.addEventListener('click', function() {
                    layout.classList.toggle('collapsed');
                    localStorage.setItem('bitcore-sidebar-collapsed', layout.classList.contains('collapsed'));
                });
            }
            
            // Mobile sidebar
            const mobileToggle = document.querySelector('[data-mobile-menu]');
            const overlay = document.querySelector('.bitcore-overlay');
            
            if (mobileToggle) {
                mobileToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('open');
                    if (overlay) overlay.classList.toggle('active');
                });
            }
            
            if (overlay) {
                overlay.addEventListener('click', function() {
                    sidebar.classList.remove('open');
                    overlay.classList.remove('active');
                });
            }
        },
        
        /**
         * Navigation active state
         */
        initNavigation: function() {
            const navLinks = document.querySelectorAll('.bitcore-nav__link');
            const currentPath = window.location.pathname;
            
            navLinks.forEach(function(link) {
                const href = link.getAttribute('href');
                if (href && currentPath.includes(href.replace(/\/$/, ''))) {
                    link.classList.add('bitcore-nav__link--active');
                }
            });
        },
        
        /**
         * Accessibility enhancements
         */
        initAccessibility: function() {
            // Skip link focus
            const skipLink = document.querySelector('.bitcore-skip-link');
            if (skipLink) {
                skipLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    const target = document.querySelector(skipLink.getAttribute('href'));
                    if (target) {
                        target.setAttribute('tabindex', '-1');
                        target.focus();
                    }
                });
            }
            
            // Keyboard navigation for cards
            const cards = document.querySelectorAll('.bitcore-card--interactive');
            cards.forEach(function(card) {
                card.setAttribute('tabindex', '0');
                card.setAttribute('role', 'button');
                
                card.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        card.click();
                    }
                });
            });
        },
        
        /**
         * Notification system
         */
        initNotifications: function() {
            this.notifications = {
                container: null,
                
                createContainer: function() {
                    if (!this.container) {
                        this.container = document.createElement('div');
                        this.container.className = 'bitcore-toast-container';
                        this.container.style.cssText = 'position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;';
                        document.body.appendChild(this.container);
                    }
                    return this.container;
                },
                
                show: function(message, type = 'info', duration = 5000) {
                    const container = this.createContainer();
                    const toast = document.createElement('div');
                    toast.className = 'bitcore-toast bitcore-toast--' + type;
                    toast.innerHTML = '<span class="bitcore-toast__message">' + message + '</span>';
                    container.appendChild(toast);
                    
                    setTimeout(function() {
                        toast.style.animation = 'slideOut 0.3s ease forwards';
                        setTimeout(function() { toast.remove(); }, 300);
                    }, duration);
                    
                    return toast;
                },
                
                success: function(message, duration) { return this.show(message, 'success', duration); },
                error: function(message, duration) { return this.show(message, 'error', duration); },
                warning: function(message, duration) { return this.show(message, 'warning', duration); },
                info: function(message, duration) { return this.show(message, 'info', duration); }
            };
        },
        
        /**
         * Utility: Debounce
         */
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },
        
        /**
         * Utility: Format number with commas
         */
        formatNumber: function(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        },
        
        /**
         * Utility: Format relative time
         */
        formatRelativeTime: function(date) {
            const now = new Date();
            const diff = Math.floor((now - date) / 1000);
            
            if (diff < 60) return 'just now';
            if (diff < 3600) return Math.floor(diff / 60) + ' min ago';
            if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
            if (diff < 604800) return Math.floor(diff / 86400) + ' days ago';
            
            return date.toLocaleDateString();
        }
    };
    
    // Expose globally
    window.BITCORE = BITCORE;
    
    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() { BITCORE.init(); });
    } else {
        BITCORE.init();
    }
    
})();
