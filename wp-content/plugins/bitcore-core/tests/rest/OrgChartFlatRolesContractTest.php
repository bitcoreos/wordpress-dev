<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);
define('BITCORE_TEST_ROOT', dirname(__DIR__, 2));

$GLOBALS['bitcore_org_flat_test'] = [];
function bitcore_org_flat_reset(): void {
    $GLOBALS['bitcore_org_flat_test'] = [
        'routes' => [],
        'logged_in' => true,
    ];
}
function register_rest_route(string $namespace, string $route, array $args): bool {
    $GLOBALS['bitcore_org_flat_test']['routes'][] = [$namespace, $route, $args];
    return true;
}
function rest_ensure_response(mixed $data): WP_REST_Response {
    return $data instanceof WP_REST_Response ? $data : new WP_REST_Response($data);
}
function __return_true(): bool { return true; }
function is_user_logged_in(): bool { return (bool) $GLOBALS['bitcore_org_flat_test']['logged_in']; }
function apply_filters(string $hook, mixed $value, mixed ...$args): mixed { return $value; }

class WP_REST_Request {
    private array $params;
    public function __construct(array $params = []) { $this->params = $params; }
    public function get_param(string $key): mixed { return $this->params[$key] ?? null; }
}
class WP_REST_Response {
    private mixed $data;
    public function __construct(mixed $data = null) { $this->data = $data; }
    public function get_data(): mixed { return $this->data; }
}

bitcore_org_flat_reset();
require_once BITCORE_TEST_ROOT . '/infrastructure/coordination/class-org-chart.php';
require_once BITCORE_TEST_ROOT . '/rest/class-org-chart-api.php';

final class OrgChartFlatRolesContractTest {
    private int $passed = 0;
    private int $failed = 0;

    public function run(): int {
        $this->test_org_chart_is_flat_role_registry();
        $this->test_rest_routes_expose_flat_role_surface_only();
        $this->test_assign_role_ignores_hierarchy_input();
        $this->test_agent_info_exposes_role_not_tree_metadata();
        $this->test_admin_api_table_uses_flat_role_language();
        $this->report();
        return $this->failed === 0 ? 0 : 1;
    }

    private function api(): \BITCORE\Rest\Org_Chart_Api {
        bitcore_org_flat_reset();
        return new \BITCORE\Rest\Org_Chart_Api();
    }

    private function test_org_chart_is_flat_role_registry(): void {
        $org = new \BITCORE\Infrastructure\Coordination\OrgChart();
        $this->assert_same(true, $org->assign_role('aria', 'specialist'), 'flat_assign_specialist_role');
        $this->assert_same(true, $org->assign_role('forge', 'worker', 'aria'), 'flat_assign_ignores_boss_argument_success');
        $this->assert_same('worker', $org->get_role('forge'), 'flat_role_is_stored');
        $this->assert_same(null, $org->get_boss('forge'), 'flat_role_has_no_boss');
        $this->assert_same([], $org->get_reports('aria'), 'flat_role_has_no_direct_reports');
        $this->assert_same([], $org->get_all_subordinates('aria'), 'flat_role_has_no_recursive_reports');
        $this->assert_same(false, $org->is_manager('aria'), 'flat_role_never_manager_by_reports');
        $this->assert_same(['forge'], $org->get_chain_of_command('forge'), 'flat_chain_is_self_only_for_existing_agent');
        $this->assert_same([], $org->broadcast_to_reports('aria', ['type' => 'notice']), 'flat_broadcast_has_no_hierarchy_targets');
        $this->assert_same(['aria', 'forge'], $org->get_all_agents(), 'flat_all_agents_preserves_insert_order_ids');
        $this->assert_same(['aria'], $org->get_agents_by_role('specialist'), 'flat_get_agents_by_role');
        $this->assert_same(false, $org->assign_role('muse', 'invalid'), 'flat_rejects_invalid_role');
    }

    private function test_rest_routes_expose_flat_role_surface_only(): void {
        $this->api();
        $routes = $GLOBALS['bitcore_org_flat_test']['routes'];
        $this->assert_same(2, count($routes), 'org_flat_route_count');
        $this->assert_route('bitcore/v1', '/org-chart', ['GET', 'POST']);
        $this->assert_route('bitcore/v1', '/org-chart/(?P<agent_id>[\\w-]+)', ['GET', 'DELETE']);
        foreach ($routes as $route) {
            $this->assert_not_contains('/reports', $route[1], 'org_flat_no_reports_route_' . $route[1]);
            $this->assert_not_contains('/chain', $route[1], 'org_flat_no_chain_route_' . $route[1]);
            $this->assert_not_contains('/broadcast', $route[1], 'org_flat_no_broadcast_route_' . $route[1]);
        }
        $post_args = $routes[0][2][1]['args'] ?? [];
        $this->assert_true(isset($post_args['agent_id']), 'org_flat_post_requires_agent_id');
        $this->assert_true(isset($post_args['role']), 'org_flat_post_requires_role');
        $this->assert_true(!isset($post_args['boss_id']), 'org_flat_post_does_not_accept_boss_id');
    }

