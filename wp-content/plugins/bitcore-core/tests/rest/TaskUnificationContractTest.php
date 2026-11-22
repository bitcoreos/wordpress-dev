<?php
declare(strict_types=1);

/**
 * P2.6 Task Unification Contract Test.
 *
 * Purpose:
 * - Goals = Tasks = Missions converge on one canonical task model.
 * - Public /bitcore/v1/tasks must use agent_task, not obsolete bitcore_task.
 * - Active route registration must point at the canonical controller.
 * - Taxonomies must attach to agent_task.
 *
 * This is a standalone source-contract test. It performs no database writes,
 * no WordPress bootstrap, no network calls, and no deployment.
 */

define('BITCORE_TEST_ROOT', dirname(__DIR__, 2));

final class TaskUnificationContractTest {
    private int $passed = 0;
    private int $failed = 0;
    private string $root;
    private string $main;
    private string $legacy;
    private string $canonical;
    private string $module;

    public function __construct() {
        $this->root = BITCORE_TEST_ROOT;
        $this->main = $this->root . '/bitcore-core.php';
        $this->legacy = $this->root . '/rest/tasks.php';
        $this->canonical = $this->root . '/rest/class-rest-tasks.php';
        $this->module = $this->root . '/classes/modules/tasks.php';
    }

    public function run(): int {
        $this->test_required_files_exist();
        $this->test_agent_task_is_canonical_model();
        $this->test_active_tasks_route_uses_canonical_controller();
        $this->test_obsolete_bitcore_task_is_not_active_route_model();
        $this->test_task_type_taxonomy_attaches_to_agent_task();
        $this->test_task_type_default_terms_follow_unification_contract();
        $this->test_canonical_controller_exposes_unified_contract();
        $this->test_legacy_controller_is_not_registered_for_public_tasks_route();
        $this->test_no_missions_or_goals_controller_owns_tasks_route();
        $this->test_legacy_goals_and_missions_controllers_are_not_active_rest_registrars();
        $this->test_legacy_task_like_modules_are_not_active_core_modules();
        $this->test_admin_api_tools_documents_canonical_tasks_endpoint();
        $this->test_obsolete_goals_infrastructure_namespace_is_not_autoloaded();
        $this->test_dormant_legacy_controllers_do_not_register_routes();
        $this->test_residual_legacy_modules_are_inert_compatibility_shells();
        $this->test_remaining_task_family_legacy_surfaces_are_retired();
        return $this->report();
    }

    private function test_required_files_exist(): void {
        foreach ([$this->main, $this->legacy, $this->canonical, $this->module] as $file) {
            $this->assert_true(is_file($file), 'file_exists_' . basename($file));
        }
    }

    private function test_agent_task_is_canonical_model(): void {
        $module = $this->read($this->module);
        $this->assert_contains("const POST_TYPE = 'agent_task'", $module, 'module_declares_agent_task_post_type');
        $this->assert_contains("const TAX_STATUS = 'task_status'", $module, 'module_declares_task_status_taxonomy');
        $this->assert_contains("const TAX_TYPE = 'task_type'", $module, 'module_declares_task_type_taxonomy');
        $this->assert_contains("register_post_type(self::POST_TYPE", $module, 'module_registers_canonical_post_type');
        $this->assert_not_contains("const POST_TYPE = 'bitcore_task'", $module, 'module_does_not_declare_legacy_post_type');
    }

    private function test_active_tasks_route_uses_canonical_controller(): void {
        $main = $this->read($this->main);
        $this->assert_contains("rest_api_init", $main, 'main_has_rest_api_init_hooks');
        $this->assert_true(
            str_contains($main, "class-rest-tasks.php") || str_contains($main, "REST_Tasks"),
            'main_loads_or_references_canonical_rest_tasks_controller'
        );
        $this->assert_not_contains(
            "add_action('rest_api_init', ['BITCORE_Rest_Tasks', 'register_routes'])",
            $main,
            'main_does_not_register_legacy_static_tasks_controller'
        );
    }

    private function test_obsolete_bitcore_task_is_not_active_route_model(): void {
        $main = $this->read($this->main);
        $canonical = $this->read($this->canonical);
        $this->assert_contains("const POST_TYPE = 'agent_task'", $canonical, 'canonical_controller_uses_agent_task_constant');
        $this->assert_not_contains("'post_type'      => 'bitcore_task'", $main, 'main_no_inline_legacy_task_queries');
        $this->assert_not_contains("register_taxonomy('task_type', ['bitcore_task']", $main, 'main_no_legacy_task_type_taxonomy_target');
    }

