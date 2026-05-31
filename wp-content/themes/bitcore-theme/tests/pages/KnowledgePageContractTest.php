<?php
declare(strict_types=1);

final class KnowledgePageContractTest {
    private int $passed = 0;
    private int $failed = 0;
    private string $source;

    public function __construct() {
        $path = dirname(__DIR__, 2) . '/page-knowledge.php';
        if (!is_file($path)) {
            fwrite(STDERR, "Missing page-knowledge.php\n");
            exit(1);
        }
        $this->source = file_get_contents($path) ?: '';
    }

    public function run(): int {
        $this->test_template_auth_and_nonce_contract();
        $this->test_required_panels_and_accessibility_ids();
        $this->test_fetch_helper_contract();
        $this->test_graph_endpoint_contract();
        $this->test_artifact_endpoint_contract_matches_my_posts_api();
        $this->test_render_card_accepts_known_api_shapes();
        $this->test_empty_states_are_specific_and_non_placeholder();
        $this->test_search_ui_is_wired_to_existing_data_loading();
        $this->test_remote_context_search_contract();
        $this->test_dom_load_and_tab_switching_contract();
        $this->test_mobile_and_no_placeholder_contract();
        echo "\nAssertions passed: {$this->passed}\n";
        echo "Assertions failed: {$this->failed}\n";
        return $this->failed === 0 ? 0 : 1;
    }

    private function test_template_auth_and_nonce_contract(): void {
        $this->assert_contains('Template Name: Knowledge', $this->source, 'knowledge_template_name_present');
        $this->assert_contains('is_user_logged_in()', $this->source, 'knowledge_requires_logged_in_user');
        $this->assert_contains('bitcore_get_login_url($_SERVER[\'REQUEST_URI\'])', $this->source, 'knowledge_redirects_to_bitcore_login_adapter_with_return_url');
        $this->assert_contains('wp_create_nonce("wp_rest")', $this->source, 'knowledge_uses_wp_rest_nonce');
        $this->assert_contains("'X-WP-Nonce': nonce", $this->source, 'knowledge_fetch_sends_nonce');
    }

    private function test_required_panels_and_accessibility_ids(): void {
        foreach (['prompts', 'skills', 'artifacts'] as $section) {
            $this->assert_contains('data-tab="' . $section . '"', $this->source, "knowledge_tab_{$section}_present");
            $this->assert_contains('id="' . $section . '-panel"', $this->source, "knowledge_panel_{$section}_present");
            $this->assert_contains('id="' . $section . '-grid"', $this->source, "knowledge_grid_{$section}_present");
            $this->assert_contains('id="' . $section . '-count"', $this->source, "knowledge_count_{$section}_present");
        }
        $this->assert_contains('id="knowledge-search"', $this->source, 'knowledge_search_input_present');
        $this->assert_contains('id="search-btn"', $this->source, 'knowledge_search_button_present');
    }

    private function test_fetch_helper_contract(): void {
        $body = $this->extract_function_body('api');
        $this->assert_contains("const restUrl = '/wp-json/bitcore/v1/';", $this->source, 'knowledge_rest_base_bitcore_v1');
        $this->assert_contains('endpoint.replace(/^\\//, \'\')', $body, 'knowledge_api_normalizes_leading_slash');
        $this->assert_contains("'Accept': 'application/json'", $body, 'knowledge_api_accepts_json');
        $this->assert_contains("'X-WP-Nonce': nonce", $body, 'knowledge_api_nonce_header');
        $this->assert_contains('if (!res.ok) throw new Error(res.statusText);', $body, 'knowledge_api_rejects_non_2xx');
        $this->assert_contains('return await res.json().catch(() => null);', $body, 'knowledge_api_handles_non_json_body');
        $this->assert_contains('catch (e) { return null; }', $body, 'knowledge_api_safe_null_on_failure');
        $this->assert_contains('options = {}', $body, 'knowledge_api_accepts_options');
        $this->assert_contains("method: options.method || 'GET'", $body, 'knowledge_api_supports_post_method');
        $this->assert_contains("'Content-Type': 'application/json'", $body, 'knowledge_api_content_type_json');
        $this->assert_contains('body: options.body ? JSON.stringify(options.body) : undefined', $body, 'knowledge_api_serializes_json_body');
    }

    private function test_graph_endpoint_contract(): void {
        $prompts = $this->extract_function_body('loadPrompts');
        $skills = $this->extract_function_body('loadSkills');
        $this->assert_contains("api('knowledge/nodes')", $prompts, 'knowledge_prompts_use_nodes_endpoint');
        $this->assert_contains('data.nodes', $prompts, 'knowledge_prompts_read_nodes_shape');
        $this->assert_contains('Array.isArray(data)', $prompts, 'knowledge_prompts_accept_legacy_array_shape');
        $this->assert_contains("api('knowledge/edges')", $skills, 'knowledge_skills_use_edges_endpoint');
        $this->assert_contains('data.edges', $skills, 'knowledge_skills_read_edges_shape');
        $this->assert_contains('Array.isArray(data)', $skills, 'knowledge_skills_accept_legacy_array_shape');
    }

