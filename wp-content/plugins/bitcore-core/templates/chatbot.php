<?php
declare(strict_types=1);
/**
 * chatbot
 *
 * @package    Bitcore
 * @since      2026.03.01
 * @version    1.3.0
 * @author     hyperion
 */


if (!defined('ABSPATH')) {
    exit;
}

/**
 * BITCORE Chatbot Template
 */
?>
<div class="bitcore-chatbot" data-agent="<?php echo esc_attr($atts['agent']); ?>" data-theme="<?php echo esc_attr($atts['theme']); ?>">
    <div class="chatbot-header">
        <div class="chatbot-avatar bitflux-avatar" data-state="welcome"></div>
        <div class="chatbot-info">
            <h3><?php echo esc_html(ucfirst($atts['agent'])); ?></h3>
            <span class="chatbot-status">Online</span>
        </div>
    </div>
    <div class="chatbot-messages" id="chat-messages">
        <div class="chat-message agent">Welcome to BITCORE. How can I help you today?</div>
    </div>
    <div class="chatbot-input">
        <input type="text" id="chat-input" placeholder="Type your message..." />
        <button id="chat-send">Send</button>
    </div>
    <input type="hidden" id="chat-conversation-id" value="<?php echo esc_attr($this->core->session_service->get_conversation_id()); ?>" />
</div>
