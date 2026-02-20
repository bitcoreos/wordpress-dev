/**
 * BITCORE Librarian - WordPress Plugin JavaScript
 * UI interactivity only - no API credentials
 */

(function($) {
    'use strict';
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        initChatInterface();
        initQueueRefresh();
    });
    
    /**
     * Initialize chat interface
     */
    function initChatInterface() {
        const $input = $('#bitcore-intent-input');
        const $submit = $('#bitcore-submit-intent');
        const $history = $('#bitcore-chat-history');
        
        if (!$input.length || !$submit.length) return;
        
        // Submit on button click
        $submit.on('click', function() {
            submitIntent();
        });
        
        // Submit on Enter (but allow Shift+Enter for new line)
        $input.on('keydown', function(e) {
            if (e.which === 13 && !e.shiftKey) {
                e.preventDefault();
                submitIntent();
            }
        });
        
        // Auto-resize textarea
        $input.on('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });
        
        function submitIntent() {
            const intent = $input.val().trim();
            const scope = $('input[name="scope"]:checked').val() || 'quick';
            
            if (!intent) {
                showError('Please enter what you\'d like to research.');
                return;
            }
            
            // Add user message to chat
            addUserMessage(intent, scope);
            
            // Clear input
            $input.val('').css('height', 'auto');
            
            // Show loading state
            $submit.prop('disabled', true).text('...');
            
            // Send to WordPress AJAX endpoint
            $.ajax({
                url: bitcoreAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bitcore_submit_intent',
                    nonce: bitcoreAjax.nonce,
                    intent: intent,
                    scope: scope
                },
                success: function(response) {
                    if (response.success) {
                        addLibrarianResponse(response.data);
                    } else {
                        addLibrarianError(response.data || 'Unable to queue request.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('BITCORE Librarian Error:', error);
                    addLibrarianError('Connection failed. The librarians will return shortly.');
                },
                complete: function() {
                    $submit.prop('disabled', false).text('→');
                }
            });
        }
        
        function addUserMessage(intent, scope) {
            const scopeLabel = scope === 'quick' ? 'Quick' : 'Deep';
            const $message = $(`
                <div class="bitcore-message bitcore-message-user" style="text-align: right; margin-bottom: 16px; animation: bitcore-fade-in 0.3s ease;">
                    <div class="bitcore-message-content" style="background: var(--bitcore-text); color: white; padding: 12px 16px; border-radius: 12px 12px 4px 12px; display: inline-block; text-align: left; max-width: 80%;">
                        <p style="margin: 0; font-size: 14px; line-height: 1.5;">${escapeHtml(intent)}</p>
                        <span style="font-size: 11px; opacity: 0.7; margin-top: 6px; display: block; font-family: var(--bitcore-mono);">${scopeLabel}</span>
                    </div>
                </div>
            `);
            
            $history.append($message);
            scrollToBottom();
        }
        
        function addLibrarianResponse(data) {
            const $message = $(`
                <div class="bitcore-message bitcore-message-librarian" style="animation: bitcore-fade-in 0.3s ease;">
                    <div class="bitcore-message-content">
                        <p style="margin: 0 0 10px 0; line-height: 1.6;">${escapeHtml(data.message)}</p>
                        <div class="bitcore-queue-info" style="display: flex; gap: 16px; margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--bitcore-border); font-size: 12px; font-family: var(--bitcore-mono);">
                            <span class="bitcore-queue-position" style="color: var(--bitcore-text-sub);">Queue position: ${data.queue_position}</span>
                            <span class="bitcore-estimated-time" style="color: var(--bitcore-accent);">Est: ${data.estimated_time}</span>
                        </div>
                    </div>
                    <span class="bitcore-timestamp" style="font-size: 11px; color: var(--bitcore-text-sub); margin-top: 6px; display: block; font-family: var(--bitcore-mono);">Now</span>
                </div>
            `);
            
            $history.append($message);
            scrollToBottom();
        }
        
        function addLibrarianError(message) {
            const $message = $(`
                <div class="bitcore-message bitcore-message-librarian" style="animation: bitcore-fade-in 0.3s ease;">
                    <div class="bitcore-message-content" style="border-left: 3px solid var(--bitcore-accent-red);">
                        <p style="margin: 0; color: var(--bitcore-accent-red);">⚠ ${escapeHtml(message)}</p>
                    </div>
                    <span class="bitcore-timestamp" style="font-size: 11px; color: var(--bitcore-text-sub); margin-top: 6px; display: block; font-family: var(--bitcore-mono);">Now</span>
                </div>
            `);
            
            $history.append($message);
            scrollToBottom();
        }
        
        function scrollToBottom() {
            $history.animate({ scrollTop: $history[0].scrollHeight }, 300);
        }
        
        function showError(message) {
            // Simple alert for now - could be enhanced with toast
            alert(message);
        }
    }
    
    /**
     * Initialize queue status auto-refresh
     */
    function initQueueRefresh() {
        const $queueList = $('.bitcore-queue-list');
        
        if (!$queueList.length) return;
        
        // Auto-refresh queue every 30 seconds
        setInterval(function() {
            refreshQueueStatus();
        }, 30000);
        
        function refreshQueueStatus() {
            // In production, this would fetch from n8n
            // For now, just animate progress bars
            $('.bitcore-progress-bar').each(function() {
                const $bar = $(this);
                const currentWidth = parseInt($bar.css('width')) || 0;
                const parentWidth = $bar.parent().width();
                const percentage = (currentWidth / parentWidth) * 100;
                
                if (percentage < 100) {
                    const newPercentage = Math.min(percentage + Math.random() * 10, 100);
                    $bar.css('width', newPercentage + '%');
                }
            });
        }
    }
    
    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
})(jQuery);