    private function test_assign_role_ignores_hierarchy_input(): void {
        $api = $this->api();
        $response = $api->assign_role(new WP_REST_Request([
            'agent_id' => 'forge',
            'role' => 'worker',
            'boss_id' => 'aria',
        ]));
        $this->assert_same(true, $response->get_data()['success'] ?? null, 'org_flat_api_assign_success');
        $info = $api->get_agent_info(new WP_REST_Request(['agent_id' => 'forge']))->get_data();
        $this->assert_same('worker', $info['role'] ?? null, 'org_flat_api_role_stored');
        $this->assert_true(!array_key_exists('boss', $info), 'org_flat_api_no_boss_key');
        $this->assert_true(!array_key_exists('reports', $info), 'org_flat_api_no_reports_key');
        $this->assert_true(!array_key_exists('is_manager', $info), 'org_flat_api_no_is_manager_key');
    }

    private function test_agent_info_exposes_role_not_tree_metadata(): void {
        $api = $this->api();
        $api->assign_role(new WP_REST_Request(['agent_id' => 'aria', 'role' => 'specialist']));
        $info = $api->get_agent_info(new WP_REST_Request(['agent_id' => 'aria']))->get_data();
        $this->assert_same('aria', $info['agent_id'] ?? null, 'org_flat_info_includes_agent_id');
        $this->assert_same('specialist', $info['role'] ?? null, 'org_flat_info_includes_role');
        $this->assert_same(true, $info['exists'] ?? null, 'org_flat_info_includes_exists');
        foreach (['boss', 'reports', 'chain', 'is_manager', 'subordinates'] as $key) {
            $this->assert_true(!array_key_exists($key, $info), 'org_flat_info_omits_' . $key);
        }
    }



    private function test_admin_api_table_uses_flat_role_language(): void {
        $admin_file = BITCORE_TEST_ROOT . '/admin/api-tools.php';
        $this->assert_true(is_file($admin_file), 'org_flat_admin_api_tools_exists');
        $content = file_get_contents($admin_file);
        $this->assert_contains('/bitcore/v1/org-chart', $content, 'org_flat_admin_lists_org_chart_endpoint');
        $this->assert_not_contains('Agent hierarchy', $content, 'org_flat_admin_no_hierarchy_label');
        $this->assert_contains('Flat agent roles', $content, 'org_flat_admin_flat_role_label');
    }

    private function assert_route(string $namespace, string $route, array $methods): void {
        foreach ($GLOBALS['bitcore_org_flat_test']['routes'] as $registered) {
            if ($registered[0] !== $namespace || $registered[1] !== $route) {
                continue;
            }
            $actual = [];
            foreach ($registered[2] as $definition) {
                if (isset($definition['methods'])) {
                    $actual[] = $definition['methods'];
                }
            }
            sort($actual);
            sort($methods);
            $this->assert_same($methods, $actual, 'route_' . preg_replace('/[^a-z0-9]+/i', '_', $route));
            return;
        }
        $this->assert_true(false, 'route_missing_' . $route);
    }

    private function assert_contains(string $needle, string $haystack, string $label): void {
        $this->assert_true(strpos($haystack, $needle) !== false, $label);
    }

    private function assert_not_contains(string $needle, string $haystack, string $label): void {
        $this->assert_true(strpos($haystack, $needle) === false, $label);
    }

    private function assert_same(mixed $expected, mixed $actual, string $label): void {
        if ($expected === $actual) {
            echo "PASS {$label}\n";
            $this->passed++;
            return;
        }
        echo "FAIL {$label} expected=" . var_export($expected, true) . ' actual=' . var_export($actual, true) . "\n";
        $this->failed++;
    }

    private function assert_true(bool $condition, string $label): void {
        if ($condition) {
            echo "PASS {$label}\n";
            $this->passed++;
            return;
        }
        echo "FAIL {$label}\n";
        $this->failed++;
    }

    private function report(): void {
        echo "\nAssertions passed: {$this->passed}\n";
        echo "Assertions failed: {$this->failed}\n";
    }
}

exit((new OrgChartFlatRolesContractTest())->run());