    private function test_task_type_taxonomy_attaches_to_agent_task(): void {
        $main = $this->read($this->main);
        $module = $this->read($this->module);
        $this->assert_contains("register_taxonomy(self::TAX_TYPE, self::POST_TYPE", $module, 'module_task_type_attaches_to_agent_task');
        $this->assert_not_contains("register_taxonomy('task_type', ['bitcore_task']", $main, 'main_task_type_taxonomy_not_attached_to_legacy_post_type');
        $this->assert_true(
            str_contains($main, "register_taxonomy('task_type', ['agent_task']") || str_contains($module, "register_taxonomy(self::TAX_TYPE, self::POST_TYPE"),
            'task_type_has_agent_task_attachment_path'
        );
    }

    private function test_task_type_default_terms_follow_unification_contract(): void {
        $module = $this->read($this->module);
        $this->assert_contains('register_default_types', $module, 'module_registers_default_task_types_method');
        $this->assert_contains('add_action(\'init\', [$this, \'register_default_types\'], 20)', $module, 'module_hooks_default_task_types_on_init');
        foreach (['task', 'goal', 'milestone', 'sprint'] as $slug) {
            $this->assert_contains("'" . $slug . "'", $module, 'module_default_task_type_' . $slug);
        }
        $this->assert_contains('wp_insert_term($name, self::TAX_TYPE', $module, 'module_inserts_default_task_type_terms');
    }

    private function test_canonical_controller_exposes_unified_contract(): void {
        $canonical = $this->read($this->canonical);
        foreach (['get_items', 'get_item', 'create_item', 'update_item', 'delete_item', 'dispatch_task'] as $method) {
            $this->assert_contains('function ' . $method, $canonical, 'canonical_method_' . $method);
        }
        foreach (['_bitcore_priority', '_bitcore_assigned_to', '_bitcore_params'] as $meta_key) {
            $this->assert_contains($meta_key, $canonical, 'canonical_meta_' . $meta_key);
        }
        foreach (['task_status', 'task_type', 'bitcore_channel'] as $taxonomy) {
            $this->assert_contains($taxonomy, $canonical, 'canonical_taxonomy_' . $taxonomy);
        }
    }

    private function test_legacy_controller_is_not_registered_for_public_tasks_route(): void {
        $main = $this->read($this->main);
        $legacy = $this->read($this->legacy);
        $this->assert_contains('class BITCORE_Rest_Tasks', $legacy, 'legacy_controller_file_identified');
        $this->assert_contains("'post_type'    => 'bitcore_task'", $legacy, 'legacy_controller_still_uses_legacy_model');
        $this->assert_not_contains('BITCORE_Rest_Tasks', $this->active_tasks_route_registration_slice($main), 'active_tasks_route_slice_not_legacy_controller');
    }

    private function test_no_missions_or_goals_controller_owns_tasks_route(): void {
        $main = $this->read($this->main);
        $slice = $this->active_tasks_route_registration_slice($main);
        $this->assert_not_contains('Goals_Api', $slice, 'goals_api_does_not_own_tasks_route');
        $this->assert_not_contains('Missions_Api', $slice, 'missions_api_does_not_own_tasks_route');
    }

    private function test_legacy_goals_and_missions_controllers_are_not_active_rest_registrars(): void {
        $main = $this->read($this->main);
        $this->assert_not_contains('class-goals-api.php', $main, 'main_does_not_load_legacy_goals_api');
        $this->assert_not_contains('Goals_Api', $main, 'main_does_not_instantiate_legacy_goals_api');
        $this->assert_not_contains('class-missions-api.php', $main, 'main_does_not_load_legacy_missions_api');
        $this->assert_not_contains('Missions_Api', $main, 'main_does_not_instantiate_legacy_missions_api');
    }

    private function test_legacy_task_like_modules_are_not_active_core_modules(): void {
        $core = $this->read($this->root . '/classes/core.php');
        foreach (['Missions', 'Sprints', 'Marathons'] as $module) {
            $this->assert_not_contains('public ?BITCORE_Modules_' . $module, $core, 'core_has_no_' . strtolower($module) . '_module_property');
            $this->assert_not_contains('new BITCORE_Modules_' . $module, $core, 'core_does_not_instantiate_' . strtolower($module) . '_module');
        }
    }


