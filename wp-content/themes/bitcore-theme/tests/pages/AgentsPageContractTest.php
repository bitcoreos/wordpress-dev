<?php
declare(strict_types=1);

final class AgentsPageContractTest {
    private int $passed = 0;
    private int $failed = 0;
    private string $source;

    public function __construct() {
        $path = dirname(__DIR__, 2) . '/page-agents.php';
        if (!is_file($path)) {
            fwrite(STDERR, "Missing page-agents.php\n");
            exit(1);
        }
        $this->source = file_get_contents($path) ?: '';
    }

    public function run(): int {
        $this->test_template_and_auth_contract();
        $this->test_required_agent_cards_are_defined();
        $this->test_required_eliza_routes_are_used();
        $this->test_health_accepts_bridge_ok_or_healthy_status();
        $this->test_single_active_rendering_and_actions();
        $this->test_open_chat_label_is_consistent_for_all_cards();
        $this->test_mobile_breakpoint_and_accessibility_basics();
        $this->test_no_placeholder_or_random_status_logic();
        echo "\nAssertions passed: {$this->passed}\n";
        echo "Assertions failed: {$this->failed}\n";
        return $this->failed === 0 ? 0 : 1;
    }

    private function test_template_and_auth_contract(): void {
        $this->assert_contains('Template Name: Agent Directory', $this->source, 'agents_template_name_present');
        $this->assert_contains('is_user_logged_in()', $this->source, 'agents_requires_logged_in_user');
        $this->assert_contains('wp_login_url($_SERVER[\'REQUEST_URI\'])', $this->source, 'agents_redirects_to_login_with_return_url');
        $this->assert_contains('wp_create_nonce("wp_rest")', $this->source, 'agents_uses_wp_rest_nonce');
        $this->assert_contains("'X-WP-Nonce': NONCE", $this->source, 'agents_fetch_sends_nonce');
    }

    private function test_required_agent_cards_are_defined(): void {
        foreach (['Aria', 'Forge', 'Muse'] as $name) {
            $this->assert_contains("name: '{$name}'", $this->source, "agents_static_definition_{$name}");
        }
        foreach (['aria', 'forge', 'muse'] as $slug) {
            $this->assert_contains("slug: '{$slug}'", $this->source, "agents_slug_{$slug}");
        }
        $this->assert_contains('emoji:', $this->source, 'agents_have_emoji_field');
        $this->assert_contains('style:', $this->source, 'agents_have_style_field');
        $this->assert_contains('description:', $this->source, 'agents_have_description_field');
    }

    private function test_required_eliza_routes_are_used(): void {
        foreach (['/health', '/agents', '/activate', '/deactivate'] as $route) {
            $this->assert_contains($route, $this->source, "agents_uses_route_{$route}");
        }
        $this->assert_contains('rest_url("bitcore/v1/eliza")', $this->source, 'agents_uses_eliza_rest_base');
        $this->assert_contains('apiGet(\'/agents\')', $this->source, 'agents_loads_live_agent_status');
        $this->assert_contains("apiPost('/agents/' + agentId + '/activate')", $this->source, 'agents_posts_activate');
        $this->assert_contains("apiPost('/agents/' + agentId + '/deactivate')", $this->source, 'agents_posts_deactivate');
    }

    private function test_health_accepts_bridge_ok_or_healthy_status(): void {
        $body = $this->extract_function_body('checkHealth');
        $this->assert_contains("data.status === 'healthy'", $body, 'agents_health_accepts_healthy_status');
        $this->assert_contains("data.status === 'ok'", $body, 'agents_health_accepts_eliza_ok_status');
        $this->assert_contains('Server Healthy', $body, 'agents_health_sets_healthy_label');
        $this->assert_contains('Server Degraded', $body, 'agents_health_sets_degraded_label');
        $this->assert_contains('Server Offline', $body, 'agents_health_sets_offline_label');
    }

    private function test_single_active_rendering_and_actions(): void {
        $render = $this->extract_function_body('renderAgents');
        $activate = $this->extract_function_body('activateAgent');
        $this->assert_contains('const isActive = activeAgentId === agent.id;', $render, 'agents_only_one_card_active_by_active_id');
        $this->assert_contains('card.className = \'agent-card\' + (isActive ? \' active\' : \'\');', $render, 'agents_active_class_contract');
        $this->assert_contains('Active', $render, 'agents_active_badge_label');
        $this->assert_contains('Inactive', $render, 'agents_inactive_badge_label');
        $this->assert_contains('Start Agent', $render, 'agents_start_button_label');
        $this->assert_contains('Switch Agent', $render, 'agents_switch_button_label');
        $this->assert_contains('activeAgentId = agentId;', $activate, 'agents_sets_active_after_activation');
    }

    private function test_open_chat_label_is_consistent_for_all_cards(): void {
        $this->assert_contains('Open Chat', $this->source, 'agents_open_chat_label_present');
        $this->assert_not_contains('>💬 Chat</a>', $this->source, 'agents_no_ambiguous_chat_label');
        foreach (['aria', 'forge', 'muse'] as $slug) {
            $this->assert_contains('/chat/?agent=', $this->source, "agents_chat_link_base_{$slug}");
        }
    }

    private function test_mobile_breakpoint_and_accessibility_basics(): void {
        $this->assert_contains('@media (max-width: 900px)', $this->source, 'agents_mobile_breakpoint_900');
        $this->assert_contains('grid-template-columns: 1fr;', $this->source, 'agents_mobile_single_column');
        $this->assert_contains('id="status-bar"', $this->source, 'agents_status_bar_id');
        $this->assert_contains('id="agent-grid"', $this->source, 'agents_grid_id');
        $this->assert_contains('id="refresh-btn"', $this->source, 'agents_refresh_button_id');
    }

    private function test_no_placeholder_or_random_status_logic(): void {
        foreach (['rand(', 'mt_rand(', 'wp_rand(', 'TODO', 'placeholder'] as $needle) {
            $this->assert_not_contains($needle, $this->source, 'agents_no_' . preg_replace('/[^a-z0-9]+/i', '_', $needle));
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

$test = new AgentsPageContractTest();
exit($test->run());
