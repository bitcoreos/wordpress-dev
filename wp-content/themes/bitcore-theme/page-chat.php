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
    <style>
        :root {
            --bg-primary: #0a0a0f;
            --bg-secondary: #12121a;
            --bg-tertiary: #1a1a25;
            --bg-elevated: #22222e;
            --text-primary: #f0f0f5;
            --text-secondary: #a0a0b0;
            --text-muted: #6a6a7a;
            --accent-primary: #00d4aa;
            --accent-secondary: #6366f1;
            --accent-warning: #f59e0b;
            --accent-danger: #ef4444;
            --accent-success: #22c55e;
            --border-default: #2a2a3a;
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 16px;
            --space-xs: 4px;
            --space-sm: 8px;
            --space-md: 16px;
            --space-lg: 24px;
            --space-xl: 32px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* Chat Layout */
        .chat-wrapper {
            display: flex;
            flex-direction: column;
            height: 100vh;
            max-width: 900px;
            margin: 0 auto;
            width: 100%;
        }

        /* Header */
        .chat-header {
            display: flex;
            align-items: center;
            gap: var(--space-md);
            padding: var(--space-md) var(--space-lg);
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-default);
            flex-shrink: 0;
        }
        .chat-back {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 4px;
            transition: color 0.2s;
        }
        .chat-back:hover { color: var(--accent-primary); }
        .chat-agent-info {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            flex: 1;
        }
        .chat-agent-emoji {
            font-size: 1.5rem;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-tertiary);
            border-radius: var(--radius-md);
        }
        .chat-agent-name {
            font-weight: 600;
            font-size: 1.1rem;
        }
        .chat-agent-style {
            font-size: 0.75rem;
            color: var(--accent-secondary);
        }
        .chat-agent-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.7rem;
            color: var(--text-muted);
        }
        .chat-agent-status .dot {
            width: 7px; height: 7px;
            border-radius: 50%;
            background: var(--accent-success);
        }
        .chat-agent-status .dot.inactive { background: var(--text-muted); }
        .chat-header-actions {
            display: flex;
            gap: var(--space-sm);
            align-items: center;
        }

        /* Rate Limit Bar */
        .rate-bar {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            padding: var(--space-xs) var(--space-md);
            background: var(--bg-tertiary);
            border-bottom: 1px solid var(--border-default);
            font-size: 0.75rem;
            color: var(--text-muted);
            flex-shrink: 0;
        }
        .rate-bar .rate-track {
            flex: 1;
            height: 4px;
            background: var(--bg-elevated);
            border-radius: 2px;
            overflow: hidden;
        }
        .rate-bar .rate-fill {
            height: 100%;
            background: var(--accent-primary);
            border-radius: 2px;
            transition: width 0.3s;
        }
        .rate-bar .rate-fill.warning { background: var(--accent-warning); }
        .rate-bar .rate-fill.danger { background: var(--accent-danger); }

        /* Messages Area */
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: var(--space-lg);
            display: flex;
            flex-direction: column;
            gap: var(--space-md);
        }

        .chat-messages::-webkit-scrollbar {
            width: 6px;
        }
        .chat-messages::-webkit-scrollbar-track {
            background: var(--bg-primary);
        }
        .chat-messages::-webkit-scrollbar-thumb {
            background: var(--border-default);
            border-radius: 3px;
        }

        /* Empty State */
        .chat-empty {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            text-align: center;
            padding: var(--space-xl);
        }
        .chat-empty .empty-emoji {
            font-size: 3rem;
            margin-bottom: var(--space-md);
        }
        .chat-empty h3 {
            font-size: 1.1rem;
            color: var(--text-secondary);
            margin-bottom: var(--space-xs);
        }
        .chat-empty p {
            font-size: 0.85rem;
            max-width: 400px;
        }

        /* Message Bubbles */
        .message {
            display: flex;
            flex-direction: column;
            max-width: 75%;
            animation: fadeIn 0.2s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .message.user {
            align-self: flex-end;
        }
        .message.agent {
            align-self: flex-start;
        }
        .message-bubble {
            padding: var(--space-sm) var(--space-md);
            border-radius: var(--radius-md);
            line-height: 1.5;
            font-size: 0.9rem;
            word-wrap: break-word;
        }
        .message.user .message-bubble {
            background: var(--accent-secondary);
            color: #fff;
            border-bottom-right-radius: var(--radius-xs, 4px);
        }
        .message.agent .message-bubble {
            background: var(--bg-elevated);
            color: var(--text-primary);
            border-bottom-left-radius: var(--radius-xs, 4px);
        }
        .message-meta {
            font-size: 0.7rem;
            color: var(--text-muted);
            margin-top: var(--space-xs);
            padding: 0 var(--space-xs);
        }
        .message.user .message-meta {
            text-align: right;
        }

        /* Thinking Indicator */
        .thinking-indicator {
            display: none;
            align-self: flex-start;
            flex-direction: column;
            max-width: 75%;
        }
        .thinking-indicator.visible {
            display: flex;
        }
        .thinking-dots {
            display: flex;
            gap: 4px;
            padding: var(--space-sm) var(--space-md);
            background: var(--bg-elevated);
            border-radius: var(--radius-md);
            border-bottom-left-radius: 4px;
        }
        .thinking-dots span {
            width: 8px; height: 8px;
            background: var(--text-muted);
            border-radius: 50%;
            animation: bounce 1.4s infinite;
        }
        .thinking-dots span:nth-child(2) { animation-delay: 0.2s; }
        .thinking-dots span:nth-child(3) { animation-delay: 0.4s; }
        @keyframes bounce {
            0%, 80%, 100% { transform: translateY(0); opacity: 0.4; }
            40% { transform: translateY(-6px); opacity: 1; }
        }
        .thinking-label {
            font-size: 0.7rem;
            color: var(--text-muted);
            margin-top: var(--space-xs);
            padding: 0 var(--space-xs);
        }

        /* Input Area */
        .chat-input-area {
            padding: var(--space-md) var(--space-lg);
            background: var(--bg-secondary);
            border-top: 1px solid var(--border-default);
            flex-shrink: 0;
        }
        .session-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-sm);
            font-size: 0.75rem;
            color: var(--text-muted);
        }
        .session-controls select {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: 1px solid var(--border-default);
            border-radius: var(--radius-sm);
            padding: 4px var(--space-sm);
            font-size: 0.75rem;
            font-family: inherit;
        }
        .chat-input-row {
            display: flex;
            gap: var(--space-sm);
        }
        .chat-input {
            flex: 1;
            padding: var(--space-sm) var(--space-md);
            background: var(--bg-tertiary);
            border: 1px solid var(--border-default);
            border-radius: var(--radius-md);
            color: var(--text-primary);
            font-size: 0.9rem;
            font-family: inherit;
            resize: none;
            min-height: 44px;
            max-height: 120px;
            outline: none;
            transition: border-color 0.2s;
        }
        .chat-input:focus {
            border-color: var(--accent-primary);
        }
        .chat-input::placeholder {
            color: var(--text-muted);
        }
        .btn {
            padding: var(--space-sm) var(--space-md);
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-default);
            background: var(--bg-tertiary);
            color: var(--text-primary);
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.85rem;
            font-family: inherit;
        }
        .btn:hover { background: var(--bg-elevated); border-color: var(--accent-primary); }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-primary {
            background: var(--accent-primary);
            border-color: var(--accent-primary);
            color: #000;
            font-weight: 500;
        }
        .btn-primary:hover { opacity: 0.9; }
        .btn-primary:disabled { opacity: 0.5; }
        .btn-sm { padding: 4px 10px; font-size: 0.75rem; }
        .btn-danger {
            color: var(--accent-danger);
            border-color: var(--accent-danger);
        }
        .btn-danger:hover { background: rgba(239, 68, 68, 0.1); }

        .starter-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 18px;
            width: min(760px, 100%);
        }
        .starter-btn {
            border: 1px solid var(--border-default);
            border-radius: 10px;
            background: var(--bg-tertiary);
            color: var(--text-primary);
            padding: 12px;
            text-align: left;
            cursor: pointer;
            font: inherit;
        }
        .starter-btn:hover { border-color: var(--accent-primary); }
        .starter-btn span { display: block; color: var(--text-muted); font-size: 0.78rem; margin-top: 4px; }

        /* Toast */
        .toast {
            position: fixed;
            top: var(--space-lg);
            right: var(--space-lg);
            padding: var(--space-md) var(--space-lg);
            background: var(--bg-elevated);
            border: 1px solid var(--border-default);
            border-radius: var(--radius-md);
            color: var(--text-primary);
            font-size: 0.875rem;
            z-index: 1000;
            opacity: 0;
            transform: translateY(-10px);
            transition: all 0.3s;
            pointer-events: none;
        }
        .toast.show {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }
        .toast.success { border-left: 4px solid var(--accent-success); }
        .toast.error { border-left: 4px solid var(--accent-danger); }
        .toast.warning { border-left: 4px solid var(--accent-warning); }

        /* Error message */
        .error-message {
            text-align: center;
            padding: var(--space-md);
            color: var(--accent-danger);
            font-size: 0.85rem;
        }

        /* Mobile */
        @media (max-width: 900px) {
            .chat-wrapper {
                max-width: 100%;
            }
            .chat-header {
                padding: var(--space-sm) var(--space-md);
            }
            .chat-messages {
                padding: var(--space-md);
            }
            .starter-grid { grid-template-columns: 1fr; }
            .chat-input-area {
                padding: var(--space-sm) var(--space-md);
            }
            .message {
                max-width: 85%;
            }
            .chat-agent-style {
                display: none;
            }
        }
    </style>
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

    <script>
    (function() {
        'use strict';

        // Agent definitions
        const AGENTS = {
            'aria': {
                id: 'eac677dd-94c4-02ab-bfea-bc554e52b2a3',
                name: 'Aria',
                emoji: '🔬',
                style: 'Analytical & Precise',
                description: 'Evidence-first research agent'
            },
            'forge': {
                id: '3ab11b75-ca36-0cee-a3d7-7bfcab3c27cd',
                name: 'Forge',
                emoji: '🔨',
                style: 'Practical & Action-Oriented',
                description: 'Gets things done'
            },
            'muse': {
                id: 'cf0dee2b-9fc6-041b-83a0-90a644d26362',
                name: 'Muse',
                emoji: '✨',
                style: 'Creative & Exploratory',
                description: 'Ideation and strategy'
            }
        };

        const API_BASE = '<?php echo esc_url(rest_url("bitcore/v1/eliza")); ?>';
        const EVENTS_STREAM = '<?php echo esc_url(rest_url("bitcore/v1/events/stream")); ?>';
        const NONCE = '<?php echo wp_create_nonce("wp_rest"); ?>';

        // Resolve agent from URL param
        const urlParams = new URLSearchParams(window.location.search);
        const agentSlug = urlParams.get('agent') || 'aria';
        const agent = AGENTS[agentSlug] || AGENTS['aria'];

        // State
        let currentSessionId = null;
        let messages = [];
        let isSending = false;
        let realtimeSource = null;
        let rateUsed = 0;
        let rateLimit = 100;
        let seenMessageIds = new Set();
        let lastMessageCursor = 0;

        // ---- DOM Refs ----
        const chatMessages = document.getElementById('chat-messages');
        const chatInput = document.getElementById('chat-input');
        const sendBtn = document.getElementById('send-btn');
        const thinkingEl = document.getElementById('thinking');
        const thinkingLabel = document.getElementById('thinking-label');
        const chatEmpty = document.getElementById('chat-empty');
        const sessionSelect = document.getElementById('session-select');
        const rateLabel = document.getElementById('rate-label');
        const rateFill = document.getElementById('rate-fill');

        // ---- Init Agent Header ----
        function initAgentHeader() {
            document.getElementById('agent-emoji').textContent = agent.emoji;
            document.getElementById('agent-name').textContent = agent.name;
            document.getElementById('agent-style').textContent = agent.style;
            document.getElementById('empty-emoji').textContent = agent.emoji;
            document.getElementById('empty-title').textContent = 'Chat with ' + agent.name;
            document.title = agent.name + ' Chat - Agent Chat';
        }

        // ---- API Helpers ----
        async function apiGet(path) {
            const res = await fetch(API_BASE + path, {
                headers: { 'X-WP-Nonce': NONCE }
            });
            if (!res.ok) {
                const err = await res.json().catch(function() { return {}; });
                throw new Error(err.message || 'API error: ' + res.status);
            }
            return res.json();
        }

        async function apiPost(path, body) {
            const res = await fetch(API_BASE + path, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': NONCE
                },
                body: JSON.stringify(body || {})
            });
            if (!res.ok) {
                const err = await res.json().catch(function() { return {}; });
                if (res.status === 429) {
                    throw new Error('Daily call limit reached. Limit resets at midnight UTC.');
                }
                throw new Error(err.message || 'API error: ' + res.status);
            }
            return res.json();
        }

        async function apiDelete(path) {
            const res = await fetch(API_BASE + path, {
                method: 'DELETE',
                headers: { 'X-WP-Nonce': NONCE }
            });
            if (!res.ok) throw new Error('API error: ' + res.status);
            return res.json();
        }

        // ---- Toast ----
        function showToast(message, type) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = 'toast ' + type + ' show';
            setTimeout(function() { toast.className = 'toast'; }, 3500);
        }

        // ---- Rate Limit ----
        function updateRateDisplay() {
            const safeLimit = Math.max(Number(rateLimit) || 100, 1);
            const safeUsed = Math.max(Number(rateUsed) || 0, 0);
            const pct = Math.min((safeUsed / safeLimit) * 100, 100);
            rateLabel.textContent = safeUsed + ' / ' + safeLimit + ' calls today';
            rateFill.style.width = pct + '%';
            rateFill.className = 'rate-fill' +
                (pct > 90 ? ' danger' : pct > 70 ? ' warning' : '');
        }

        async function loadRateLimit() {
            try {
                const data = await apiGet('/rate-limit');
                if (data && typeof data.limit !== 'undefined') {
                    rateLimit = Number(data.limit) || rateLimit;
                }
                if (data && typeof data.remaining !== 'undefined') {
                    const remaining = Math.max(Number(data.remaining) || 0, 0);
                    rateUsed = Math.max(rateLimit - remaining, 0);
                }
                updateRateDisplay();
            } catch (e) {
                updateRateDisplay();
            }
        }

        // ---- Agent Status ----
        async function checkAgentStatus() {
            const dot = document.getElementById('agent-dot');
            const statusText = document.getElementById('agent-status-text');
            try {
                const data = await apiGet('/agents');
                const agents = Array.isArray(data) ? data : (data.agents || []);
                const found = agents.find(function(a) { return a.id === agent.id; });
                if (found && (found.status === 'active' || found.status === 'running')) {
                    dot.className = 'dot';
                    statusText.textContent = 'active';
                } else {
                    dot.className = 'dot inactive';
                    statusText.textContent = 'inactive';
                }
            } catch (e) {
                dot.className = 'dot inactive';
                statusText.textContent = 'unknown';
            }
        }

        // ---- Sessions ----
        async function loadSessions() {
            try {
                const data = await apiGet('/sessions');
                const sessions = Array.isArray(data) ? data : (data.sessions || []);
                sessionSelect.innerHTML = '<option value="">New session</option>';
                sessions.forEach(function(s) {
                    const opt = document.createElement('option');
                    opt.value = s.id || s.sessionId || '';
                    opt.textContent = (s.name || s.id || s.sessionId || '').substring(0, 30);
                    sessionSelect.appendChild(opt);
                });
                if (currentSessionId && !sessions.find(function(s) { return (s.id || s.sessionId) === currentSessionId; })) {
                    currentSessionId = null;
                }
            } catch (e) {
                // Sessions unavailable - that's ok
            }
        }

        async function createSessionInternal(silent) {
            try {
                const data = await apiPost('/sessions', { agentId: agent.id });
                currentSessionId = data.id || data.sessionId || '';
                seenMessageIds = new Set();
                lastMessageCursor = 0;
                await loadSessions();
                if (currentSessionId) {
                    sessionSelect.value = currentSessionId;
                }
                if (!silent) { showToast('New session started', 'success'); }
            } catch (e) {
                // If session creation fails, continue without session ID
                currentSessionId = null;
            }
        }

        async function createSession() {
            await createSessionInternal(false);
        }

        async function autoStartSession() {
            if (currentSessionId) return;
            await createSessionInternal(true);
        }

        async function clearSession() {
            if (currentSessionId) {
                try {
                    await apiDelete('/sessions/' + currentSessionId);
                } catch (e) {
                    // Ignore cleanup errors
                }
            }
            currentSessionId = null;
            messages = [];
            seenMessageIds = new Set();
            lastMessageCursor = 0;
            renderMessages();
            chatEmpty.style.display = 'flex';
            sessionSelect.value = '';
            showToast('Session cleared', 'success');
        }

        // ---- Messages ----
        function formatTime(ts) {
            if (!ts) return '';
            var d = new Date(ts);
            return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }

        function getMessageId(msg) {
            return msg && (msg.id || msg.messageId || msg.uuid || '');
        }

        function getMessageTimestamp(msg) {
            var raw = msg && (msg.createdAt || msg.created_at || msg.timestamp || '');
            if (typeof raw === 'number') return raw;
            if (typeof raw === 'string' && raw !== '') {
                var parsed = Date.parse(raw);
                return Number.isNaN(parsed) ? 0 : parsed;
            }
            return 0;
        }

        function isAgentMessage(msg) {
            if (!msg || typeof msg !== 'object') return false;
            if (msg.isAgent === true) return true;
            if (msg.role === 'agent' || msg.source_type === 'agent_response') return true;
            var author = msg.authorId || msg.author_id || msg.senderId || msg.sender_id || '';
            return Boolean(author && author === agent.id);
        }

        function normalizeSessionMessage(msg) {
            return {
                id: getMessageId(msg),
                role: isAgentMessage(msg) ? 'agent' : 'user',
                content: msg.content || msg.text || msg.message || msg.response || '',
                timestamp: msg.createdAt || msg.created_at || msg.timestamp || new Date().toISOString()
            };
        }

        function addSessionMessage(msg) {
            var normalized = normalizeSessionMessage(msg);
            if (!normalized.content) return false;
            if (normalized.id && seenMessageIds.has(normalized.id)) return false;
            if (normalized.id) seenMessageIds.add(normalized.id);
            messages.push(normalized);
            var cursor = getMessageTimestamp(msg);
            if (cursor > lastMessageCursor) lastMessageCursor = cursor;
            return true;
        }

        async function loadSessionMessages(limit) {
            if (!currentSessionId) return false;
            var data = await apiGet('/sessions/' + encodeURIComponent(currentSessionId) + '/messages?limit=' + encodeURIComponent(limit || 50));
            var list = Array.isArray(data) ? data : (data.messages || []);
            var changed = false;
            list.forEach(function(item) {
                if (addSessionMessage(item)) changed = true;
            });
            if (data && data.cursors && data.cursors.after) {
                lastMessageCursor = Math.max(Number(data.cursors.after) || 0, lastMessageCursor);
            }
            if (changed) renderMessages();
            return changed;
        }

        async function pollForAgentReply(previousCursor) {
            if (!currentSessionId) return false;
            var started = Date.now();
            var attempts = 0;
            while (Date.now() - started < 20000 && attempts < 10) {
                attempts++;
                var after = Math.max(Number(previousCursor) || 0, 0);
                var path = '/sessions/' + encodeURIComponent(currentSessionId) + '/messages?limit=20' + (after ? '&after=' + encodeURIComponent(after) : '');
                var data = await apiGet(path);
                var list = Array.isArray(data) ? data : (data.messages || []);
                var foundAgent = false;
                list.forEach(function(item) {
                    var cursor = getMessageTimestamp(item);
                    if (cursor > lastMessageCursor) lastMessageCursor = cursor;
                    if (isAgentMessage(item) && addSessionMessage(item)) {
                        foundAgent = true;
                    }
                });
                if (data && data.cursors && data.cursors.after) {
                    lastMessageCursor = Math.max(Number(data.cursors.after) || 0, lastMessageCursor);
                }
                if (foundAgent) {
                    renderMessages();
                    return true;
                }
                await new Promise(function(resolve) { setTimeout(resolve, attempts < 3 ? 1000 : 2000); });
            }
            return false;
        }

        function renderMessages() {
            // Remove existing messages but keep thinking indicator
            var existing = chatMessages.querySelectorAll('.message');
            existing.forEach(function(el) { el.remove(); });

            if (messages.length === 0) {
                chatEmpty.style.display = 'flex';
                return;
            }

            chatEmpty.style.display = 'none';

            messages.forEach(function(msg) {
                var div = document.createElement('div');
                div.className = 'message ' + (msg.role === 'user' ? 'user' : 'agent');

                var bubble = document.createElement('div');
                bubble.className = 'message-bubble';
                bubble.textContent = msg.content || msg.text || '';

                var meta = document.createElement('div');
                meta.className = 'message-meta';
                meta.textContent = msg.role === 'user' ? 'You' : agent.name;

                div.appendChild(bubble);
                div.appendChild(meta);
                chatMessages.insertBefore(div, thinkingEl);
            });

            scrollToBottom();
        }

        function scrollToBottom() {
            requestAnimationFrame(function() {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            });
        }

        // ---- Send Message ----
        async function sendMessage() {
            var text = chatInput.value.trim();
            if (!text || isSending) return;

            isSending = true;
            sendBtn.disabled = true;
            chatInput.value = '';
            autoResizeInput();

            // Add user message locally
            messages.push({ role: 'user', content: text, timestamp: new Date().toISOString() });
            renderMessages();

            // Show thinking
            thinkingEl.className = 'thinking-indicator visible';
            thinkingLabel.textContent = agent.name + ' is thinking...';
            scrollToBottom();

            try {
                var body = {
                    message: text,
                    agentId: agent.id
                };
                if (currentSessionId) {
                    body.sessionId = currentSessionId;
                }

                var response = await apiPost('/chat', body);
                rateUsed++;
                updateRateDisplay();

                // The Sessions API POST response is a submitted-message acknowledgement.
                // Capture session ID, then fetch actual assistant replies from session messages.
                if (response.sessionId || response.session_id || response.channel_id) {
                    currentSessionId = response.sessionId || response.session_id || response.channel_id;
                    sessionSelect.value = currentSessionId;
                }

                var priorCursor = lastMessageCursor;
                var gotReply = await pollForAgentReply(priorCursor);
                if (!gotReply) {
                    messages.push({
                        role: 'agent',
                        content: agent.name + ' accepted the message. Waiting for the reply stream...',
                        timestamp: new Date().toISOString()
                    });
                    renderMessages();
                }
                loadSessions();
                loadRateLimit();
            } catch (e) {
                // Show error as agent message
                messages.push({
                    role: 'agent',
                    content: '⚠ Error: ' + e.message,
                    timestamp: new Date().toISOString()
                });
                renderMessages();
                showToast(e.message, 'error');
            } finally {
                thinkingEl.className = 'thinking-indicator';
                isSending = false;
                sendBtn.disabled = false;
                chatInput.focus();
            }
        }

        // ---- Auto-Resize Textarea ----
        function autoResizeInput() {
            chatInput.style.height = 'auto';
            chatInput.style.height = Math.min(chatInput.scrollHeight, 120) + 'px';
        }

        // ---- Realtime Events ----
        function connectRealtime() {
            if (!('EventSource' in window)) {
                return;
            }
            if (realtimeSource) {
                realtimeSource.close();
            }
            const streamUrl = EVENTS_STREAM + '?_wpnonce=' + encodeURIComponent(NONCE);
            realtimeSource = new EventSource(streamUrl);
            realtimeSource.addEventListener('THINKING', function(event) {
                handleRealtimeEvent('THINKING', parseRealtimePayload(event));
            });
            realtimeSource.addEventListener('messageComplete', function(event) {
                handleRealtimeEvent('messageComplete', parseRealtimePayload(event));
            });
            realtimeSource.addEventListener('message_sent', function(event) {
                handleRealtimeEvent('message_sent', parseRealtimePayload(event));
            });
            realtimeSource.addEventListener('system', function(event) {
                handleRealtimeEvent('system', parseRealtimePayload(event));
            });
            realtimeSource.onerror = function() {
                // EventSource reconnects automatically; keep UI usable during reconnects.
            };
        }

        function parseRealtimePayload(event) {
            try {
                const parsed = JSON.parse(event.data || '{}');
                return parsed.payload || parsed;
            } catch (e) {
                return {};
            }
        }

        function isRelevantRealtimePayload(payload) {
            if (!payload || typeof payload !== 'object') {
                return false;
            }
            const payloadSession = payload.sessionId || payload.session_id || payload.channel_id || payload.channelId || null;
            const payloadAgent = payload.agentId || payload.agent_id || payload.targetAgentId || null;
            if (payloadSession && currentSessionId && payloadSession !== currentSessionId) {
                return false;
            }
            if (payloadAgent && payloadAgent !== agent.id) {
                return false;
            }
            return true;
        }

        function handleRealtimeEvent(type, payload) {
            if (!isRelevantRealtimePayload(payload)) {
                return;
            }
            if (type === 'THINKING') {
                thinkingEl.className = 'thinking-indicator visible';
                thinkingLabel.textContent = agent.name + ' is thinking...';
                scrollToBottom();
                return;
            }
            if (type === 'messageComplete' || type === 'message_sent') {
                thinkingEl.className = 'thinking-indicator';
                const content = payload.content || payload.text || payload.message || payload.response || '';
                if (content && addSessionMessage(Object.assign({}, payload, { content: content }))) {
                    renderMessages();
                }
            }
        }

        window.addEventListener('beforeunload', function() {
            if (realtimeSource) {
                realtimeSource.close();
            }
        });

        // ---- Event Listeners ----
        sendBtn.addEventListener('click', sendMessage);

        chatInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        chatInput.addEventListener('input', autoResizeInput);

        document.getElementById('new-session-btn').addEventListener('click', function() {
            createSession();
        });

        document.getElementById('clear-session-btn').addEventListener('click', function() {
            clearSession();
        });

        document.getElementById('refresh-sessions-btn').addEventListener('click', function() {
            loadSessions();
        });

        document.querySelectorAll('.starter-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                chatInput.value = btn.getAttribute('data-starter') || '';
                autoResizeInput();
                chatInput.focus();
            });
        });

        sessionSelect.addEventListener('change', function() {
            currentSessionId = sessionSelect.value || null;
            if (currentSessionId) {
                // Load messages for this session
                messages = [];
                seenMessageIds = new Set();
                lastMessageCursor = 0;
                renderMessages();
                loadSessionMessages(50);
                showToast('Switched to session', 'success');
            } else {
                messages = [];
                seenMessageIds = new Set();
                lastMessageCursor = 0;
                renderMessages();
            }
        });

        // ---- Init ----
        initAgentHeader();
        updateRateDisplay();
        loadRateLimit();
        checkAgentStatus();
        loadSessions().then(autoStartSession);
        connectRealtime();

        // Auto-focus input
        chatInput.focus();
    })();
    </script>
</body>
</html>