    private function test_admin_api_tools_documents_canonical_tasks_endpoint(): void {
        $admin = $this->read($this->root . '/admin/api-tools.php');
        $this->assert_contains('/bitcore/v1/tasks', $admin, 'admin_documents_canonical_tasks_endpoint');
        $this->assert_contains('Unified tasks', $admin, 'admin_describes_unified_tasks');
        $this->assert_not_contains('/bitcore/v1/goals', $admin, 'admin_does_not_document_legacy_goals_endpoint');
        $this->assert_not_contains('/bitcore/v1/missions', $admin, 'admin_does_not_document_legacy_missions_endpoint');
        $this->assert_not_contains('Goal hierarchy', $admin, 'admin_does_not_describe_goal_hierarchy_endpoint');
    }

    private function test_obsolete_goals_infrastructure_namespace_is_not_autoloaded(): void {
        $autoload = $this->read($this->root . '/classes/psr4-autoloader.php');
        $this->assert_not_contains('BITCORE\\Infrastructure\\Goals\\', $autoload, 'autoload_does_not_map_obsolete_goals_namespace');
        $this->assert_not_contains('/infrastructure/goals/', $autoload, 'autoload_does_not_map_obsolete_goals_directory');
    }


    private function test_dormant_legacy_controllers_do_not_register_routes(): void {
        $legacy_files = [
            'rest/class-goals-api.php' => ['/goals', 'GoalHierarchy'],
            'rest/class-missions-api.php' => ['/missions', 'mission_status'],
        ];
        foreach ($legacy_files as $relative => $legacy_needles) {
            $content = $this->read($this->root . '/' . $relative);
            $this->assert_contains('@deprecated', $content, 'legacy_controller_marked_deprecated_' . basename($relative));
            $this->assert_not_contains('register_rest_route(', $content, 'legacy_controller_no_register_rest_route_' . basename($relative));
            foreach ($legacy_needles as $needle) {
                $this->assert_not_contains($needle, $content, 'legacy_controller_no_' . preg_replace('/[^a-z0-9]+/i', '_', $relative . '_' . $needle));
            }
            $this->assert_contains('/bitcore/v1/tasks', $content, 'legacy_controller_points_to_canonical_tasks_' . basename($relative));
        }
    }


    private function test_residual_legacy_modules_are_inert_compatibility_shells(): void {
        $mission_module = $this->read($this->root . '/classes/modules/missions.php');
        $this->assert_contains('@deprecated', $mission_module, 'missions_module_marked_deprecated');
        $this->assert_not_contains('add_action(', $mission_module, 'missions_module_no_init_hooks');
        $this->assert_not_contains('register_post_type(', $mission_module, 'missions_module_no_register_post_type');
        $this->assert_not_contains('register_taxonomy(', $mission_module, 'missions_module_no_register_taxonomy');
        $this->assert_not_contains("'mission'", $mission_module, 'missions_module_no_legacy_post_type_literal');
        $this->assert_not_contains('mission_status', $mission_module, 'missions_module_no_legacy_taxonomy');
        $this->assert_not_contains('rest_base', $mission_module, 'missions_module_no_legacy_rest_base');
        $this->assert_contains('/bitcore/v1/tasks', $mission_module, 'missions_module_points_to_canonical_tasks');

        foreach (['GoalHierarchy.php', 'GoalAncestry.php'] as $file) {
            $content = $this->read($this->root . '/infrastructure/goals/' . $file);
            $this->assert_contains('@deprecated', $content, 'goals_infrastructure_marked_deprecated_' . $file);
            $this->assert_not_contains('$wpdb', $content, 'goals_infrastructure_no_wpdb_' . $file);
            $this->assert_not_contains('bitcore_goals', $content, 'goals_infrastructure_no_legacy_table_' . $file);
            $this->assert_not_contains('ancestry_path', $content, 'goals_infrastructure_no_legacy_ancestry_storage_' . $file);
            $this->assert_contains('/bitcore/v1/tasks', $content, 'goals_infrastructure_points_to_canonical_tasks_' . $file);
        }
    }


