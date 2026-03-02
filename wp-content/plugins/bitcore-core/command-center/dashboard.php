<?php

declare(strict_types=1);

/**
 * BITCORE Command Center - Dashboard Controller
 *
 * @package BITCORE\CommandCenter
 * @since 1.0.0
 */

namespace BITCORE\CommandCenter;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Dashboard Controller
 *
 * Main controller for the Command Center dashboard with swarm orchestration.
 *
 * @since 1.0.0
 */
class Dashboard_Controller
{
    /** @var string REST API namespace */
    private string $namespace = 'bitcore/v1';

    /** @var Swarm_Monitor Swarm monitor instance */
    private Swarm_Monitor $swarm_monitor;

    /** @var Workflow_Runner Workflow runner instance */
    private Workflow_Runner $workflow_runner;

    /**
     * Constructor.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->swarm_monitor = new Swarm_Monitor();
        $this->workflow_runner = new Workflow_Runner();
        $this->init();
    }

    /**
     * Initialize the module.
     *
     * @since 1.0.0
     */
    public function init(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);

        // Register shortcodes for dashboard templates
        add_shortcode('bitcore_dashboard', [$this, 'render_dashboard']);
        add_shortcode('bitcore_agent_panel', [$this, 'render_agent_panel']);
        add_shortcode('bitcore_workflow_panel', [$this, 'render_workflow_panel']);

