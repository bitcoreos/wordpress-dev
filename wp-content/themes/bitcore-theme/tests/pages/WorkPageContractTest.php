<?php
declare(strict_types=1);

/**
 * P2.6 Work Page Contract Test.
 *
 * Purpose:
 * - Work UI must consume the canonical /bitcore/v1/tasks API only.
 * - Goals/Missions are task_type-filtered task views, not separate REST resources.
 * - This test is source-only: no WordPress bootstrap, no DB writes, no network calls.
 */
final class WorkPageContractTest {
    private int $passed = 0;
    private int $failed = 0;
    private string $page;
    private string $js;

    public function __construct() {
        $themeRoot = dirname(__DIR__, 2);
        $pagePath = $themeRoot . '/page-work.php';
        $jsPath = $themeRoot . '/assets/js/work.js';
        if (!is_file($pagePath)) {
            fwrite(STDERR, "Missing page-work.php\n");
            exit(1);
        }
        if (!is_file($jsPath)) {
            fwrite(STDERR, "Missing assets/js/work.js\n");
            exit(1);
        }
        $this->page = file_get_contents($pagePath) ?: '';
        $this->js = file_get_contents($jsPath) ?: '';
    }

    public function run(): int {
        $this->test_template_and_rest_bootstrap_contract();
        $this->test_work_ui_uses_only_canonical_tasks_rest_resource();
        $this->test_goal_and_mission_views_are_task_type_filtered_views();
        $this->test_create_actions_post_to_tasks_with_task_type_payloads();
        $this->test_delete_and_status_updates_target_tasks_resource();
        $this->test_unified_task_form_exposes_type_status_and_agent_assignment();
        $this->test_task_create_payload_uses_selected_type_status_priority_and_agent();
        $this->test_task_detail_supports_status_update_and_dispatch();
        $this->test_sprint_is_supported_as_unified_task_type();
        $this->test_no_placeholder_random_or_dead_legacy_api_contract();
        echo "\nAssertions passed: {$this->passed}\n";
        echo "Assertions failed: {$this->failed}\n";
        return $this->failed === 0 ? 0 : 1;
    }

    private function test_template_and_rest_bootstrap_contract(): void {
        $this->assert_contains('Template Name: Work', $this->page, 'work_template_name_present');
        $this->assert_contains("'restUrl' => rest_url('bitcore/v1/')", $this->page, 'work_exposes_bitcore_rest_base');
        $this->assert_contains("'nonce' => wp_create_nonce('wp_rest')", $this->page, 'work_exposes_wp_rest_nonce');
        $this->assert_contains('/assets/js/work.js', $this->page, 'work_loads_controller_js');
        $this->assert_contains('Tasks · Goals · Missions', $this->page, 'work_labels_unified_task_goal_mission_concept');
    }

    private function test_work_ui_uses_only_canonical_tasks_rest_resource(): void {
        foreach (["+ 'tasks'", "+ 'tasks/", "'tasks'", "'tasks/"] as $needle) {
            $this->assert_contains($needle, $this->js, 'work_uses_canonical_tasks_route_' . preg_replace('/[^a-z0-9]+/i', '_', $needle));
        }
        foreach (["this.config.restUrl + 'goals'", "this.config.restUrl + 'goals/", "/wp-json/bitcore/v1/goals"] as $needle) {
            $this->assert_not_contains($needle, $this->js, 'work_does_not_call_legacy_goals_route_' . preg_replace('/[^a-z0-9]+/i', '_', $needle));
        }
        foreach (["this.config.restUrl + 'missions'", "this.config.restUrl + 'missions/", "/wp-json/bitcore/v1/missions"] as $needle) {
            $this->assert_not_contains($needle, $this->js, 'work_does_not_call_legacy_missions_route_' . preg_replace('/[^a-z0-9]+/i', '_', $needle));
        }
    }

