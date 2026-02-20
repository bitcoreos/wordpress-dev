<?php
/**
 * Template: Chat Interface
 * Conversational UI for submitting research intents
 * NO API credentials - UI only
 */
?>
<div class="bitcore-chat-container">
    
    <!-- Header -->
    <div class="bitcore-chat-header">
        <div class="bitcore-avatar">🧠</div>
        <div class="bitcore-header-text">
            <h3>Coraline</h3>
            <span class="bitcore-status">Librarian Online</span>
        </div>
    </div>
    
    <!-- Chat History -->
    <div class="bitcore-chat-history" id="bitcore-chat-history">
        
        <!-- Welcome Message -->
        <div class="bitcore-message bitcore-message-librarian">
            <div class="bitcore-message-content">
                <p>Hello. I'm Coraline, a librarian for the BITCORE knowledge system.</p>
                <p>Tell me what you'd like to research, and I'll queue it for the swarm.</p>
            </div>
            <span class="bitcore-timestamp">Now</span>
        </div>
        
    </div>
    
    <!-- Input Area -->
    <div class="bitcore-chat-input-area">
        <div class="bitcore-scope-selector">
            <label>
                <input type="radio" name="scope" value="quick" checked>
                <span>Quick (5-10 min)</span>
            </label>
            <label>
                <input type="radio" name="scope" value="deep">
                <span>Deep (30-60 min)</span>
            </label>
        </div>
        
        <div class="bitcore-input-row">
            <textarea 
                id="bitcore-intent-input" 
                placeholder="I want to research..."
                rows="2"
            ></textarea>
            <button id="bitcore-submit-intent" class="bitcore-send-btn" aria-label="Send">
                →
            </button>
        </div>
        
        <p class="bitcore-privacy-note">
            Your request will be queued for the librarian swarm. 
            <a href="#" class="bitcore-queue-link">View queue status →</a>
        </p>
    </div>
    
</div>
