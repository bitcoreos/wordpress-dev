<?php
/**
 * Privacyretentiontest.
 *
 * @package    Bitcore\Tests
 * @since      2026.11.23
 * @version    1.0.0
 * @author     coraline
 */

declare(strict_types=1);

/**
 * Standalone tests for ASHI zero-retention cleanup behavior.
 * Uses fake DB and temp uploads only; no live database or production files.
 */

if (!defined('ABSPATH')) { define('ABSPATH', __DIR__ . '/'); }
if (!defined('HOUR_IN_SECONDS')) { define('HOUR_IN_SECONDS', 3600); }
if (!defined('DAY_IN_SECONDS')) { define('DAY_IN_SECONDS', 86400); }

$GLOBALS['bitcore_privacy_test_state'] = [];

function bitcore_privacy_test_rm_rf(string $path): void {
    if (!is_dir($path)) { return; }
    foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $item) {
        $full = $path . DIRECTORY_SEPARATOR . $item;
        is_dir($full) ? bitcore_privacy_test_rm_rf($full) : unlink($full);
    }
    rmdir($path);
}

function bitcore_privacy_test_reset(array $columns = ['id', 'expires_at', 'updated_at', 'created_at']): void {
    $tmp = sys_get_temp_dir() . '/bitcore_privacy_test_' . getmypid();
    if (is_dir($tmp)) { bitcore_privacy_test_rm_rf($tmp); }
    mkdir($tmp . '/bitcore/transcriptions', 0777, true);
    $GLOBALS['bitcore_privacy_test_state'] = [
        'actions' => [], 'filters' => [], 'applied_filters' => [],
        'uploads_basedir' => $tmp, 'columns' => $columns, 'options' => [],
    ];
    $GLOBALS['wpdb'] = new BITCORE_Privacy_Test_WPDB();
}