    private function test_goal_and_mission_views_are_task_type_filtered_views(): void {
        $loadGoals = $this->extract_function_body('loadGoals', $this->js);
        $loadMissions = $this->extract_function_body('loadMissions', $this->js);
        $this->assert_contains("'tasks?task_type=goal'", $loadGoals, 'work_goals_load_from_tasks_goal_filter');
        $this->assert_contains("'tasks?task_type=milestone'", $loadMissions, 'work_missions_load_from_tasks_milestone_filter');
        $this->assert_not_contains("this.config.restUrl + 'goals'", $loadGoals, 'work_goals_loader_no_legacy_endpoint');
        $this->assert_not_contains("this.config.restUrl + 'missions'", $loadMissions, 'work_missions_loader_no_legacy_endpoint');
    }

    private function test_create_actions_post_to_tasks_with_task_type_payloads(): void {
        $createTask = $this->extract_function_body('createTask', $this->js);
        $createGoal = $this->extract_function_body('createGoal', $this->js);
        $createMission = $this->extract_function_body('createMission', $this->js);
        $this->assert_contains("this.config.restUrl + 'tasks'", $createTask, 'work_create_task_posts_tasks');
        $this->assert_contains("this.config.restUrl + 'tasks'", $createGoal, 'work_create_goal_posts_tasks');
        $this->assert_contains("this.config.restUrl + 'tasks'", $createMission, 'work_create_mission_posts_tasks');
        $this->assert_contains("task_type: typeEl ? typeEl.value : 'task'", $createTask, 'work_create_task_uses_selected_task_type_in_create_actions');
        $this->assert_contains("task_type: 'goal'", $createGoal, 'work_create_goal_sets_task_type_goal');
        $this->assert_contains("task_type: 'milestone'", $createMission, 'work_create_mission_sets_task_type_milestone');
    }

    private function test_delete_and_status_updates_target_tasks_resource(): void {
        $deleteGoal = $this->extract_function_body('deleteGoal', $this->js);
        $updateMission = $this->extract_function_body('updateMissionStatus', $this->js);
        $deleteMission = $this->extract_function_body('deleteMission', $this->js);
        $this->assert_contains("this.config.restUrl + 'tasks/' + goalId", $deleteGoal, 'work_delete_goal_targets_tasks_resource');
        $this->assert_contains("this.config.restUrl + 'tasks/' + missionId", $updateMission, 'work_update_mission_targets_tasks_resource');
        $this->assert_contains("this.config.restUrl + 'tasks/' + missionId", $deleteMission, 'work_delete_mission_targets_tasks_resource');
        $this->assert_contains('method: \'POST\'', $updateMission, 'work_update_mission_uses_existing_post_update_contract');
        $this->assert_contains('status: newStatus', $updateMission, 'work_update_mission_sends_status');
    }

    private function test_unified_task_form_exposes_type_status_and_agent_assignment(): void {
        foreach (['new-task-type', 'new-task-status', 'new-task-assigned-to', 'new-task-priority'] as $id) {
            $this->assert_contains('id="' . $id . '"', $this->page, 'work_create_task_form_has_' . str_replace('-', '_', $id));
        }
        foreach (['task', 'goal', 'milestone', 'sprint'] as $type) {
            $this->assert_contains('<option value="' . $type . '"', $this->page, 'work_create_task_form_supports_type_' . $type);
        }
        foreach (['inbox', 'backlog', 'doing', 'review', 'done', 'archived'] as $status) {
            $this->assert_contains('<option value="' . $status . '"', $this->page, 'work_create_task_form_supports_status_' . $status);
        }
    }

    private function test_task_create_payload_uses_selected_type_status_priority_and_agent(): void {
        $createTask = $this->extract_function_body('createTask', $this->js);
        foreach (['new-task-type', 'new-task-status', 'new-task-priority', 'new-task-assigned-to'] as $id) {
            $this->assert_contains("document.getElementById('" . $id . "')", $createTask, 'work_create_task_reads_' . str_replace('-', '_', $id));
        }
        $this->assert_contains('task_type: typeEl ? typeEl.value : \'task\'', $createTask, 'work_create_task_uses_selected_task_type');
        $this->assert_contains('status: statusEl ? statusEl.value : \'inbox\'', $createTask, 'work_create_task_uses_selected_status');
        $this->assert_contains('priority: priorityEl ? priorityEl.value : \'normal\'', $createTask, 'work_create_task_uses_selected_priority');
        $this->assert_contains('assigned_to: assignedEl ? assignedEl.value : \'\'', $createTask, 'work_create_task_uses_selected_assigned_agent');
        $this->assert_not_contains("task_type: 'task'", $createTask, 'work_create_task_no_hardcoded_task_type');
    }