    private function test_artifact_endpoint_contract_matches_my_posts_api(): void {
        $body = $this->extract_function_body('loadArtifacts');
        $this->assert_contains("api('my-posts?type=bitcore_artifact')", $body, 'knowledge_artifacts_filter_my_posts_to_artifacts');
        $this->assert_contains('data.items', $body, 'knowledge_artifacts_read_my_posts_items_shape');
        $this->assert_not_contains('data.posts', $body, 'knowledge_artifacts_do_not_read_nonexistent_posts_shape');
        $this->assert_contains('Array.isArray(data)', $body, 'knowledge_artifacts_accept_legacy_array_shape');
        $this->assert_contains("'artifacts-count'", $body, 'knowledge_artifacts_updates_count');
    }

    private function test_render_card_accepts_known_api_shapes(): void {
        $body = $this->extract_function_body('renderCard');
        foreach (['item.title', 'item.description', 'item.excerpt', 'item.content', 'item.source', 'item.updated_at', 'item.tags'] as $needle) {
            $this->assert_contains($needle, $body, 'knowledge_render_card_uses_' . preg_replace('/[^a-z0-9]+/i', '_', $needle));
        }
        $this->assert_contains('Untitled', $body, 'knowledge_render_card_title_fallback');
        $this->assert_contains('No description', $body, 'knowledge_render_card_description_fallback');
        $this->assert_contains('BITCORE', $body, 'knowledge_render_card_source_fallback');
        $this->assert_contains('escapeHtml', $body, 'knowledge_render_card_escapes_dynamic_fields');
        $this->assert_contains('function escapeHtml', $this->source, 'knowledge_escape_html_function_present');
    }

    private function test_empty_states_are_specific_and_non_placeholder(): void {
        $this->assert_contains('No prompts found. Create one in Bithub.', $this->source, 'knowledge_prompts_empty_state_specific');
        $this->assert_contains('No skills found. Create one in BitWiki.', $this->source, 'knowledge_skills_empty_state_specific');
        $this->assert_contains('No artifacts found. Create one in My Posts.', $this->source, 'knowledge_artifacts_empty_state_specific');
    }

    private function test_search_ui_is_wired_to_existing_data_loading(): void {
        $this->assert_contains("document.getElementById('search-btn')", $this->source, 'knowledge_search_button_listener_lookup');
        $this->assert_contains("document.getElementById('knowledge-search')", $this->source, 'knowledge_search_input_lookup');
        $this->assert_contains("addEventListener('click'", $this->source, 'knowledge_search_click_listener');
        $this->assert_contains("addEventListener('keydown'", $this->source, 'knowledge_search_keydown_listener');
    }


    private function test_remote_context_search_contract(): void {
        $body = $this->extract_function_body('runSearch');
        $this->assert_contains("api('context/search'", $body, 'knowledge_search_calls_context_search_endpoint');
        $this->assert_contains("method: 'POST'", $body, 'knowledge_search_uses_post');
        $this->assert_contains('body: { query }', $body, 'knowledge_search_sends_query_body');
        $this->assert_contains('data.items', $body, 'knowledge_search_reads_items_shape');
        $this->assert_contains('mergeRemoteSearchResults', $body, 'knowledge_search_merges_remote_results');
        $this->assert_contains('function mergeRemoteSearchResults', $this->source, 'knowledge_merge_remote_search_function_present');
    }

    private function test_dom_load_and_tab_switching_contract(): void {
        $this->assert_contains("document.querySelectorAll('.tab-btn')", $this->source, 'knowledge_tab_buttons_selected');
        $this->assert_contains("document.querySelectorAll('.knowledge-panel')", $this->source, 'knowledge_panels_selected');
        $this->assert_contains("document.addEventListener('DOMContentLoaded'", $this->source, 'knowledge_domcontentloaded_listener');
        foreach (['loadPrompts();', 'loadSkills();', 'loadArtifacts();'] as $needle) {
            $this->assert_contains($needle, $this->source, 'knowledge_dom_loads_' . preg_replace('/[^a-z0-9]+/i', '_', $needle));
        }
    }

    private function test_mobile_and_no_placeholder_contract(): void {
        $this->assert_contains('@media (max-width: 900px)', $this->source, 'knowledge_mobile_breakpoint_900');
        $this->assert_contains('grid-template-columns: 1fr;', $this->source, 'knowledge_mobile_single_column');
        foreach (['rand(', 'mt_rand(', 'wp_rand(', 'TODO'] as $needle) {
            $this->assert_not_contains($needle, $this->source, 'knowledge_no_' . preg_replace('/[^a-z0-9]+/i', '_', $needle));
        }
    }

    private function extract_function_body(string $functionName): string {
        $needle = 'function ' . $functionName . '(';
        $offset = strpos($this->source, $needle);
        if ($offset === false) { return ''; }

        $length = strlen($this->source);
        $parenDepth = 0;
        $brace = false;
        for ($i = $offset; $i < $length; $i++) {
            if ($this->source[$i] === '(') { $parenDepth++; }
            if ($this->source[$i] === ')') { $parenDepth--; }
            if ($parenDepth === 0 && $this->source[$i] === '{') {
                $brace = $i;
                break;
            }
        }
        if ($brace === false) { return ''; }

        $depth = 0;
        for ($i = $brace; $i < $length; $i++) {
            if ($this->source[$i] === '{') { $depth++; }
            if ($this->source[$i] === '}') { $depth--; }
            if ($depth === 0) { return substr($this->source, $offset, $i - $offset + 1); }
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

$test = new KnowledgePageContractTest();
exit($test->run());
