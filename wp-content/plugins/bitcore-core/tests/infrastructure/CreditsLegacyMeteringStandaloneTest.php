<?php
/**
 * Creditslegacymeteringstandalonetest.
 *
 * @package    Bitcore\Tests
 * @since      2026.11.23
 * @version    1.0.0
 * @author     coraline
 */

declare(strict_types=1);

final class CreditsLegacyMeteringStandaloneTest {
    private int $passed = 0;
    private int $failed = 0;
    private string $root;

    public function __construct() {
        $this->root = dirname(__DIR__, 2);
    }

    public function run(): void {
        $this->test_no_production_php_writes_legacy_eliza_call_counter();
        $this->test_eliza_api_uses_unified_credits_manager();
        $this->test_core_provisioning_relies_on_lazy_credits_initialization();
        $this->summary();
    }

    private function production_php_files(): array {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $path = $file->getPathname();
            if (!$file->isFile() || substr($path, -4) !== '.php') {
                continue;
            }
            if (strpos($path, DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR) !== false) {
                continue;
            }
            $files[] = $path;
        }
        sort($files);
        return $files;
    }

    private function test_no_production_php_writes_legacy_eliza_call_counter(): void {
        $matches = [];
        foreach ($this->production_php_files() as $file) {
            $source = file_get_contents($file);
            if (is_string($source) && strpos($source, 'bitcore_eliza_calls_') !== false) {
                $matches[] = str_replace($this->root . DIRECTORY_SEPARATOR, '', $file);
            }
        }
        $this->assert_same([], $matches, 'production_has_no_legacy_eliza_call_counter_key');
    }

    private function test_eliza_api_uses_unified_credits_manager(): void {
        // Post-split: the credits decision/consume calls live in the Eliza traits;
        // read them alongside the entry class so the contract still proves the
        // Eliza API routes usage through the unified credits manager.
        $source = (string) file_get_contents($this->root . '/rest/class-eliza-api.php')
            . (string) file_get_contents($this->root . '/rest/traits/trait-eliza-rate-limit.php')
            . (string) file_get_contents($this->root . '/rest/traits/trait-eliza-auth.php');
        $this->assert_true(is_string($source) && strpos($source, 'Credits_Manager') !== false, 'eliza_api_references_credits_manager');
        $this->assert_true(is_string($source) && strpos($source, 'bitcore_credits_usage') === false, 'eliza_api_does_not_touch_storage_key_directly');
        $this->assert_true(is_string($source) && strpos($source, 'can_consume(') !== false, 'eliza_api_checks_credits_decision');
        $this->assert_true(is_string($source) && strpos($source, 'consume(') !== false, 'eliza_api_consumes_credits_via_manager');
    }

    private function test_core_provisioning_relies_on_lazy_credits_initialization(): void {
        // Post-split: active-agent meta and the lazy-credits note live in the
        // core eliza trait; read it alongside the core composition file.
        $source = (string) file_get_contents($this->root . '/classes/core.php')
            . (string) file_get_contents($this->root . '/classes/traits/trait-core-eliza.php');
        $this->assert_true(is_string($source) && strpos($source, 'Credits usage is lazily initialized by Credits_Manager.') !== false, 'core_documents_lazy_credits_initialization');
        $this->assert_true(is_string($source) && strpos($source, 'bitcore_eliza_active_agent') !== false, 'core_still_sets_active_agent');
        $this->assert_true(is_string($source) && strpos($source, 'bitcore_eliza_active_agent_name') !== false, 'core_still_sets_active_agent_name');
    }

    private function assert_true(mixed $actual, string $label): void {
        $this->assert_same(true, $actual, $label);
    }

    private function assert_same(mixed $expected, mixed $actual, string $label): void {
        if ($expected === $actual) {
            $this->passed++;
            echo "PASS {$label}" . PHP_EOL;
            return;
        }
        $this->failed++;
        echo "FAIL {$label} expected=" . var_export($expected, true) . " actual=" . var_export($actual, true) . PHP_EOL;
    }

    private function summary(): void {
        echo PHP_EOL . "Assertions passed: {$this->passed}" . PHP_EOL;
        echo "Assertions failed: {$this->failed}" . PHP_EOL;
        if ($this->failed > 0) {
            exit(1);
        }
    }
}

(new CreditsLegacyMeteringStandaloneTest())->run();
