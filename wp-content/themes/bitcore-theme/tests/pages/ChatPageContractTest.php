<?php
declare(strict_types=1);

final class ChatPageContractTest {
    private int $passed = 0;
    private int $failed = 0;
    private string $source;

    public function __construct() {
        $path = dirname(__DIR__, 2) . '/page-chat.php';
        if (!is_file($path)) {
            fwrite(STDERR, "Missing page-chat.php\n");
            exit(1);
        }
        $this->source = file_get_contents($path) ?: '';
    }

    public function run(): int {
        $this->test_template_auth_and_nonce_contract();
        $this->test_required_agent_and_rest_contract();
        $this->test_send_message_contract();
        $this->test_session_management_contract();
        $this->test_realtime_sse_and_message_complete_contract();
        $this->test_rate_limit_is_loaded_from_endpoint_not_local_counter_only();
        $this->test_accessibility_and_mobile_contract();
        $this->test_no_placeholders_or_dead_polling_state();
        echo "\nAssertions passed: {$this->passed}\n";
        echo "Assertions failed: {$this->failed}\n";
        return $this->failed === 0 ? 0 : 1;
    }

    private function test_template_auth_and_nonce_contract(): void {
        $this->assert_contains('Template Name: Agent Chat', $this->source, 'chat_template_name_present');
        $this->assert_contains('is_user_logged_in()', $this->source, 'chat_requires_logged_in_user');
        $this->assert_contains('wp_login_url($_SERVER[\'REQUEST_URI\'])', $this->source, 'chat_redirects_to_login_with_return_url');
        $this->assert_contains('wp_create_nonce("wp_rest")', $this->source, 'chat_uses_wp_rest_nonce');
        $this->assert_contains("'X-WP-Nonce': NONCE", $this->source, 'chat_fetch_sends_nonce');
    }

    private function test_required_agent_and_rest_contract(): void {
        foreach (['Aria', 'Forge', 'Muse'] as $name) {
            $this->assert_contains("name: '{$name}'", $this->source, "chat_agent_definition_{$name}");
        }
        foreach (['aria', 'forge', 'muse'] as $slug) {
            $this->assert_contains("'{$slug}':", $this->source, "chat_agent_slug_{$slug}");
        }
        $this->assert_contains('rest_url("bitcore/v1/eliza")', $this->source, 'chat_uses_eliza_rest_base');
        foreach (["apiGet('/agents')", "apiGet('/sessions')", "apiPost('/sessions'", "apiPost('/chat'", "apiDelete('/sessions/" ] as $needle) {
            $this->assert_contains($needle, $this->source, 'chat_uses_' . preg_replace('/[^a-z0-9]+/i', '_', $needle));
        }
        $this->assert_contains('URLSearchParams(window.location.search)', $this->source, 'chat_reads_url_params');
        $this->assert_contains("urlParams.get('agent') || 'aria'", $this->source, 'chat_defaults_agent_to_aria');
    }

    private function test_send_message_contract(): void {
        $body = $this->extract_function_body('sendMessage');
        $this->assert_contains('var text = chatInput.value.trim();', $body, 'chat_trims_message_before_send');
        $this->assert_contains('if (!text || isSending) return;', $body, 'chat_blocks_empty_or_duplicate_send');
        $this->assert_contains("message: text", $body, 'chat_send_body_includes_message');
        $this->assert_contains("agentId: agent.id", $body, 'chat_send_body_includes_agent_id');
        $this->assert_contains('body.sessionId = currentSessionId;', $body, 'chat_send_body_includes_session_id_when_present');
        $this->assert_contains("apiPost('/chat', body)", $body, 'chat_posts_to_eliza_chat');
        $this->assert_contains("thinkingEl.className = 'thinking-indicator visible';", $body, 'chat_shows_thinking_before_request');
        $this->assert_contains("thinkingEl.className = 'thinking-indicator';", $body, 'chat_hides_thinking_after_request');
        $this->assert_contains("response.content", $body, 'chat_accepts_response_content');
        $this->assert_contains("response.text", $body, 'chat_accepts_response_text');
        $this->assert_contains("response.message", $body, 'chat_accepts_response_message');
        $this->assert_contains("response.response", $body, 'chat_accepts_response_response');
        $this->assert_contains('Array.isArray(response)', $body, 'chat_accepts_array_response');
        $this->assert_contains('response.channel_id', $body, 'chat_captures_backend_channel_id');
    }

