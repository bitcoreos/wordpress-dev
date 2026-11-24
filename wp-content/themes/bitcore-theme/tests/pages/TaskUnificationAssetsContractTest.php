<?php
/**
 * Taskunificationassetscontracttest.
 *
 * @package    Bitcore\Theme
 * @since      2026.11.23
 * @version    1.0.0
 * @author     coraline
 */

declare(strict_types=1);

/**
 * Standalone contract test: theme assets must use unified tasks API.
 * No WordPress bootstrap, DB writes, network calls, or deployment.
 */
final class TaskUnificationAssetsContractTest {
    private int $passed = 0;
    private int $failed = 0;
    private string $root;

    public function __construct() {
        $this->root = dirname(__DIR__, 2);
    }

    public function run(): int {
        $this->test_goals_asset_exists_only_as_unified_tasks_view();
        $this->test_theme_production_js_has_no_legacy_goal_or_mission_rest_calls();
        return $this->report();
    }

    private function test_goals_asset_exists_only_as_unified_tasks_view(): void {
        $path = $this->root . '/assets/js/goals.js';
        $this->assert_true(is_file($path), 'goals_asset_exists_for_slug_backward_compatibility');
        $js = is_file($path) ? file_get_contents($path) : '';
        $this->assert_contains('/wp-json/bitcore/v1/tasks?task_type=goal', $js, 'goals_asset_uses_tasks_goal_filter');
        $this->assert_not_contains('/wp-json/bitcore/v1/goals', $js, 'goals_asset_no_legacy_goals_endpoint');
        $this->assert_not_contains("endpoint: '/wp-json/bitcore/v1/goals'", $js, 'goals_asset_no_legacy_endpoint_config');
    }

    private function test_theme_production_js_has_no_legacy_goal_or_mission_rest_calls(): void {
        $files = $this->production_js_files($this->root . '/assets/js');
        $this->assert_true(count($files) > 0, 'production_js_files_discovered');
        foreach ($files as $file) {
            $rel = substr($file, strlen($this->root) + 1);
            $js = file_get_contents($file);
            foreach (['/wp-json/bitcore/v1/goals', '/wp-json/bitcore/v1/missions', "restUrl + 'goals", "restUrl + 'missions"] as $needle) {
                $this->assert_not_contains($needle, $js, 'no_legacy_endpoint_' . preg_replace('/[^a-z0-9]+/i', '_', $rel . '_' . $needle));
            }
        }
    }

    /** @return list<string> */
    private function production_js_files(string $dir): array {
        if (!is_dir($dir)) return [];
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        $files = [];
        foreach ($rii as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'js') continue;
            $path = $file->getPathname();
            if (str_contains($path, '/tests/') || str_contains($path, '/.playwright-cli/')) continue;
            $files[] = $path;
        }
        sort($files);
        return $files;
    }

    private function assert_true(bool $condition, string $name): void { $condition ? $this->pass($name) : $this->fail($name); }
    private function assert_contains(string $needle, string $haystack, string $name): void { str_contains($haystack, $needle) ? $this->pass($name) : $this->fail($name . " missing='{$needle}'"); }
    private function assert_not_contains(string $needle, string $haystack, string $name): void { !str_contains($haystack, $needle) ? $this->pass($name) : $this->fail($name . " unexpected='{$needle}'"); }
    private function pass(string $name): void { $this->passed++; echo "PASS {$name}\n"; }
    private function fail(string $name): void { $this->failed++; echo "FAIL {$name}\n"; }
    private function report(): int { echo "\nAssertions passed: {$this->passed}\nAssertions failed: {$this->failed}\n"; return $this->failed === 0 ? 0 : 1; }
}

exit((new TaskUnificationAssetsContractTest())->run());
