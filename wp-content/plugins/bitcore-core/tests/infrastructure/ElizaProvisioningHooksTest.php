<?php
declare(strict_types=1);

/**
 * Standalone behavioral contract tests for BITCORE_Core ElizaOS provisioning hooks.
 * These tests avoid live WordPress, network, database, Docker, and ElizaOS.
 */

define('ABSPATH', __DIR__);
define('BITCORE_VERSION', 'test');
define('BITCORE_PATH', dirname(__DIR__, 2) . '/');

class WP_User { public int $ID; public function __construct(int $id) { $this->ID = $id; } }
class BITCORE_Container {}
class BITCORE_Services_Response_ID_Manager {}
class BITCORE_Services_Message_Builder {}
class BITCORE_Services_Session {}
class BITCORE_Services_Usage_Stats {}
class BITCORE_Services_Connector_N8N {}
class BITCORE_Services_Connector_BIThub {}
class BITCORE_Services_Connector_BITwiki {}
class BITCORE_Modules_Chatbot {}
class BITCORE_Modules_Discussions {}
class BITCORE_MCP_Core {}
class BITCORE_Rest_API {}
class BITCORE_Rest_Tasks {}
class BITCORE_Modules_Search {}
class BITCORE_Modules_Forms {}
class BITCORE_Modules_Tasks {}