function add_filter(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void { $GLOBALS['bitcore_privacy_test_state']['filters'][] = [$hook, $priority, $accepted_args, $callback]; }
function add_action(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void { $GLOBALS['bitcore_privacy_test_state']['actions'][] = [$hook, $priority, $accepted_args, $callback]; }
function apply_filters(string $hook, mixed $value, mixed ...$args): mixed { $GLOBALS['bitcore_privacy_test_state']['applied_filters'][] = [$hook, $value, $args]; return $value; }
function get_option(string $key, mixed $default = false): mixed { return $GLOBALS['bitcore_privacy_test_state']['options'][$key] ?? $default; }
function wp_upload_dir(): array { return ['basedir' => $GLOBALS['bitcore_privacy_test_state']['uploads_basedir']]; }
function user_can(int $user_id, string $capability): bool { return false; }
function get_post(int $post_id): mixed { return null; }
function get_posts(array $args): array { return []; }
function get_post_meta(int $post_id): array { return []; }
function wp_delete_post(int $post_id, bool $force_delete = false): bool { return true; }
function delete_user_meta(int $user_id, string $key): bool { return true; }

final class BITCORE_Privacy_Test_WPDB {
    public string $prefix = 'wp_';
    public array $queries = [];
    public array $prepared = [];
    public array $column_queries = [];

    public function get_col(string $query, int $column_offset = 0): array {
        $this->column_queries[] = [$query, $column_offset];
        return $GLOBALS['bitcore_privacy_test_state']['columns'];
    }

    public function prepare(string $query, mixed ...$args): string {
        $this->prepared[] = [$query, $args];
        foreach ($args as $arg) {
            $query = preg_replace('/%s|%d/', is_int($arg) ? (string) $arg : "'" . (string) $arg . "'", $query, 1) ?? $query;
        }
        return $query;
    }

    public function query(string $query): int { $this->queries[] = $query; return 3; }
}

bitcore_privacy_test_reset();
require_once dirname(__DIR__, 2) . '/infrastructure/class-privacy.php';

final class PrivacyRetentionStandaloneTest {
    private int $passed = 0;
    private int $failed = 0;

    public function run(): void {
        $this->test_constructor_registers_retention_hooks();
        $this->test_cleanup_sessions_deletes_expired_and_stale_updated_at_schema();
        $this->test_cleanup_sessions_uses_last_activity_when_updated_at_absent();
        $this->test_cleanup_sessions_uses_created_at_fallback_without_expiry_column();
        $this->test_cleanup_sessions_noops_when_schema_has_no_safe_age_column();
        $this->test_cleanup_transcriptions_deletes_only_json_files_older_than_ttl();
        $this->test_cleanup_transcriptions_ignores_missing_directory();
        $this->summary();
    }

    private function test_constructor_registers_retention_hooks(): void {
        bitcore_privacy_test_reset();
        new BITCORE_Privacy();
        $hooks = array_map(fn($row) => $row[0], $GLOBALS['bitcore_privacy_test_state']['actions']);
        $filters = array_map(fn($row) => $row[0], $GLOBALS['bitcore_privacy_test_state']['filters']);
        $this->assert_true(in_array('bitcore_cleanup_sessions', $hooks, true), 'registers_session_cleanup_hook');
        $this->assert_true(in_array('bitcore_cleanup_transcriptions', $hooks, true), 'registers_transcription_cleanup_hook');
        $this->assert_true(in_array('map_meta_cap', $filters, true), 'registers_capability_filter');
    }

    private function test_cleanup_sessions_deletes_expired_and_stale_updated_at_schema(): void {
        bitcore_privacy_test_reset(['id', 'expires_at', 'updated_at', 'created_at']);
        (new BITCORE_Privacy())->cleanup_sessions();
        $prepared = $GLOBALS['wpdb']->prepared[0][0] ?? '';
        $this->assert_contains('DELETE FROM', $prepared, 'session_cleanup_deletes_rows');
        $this->assert_contains('bitcore_sessions', $prepared, 'session_cleanup_targets_sessions_table');
        $this->assert_contains('expires_at IS NOT NULL', $prepared, 'session_cleanup_honors_explicit_expiry');
        $this->assert_contains('expires_at IS NULL AND updated_at', $prepared, 'session_cleanup_uses_updated_at_ttl_when_not_explicitly_expired');
        $this->assert_not_contains('last_activity', $prepared, 'session_cleanup_does_not_reference_missing_last_activity');
    }

    private function test_cleanup_sessions_uses_last_activity_when_updated_at_absent(): void {
        bitcore_privacy_test_reset(['id', 'session_id', 'last_activity', 'expires_at']);
        (new BITCORE_Privacy())->cleanup_sessions();
        $prepared = $GLOBALS['wpdb']->prepared[0][0] ?? '';
        $this->assert_contains('last_activity', $prepared, 'session_cleanup_supports_last_activity_schema');
        $this->assert_not_contains('updated_at', $prepared, 'session_cleanup_does_not_reference_missing_updated_at');
    }

    private function test_cleanup_sessions_uses_created_at_fallback_without_expiry_column(): void {
        bitcore_privacy_test_reset(['id', 'agent_id', 'created_at']);
        (new BITCORE_Privacy())->cleanup_sessions();
        $prepared = $GLOBALS['wpdb']->prepared[0][0] ?? '';
        $this->assert_contains('created_at < %s', $prepared, 'session_cleanup_supports_created_at_only_schema');
        $this->assert_not_contains('expires_at', $prepared, 'session_cleanup_does_not_reference_missing_expires_at');
    }

    private function test_cleanup_sessions_noops_when_schema_has_no_safe_age_column(): void {
        bitcore_privacy_test_reset(['id', 'agent_id', 'status']);
        (new BITCORE_Privacy())->cleanup_sessions();
        $this->assert_same([], $GLOBALS['wpdb']->queries, 'session_cleanup_noops_without_safe_retention_column');
    }

    private function test_cleanup_transcriptions_deletes_only_json_files_older_than_ttl(): void {
        bitcore_privacy_test_reset();
        $dir = $GLOBALS['bitcore_privacy_test_state']['uploads_basedir'] . '/bitcore/transcriptions';
        $old_json = $dir . '/old.json'; $fresh_json = $dir . '/fresh.json'; $old_txt = $dir . '/old.txt';
        file_put_contents($old_json, '{}'); file_put_contents($fresh_json, '{}'); file_put_contents($old_txt, 'keep');
        touch($old_json, time() - (8 * DAY_IN_SECONDS)); touch($fresh_json, time()); touch($old_txt, time() - (8 * DAY_IN_SECONDS));
        (new BITCORE_Privacy())->cleanup_transcriptions();
        $this->assert_same(false, file_exists($old_json), 'old_json_transcription_deleted');
        $this->assert_same(true, file_exists($fresh_json), 'fresh_json_transcription_kept');
        $this->assert_same(true, file_exists($old_txt), 'non_json_file_kept');
    }

    private function test_cleanup_transcriptions_ignores_missing_directory(): void {
        bitcore_privacy_test_reset();
        bitcore_privacy_test_rm_rf($GLOBALS['bitcore_privacy_test_state']['uploads_basedir'] . '/bitcore/transcriptions');
        (new BITCORE_Privacy())->cleanup_transcriptions();
        $this->assert_true(true, 'missing_transcription_directory_is_noop');
    }

    private function assert_contains(string $needle, string $haystack, string $label): void { $this->assert_same(true, str_contains($haystack, $needle), $label . ' needle=' . $needle); }
    private function assert_not_contains(string $needle, string $haystack, string $label): void { $this->assert_same(false, str_contains($haystack, $needle), $label . ' needle=' . $needle); }
    private function assert_true(bool $actual, string $label): void { $this->assert_same(true, $actual, $label); }
    private function assert_same(mixed $expected, mixed $actual, string $label): void {
        if ($expected === $actual) { $this->passed++; echo "PASS {$label}\n"; return; }
        $this->failed++; echo "FAIL {$label}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
    }
    private function summary(): void {
        echo "\nAssertions passed: {$this->passed}\nAssertions failed: {$this->failed}\n";
        bitcore_privacy_test_rm_rf($GLOBALS['bitcore_privacy_test_state']['uploads_basedir']);
        if ($this->failed > 0) { exit(1); }
    }
}

(new PrivacyRetentionStandaloneTest())->run();
