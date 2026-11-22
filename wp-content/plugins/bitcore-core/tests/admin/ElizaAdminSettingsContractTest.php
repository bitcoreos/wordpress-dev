<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);
define('HOUR_IN_SECONDS', 3600);
define('DAY_IN_SECONDS', 86400);
define('BITCORE_TEST_ROOT', dirname(__DIR__, 2));

$GLOBALS['bitcore_admin_settings_test'] = [];
function bitcore_admin_settings_reset(): void {
    $GLOBALS['bitcore_admin_settings_test'] = [
        'actions' => [],
        'settings' => [],
        'sections' => [],
        'fields' => [],
        'submenus' => [],
        'options' => [],
        'filters' => [],
        'next_scheduled' => false,
        'scheduled' => [],
    ];
}
function add_action(string $hook, callable $callback, int $priority = 10, int $args = 1): void {
    $GLOBALS['bitcore_admin_settings_test']['actions'][] = compact('hook', 'callback', 'priority', 'args');
}
function add_filter(string $hook, callable $callback, int $priority = 10, int $args = 1): void {
    $GLOBALS['bitcore_admin_settings_test']['filters'][] = compact('hook', 'callback', 'priority', 'args');
}
function apply_filters(string $hook, mixed $value, mixed ...$args): mixed { return $value; }
function register_setting(string $group, string $name, array $args = []): void {
    $GLOBALS['bitcore_admin_settings_test']['settings'][$name] = ['group' => $group, 'args' => $args];
}
function add_settings_section(string $id, string $title, mixed $callback, string $page): void {
    $GLOBALS['bitcore_admin_settings_test']['sections'][$id] = compact('id', 'title', 'callback', 'page');
}
function add_settings_field(string $id, string $title, mixed $callback, string $page, string $section, array $args = []): void {
    $GLOBALS['bitcore_admin_settings_test']['fields'][$id] = compact('id', 'title', 'callback', 'page', 'section', 'args');
}
function add_submenu_page(string $parent, string $page_title, string $menu_title, string $capability, string $slug, callable $callback): void {
    $GLOBALS['bitcore_admin_settings_test']['submenus'][] = compact('parent', 'page_title', 'menu_title', 'capability', 'slug', 'callback');
}
function __return_null(): null { return null; }
function __($text, string $domain = ''): string { return (string) $text; }
function esc_url_raw($value): string { return trim((string) $value); }
function sanitize_text_field($value): string { return trim(strip_tags((string) $value)); }
function absint($value): int { return max(0, (int) $value); }
function esc_attr($value): string { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_html($value): string { return htmlspecialchars((string) $value, ENT_QUOTES); }
function selected($selected, $current, bool $display = true): string { return (string) $selected === (string) $current ? 'selected="selected"' : ''; }
function checked($checked, $current = true, bool $display = true): string { return $checked == $current ? 'checked="checked"' : ''; }
function get_option(string $key, mixed $default = false): mixed { return $GLOBALS['bitcore_admin_settings_test']['options'][$key] ?? $default; }
function current_user_can(string $cap): bool { return true; }
function wp_die($message): void { throw new RuntimeException((string) $message); }
function settings_fields(string $group): void { echo "settings_fields:$group\n"; }
function do_settings_sections(string $page): void {
    echo "do_settings_sections:$page\n";
    foreach ($GLOBALS['bitcore_admin_settings_test']['sections'] as $section) {
        if (($section['page'] ?? '') !== $page) {
            continue;
        }
        echo '<h2>' . esc_html($section['title'] ?? '') . "</h2>\n";
        foreach ($GLOBALS['bitcore_admin_settings_test']['fields'] as $field) {
            if (($field['page'] ?? '') !== $page || ($field['section'] ?? '') !== ($section['id'] ?? '')) {
                continue;
            }
            echo '<label>' . esc_html($field['title'] ?? '') . '</label>';
            call_user_func($field['callback'], $field['args'] ?? []);
            echo "\n";
        }
    }
}
function submit_button(): void { echo "submit_button\n"; }
function wp_remote_get(string $url, array $args = []): array { return ['response' => ['code' => 200], 'body' => '{"status":"ok"}']; }
function is_wp_error($value): bool { return false; }
function wp_remote_retrieve_response_code($response): int { return (int) ($response['response']['code'] ?? 0); }
function wp_remote_retrieve_body($response): string { return (string) ($response['body'] ?? ''); }
function wp_next_scheduled(string $hook): bool { return (bool) $GLOBALS['bitcore_admin_settings_test']['next_scheduled']; }
function wp_schedule_event(int $time, string $recurrence, string $hook): void { $GLOBALS['bitcore_admin_settings_test']['scheduled'][] = compact('time', 'recurrence', 'hook'); }

bitcore_admin_settings_reset();
require_once BITCORE_TEST_ROOT . '/admin/class-eliza-settings.php';
require_once BITCORE_TEST_ROOT . '/infrastructure/class-privacy.php';

final class ElizaAdminSettingsContractTest {
    private int $passed = 0;
    private int $failed = 0;

    public function run(): int {
        $this->test_constructor_registers_admin_hooks();
        $this->test_registers_required_eliza_and_retention_settings();
        $this->test_auth_token_setting_defaults_to_empty_string();
        $this->test_retention_fields_are_rendered_in_privacy_section();
        $this->test_retention_sanitizers_are_bounded();
        $this->test_privacy_ttl_reads_admin_options();
        $this->report();
        return $this->failed === 0 ? 0 : 1;
    }

    private function fresh_settings(): BITCORE_Eliza_Settings {
        bitcore_admin_settings_reset();
        return new BITCORE_Eliza_Settings();
    }

    private function test_constructor_registers_admin_hooks(): void {
        $this->fresh_settings();
        $hooks = array_column($GLOBALS['bitcore_admin_settings_test']['actions'], 'hook');
        $this->assert_contains('admin_init', $hooks, 'settings_hooks_admin_init');
        $this->assert_contains('admin_menu', $hooks, 'settings_hooks_admin_menu');
    }

    private function test_registers_required_eliza_and_retention_settings(): void {
        $settings = $this->fresh_settings();
        $settings->register_settings();
        foreach (['bitcore_eliza_server_url', 'bitcore_eliza_auth_token', 'bitcore_eliza_daily_limit'] as $key) {
            $this->assert_true(isset($GLOBALS['bitcore_admin_settings_test']['settings'][$key]), 'settings_registers_' . $key);
        }
        $this->assert_setting('bitcore_session_retention_hours', 'integer', 24);
        $this->assert_setting('bitcore_transcription_retention_days', 'integer', 7);
        $this->assert_same('bitcore_eliza_settings', $GLOBALS['bitcore_admin_settings_test']['settings']['bitcore_session_retention_hours']['group'] ?? null, 'retention_settings_group');
    }

    private function test_auth_token_setting_defaults_to_empty_string(): void {
        $settings = $this->fresh_settings();
        $settings->register_settings();
        $setting = $GLOBALS['bitcore_admin_settings_test']['settings']['bitcore_eliza_auth_token'] ?? null;
        $this->assert_true(is_array($setting), 'settings_registers_eliza_auth_token_for_default_check');
        $this->assert_same('', $setting['args']['default'] ?? null, 'settings_default_eliza_auth_token_is_empty');
    }

    private function test_retention_fields_are_rendered_in_privacy_section(): void {
        $settings = $this->fresh_settings();
        $settings->register_settings();
        $this->assert_true(isset($GLOBALS['bitcore_admin_settings_test']['sections']['bitcore_eliza_section_retention']), 'retention_section_registered');
        $this->assert_field('bitcore_session_retention_hours', 'bitcore_eliza_section_retention');
        $this->assert_field('bitcore_transcription_retention_days', 'bitcore_eliza_section_retention');
        ob_start();
        $settings->render_settings_page();
        $html = ob_get_clean();
        $decoded_html = html_entity_decode($html, ENT_QUOTES);
        $this->assert_contains('Privacy & Retention', $decoded_html, 'render_mentions_privacy_retention');
        $this->assert_contains('bitcore_session_retention_hours', $html, 'render_includes_session_retention_input');
        $this->assert_contains('bitcore_transcription_retention_days', $html, 'render_includes_transcription_retention_input');
    }

    private function test_retention_sanitizers_are_bounded(): void {
        $settings = $this->fresh_settings();
        $this->assert_same(1, $settings->sanitize_session_retention_hours(0), 'session_retention_min_bound');
        $this->assert_same(24, $settings->sanitize_session_retention_hours('24'), 'session_retention_accepts_24');
        $this->assert_same(720, $settings->sanitize_session_retention_hours(9999), 'session_retention_max_bound');
        $this->assert_same(1, $settings->sanitize_transcription_retention_days(0), 'transcription_retention_min_bound');
        $this->assert_same(7, $settings->sanitize_transcription_retention_days('7'), 'transcription_retention_accepts_7');
        $this->assert_same(90, $settings->sanitize_transcription_retention_days(9999), 'transcription_retention_max_bound');
    }

    private function test_privacy_ttl_reads_admin_options(): void {
        bitcore_admin_settings_reset();
        $GLOBALS['bitcore_admin_settings_test']['options']['bitcore_session_retention_hours'] = 6;
        $GLOBALS['bitcore_admin_settings_test']['options']['bitcore_transcription_retention_days'] = 3;
        $privacy = new BITCORE_Privacy();
        $this->assert_same(6 * HOUR_IN_SECONDS, $privacy->get_session_ttl_seconds(), 'privacy_session_ttl_from_option');
        $this->assert_same(3 * DAY_IN_SECONDS, $privacy->get_transcription_ttl_seconds(), 'privacy_transcription_ttl_from_option');
    }

    private function assert_setting(string $key, string $type, int $default): void {
        $setting = $GLOBALS['bitcore_admin_settings_test']['settings'][$key] ?? null;
        $this->assert_true(is_array($setting), 'settings_registers_' . $key);
        $this->assert_same($type, $setting['args']['type'] ?? null, 'settings_type_' . $key);
        $this->assert_same($default, $setting['args']['default'] ?? null, 'settings_default_' . $key);
    }

    private function assert_field(string $key, string $section): void {
        $field = $GLOBALS['bitcore_admin_settings_test']['fields'][$key] ?? null;
        $this->assert_true(is_array($field), 'settings_field_' . $key);
        $this->assert_same($section, $field['section'] ?? null, 'settings_field_section_' . $key);
    }

    private function assert_contains(mixed $needle, mixed $haystack, string $label): void {
        $ok = is_array($haystack) ? in_array($needle, $haystack, true) : strpos((string) $haystack, (string) $needle) !== false;
        $this->assert_true($ok, $label);
    }

    private function assert_same(mixed $expected, mixed $actual, string $label): void {
        if ($expected === $actual) { echo "PASS {$label}\n"; $this->passed++; return; }
        echo "FAIL {$label} expected=" . var_export($expected, true) . ' actual=' . var_export($actual, true) . "\n";
        $this->failed++;
    }

    private function assert_true(bool $condition, string $label): void {
        if ($condition) { echo "PASS {$label}\n"; $this->passed++; return; }
        echo "FAIL {$label}\n";
        $this->failed++;
    }

    private function report(): void {
        echo "\nAssertions passed: {$this->passed}\n";
        echo "Assertions failed: {$this->failed}\n";
    }
}

exit((new ElizaAdminSettingsContractTest())->run());