$GLOBALS['bitcore_provisioning_test'] = [];
function bitcore_provisioning_reset(): void {
    $GLOBALS['bitcore_provisioning_test'] = [
        'actions' => [],
        'meta' => [],
        'meta_updates' => [],
        'options' => [
            'bitcore_eliza_default_agent' => 'aria',
            'bitcore_eliza_agent_aria' => 'aria-uuid',
            'bitcore_eliza_agent_forge' => 'forge-uuid',
            'bitcore_eliza_agent_muse' => 'muse-uuid',
        ],
        'caps' => [],
        'remote_posts' => [],
        'did_actions' => [],
    ];
}
function add_action(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void {
    $GLOBALS['bitcore_provisioning_test']['actions'][] = [$hook, $callback, $priority, $accepted_args];
}
function add_shortcode(string $tag, callable $callback): void {}
function is_admin(): bool { return false; }
function get_option(string $key, mixed $default = false): mixed { return $GLOBALS['bitcore_provisioning_test']['options'][$key] ?? $default; }
function user_can(int $user_id, string $capability): bool { return !empty($GLOBALS['bitcore_provisioning_test']['caps'][$user_id][$capability]); }
function get_user_meta(int $user_id, string $key, bool $single = false): mixed { return $GLOBALS['bitcore_provisioning_test']['meta'][$user_id][$key] ?? ''; }
function update_user_meta(int $user_id, string $key, mixed $value): bool {
    $GLOBALS['bitcore_provisioning_test']['meta'][$user_id][$key] = $value;
    $GLOBALS['bitcore_provisioning_test']['meta_updates'][] = [$user_id, $key, $value];
    return true;
}
function sanitize_text_field(mixed $value): string { return trim(strip_tags((string) $value)); }
function current_time(string $type = 'mysql'): string { return '2026-05-28 10:15:00'; }
function do_action(string $hook, mixed ...$args): void { $GLOBALS['bitcore_provisioning_test']['did_actions'][] = [$hook, $args]; }
function wp_remote_post(string $url, array $args = []): array {
    $GLOBALS['bitcore_provisioning_test']['remote_posts'][] = [$url, $args];
    return ['response' => ['code' => 200], 'body' => '{}'];
}
function wp_doing_ajax(): bool { return false; }
function wp_is_json_request(): bool { return false; }
function rest_url(string $path = ''): string { return 'https://wp.test/wp-json/' . ltrim($path, '/'); }

bitcore_provisioning_reset();
require_once dirname(__DIR__, 2) . '/classes/core.php';

final class ElizaProvisioningHooksStandaloneTest {
    private int $passed = 0;
    private int $failed = 0;

    public function run(): void {
        $this->test_source_registers_global_user_hooks_from_core_init();
        $this->test_source_registers_subscription_purchase_hook_from_core_init();
        $this->test_registration_provisions_default_agent_for_readable_user();
        $this->test_registration_is_idempotent_and_preserves_existing_agent();
        $this->test_registration_skips_users_without_read_capability();
        $this->test_login_recovers_missing_active_agent();
        $this->test_login_preserves_existing_active_agent_without_remote_side_effects();
        $this->test_invalid_default_agent_falls_back_to_aria();
        $this->test_subscription_purchase_hook_delegates_to_credits_manager();
        $this->report();
    }

    private function core(): BITCORE_Core {
        return (new ReflectionClass(BITCORE_Core::class))->newInstanceWithoutConstructor();
    }

    private function core_bootstrap_source(): string {
        // Post-split: the global hook registrations live in the core init trait;
        // read it alongside the core composition file.
        return (string) file_get_contents(dirname(__DIR__, 2) . '/classes/core.php')
            . (string) file_get_contents(dirname(__DIR__, 2) . '/classes/traits/trait-core-init.php');
    }

    private function test_source_registers_global_user_hooks_from_core_init(): void {
        $source = $this->core_bootstrap_source();
        $this->assert_true(is_string($source) && strpos($source, "add_action('user_register', [\$this, 'eliza_user_registered'])") !== false, 'core_registers_user_register_hook');
        $this->assert_true(is_string($source) && strpos($source, "add_action('wp_login', [\$this, 'eliza_user_login'], 10, 2)") !== false, 'core_registers_wp_login_hook');
    }

    private function test_source_registers_subscription_purchase_hook_from_core_init(): void {
        $source = $this->core_bootstrap_source();
        $this->assert_true(is_string($source) && strpos($source, "add_action('bitcore_subscription_purchase', [\$this, 'provision_subscription_purchase'], 10, 3)") !== false, 'core_registers_subscription_purchase_hook');
    }

    private function test_registration_provisions_default_agent_for_readable_user(): void {
        bitcore_provisioning_reset();
        $GLOBALS['bitcore_provisioning_test']['caps'][7]['read'] = true;
        $GLOBALS['bitcore_provisioning_test']['options']['bitcore_eliza_default_agent'] = 'forge';
        $this->core()->eliza_user_registered(7);
        $this->assert_same('forge-uuid', get_user_meta(7, 'bitcore_eliza_active_agent', true), 'registration_sets_default_agent_id');
        $this->assert_same('forge', get_user_meta(7, 'bitcore_eliza_active_agent_name', true), 'registration_sets_default_agent_name');
        $this->assert_same([], $GLOBALS['bitcore_provisioning_test']['remote_posts'], 'registration_has_no_remote_side_effect');
    }

    private function test_registration_is_idempotent_and_preserves_existing_agent(): void {
        bitcore_provisioning_reset();
        $GLOBALS['bitcore_provisioning_test']['caps'][8]['read'] = true;
        $GLOBALS['bitcore_provisioning_test']['meta'][8]['bitcore_eliza_active_agent'] = 'custom-agent-uuid';
        $GLOBALS['bitcore_provisioning_test']['meta'][8]['bitcore_eliza_active_agent_name'] = 'custom';
        $this->core()->eliza_user_registered(8);
        $this->assert_same('custom-agent-uuid', get_user_meta(8, 'bitcore_eliza_active_agent', true), 'registration_preserves_existing_agent_id');
        $this->assert_same('custom', get_user_meta(8, 'bitcore_eliza_active_agent_name', true), 'registration_preserves_existing_agent_name');
        $this->assert_same([], $GLOBALS['bitcore_provisioning_test']['meta_updates'], 'registration_existing_agent_no_writes');
    }

    private function test_registration_skips_users_without_read_capability(): void {
        bitcore_provisioning_reset();
        $this->core()->eliza_user_registered(9);
        $this->assert_same('', get_user_meta(9, 'bitcore_eliza_active_agent', true), 'registration_no_read_no_agent');
        $this->assert_same([], $GLOBALS['bitcore_provisioning_test']['meta_updates'], 'registration_no_read_no_writes');
    }

    private function test_login_recovers_missing_active_agent(): void {
        bitcore_provisioning_reset();
        $GLOBALS['bitcore_provisioning_test']['options']['bitcore_eliza_default_agent'] = 'muse';
        $this->core()->eliza_user_login('muse-user', new WP_User(10));
        $this->assert_same('muse-uuid', get_user_meta(10, 'bitcore_eliza_active_agent', true), 'login_recovers_missing_agent_id');
        $this->assert_same('muse', get_user_meta(10, 'bitcore_eliza_active_agent_name', true), 'login_recovers_missing_agent_name');
    }

    private function test_login_preserves_existing_active_agent_without_remote_side_effects(): void {
        bitcore_provisioning_reset();
        $GLOBALS['bitcore_provisioning_test']['meta'][11]['bitcore_eliza_active_agent'] = 'existing-uuid';
        $GLOBALS['bitcore_provisioning_test']['meta'][11]['bitcore_eliza_active_agent_name'] = 'existing';
        $this->core()->eliza_user_login('existing-user', new WP_User(11));
        $this->assert_same('existing-uuid', get_user_meta(11, 'bitcore_eliza_active_agent', true), 'login_preserves_existing_agent_id');
        $this->assert_same('existing', get_user_meta(11, 'bitcore_eliza_active_agent_name', true), 'login_preserves_existing_agent_name');
        $this->assert_same([], $GLOBALS['bitcore_provisioning_test']['meta_updates'], 'login_existing_agent_no_writes');
        $this->assert_same([], $GLOBALS['bitcore_provisioning_test']['remote_posts'], 'login_existing_agent_no_remote_side_effect');
    }

    private function test_invalid_default_agent_falls_back_to_aria(): void {
        bitcore_provisioning_reset();
        $GLOBALS['bitcore_provisioning_test']['caps'][12]['read'] = true;
        $GLOBALS['bitcore_provisioning_test']['options']['bitcore_eliza_default_agent'] = 'invalid';
        $this->core()->eliza_user_registered(12);
        $this->assert_same('aria-uuid', get_user_meta(12, 'bitcore_eliza_active_agent', true), 'invalid_default_falls_back_agent_id');
        $this->assert_same('aria', get_user_meta(12, 'bitcore_eliza_active_agent_name', true), 'invalid_default_falls_back_agent_name');
    }

    private function test_subscription_purchase_hook_delegates_to_credits_manager(): void {
        bitcore_provisioning_reset();
        $result = $this->core()->provision_subscription_purchase(13, 'pro', ['source' => '<b>checkout</b>', 'payment_id' => '<i>pay_456</i>']);
        $this->assert_same(true, $result, 'subscription_purchase_core_method_returns_true');
        $this->assert_same('pro', get_user_meta(13, 'bitcore_subscription_tier', true), 'subscription_purchase_core_sets_tier');
        $this->assert_same(10, get_user_meta(13, 'bitcore_agent_limit', true), 'subscription_purchase_core_sets_agent_limit');
        $purchase = get_user_meta(13, 'bitcore_subscription_last_purchase', true);
        $this->assert_same('checkout', $purchase['source'] ?? null, 'subscription_purchase_core_sanitizes_source');
        $this->assert_same('pay_456', $purchase['payment_id'] ?? null, 'subscription_purchase_core_sanitizes_payment_id');
    }

    private function assert_true(mixed $actual, string $label): void { $this->assert_same(true, $actual, $label); }
    private function assert_same(mixed $expected, mixed $actual, string $label): void {
        if ($expected === $actual) { $this->passed++; echo "PASS {$label}" . PHP_EOL; return; }
        $this->failed++; echo "FAIL {$label} expected=" . var_export($expected, true) . " actual=" . var_export($actual, true) . PHP_EOL;
    }
    private function report(): void {
        echo PHP_EOL . "Assertions passed: {$this->passed}" . PHP_EOL;
        echo "Assertions failed: {$this->failed}" . PHP_EOL;
        if ($this->failed > 0) { exit(1); }
    }
}

(new ElizaProvisioningHooksStandaloneTest())->run();
