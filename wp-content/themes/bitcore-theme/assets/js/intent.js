/**
 * BITCORE Intent - Research Submission Form
 * @package BITCORE_Theme
 * @version 1.0.0
 */

(function() {
    'use strict';
    
    const Intent = {
        
        config: {
            endpoint: '/wp-json/bitcore/v1/intent',
            form: '#bitcore-intent-form',
            titleInput: '#intent-title',
            descriptionInput: '#intent-description',
            scopeSelect: '#intent-scope',
            submitButton: '#intent-submit'
        },
        
        state: {
            submitting: false,
            error: null
        },
        
        init: function() {
            this.form = document.querySelector(this.config.form);
            if (!this.form) return;
            
            this.titleInput = document.querySelector(this.config.titleInput);
            this.descriptionInput = document.querySelector(this.config.descriptionInput);
            this.scopeSelect = document.querySelector(this.config.scopeSelect);
            this.submitButton = document.querySelector(this.config.submitButton);
            
            if (!this.titleInput || !this.submitButton) return;
            
            this.bindEvents();
        },
        
        bindEvents: function() {
            const self = this;
            
            this.form.addEventListener('submit', function(e) {
                e.preventDefault();
                self.submitIntent();
            });
            
            this.submitButton.addEventListener('click', function(e) {
                e.preventDefault();
                self.submitIntent();
            });
        },
        
        submitIntent: function() {
            if (this.state.submitting) return;
            
            const title = this.titleInput.value.trim();
            const description = this.descriptionInput ? this.descriptionInput.value.trim() : '';
            const scope = this.scopeSelect ? this.scopeSelect.value : 'general';
            
            if (!title) {
                this.showError('Please enter a title for your research request.');
                return;
            }
            
            const self = this;
            this.state.submitting = true;
            this.setLoading(true);
            
            fetch(this.config.endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    title: title,
                    description: description,
                    scope: scope
                })
            })
            .then(function(response) {
                if (response.status === 401) {
                    throw new Error('Authentication required. Please log in to submit research requests.');
                }
                if (response.status === 404) {
                    throw new Error('Intent endpoint not configured. Please set up the n8n workflow.');
                }
                if (!response.ok) {
                    throw new Error('Failed to submit request (HTTP ' + response.status + ')');
                }
                return response.json();
            })
            .then(function(data) {
                self.state.submitting = false;
                self.setLoading(false);
                self.showSuccess('Research request submitted successfully!');
                self.form.reset();
            })
            .catch(function(error) {
                self.state.submitting = false;
                self.setLoading(false);
                self.showError(error.message);
            });
        },
        
        setLoading: function(loading) {
            if (loading) {
                this.submitButton.disabled = true;
                this.submitButton.innerHTML = '<span class="bitcore-spinner"></span> Submitting...';
            } else {
                this.submitButton.disabled = false;
                this.submitButton.textContent = 'Submit Request';
            }
        },
        
        showSuccess: function(message) {
            if (window.BITCORE && window.BITCORE.notifications) {
                window.BITCORE.notifications.success(message);
            } else {
                this.showToast(message, 'success');
            }
        },
        
        showError: function(message) {
            if (window.BITCORE && window.BITCORE.notifications) {
                window.BITCORE.notifications.error(message);
            } else {
                this.showToast(message, 'error');
            }
        },
        
        showToast: function(message, type) {
            const toast = document.createElement('div');
            toast.className = 'bitcore-toast bitcore-toast--' + type;
            toast.innerHTML = '<span class="bitcore-toast__message">' + this.escapeHtml(message) + '</span>';
            
            let container = document.querySelector('.bitcore-toast-container');
            if (!container) {
                container = document.createElement('div');
                container.className = 'bitcore-toast-container';
                container.style.cssText = 'position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;';
                document.body.appendChild(container);
            }
            
            container.appendChild(toast);
            
            setTimeout(function() {
                toast.style.animation = 'slideOut 0.3s ease forwards';
                setTimeout(function() { toast.remove(); }, 300);
            }, 5000);
        },
        
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };
    
    window.BITCORE_Intent = Intent;
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() { Intent.init(); });
    } else {
        Intent.init();
    }
    
})();
