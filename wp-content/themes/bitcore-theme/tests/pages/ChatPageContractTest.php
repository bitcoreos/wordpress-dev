<?php
/**
 * Chatpagecontracttest.
 *
 * @package    Bitcore\Theme
 * @since      2026.11.23
 * @version    1.0.0
 * @author     coraline
 */

declare(strict_types=1);

final class ChatPageContractTest {
    private int $passed = 0;
    private int $failed = 0;
    private string $source;
    private string $pageChatPath;
    private array $partialSources = [];

    public function __construct() {
        $path = dirname(__DIR__, 2) . '/page-chat.php';
        if (!is_file($path)) {
            fwrite(STDERR, 'Missing page-chat.php' . PHP_EOL);
            exit(1);
        }
        $this->pageChatPath = $path;
        $this->source = $this->aggregate_template_source($path, dirname(__DIR__, 2));
    }

    /**
     * Behaviour assertions must not pin an implementation location: the chat
     * template is a thin orchestrator plus include()d template-parts. Read the
     * orchestrator and every partial it includes as one logical source.
     */
    private function aggregate_template_source(string $path, string $themeRoot): string {
        $main = (string) file_get_contents($path);
        $sources = [$main];
        $queue = [$main];
        $seen = [$path => true];
        $needle = 'get_template_directory()';
        while ($queue !== []) {
            $src = array_shift($queue);
            $offset = 0;
            while (($hit = strpos($src, $needle, $offset)) !== false) {
                $offset = $hit + strlen($needle);
                $rel = $this->included_template_part($src, $offset);
                if ($rel === null) {
                    continue;
                }
                $full = $themeRoot . '/' . $rel;
                if (isset($seen[$full]) || !is_file($full)) {
                    continue;
                }
                $seen[$full] = true;
                $part = (string) file_get_contents($full);
                $this->partialSources[$rel] = $part;
                $sources[] = $part;
                $queue[] = $part;
            }
        }
        return implode(PHP_EOL, $sources);
    }

    /**
     * Return the theme-relative template-parts path at $offset, or null.
     */
    private function included_template_part(string $src, int $offset): ?string {
        $window = substr($src, $offset, 200);
        $sq = chr(39);
        $dq = chr(34);
        $positions = [];
        foreach ([$sq, $dq] as $quote) {
            $p = strpos($window, $quote);
            if ($p !== false) {
                $positions[$p] = $quote;
            }
        }
        if ($positions === []) {
            return null;
        }
        ksort($positions);
        $first = array_key_first($positions);
        $quote = $positions[$first];
        $close = strpos($window, $quote, $first + 1);
        if ($close === false) {
            return null;
        }
        $rel = ltrim(substr($window, $first + 1, $close - $first - 1), '/');
        return strpos($rel, 'template-parts/') === 0 ? $rel : null;
    }

    public function run(): int {
        $this->test_template_auth_and_nonce_contract();
        $this->test_required_agent_and_rest_contract();
        $this->test_send_message_contract();
        $this->test_session_management_contract();
        $this->test_session_message_history_contract();
        $this->test_realtime_sse_and_message_complete_contract();
        $this->test_rate_limit_is_loaded_from_endpoint_not_local_counter_only();
        $this->test_chat_has_starter_messages_and_autostarts_session();
        $this->test_accessibility_and_mobile_contract();
        $this->test_no_placeholders_or_dead_polling_state();
        $this->test_page_chat_stays_within_loc_gate();
        echo "\nAssertions passed: {$this->passed}\n";
        echo "Assertions failed: {$this->failed}\n";
        return $this->failed === 0 ? 0 : 1;
    }

    private function test_template_auth_and_nonce_contract(): void {
        $this->assert_contains('Template Name: Agent Chat', $this->source, 'chat_template_name_present');
        $this->assert_contains('is_user_logged_in()', $this->source, 'chat_requires_logged_in_user');
        $this->assert_contains('bitcore_get_login_url($_SERVER[\'REQUEST_URI\'])', $this->source, 'chat_redirects_to_bitcore_login_adapter_with_return_url');
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
        $this->assert_contains('pollForAgentReply', $body, 'chat_polls_for_agent_reply_after_acknowledgement');
        $this->assert_contains("thinkingEl.className = 'thinking-indicator visible';", $body, 'chat_shows_thinking_before_request');
        $this->assert_contains("thinkingEl.className = 'thinking-indicator';", $body, 'chat_hides_thinking_after_request');
        $this->assert_not_contains('JSON.stringify(response)', $body, 'chat_does_not_render_ack_json_as_agent_text');
        $this->assert_not_contains('response.content', $body, 'chat_does_not_render_ack_content_as_agent_text');
        $this->assert_not_contains('Array.isArray(response)', $body, 'chat_does_not_treat_ack_as_response_array');
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

    private function test_session_message_history_contract(): void {
        $this->assert_contains('function loadSessionMessages', $this->source, 'chat_load_session_messages_function_present');
        $this->assert_contains('function pollForAgentReply', $this->source, 'chat_poll_for_agent_reply_function_present');
        $this->assert_contains("'/sessions/' + encodeURIComponent(currentSessionId) + '/messages", $this->source, 'chat_uses_session_messages_proxy');
        $this->assert_contains('function isAgentMessage', $this->source, 'chat_has_agent_message_filter');
        $this->assert_contains('msg.isAgent === true', $this->source, 'chat_uses_isAgent_discriminator');
        $this->assert_contains('seenMessageIds', $this->source, 'chat_deduplicates_session_messages');
        $this->assert_contains('lastMessageCursor', $this->source, 'chat_tracks_message_cursor');
        $this->assert_contains('loadSessionMessages(50)', $this->source, 'chat_loads_history_when_switching_sessions');
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

    private function test_chat_has_starter_messages_and_autostarts_session(): void {
        $this->assert_contains('data-starter', $this->source, 'chat_has_starter_message_buttons');
        $this->assert_contains('Orient me', $this->source, 'chat_has_orientation_starter');
        $this->assert_contains('Plan work', $this->source, 'chat_has_work_planning_starter');
        $this->assert_contains('Research brief', $this->source, 'chat_has_research_starter');
        $this->assert_contains('function autoStartSession()', $this->source, 'chat_has_auto_start_session_function');
        $this->assert_contains('loadSessions().then(autoStartSession)', $this->source, 'chat_autostarts_after_loading_sessions');
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

    private function test_page_chat_stays_within_loc_gate(): void {
        $max = 500;
        $raw = (string) file_get_contents($this->pageChatPath);
        $orchestratorLines = substr_count($raw, chr(10));
        $this->assert_true($orchestratorLines < $max, 'chat_orchestrator_under_500_lines');
        $this->assert_true($this->partialSources !== [], 'chat_split_into_template_parts');
        foreach ($this->partialSources as $rel => $body) {
            $lines = substr_count($body, chr(10));
            $this->assert_true($lines < $max, 'chat_partial_under_500_lines_' . preg_replace('/[^a-z0-9]+/i', '_', $rel));
        }
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
