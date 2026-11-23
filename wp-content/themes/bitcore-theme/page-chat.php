<?php
/**
 * Template Name: Agent Chat
 * Description: Chat interface for ElizaOS agents (REST polling)
 * @package bitcore-librarian-theme
 * @since 1.2.0
 */
if (!is_user_logged_in()) {
    wp_redirect(bitcore_get_login_url($_SERVER['REQUEST_URI']));
    exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_title('|', true, 'right'); ?> - Agent Chat</title>
    <?php wp_head(); ?>
<?php include(get_template_directory() . '/template-parts/chat-styles.php'); ?>
</head>
<body <?php body_class(); ?>>
<?php include(get_template_directory() . "/nav-header.php"); ?>
    <div class="chat-wrapper">
        <!-- Header -->
        <header class="chat-header">
            <a href="/agents/" class="chat-back">← Agents</a>
            <div class="chat-agent-info">
                <div class="chat-agent-emoji" id="agent-emoji">🤖</div>
                <div>
                    <div class="chat-agent-name" id="agent-name">Agent</div>
                    <div class="chat-agent-style" id="agent-style">Loading...</div>
                </div>
                <div class="chat-agent-status">
                    <span class="dot" id="agent-dot"></span>
                    <span id="agent-status-text">checking</span>
                </div>
            </div>
            <div class="chat-header-actions">
                <button class="btn btn-sm" id="new-session-btn">+ New Session</button>
                <button class="btn btn-sm btn-danger" id="clear-session-btn">Clear</button>
            </div>
        </header>

        <!-- Rate Limit Bar -->
        <div class="rate-bar">
            <span id="rate-label">0 / 100 calls today</span>
            <div class="rate-track">
                <div class="rate-fill" id="rate-fill" style="width: 0%"></div>
            </div>
        </div>

        <!-- Messages -->
        <div class="chat-messages" id="chat-messages">
            <div class="chat-empty" id="chat-empty">
                <div class="empty-emoji" id="empty-emoji">🤖</div>
                <h3 id="empty-title">Start a conversation</h3>
                <p>Send a message to begin chatting with this agent. Each session maintains its own conversation history.</p>
                <div class="starter-grid" aria-label="Starter messages">
                    <button class="starter-btn" data-starter="Help me understand what I can do in ASHI today.">Orient me<span>Show the useful parts of the surface.</span></button>
                    <button class="starter-btn" data-starter="Turn my current idea into an actionable task list.">Plan work<span>Create tasks and next steps.</span></button>
                    <button class="starter-btn" data-starter="Research this topic and return a structured brief with sources.">Research brief<span>Ask for a sourced synthesis.</span></button>
                </div>
            </div>
            <div class="thinking-indicator" id="thinking">
                <div class="thinking-dots">
                    <span></span><span></span><span></span>
                </div>
                <div class="thinking-label" id="thinking-label">Thinking...</div>
            </div>
        </div>

        <!-- Input -->
        <div class="chat-input-area">
            <div class="session-controls">
                <span>Session: <select id="session-select"><option value="">New session</option></select></span>
                <button class="btn btn-sm" id="refresh-sessions-btn">⟳</button>
            </div>
            <div class="chat-input-row">
                <textarea class="chat-input" id="chat-input" placeholder="Type your message..." rows="1"></textarea>
                <button class="btn btn-primary" id="send-btn">Send</button>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div class="toast" id="toast"></div>

    <?php wp_footer(); ?>

<?php include(get_template_directory() . '/template-parts/chat-app-core.php'); ?>
<?php include(get_template_directory() . '/template-parts/chat-app-events.php'); ?>
</body>
</html>
