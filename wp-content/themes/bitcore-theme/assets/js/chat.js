/**
 * BITCORE Chat - AI Chat Interface
 * @package BITCORE_Theme
 * @version 1.0.0
 */

(function() {
    'use strict';
    
    const Chat = {
        
        config: {
            endpoint: '/wp-json/bitcore/v1/chat',
            container: '#bitcore-chat',
            messagesContainer: '#chat-messages',
            input: '#chat-input',
            sendButton: '#chat-send'
        },
        
        state: {
            messages: [],
            loading: false,
            error: null,
            endpointAvailable: null
        },
        
        init: function() {
            this.container = document.querySelector(this.config.container);
            if (!this.container) return;
            
            this.messagesContainer = document.querySelector(this.config.messagesContainer);
            this.input = document.querySelector(this.config.input);
            this.sendButton = document.querySelector(this.config.sendButton);
            
            if (!this.messagesContainer || !this.input || !this.sendButton) return;
            
            this.checkEndpoint();
            this.bindEvents();
        },
        
        checkEndpoint: function() {
            const self = this;
            
            fetch(this.config.endpoint, {
                method: 'GET',
                headers: { 'Content-Type': 'application/json' }
            })
            .then(function(response) {
                self.state.endpointAvailable = response.ok || response.status !== 404;
                if (!self.state.endpointAvailable) {
                    self.showWarning('Chat endpoint not configured. Connect n8n workflow to enable.');
                }
            })
            .catch(function() {
                self.state.endpointAvailable = false;
            });
        },
        
        bindEvents: function() {
            const self = this;
            
            this.sendButton.addEventListener('click', function() {
                self.sendMessage();
            });
            
            this.input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    self.sendMessage();
                }
            });
        },
        
        sendMessage: function() {
            const message = this.input.value.trim();
            if (!message || this.state.loading) return;
            
            // Add user message
            this.addMessage('user', message);
            this.input.value = '';
            
            // Check if endpoint is available
            if (this.state.endpointAvailable === false) {
                this.addMessage('system', 'Chat is not configured. Please set up the n8n workflow for the chat endpoint.');
                return;
            }
            
            // Send to API
            const self = this;
            this.state.loading = true;
            this.showTyping();
            
            fetch(this.config.endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: message })
            })
            .then(function(response) {
                if (!response.ok) throw new Error('Failed to send message');
                return response.json();
            })
            .then(function(data) {
                self.hideTyping();
                self.state.loading = false;
                self.addMessage('assistant', data.response || data.message || 'Message received.');
            })
            .catch(function(error) {
                self.hideTyping();
                self.state.loading = false;
                self.addMessage('system', 'Failed to connect to chat service. Error: ' + error.message);
            });
        },
        
        addMessage: function(role, content) {
            const messageDiv = document.createElement('div');
            messageDiv.className = 'bitcore-chat__message bitcore-chat__message--' + role;
            
            const avatarMap = { user: '👤', assistant: '🤖', system: '⚙️' };
            const avatar = avatarMap[role] || '💬';
            
            messageDiv.innerHTML =
                '<div class="bitcore-chat__avatar">' + avatar + '</div>' +
                '<div class="bitcore-chat__bubble">' + this.escapeHtml(content) + '</div>';
            
            this.messagesContainer.appendChild(messageDiv);
            this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
            
            this.state.messages.push({ role: role, content: content });
        },
        
        showTyping: function() {
            const typing = document.createElement('div');
            typing.id = 'chat-typing';
            typing.className = 'bitcore-chat__message bitcore-chat__message--typing';
            typing.innerHTML =
                '<div class="bitcore-chat__avatar">🤖</div>' +
                '<div class="bitcore-chat__bubble"><span class="bitcore-typing-dots"><span>.</span><span>.</span><span>.</span></span></div>';
            
            this.messagesContainer.appendChild(typing);
            this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
        },
        
        hideTyping: function() {
            const typing = document.getElementById('chat-typing');
            if (typing) typing.remove();
        },
        
        showWarning: function(message) {
            const warning = document.createElement('div');
            warning.className = 'bitcore-chat__warning';
            warning.innerHTML = '⚠️ ' + this.escapeHtml(message);
            this.container.insertBefore(warning, this.messagesContainer);
        },
        
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };
    
    window.BITCORE_Chat = Chat;
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() { Chat.init(); });
    } else {
        Chat.init();
    }
    )();