        if (defined('WP_CLI') && WP_CLI) {
            $this->register_cli_commands();
        }
    }

    /**
     * Register REST API routes.
     *
     * @since 1.0.0
     */
    public function register_routes(): void
    {
        // Swarm endpoints
        register_rest_route($this->namespace, '/command-center/agents', [
            'methods' => 'GET',
            'callback' => [$this, 'get_agents'],
            'permission_callback' => [$this, 'can_view'],
        ]);

        register_rest_route($this->namespace, '/command-center/agents/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_agent'],
            'permission_callback' => [$this, 'can_view'],
        ]);

        register_rest_route($this->namespace, '/command-center/agents/(?P<id>[a-zA-Z0-9_-]+)/ping', [
            'methods' => 'POST',
            'callback' => [$this, 'ping_agent'],
            'permission_callback' => [$this, 'can_manage'],
        ]);

        register_rest_route($this->namespace, '/command-center/health', [
            'methods' => 'GET',
            'callback' => [$this, 'get_health'],
            'permission_callback' => [$this, 'can_view'],
        ]);

        // Workflow endpoints
        register_rest_route($this->namespace, '/command-center/workflows', [
            'methods' => 'GET',
            'callback' => [$this, 'get_workflows'],
            'permission_callback' => [$this, 'can_view'],
        ]);

        register_rest_route($this->namespace, '/command-center/workflows/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_workflow'],
            'permission_callback' => [$this, 'can_view'],
        ]);

        register_rest_route($this->namespace, '/command-center/workflows/(?P<id>\d+)/execute', [
            'methods' => 'POST',
            'callback' => [$this, 'execute_workflow'],
            'permission_callback' => [$this, 'can_execute'],
        ]);

        register_rest_route($this->namespace, '/command-center/executions/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_execution_status'],
            'permission_callback' => [$this, 'can_view'],
        ]);

        register_rest_route($this->namespace, '/command-center/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'get_stats'],
            'permission_callback' => [$this, 'can_view'],
        ]);
    }

    // === REST Callbacks ===

    /**
     * Get all agents.
     */
    public function get_agents(WP_REST_Request $request): WP_REST_Response
    {
        $agents = $this->swarm_monitor->get_all_agents();
        return rest_ensure_response([
            'success' => true,
            'data' => $agents,
        ]);
    }

    /**
     * Get single agent.
     */
    public function get_agent(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = $request->get_param('id');
        $agent = $this->swarm_monitor->get_agent($id);

        if (!$agent) {
            return new WP_Error('agent_not_found', 'Agent not found.', ['status' => 404]);
        }

        return rest_ensure_response([
            'success' => true,
            'data' => $agent,
        ]);
    }

    /**
     * Ping agent.
     */
    public function ping_agent(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = $request->get_param('id');
        $result = $this->swarm_monitor->ping_agent($id);

        return rest_ensure_response([
            'success' => $result['success'] ?? false,
            'data' => $result,
        ]);
    }

    /**
     * Get swarm health.
     */
    public function get_health(): WP_REST_Response
    {
        $health = $this->swarm_monitor->get_health_summary();
        return rest_ensure_response([
            'success' => true,
            'data' => $health,
        ]);
    }

    /**
     * Get workflows.
     */
    public function get_workflows(WP_REST_Request $request): WP_REST_Response
    {
        $active_only = $request->get_param('active') === 'true';
        $workflows = $this->workflow_runner->get_workflows($active_only);
        return rest_ensure_response([
            'success' => true,
            'data' => $workflows,
        ]);
    }

    /**
     * Get single workflow.
     */
    public function get_workflow(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = (int) $request->get_param('id');
        $workflow = $this->workflow_runner->get_workflow($id);

        if (!$workflow) {
            return new WP_Error('workflow_not_found', 'Workflow not found.', ['status' => 404]);
        }

        return rest_ensure_response([
            'success' => true,
            'data' => $workflow,
        ]);
    }

    /**
     * Execute workflow.
     */
    public function execute_workflow(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = (int) $request->get_param('id');
        $payload = $request->get_json_params() ?? [];

        $result = $this->workflow_runner->execute($id, $payload);

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Get execution status.
     */
    public function get_execution_status(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = $request->get_param('id');
        $result = $this->workflow_runner->get_execution_status($id);

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Get combined stats.
     */
    public function get_stats(): WP_REST_Response
    {
        return rest_ensure_response([
            'success' => true,
            'data' => [
                'swarm' => $this->swarm_monitor->get_health_summary(),
                'workflows' => $this->workflow_runner->get_stats(),
            ],
        ]);
    }

    // === Permission Callbacks ===

    public function can_view(): bool
    {
        return current_user_can('read');
    }

    public function can_manage(): bool
    {
        return current_user_can('manage_options');
    }

    public function can_execute(): bool
    {
        return current_user_can('edit_posts');
    }

    // === Shortcode Renders ===

    /**
     * Render main dashboard.
     */
    public function render_dashboard(): string
    {
        ob_start();
        include __DIR__ . '/templates/dashboard.php';
        return ob_get_clean() ?: '';
    }

    /**
     * Render agent panel.
     */
    public function render_agent_panel(): string
    {
        ob_start();
        include __DIR__ . '/templates/agent-panel.php';
        return ob_get_clean() ?: '';
    }

    /**
     * Render workflow panel.
     */
    public function render_workflow_panel(): string
    {
        ob_start();
        include __DIR__ . '/templates/workflow-panel.php';
        return ob_get_clean() ?: '';
    }

    // === CLI Commands ===

    private function register_cli_commands(): void
    {
        \WP_CLI::add_command('bitcore swarm list', [$this, 'cli_swarm_list']);
        \WP_CLI::add_command('bitcore swarm ping', [$this, 'cli_swarm_ping']);
        \WP_CLI::add_command('bitcore workflow list', [$this, 'cli_workflow_list']);
        \WP_CLI::add_command('bitcore workflow run', [$this, 'cli_workflow_run']);
    }

    public function cli_swarm_list(): void
    {
        $agents = $this->swarm_monitor->get_all_agents();
        $table = [];

        foreach ($agents as $agent) {
            $table[] = [
                'ID' => $agent['id'],
                'Name' => $agent['name'],
                'Status' => $agent['status'],
                'Type' => $agent['type'],
                'Endpoint' => $agent['endpoint'] ?? 'N/A',
            ];
        }

        \WP_CLI\Utils\format_items('table', $table, ['ID', 'Name', 'Status', 'Type', 'Endpoint']);
        \WP_CLI::success(sprintf('%d agents found.', count($agents)));
    }

    public function cli_swarm_ping(array $args): void
    {
        if (!isset($args[0])) {
            \WP_CLI::error('Agent ID required.');
            return;
        }

        $result = $this->swarm_monitor->ping_agent($args[0]);

        if ($result['success']) {
            \WP_CLI::success('Agent is reachable.');
        } else {
            \WP_CLI::error('Agent unreachable: ' . ($result['error'] ?? 'Unknown error'));
        }
    }

    public function cli_workflow_list(): void
    {
        $workflows = $this->workflow_runner->get_workflows();
        $table = [];

        foreach ($workflows as $workflow) {
            $table[] = [
                'ID' => $workflow['id'] ?? 'N/A',
                'Name' => $workflow['name'] ?? 'Unnamed',
                'Active' => ($workflow['active'] ?? false) ? 'Yes' : 'No',
            ];
        }

        \WP_CLI\Utils\format_items('table', $table, ['ID', 'Name', 'Active']);
        \WP_CLI::success(sprintf('%d workflows found.', count($workflows)));
    }

    public function cli_workflow_run(array $args): void
    {
        if (!isset($args[0])) {
            \WP_CLI::error('Workflow ID required.');
            return;
        }

        $result = $this->workflow_runner->execute((int) $args[0]);

        if (is_wp_error($result)) {
            \WP_CLI::error($result->get_error_message());
            return;
        }

        \WP_CLI::success('Workflow executed successfully.');
    }
}

// Initialize
new Dashboard_Controller();
