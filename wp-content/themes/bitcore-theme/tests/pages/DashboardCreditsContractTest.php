<?php
/**
 * Dashboard Credits Contract Test.
 *
 * Verifies the visible dashboard credits badge consumes the canonical Credits API,
 * not the legacy Budget API. This is a static contract test because the dashboard
 * page embeds its client code inline.
 */
final class DashboardCreditsContractTest {
    private string $source;
    private int $passed = 0;
    private int $failed = 0;

    public function __construct() {
        $path = dirname(__DIR__, 2) . '/page-dashboard.php';
        $this->source = is_file($path) ? (string) file_get_contents($path) : '';
    }

    public function run(): int {
        $this->test_dashboard_has_credits_badge();
        $this->test_dashboard_uses_canonical_credits_api_not_budget();
        $this->test_dashboard_displays_normalized_credits_status_fields();
        $this->test_dashboard_normalizes_rest_collection_shapes();
        $this->test_dashboard_surfaces_control_center();
        printf("\nAssertions passed: %d\nAssertions failed: %d\n", $this->passed, $this->failed);
        return $this->failed > 0 ? 1 : 0;
    }

    private function test_dashboard_has_credits_badge(): void {
        $this->assert_contains('id="credits-text"', $this->source, 'dashboard_has_credits_text_target');
        $this->assert_contains('function loadCredits()', $this->source, 'dashboard_has_load_credits_function');
    }

    private function test_dashboard_uses_canonical_credits_api_not_budget(): void {
        $body = $this->function_body('loadCredits');
        $this->assert_contains("api('credits/eliza')", $body, 'dashboard_credits_calls_canonical_credits_endpoint');
        $this->assert_not_contains("api('budget')", $body, 'dashboard_credits_does_not_call_legacy_budget_endpoint');
        $this->assert_not_contains('/wp-json/bitcore/v1/budget', $this->source, 'dashboard_source_has_no_absolute_budget_endpoint');
    }

    private function test_dashboard_displays_normalized_credits_status_fields(): void {
        $body = $this->function_body('loadCredits');
        foreach (['remaining_today', 'daily_limit', 'tier'] as $field) {
            $this->assert_contains($field, $body, 'dashboard_credits_uses_' . $field);
        }
        $this->assert_contains('Credits unavailable', $body, 'dashboard_credits_keeps_failure_fallback');
    }


    private function test_dashboard_normalizes_rest_collection_shapes(): void {
        $this->assert_contains('Array.isArray(data.tasks)', $this->source, 'dashboard_reads_kanban_tasks_shape');
        $this->assert_contains('Array.isArray(data.items)', $this->source, 'dashboard_reads_my_posts_items_shape');
        $this->assert_contains('Create your first task', $this->source, 'dashboard_task_empty_state_has_action');
        $this->assert_contains('Draft a post or prompt', $this->source, 'dashboard_posts_empty_state_has_action');
    }

    private function test_dashboard_surfaces_control_center(): void {
        foreach (['Control Center', 'Prompts', 'Research Requests', 'BIThub Graph', 'Swarm Chat', 'Artifacts', 'Credits & Limits'] as $label) {
            $this->assert_contains($label, $this->source, 'dashboard_control_center_' . preg_replace('/[^a-z0-9]+/i', '_', strtolower($label)));
        }
    }

    private function function_body(string $name): string {
        $needle = 'async function ' . $name . '()';
        $start = strpos($this->source, $needle);
        if ($start === false) {
            return '';
        }
        $next = strpos($this->source, 'async function ', $start + strlen($needle));
        if ($next === false) {
            $next = strpos($this->source, 'document.addEventListener', $start);
        }
        return substr($this->source, $start, $next ? $next - $start : 800);
    }

    private function assert_contains(string $needle, string $haystack, string $label): void {
        if (strpos($haystack, $needle) !== false) {
            $this->pass($label);
            return;
        }
        $this->fail($label . ' missing [' . $needle . ']');
    }

    private function assert_not_contains(string $needle, string $haystack, string $label): void {
        if (strpos($haystack, $needle) === false) {
            $this->pass($label);
            return;
        }
        $this->fail($label . ' unexpected [' . $needle . ']');
    }

    private function pass(string $label): void { $this->passed++; echo "PASS {$label}\n"; }
    private function fail(string $label): void { $this->failed++; echo "FAIL {$label}\n"; }
}

exit((new DashboardCreditsContractTest())->run());