    private function test_session_management_contract(): void {
        $this->assert_contains('id="session-select"', $this->source, 'chat_session_select_present');
        $this->assert_contains('id="new-session-btn"', $this->source, 'chat_new_session_button_present');
        $this->assert_contains('id="clear-session-btn"', $this->source, 'chat_clear_session_button_present');
        $this->assert_contains('id="refresh-sessions-btn"', $this->source, 'chat_refresh_sessions_button_present');
        $this->assert_contains('function loadSessions()', $this->source, 'chat_load_sessions_function_present');
        $this->assert_contains('function createSession()', $this->source, 'chat_create_session_function_present');
        $this->assert_contains('function clearSession()', $this->source, 'chat_clear_session_function_present');
        $this->assert_contains("sessionSelect.addEventListener('change'", $this->source, 'chat_session_change_listener_present');
    }

    private function test_realtime_sse_and_message_complete_contract(): void {
        $this->assert_contains('EventSource', $this->source, 'chat_uses_eventsource_for_realtime');
        $this->assert_contains('events/stream', $this->source, 'chat_connects_to_events_stream');
        $this->assert_contains('messageComplete', $this->source, 'chat_handles_message_complete_event');
        $this->assert_contains('THINKING', $this->source, 'chat_handles_thinking_event');
        $this->assert_contains('function connectRealtime', $this->source, 'chat_has_realtime_connect_function');
        $this->assert_contains('function handleRealtimeEvent', $this->source, 'chat_has_realtime_event_handler');
    }

    private function test_rate_limit_is_loaded_from_endpoint_not_local_counter_only(): void {
        $this->assert_contains("apiGet('/rate-limit')", $this->source, 'chat_loads_rate_limit_endpoint');
        $this->assert_contains('function loadRateLimit', $this->source, 'chat_has_load_rate_limit_function');
        $this->assert_contains('data.remaining', $this->source, 'chat_uses_remaining_from_rate_limit_response');
        $this->assert_contains('data.limit', $this->source, 'chat_uses_limit_from_rate_limit_response');
    }

    private function test_accessibility_and_mobile_contract(): void {
        $this->assert_contains('@media (max-width: 900px)', $this->source, 'chat_mobile_breakpoint_900');
        $this->assert_contains('id="chat-messages"', $this->source, 'chat_messages_container_id');
        $this->assert_contains('id="chat-input"', $this->source, 'chat_input_id');
        $this->assert_contains('id="send-btn"', $this->source, 'chat_send_button_id');
        $this->assert_contains('id="thinking"', $this->source, 'chat_thinking_id');
        $this->assert_contains('keydown', $this->source, 'chat_keyboard_handler_present');
        $this->assert_contains("e.key === 'Enter' && !e.shiftKey", $this->source, 'chat_enter_sends_shift_enter_newline');
    }

    private function test_no_placeholders_or_dead_polling_state(): void {
        foreach (['rand(', 'mt_rand(', 'wp_rand(', 'TODO'] as $needle) {
            $this->assert_not_contains($needle, $this->source, 'chat_no_' . preg_replace('/[^a-z0-9]+/i', '_', $needle));
        }
        $this->assert_not_contains('let pollingInterval = null;', $this->source, 'chat_no_unused_polling_interval_state');
    }

    private function extract_function_body(string $functionName): string {
        $needle = 'function ' . $functionName . '(';
        $offset = strpos($this->source, $needle);
        if ($offset === false) {
            return '';
        }
        $brace = strpos($this->source, '{', $offset);
        if ($brace === false) {
            return '';
        }
        $depth = 0;
        $length = strlen($this->source);
        for ($i = $brace; $i < $length; $i++) {
            if ($this->source[$i] === '{') { $depth++; }
            if ($this->source[$i] === '}') { $depth--; }
            if ($depth === 0) {
                return substr($this->source, $offset, $i - $offset + 1);
            }
        }
        return substr($this->source, $offset);
    }

    private function assert_contains(string $needle, string $haystack, string $name): void {
        $this->assert_true(strpos($haystack, $needle) !== false, $name);
    }

    private function assert_not_contains(string $needle, string $haystack, string $name): void {
        $this->assert_true(strpos($haystack, $needle) === false, $name);
    }

    private function assert_true(bool $condition, string $name): void {
        if ($condition) {
            $this->passed++;
            echo "PASS {$name}\n";
            return;
        }
        $this->failed++;
        echo "FAIL {$name}\n";
    }
}

$test = new ChatPageContractTest();
exit($test->run());
