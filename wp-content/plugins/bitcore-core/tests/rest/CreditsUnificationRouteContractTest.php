<?php
/**
 * Credits Unification Route Contract Test.
 *
 * P2.5 contract: public billing/metering surfaces are canonicalized under
 * /bitcore/v1/credits. The legacy BudgetManager/schema may remain as an
 * internal compatibility layer, but /budget must not be registered, advertised,
 * or consumed by production UI assets.
 */
final class CreditsUnificationRouteContractTest {
    private string $root;
    private int $passed = 0;
    private int $failed = 0;

    public function __construct() {
        $this->root = dirname(__DIR__, 2);
    }

    public function run(): int {
        $this->test_core_registers_credits_api_not_budget_api();
        $this->test_admin_docs_advertise_credits_not_budget();
        $this->test_theme_assets_do_not_call_legacy_budget_endpoint();
        $this->test_internal_budget_manager_may_remain_for_credits_compatibility();
        printf("\nAssertions passed: %d\nAssertions failed: %d\n", $this->passed, $this->failed);
        return $this->failed > 0 ? 1 : 0;
    }

    private function test_core_registers_credits_api_not_budget_api(): void {
        $core = $this->read($this->root . '/bitcore-core.php');
        $this->assert_contains("/rest/class-credits-api.php", $core, 'core_requires_credits_api');
        $this->assert_contains("BITCORE\\\\Rest\\\\Credits_Api", $core, 'core_registers_credits_api');
        $this->assert_not_contains("/rest/class-budget-api.php", $core, 'core_does_not_require_budget_api');
        $this->assert_not_contains("BITCORE\\\\Rest\\\\Budget_Api", $core, 'core_does_not_register_budget_api');
    }

    private function test_admin_docs_advertise_credits_not_budget(): void {
        $admin = $this->read($this->root . '/admin/api-tools.php');
        $this->assert_contains('/bitcore/v1/credits/tiers', $admin, 'admin_docs_show_credits_tiers_endpoint');
        $this->assert_contains('/bitcore/v1/credits/{agent_id}', $admin, 'admin_docs_show_credits_status_endpoint');
        $this->assert_contains('Credits', $admin, 'admin_docs_use_credits_label');
        $this->assert_not_contains('/bitcore/v1/budget', $admin, 'admin_docs_do_not_advertise_budget_endpoint');
        $this->assert_not_contains('Budget policy', $admin, 'admin_docs_do_not_describe_budget_policy');
    }

    private function test_theme_assets_do_not_call_legacy_budget_endpoint(): void {
        $theme = dirname(dirname($this->root)) . '/themes/bitcore-theme';
        $files = $this->php_files($theme . '/assets/js');
        $this->assert_true(count($files) > 0, 'theme_assets_discovered');
        foreach ($files as $file) {
            $source = $this->read($file);
            $label = 'asset_' . basename($file) . '_has_no_legacy_budget_endpoint';
            $this->assert_not_contains('/wp-json/bitcore/v1/budget', $source, $label);
            $this->assert_not_contains("restUrl + 'budget'", $source, 'asset_' . basename($file) . '_has_no_budget_restUrl_concat');
        }
    }

    private function test_internal_budget_manager_may_remain_for_credits_compatibility(): void {
        $manager = $this->root . '/infrastructure/budget/BudgetManager.php';
        $schema = $this->root . '/database/class-budget-policies-table.php';
        $this->assert_true(is_file($manager), 'internal_budget_manager_remains_for_compatibility');
        $this->assert_true(is_file($schema), 'internal_budget_schema_remains_for_compatibility');
    }

    /** @return list<string> */
    private function php_files(string $dir): array {
        if (!is_dir($dir)) {
            return [];
        }
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        $files = [];
        foreach ($rii as $file) {
            if ($file->isFile() && preg_match('/\\.js$/', $file->getPathname())) {
                $files[] = $file->getPathname();
            }
        }
        sort($files);
        return $files;
    }

    private function read(string $path): string {
        return is_file($path) ? (string) file_get_contents($path) : '';
    }

    private function assert_contains(string $needle, string $haystack, string $label): void {
        if (strpos($haystack, $needle) !== false) { $this->pass($label); return; }
        $this->fail($label . ' missing [' . $needle . ']');
    }

    private function assert_not_contains(string $needle, string $haystack, string $label): void {
        if (strpos($haystack, $needle) === false) { $this->pass($label); return; }
        $this->fail($label . ' unexpected [' . $needle . ']');
    }

    private function assert_true(bool $condition, string $label): void {
        if ($condition) { $this->pass($label); return; }
        $this->fail($label);
    }

    private function pass(string $label): void { $this->passed++; echo "PASS {$label}\n"; }
    private function fail(string $label): void { $this->failed++; echo "FAIL {$label}\n"; }
}

exit((new CreditsUnificationRouteContractTest())->run());
