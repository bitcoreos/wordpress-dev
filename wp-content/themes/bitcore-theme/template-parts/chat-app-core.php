<?php
/**
 * Agent Chat template part: agent chat application core.
 *
 * Extracted byte-for-byte from page-chat.php (RULING 1 split).
 *
 * @package bitcore-librarian-theme
 * @subpackage template-parts
 * @version 1.0.0
 * @since 1.2.0
 * @author     coraline
 */
?>    <script>
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