    private function test_remaining_task_family_legacy_surfaces_are_retired(): void {
        foreach ([
            'classes/modules/sprints.php' => ['sprint_status', 'sprints'],
            'classes/modules/marathons.php' => ['marathon_status', 'marathons'],
        ] as $relative => $legacy_needles) {
            $content = $this->read($this->root . '/' . $relative);
            $this->assert_contains('@deprecated', $content, 'task_family_module_marked_deprecated_' . basename($relative));
            $this->assert_not_contains('add_action(', $content, 'task_family_module_no_init_hooks_' . basename($relative));
            $this->assert_not_contains('register_post_type(', $content, 'task_family_module_no_register_post_type_' . basename($relative));
            $this->assert_not_contains('register_taxonomy(', $content, 'task_family_module_no_register_taxonomy_' . basename($relative));
            $this->assert_not_contains('rest_base', $content, 'task_family_module_no_rest_base_' . basename($relative));
            foreach ($legacy_needles as $needle) {
                $this->assert_not_contains($needle, $content, 'task_family_module_no_' . preg_replace('/[^a-z0-9]+/i', '_', $relative . '_' . $needle));
            }
            $this->assert_contains('/bitcore/v1/tasks', $content, 'task_family_module_points_to_canonical_tasks_' . basename($relative));
        }

        $activity_feed = $this->read($this->root . '/classes/class-activity-feed.php');
        $this->assert_not_contains('bitcore_goals', $activity_feed, 'activity_feed_does_not_query_legacy_goals_table');
        $this->assert_not_contains("'key' => 'goals'", $activity_feed, 'activity_feed_does_not_emit_legacy_goals_bucket');

        $goals_table = $this->read($this->root . '/database/class-goals-table.php');
        $this->assert_contains('@deprecated', $goals_table, 'goals_table_marked_deprecated');
        $this->assert_not_contains('$wpdb', $goals_table, 'goals_table_no_wpdb');
        $this->assert_not_contains('bitcore_goals', $goals_table, 'goals_table_no_legacy_table');
        $this->assert_not_contains('CREATE TABLE', $goals_table, 'goals_table_no_create_table');
        $this->assert_not_contains('DROP TABLE', $goals_table, 'goals_table_no_drop_table');
        $this->assert_contains('/bitcore/v1/tasks', $goals_table, 'goals_table_points_to_canonical_tasks');

        $migration = $this->read($this->root . '/migrations/paperclip-integration.sql');
        $this->assert_not_contains('bitcore_goals', $migration, 'migration_does_not_create_legacy_goals_table');
        $this->assert_not_contains('Hierarchical goal structure', $migration, 'migration_does_not_describe_legacy_goal_hierarchy');
    }

    private function active_tasks_route_registration_slice(string $main): string {
        $lines = preg_split('/\R/', $main) ?: [];
        $hits = [];
        foreach ($lines as $index => $line) {
            if (str_contains($line, 'BITCORE_Rest_Tasks') || str_contains($line, 'REST_Tasks') || str_contains($line, 'class-rest-tasks.php')) {
                $start = max(0, $index - 4);
                $end = min(count($lines) - 1, $index + 8);
                for ($i = $start; $i <= $end; $i++) {
                    $hits[] = $lines[$i];
                }
            }
        }
        return implode("\n", $hits);
    }

    private function read(string $file): string {
        if (!is_file($file)) {
            return '';
        }
        $content = file_get_contents($file);
        return is_string($content) ? $content : '';
    }

    private function assert_true(bool $condition, string $name): void {
        $condition ? $this->pass($name) : $this->fail($name);
    }

    private function assert_contains(string $needle, string $haystack, string $name): void {
        str_contains($haystack, $needle)
            ? $this->pass($name)
            : $this->fail($name . ': missing ' . var_export($needle, true));
    }

    private function assert_not_contains(string $needle, string $haystack, string $name): void {
        !str_contains($haystack, $needle)
            ? $this->pass($name)
            : $this->fail($name . ': unexpected ' . var_export($needle, true));
    }

    private function pass(string $name): void {
        $this->passed++;
        echo "PASS {$name}\n";
    }

    private function fail(string $name): void {
        $this->failed++;
        echo "FAIL {$name}\n";
    }

    private function report(): int {
        echo "\nAssertions passed: {$this->passed}\n";
        echo "Assertions failed: {$this->failed}\n";
        return $this->failed === 0 ? 0 : 1;
    }
}

exit((new TaskUnificationContractTest())->run());