    private function test_task_detail_supports_status_update_and_dispatch(): void {
        $selectTask = $this->extract_function_body('selectTask', $this->js);
        $this->assert_contains('task-status-select', $selectTask, 'work_task_detail_has_status_select');
        $this->assert_contains('WorkPage.updateTaskStatus(', $selectTask, 'work_task_detail_has_status_update_action');
        $this->assert_contains('WorkPage.dispatchTask(', $selectTask, 'work_task_detail_has_dispatch_action');
        $this->assert_contains('Assigned:', $selectTask, 'work_task_detail_displays_assigned_agent');

        $updateTaskStatus = $this->extract_function_body('updateTaskStatus', $this->js);
        $this->assert_contains("this.config.restUrl + 'tasks/' + taskId", $updateTaskStatus, 'work_update_task_status_targets_tasks_resource');
        $this->assert_contains("method: 'PUT'", $updateTaskStatus, 'work_update_task_status_uses_put_contract');
        $this->assert_contains('status: newStatus', $updateTaskStatus, 'work_update_task_status_sends_status');

        $dispatchTask = $this->extract_function_body('dispatchTask', $this->js);
        $this->assert_contains("this.config.restUrl + 'tasks/' + taskId + '/dispatch'", $dispatchTask, 'work_dispatch_task_targets_dispatch_endpoint');
        $this->assert_contains("method: 'POST'", $dispatchTask, 'work_dispatch_task_uses_post_contract');
    }

    private function test_sprint_is_supported_as_unified_task_type(): void {
        $loadSprints = $this->extract_function_body('loadSprints', $this->js);
        $createTask = $this->extract_function_body('createTask', $this->js);
        $this->assert_contains("'tasks?task_type=sprint'", $this->js, 'work_supports_sprint_task_type_filter');
        $this->assert_contains("task_type: typeEl ? typeEl.value : 'task'", $createTask, 'work_create_task_can_submit_selected_sprint_type');
        $this->assert_contains('<option value="sprint"', $this->page, 'work_page_exposes_sprint_option_for_selected_submit');
        $this->assert_not_contains("this.config.restUrl + 'sprints'", $this->js, 'work_no_legacy_sprints_endpoint');
    }

    private function test_no_placeholder_random_or_dead_legacy_api_contract(): void {
        foreach (['rand(', 'mt_rand(', 'wp_rand(', 'TODO'] as $needle) {
            $this->assert_not_contains($needle, $this->page . "\n" . $this->js, 'work_no_' . preg_replace('/[^a-z0-9]+/i', '_', $needle));
        }
        $this->assert_not_contains('/wp-json/bitcore/v1/goals', $this->js, 'work_no_absolute_goals_rest_route');
        $this->assert_not_contains('/wp-json/bitcore/v1/missions', $this->js, 'work_no_absolute_missions_rest_route');
    }

    private function extract_function_body(string $functionName, string $source): string {
        $needle = $functionName . ': async function';
        $offset = strpos($source, $needle);
        if ($offset === false) {
            $needle = $functionName . ': function';
            $offset = strpos($source, $needle);
        }
        if ($offset === false) { return ''; }
        $brace = strpos($source, '{', $offset);
        if ($brace === false) { return ''; }
        $depth = 0;
        $length = strlen($source);
        for ($i = $brace; $i < $length; $i++) {
            if ($source[$i] === '{') { $depth++; }
            if ($source[$i] === '}') { $depth--; }
            if ($depth === 0) { return substr($source, $offset, $i - $offset + 1); }
        }
        return substr($source, $offset);
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
}

exit((new WorkPageContractTest())->run());
