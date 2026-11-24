<?php
/**
 * Discoursepermissioncapturetest.
 *
 * @package    Bitcore\Tests
 * @since      2026.11.23
 * @version    1.0.0
 * @author     coraline
 */

declare(strict_types=1);

/**
 * Standalone tests for additive WP-Discourse permission snapshot capture.
 */

define('ABSPATH', __DIR__);

$GLOBALS['bitcore_test_state'] = [];
function bitcore_discourse_test_reset(): void {
    $GLOBALS['bitcore_test_state'] = [
        'user_meta' => [],
        'meta_updates' => [],
        'actions' => [],
        'filters' => [],
        'wp_caps' => [],
        'now' => 1700000000,
    ];
}
function add_action(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void {
    $GLOBALS['bitcore_test_state']['actions'][] = [$hook, $priority, $accepted_args, $callback];
}
function add_filter(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void {
    $GLOBALS['bitcore_test_state']['filters'][] = [$hook, $priority, $accepted_args, $callback];
}
function get_user_meta(int $user_id, string $key, bool $single = false): mixed {
    return $GLOBALS['bitcore_test_state']['user_meta'][$user_id][$key] ?? '';
}
function update_user_meta(int $user_id, string $key, mixed $value): bool {
    $GLOBALS['bitcore_test_state']['user_meta'][$user_id][$key] = $value;
    $GLOBALS['bitcore_test_state']['meta_updates'][] = [$user_id, $key, $value];
    return true;
}
function current_time(string $type, bool $gmt = false): int {
    return $GLOBALS['bitcore_test_state']['now'];
}
function user_can(int $user_id, string $capability): bool {
    return !empty($GLOBALS['bitcore_test_state']['wp_caps'][$user_id][$capability]);
}
function sanitize_text_field(mixed $value): string {
    return trim(strip_tags((string) $value));
}
function sanitize_key(mixed $value): string {
    $value = strtolower((string) $value);
    return preg_replace('/[^a-z0-9_\-]/', '', $value) ?? '';
}

bitcore_discourse_test_reset();
$target = dirname(__DIR__, 2) . '/infrastructure/auth/discourse-permission-capture.php';
if (file_exists($target)) {
    require_once $target;
}

final class DiscoursePermissionCaptureStandaloneTest {
    private int $passed = 0;
    private int $failed = 0;

    public function run(): void {
        $this->test_class_exists_and_registers_expected_hooks();
        $this->test_capture_without_discourse_metadata_is_noop();
        $this->test_mirrors_discourse_id_and_username_from_user_meta();
        $this->test_payload_groups_are_sanitized_and_trust_level_inferred();
        $this->test_malformed_privilege_payload_does_not_grant_elevation();
        $this->test_stale_snapshot_is_not_authoritative();
        $this->test_fresh_group_snapshot_can_authorize_required_group();
        $this->test_wp_admin_capability_remains_authoritative();
        $this->report();
    }

    private function test_class_exists_and_registers_expected_hooks(): void {
        bitcore_discourse_test_reset();
        $this->assert_true(class_exists('BITCORE_Discourse_Permission_Capture'), 'capture_class_exists');
        if (!class_exists('BITCORE_Discourse_Permission_Capture')) {
            return;
        }
        BITCORE_Discourse_Permission_Capture::register_hooks();
        $hooks = array_map(fn($row) => $row[0], $GLOBALS['bitcore_test_state']['actions']);
        $filters = array_map(fn($row) => $row[0], $GLOBALS['bitcore_test_state']['filters']);
        $this->assert_true(in_array('wp_login', $hooks, true), 'registers_wp_login_action');
        $this->assert_true(in_array('wpdc_sso_client_after_create_user', $hooks, true), 'registers_client_create_user_action');
        $this->assert_true(in_array('wpdc_after_sso_client_user_update', $hooks, true), 'registers_client_user_update_action');
        $this->assert_true(in_array('wpdc_sso_provider_before_sso_redirect', $hooks, true), 'registers_provider_redirect_action_for_legacy_provider_mode');
    }

    private function test_capture_without_discourse_metadata_is_noop(): void {
        bitcore_discourse_test_reset();
        if (!class_exists('BITCORE_Discourse_Permission_Capture')) { $this->fail('noop_class_missing'); return; }
        $result = BITCORE_Discourse_Permission_Capture::capture_user(9);
        $this->assert_same(false, $result, 'noop_returns_false_without_discourse_identity');
        $this->assert_same([], $GLOBALS['bitcore_test_state']['meta_updates'], 'noop_writes_no_meta');
    }

    private function test_mirrors_discourse_id_and_username_from_user_meta(): void {
        bitcore_discourse_test_reset();
        if (!class_exists('BITCORE_Discourse_Permission_Capture')) { $this->fail('mirror_class_missing'); return; }
        $GLOBALS['bitcore_test_state']['user_meta'][12]['discourse_sso_user_id'] = '456';
        $GLOBALS['bitcore_test_state']['user_meta'][12]['discourse_username'] = 'iamcapote';
        $result = BITCORE_Discourse_Permission_Capture::capture_user(12);
        $snapshot = get_user_meta(12, 'bitcore_discourse_permissions_snapshot', true);
        $this->assert_same(true, $result, 'mirror_returns_true');
        $this->assert_same('456', get_user_meta(12, 'bitcore_discourse_user_id', true), 'mirrors_discourse_user_id');
        $this->assert_same('iamcapote', get_user_meta(12, 'bitcore_discourse_username', true), 'mirrors_discourse_username');
        $this->assert_same('456', $snapshot['user_id'] ?? null, 'snapshot_contains_user_id');
    }

    private function test_payload_groups_are_sanitized_and_trust_level_inferred(): void {
        bitcore_discourse_test_reset();
        if (!class_exists('BITCORE_Discourse_Permission_Capture')) { $this->fail('payload_class_missing'); return; }
        $payload = ['external_id' => '99', 'username' => 'Forge', 'groups' => 'trust_level_3,admins,<script>,bithub-core'];
        BITCORE_Discourse_Permission_Capture::capture_user(15, $payload);
        $snapshot = get_user_meta(15, 'bitcore_discourse_permissions_snapshot', true);
        $this->assert_same(['trust_level_3', 'admins', 'bithub-core'], $snapshot['groups'] ?? null, 'sanitizes_groups_drops_html_tags');
        $this->assert_same(3, $snapshot['trust_level'] ?? null, 'infers_trust_level_from_group');
        $this->assert_same('Forge', get_user_meta(15, 'bitcore_discourse_username', true), 'payload_username_preserved_safely');
    }

    private function test_malformed_privilege_payload_does_not_grant_elevation(): void {
        bitcore_discourse_test_reset();
        if (!class_exists('BITCORE_Discourse_Permission_Capture')) { $this->fail('malformed_class_missing'); return; }
        $payload = ['external_id' => '88', 'username' => 'x', 'groups' => ['admins', ['nested']], 'trust_level' => '999'];
        BITCORE_Discourse_Permission_Capture::capture_user(16, $payload);
        $snapshot = get_user_meta(16, 'bitcore_discourse_permissions_snapshot', true);
        $this->assert_same([], $snapshot['groups'] ?? null, 'malformed_groups_rejected');
        $this->assert_same(null, $snapshot['trust_level'] ?? null, 'out_of_range_trust_rejected');
        $this->assert_same(false, BITCORE_Discourse_Permission_Capture::user_has_discourse_group(16, 'admins'), 'malformed_admin_group_not_authorized');
    }

    private function test_stale_snapshot_is_not_authoritative(): void {
        bitcore_discourse_test_reset();
        if (!class_exists('BITCORE_Discourse_Permission_Capture')) { $this->fail('stale_class_missing'); return; }
        $GLOBALS['bitcore_test_state']['user_meta'][17]['bitcore_discourse_permissions_snapshot'] = [
            'captured_at' => 1000,
            'groups' => ['admins'],
            'trust_level' => 4,
        ];
        $this->assert_same([], BITCORE_Discourse_Permission_Capture::get_fresh_permissions_snapshot(17), 'stale_snapshot_returns_empty');
        $this->assert_same(false, BITCORE_Discourse_Permission_Capture::user_has_discourse_group(17, 'admins'), 'stale_group_not_authorized');
    }

    private function test_fresh_group_snapshot_can_authorize_required_group(): void {
        bitcore_discourse_test_reset();
        if (!class_exists('BITCORE_Discourse_Permission_Capture')) { $this->fail('fresh_class_missing'); return; }
        $GLOBALS['bitcore_test_state']['user_meta'][18]['discourse_sso_user_id'] = '18';
        BITCORE_Discourse_Permission_Capture::capture_user(18, ['groups' => 'bithub-core,trust_level_2']);
        $this->assert_same(true, BITCORE_Discourse_Permission_Capture::user_has_discourse_group(18, 'bithub-core'), 'fresh_group_authorizes_match');
        $this->assert_same(false, BITCORE_Discourse_Permission_Capture::user_has_discourse_group(18, 'admins'), 'fresh_group_denies_missing');
    }

    private function test_wp_admin_capability_remains_authoritative(): void {
        bitcore_discourse_test_reset();
        if (!class_exists('BITCORE_Discourse_Permission_Capture')) { $this->fail('admin_class_missing'); return; }
        $GLOBALS['bitcore_test_state']['wp_caps'][1]['manage_options'] = true;
        $this->assert_same(true, BITCORE_Discourse_Permission_Capture::user_can_administer_ashi(1), 'wp_admin_can_administer_ashi_without_discourse');
        $this->assert_same(false, BITCORE_Discourse_Permission_Capture::user_can_administer_ashi(2), 'non_admin_without_group_denied');
    }

    private function assert_true(bool $condition, string $name): void { $condition ? $this->pass($name) : $this->fail($name); }
    private function assert_same(mixed $expected, mixed $actual, string $name): void {
        $expected === $actual ? $this->pass($name) : $this->fail($name . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
    private function pass(string $name): void { $this->passed++; echo "PASS {$name}\n"; }
    private function fail(string $name): void { $this->failed++; echo "FAIL {$name}\n"; }
    private function report(): void {
        echo "\nAssertions passed: {$this->passed}\n";
        echo "Assertions failed: {$this->failed}\n";
        if ($this->failed > 0) { exit(1); }
    }
}

(new DiscoursePermissionCaptureStandaloneTest())->run();
