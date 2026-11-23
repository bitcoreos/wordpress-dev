<?php
/**
 * Agent Chat template part: agent chat realtime + init.
 *
 * Extracted byte-for-byte from page-chat.php (RULING 1 split).
 *
 * @package bitcore-librarian-theme
 * @subpackage template-parts
 * @version 1.0.0
 * @since 1.2.0
 */
?>        // ---- Send Message ----
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