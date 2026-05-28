<?php
/**
 * Title: Chat Widget
 * Slug: bitcore-theme/chat-widget
 * Categories: bitcore-theme
 * Description: Chat interface for agent communication.
 *
 * @package BITCORE_Theme
 */
?>
<!-- wp:group {"className":"bitcore-chat"} -->
<div class="wp-block-group bitcore-chat">
    <!-- wp:group {"className":"bitcore-chat__header","layout":{"type":"flex","flexWrap":"nowrap"}} -->
    <div class="wp-block-group bitcore-chat__header">
        <div class="bitcore-chat__agent-info">
            <span class="bitcore-chat__agent-avatar">AI</span>
            <span class="bitcore-chat__agent-name">BITCORE Assistant</span>
            <span class="bitcore-status bitcore-status--online">Online</span>
        </div>
    </div>
    <!-- /wp:group -->
    
    <!-- wp:group {"className":"bitcore-chat__messages","layout":{"type":"constrained"}} -->
    <div class="wp-block-group bitcore-chat__messages" id="chat-messages" role="log" aria-live="polite">
        <!-- wp:group {"className":"bitcore-chat__message","layout":{"type":"flex","flexWrap":"nowrap"}} -->
        <div class="wp-block-group bitcore-chat__message">
            <div class="bitcore-chat__avatar">AI</div>
            <div class="bitcore-chat__bubble">Hello! I am your BITCORE assistant. How can I help you today?</div>
        </div>
        <!-- /wp:group -->
    </div>
    <!-- /wp:group -->
    
    <!-- wp:group {"className":"bitcore-chat__input","layout":{"type":"flex","flexWrap":"nowrap"}} -->
    <div class="wp-block-group bitcore-chat__input">
        <input type="text" class="bitcore-input" id="chat-input" placeholder="Type your message..." aria-label="Chat message input">
        <button class="bitcore-btn bitcore-btn--primary" id="chat-send" aria-label="Send message">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        </button>
    </div>
    <!-- /wp:group -->
</div>
<!-- /wp:group -->
