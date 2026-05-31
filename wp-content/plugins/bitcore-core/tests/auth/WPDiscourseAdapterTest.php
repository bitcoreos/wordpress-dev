<?php
declare(strict_types=1);

/**
 * Standalone tests for the BITCORE adapter over the official WP Discourse plugin.
 *
 * BITCORE must not sign DiscourseConnect payloads or replace WP Discourse.
 * It only generates a safe login handoff URL and reports sanitized status.
 */

define('ABSPATH', __DIR__);

$GLOBALS['bitcore_wpdc_adapter_test'] = [
    'home_url' => 'https://ashi.bitwiki.org',
    'options' => [],
];

function home_url(string $path = ''): string {
    $base = rtrim($GLOBALS['bitcore_wpdc_adapter_test']['home_url'], '/');
    if ($path === '') {
        return $base;
    }
    return $base . '/' . ltrim($path, '/');
}
function site_url(string $path = ''): string { return home_url($path); }
function wp_parse_url(string $url, int $component = -1): mixed { return parse_url($url, $component); }
function esc_url_raw(mixed $url): string { return filter_var((string) $url, FILTER_SANITIZE_URL) ?: ''; }
function sanitize_text_field(mixed $value): string { return trim(strip_tags((string) $value)); }
function wp_unslash(mixed $value): mixed { return $value; }
function wp_login_url(string $redirect = ''): string { return home_url('/wp-login.php') . ($redirect !== '' ? '?redirect_to=' . rawurlencode($redirect) : ''); }
function get_option(string $name, mixed $default = false): mixed { return $GLOBALS['bitcore_wpdc_adapter_test']['options'][$name] ?? $default; }
function is_admin(): bool { return false; }

$target = dirname(__DIR__, 2) . '/infrastructure/auth/wp-discourse-adapter.php';
if (file_exists($target)) {
    require_once $target;
}

final class WPDiscourseAdapterStandaloneTest {
    private int $passed = 0;
    private int $failed = 0;

    public function run(): void {
        $this->test_public_functions_exist();
        $this->test_login_url_uses_wp_discourse_client_entrypoint();
        $this->test_relative_return_url_is_normalized_to_home_host();
        $this->test_external_return_url_is_rejected_to_dashboard();
        $this->test_status_reports_configuration_without_secret_values();
        $this->report();
    }

    private function test_public_functions_exist(): void {
        $this->assert_true(function_exists('bitcore_get_login_url'), 'bitcore_get_login_url_exists');
        $this->assert_true(function_exists('bitcore_wp_discourse_status'), 'bitcore_wp_discourse_status_exists');
    }

    private function test_login_url_uses_wp_discourse_client_entrypoint(): void {
        if (!function_exists('bitcore_get_login_url')) { $this->fail('login_url_function_missing'); return; }
        $url = bitcore_get_login_url('https://ashi.bitwiki.org/dashboard/');
        $this->assert_same('https://ashi.bitwiki.org/?discourse_sso=1&redirect_to=https%3A%2F%2Fashi.bitwiki.org%2Fdashboard%2F', $url, 'login_url_routes_to_wp_discourse_client_entrypoint');
        $this->assert_not_contains('sso_secret', $url, 'login_url_does_not_expose_sso_secret');
        $this->assert_not_contains('sig=', $url, 'login_url_does_not_custom_sign_payload');
    }

    private function test_relative_return_url_is_normalized_to_home_host(): void {
        if (!function_exists('bitcore_get_login_url')) { $this->fail('relative_function_missing'); return; }
        $url = bitcore_get_login_url('/chat/?agent=aria');
        $this->assert_same('https://ashi.bitwiki.org/?discourse_sso=1&redirect_to=https%3A%2F%2Fashi.bitwiki.org%2Fchat%2F%3Fagent%3Daria', $url, 'relative_return_url_normalized');
    }

    private function test_external_return_url_is_rejected_to_dashboard(): void {
        if (!function_exists('bitcore_get_login_url')) { $this->fail('external_function_missing'); return; }
        $url = bitcore_get_login_url('https://hub.bitwiki.org/welcome');
        $this->assert_same('https://ashi.bitwiki.org/?discourse_sso=1&redirect_to=https%3A%2F%2Fashi.bitwiki.org%2Fdashboard%2F', $url, 'external_return_url_rejected');
    }

    private function test_status_reports_configuration_without_secret_values(): void {
        if (!function_exists('bitcore_wp_discourse_status')) { $this->fail('status_function_missing'); return; }
        $GLOBALS['bitcore_wpdc_adapter_test']['options']['discourse_sso_client'] = [
            'sso-client-enabled' => '1',
            'sso-client-secret' => 'NEVER_PRINT_ME',
            'sso-client-login-form-redirect' => 'https://ashi.bitwiki.org/dashboard/',
        ];
        $status = bitcore_wp_discourse_status();
        $dump = var_export($status, true);
        $this->assert_same(true, is_array($status), 'status_returns_array');
        $this->assert_same(true, $status['client_configured'] ?? null, 'status_detects_client_configured');
        $this->assert_not_contains('NEVER_PRINT_ME', $dump, 'status_redacts_secret_values');
        $this->assert_not_contains('sso-client-secret', $dump, 'status_does_not_return_secret_keys');
    }

    private function assert_true(bool $condition, string $name): void { $condition ? $this->pass($name) : $this->fail($name); }
    private function assert_same(mixed $expected, mixed $actual, string $name): void {
        $expected === $actual ? $this->pass($name) : $this->fail($name . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
    private function assert_not_contains(string $needle, string $haystack, string $name): void {
        !str_contains($haystack, $needle) ? $this->pass($name) : $this->fail($name . ' unexpected [' . $needle . ']');
    }
    private function pass(string $name): void { $this->passed++; echo "PASS {$name}\n"; }
    private function fail(string $name): void { $this->failed++; echo "FAIL {$name}\n"; }
    private function report(): void {
        echo "\nAssertions passed: {$this->passed}\n";
        echo "Assertions failed: {$this->failed}\n";
        if ($this->failed > 0) { exit(1); }
    }
}

(new WPDiscourseAdapterStandaloneTest())->run();
