<?php
/**
 * Core security middleware, health endpoint and admin bootstrap
 *
 * Extracted verbatim from wp-content/plugins/bitcore-core/classes/core.php (BITCORE 500-LOC refactor); logic unchanged.
 *
 * @package    Bitcore
 * @since      2026.03.01
 * @version    1.3.0
 * @author     coraline
 */

declare(strict_types=1);


if (!defined('ABSPATH')) {
    exit;
}


trait BITCORE_Core_Security_Trait {

    /**
     * Register security middleware for REST API
     */
    public function register_security_middleware(): void {
        $pipeline = $this->container->get('security.middleware.pipeline');
        if (!$pipeline) return;

        add_filter('rest_pre_dispatch', function($result, $server, $request) use ($pipeline) {
            $context = new \BITCORE\Infrastructure\Security\RequestContext(
                path: $request->get_route(),
                method: $request->get_method(),
                headers: $request->get_headers(),
                queryParams: $request->get_query_params(),
                body: $request->get_json_params() ?? [],
                clientIp: $request->get_header('x_forwarded_for')
                    ?? $request->get_header('x_real_ip')
                    ?? $_SERVER['REMOTE_ADDR']
                    ?? 'unknown'
            );

            $response = $pipeline->process($context, fn($ctx) => new \BITCORE\Infrastructure\Security\ResponseContext(
                status: 200,
                shouldProceed: true
            ));

            if (!$response->shouldProceed) {
                return new \WP_Error(
                    'security_blocked',
                    $response->error ?? 'Request blocked by security middleware',
                    ['status' => $response->status]
                );
            }

            return $result;
        }, 10, 3);
    }

    /**
     * Register health check REST endpoint
     */
    public function register_health_endpoint(): void {
        register_rest_route('bitcore/v1', '/health', [
            'methods' => 'GET',
            'callback' => function() {
                $monitor = $this->container->get('health.monitor');
                $status = $monitor ? $monitor->checkLocal() : null;

                return rest_ensure_response([
                    'status' => $status ? $status->state->value : 'unknown',
                    'score' => $status ? $status->score : 0,
                    'checks' => $status ? array_map(fn($c) => $c->toArray(), $status->checks) : [],
                    'timestamp' => time(),
                ]);
            },
            'permission_callback' => '__return_true',
        ]);
    }

    private function init_modules(): void {
        if ($this->get_option('module_chatbot', true)) {
            $this->chatbot = new BITCORE_Modules_Chatbot($this);
        }
        if ($this->get_option('module_discussions', true)) {
            $this->discussions = new BITCORE_Modules_Discussions($this);
        }
        if ($this->get_option('module_mcp', true)) {
            $this->mcp = new BITCORE_MCP_Core($this);
        }
        if ($this->get_option('module_search', true)) {
            $this->search = new BITCORE_Modules_Search($this);
        }
        if ($this->get_option('module_forms', true)) {
            $this->forms = new BITCORE_Modules_Forms($this);
        }
        if ($this->get_option('module_tasks', true)) {
            $this->tasks = new BITCORE_Modules_Tasks($this);
        }
    }
    private function init_admin(): void {
        add_action('admin_init', [$this, 'admin_init']);

        // ElizaOS Settings (admin context only)
        require_once BITCORE_PATH . 'admin/class-eliza-settings.php';
        $this->eliza_settings = new BITCORE_Eliza_Settings();
    }


}
