<?php
/**
 * BITCORE Core Class
 * Pattern: Service Container with Modular Architecture
 * 
 * Service-oriented architecture with dependency injection container,
 * encrypted options, capability-based model selection, security middleware,
 * and A2A swarm coordination.
 *
 * @package BITCORE
 * @since 1.0.0
 */

declare(strict_types=1);

class BITCORE_Core {
    public string $version = BITCORE_VERSION;
    public bool $is_rest = false;
    public bool $is_cli = false;

    /** @var BITCORE_Container Service container */
    public ?BITCORE_Container $container = null;

    // Coraline: Pattern-based services
    public $encrypted_options = null;
    public $model_selector = null;
    public $rest_auth_filter = null;

    // Hyperion: Infrastructure service instances
    public ?BITCORE_Services_Response_ID_Manager $response_id_manager = null;
    public ?BITCORE_Services_Message_Builder $message_builder = null;
    public ?BITCORE_Services_Session $session_service = null;
    public ?BITCORE_Services_Usage_Stats $usage_stats = null;
    public ?BITCORE_Services_Connector_N8N $connector_n8n = null;
    public ?BITCORE_Services_Connector_BIThub $connector_bithub = null;
    public ?BITCORE_Services_Connector_BITwiki $connector_bitwiki = null;

    // Module instances
    public ?BITCORE_Modules_Chatbot $chatbot = null;
    public ?BITCORE_Modules_Discussions $discussions = null;
    public ?BITCORE_MCP_Core $mcp = null;
    public ?BITCORE_Rest_API $rest = null;
    public ?BITCORE_Rest_Tasks $rest_tasks = null;
    public ?BITCORE_Rest_Hermes_Api $rest_hermes = null;
    public ?BITCORE_Services_Connector_Hermes $connector_hermes = null;
    public ?BITCORE_Modules_Search $search = null;
    public ?BITCORE_Modules_Forms $forms = null;
    public ?BITCORE_Modules_Tasks $tasks = null;

    private ?array $options = null;

    public function __construct() {
        $this->is_rest = $this->is_rest_request();
        $this->is_cli = defined('WP_CLI') && WP_CLI;
        
        // Initialize container BEFORE plugins_loaded (Hyperion pattern)
        $this->init_container();

        add_action('rest_api_init', [$this, 'init_rest'], 5);
        add_action('plugins_loaded', [$this, 'init']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('admin_menu', [$this, 'admin_menu']);
    }

    /**
     * Initialize service container (Hyperion pattern with Coraline additions)
     */
    private function init_container(): void {
        require_once BITCORE_PATH . 'classes/container.php';
        $this->container = new BITCORE_Container();
        $this->container->register_core_services($this);

        // === Coraline: Intelligence Services ===
        $this->container->register('encrypted_options', function() {
            require_once BITCORE_PATH . 'classes/encrypted-options.php';
            return new BITCORE_Encrypted_Options();
        });
        
        $this->container->register('model_selector', function() {
            require_once BITCORE_PATH . 'classes/model-selector.php';
            return new BITCORE_Model_Selector();
        });

        // === Hyperion: Infrastructure Services ===
        $this->container->register('auth.manager', function() { return new \BITCORE\Infrastructure\Auth\AuthManager(); });
        $this->container->register('auth.capability', function() { return new \BITCORE\Infrastructure\Auth\CapabilityManager(); });
        $this->container->register('tenant.resolver', function() { return new \BITCORE\Infrastructure\MultiTenancy\TenantResolver(); });
        $this->container->register('cache.manager', function() { return new \BITCORE\Infrastructure\Cache\CacheManager(); });
        require_once BITCORE_PATH . 'infrastructure/queue/QueueWorker.php';
        $this->container->register('queue.worker', function() { return new \BITCORE\Infrastructure\Queue\QueueWorker(); });
        $this->container->register('logger', function() { return new \BITCORE\Infrastructure\Logging\Logger(); });
        $this->container->register('ratelimit.manager', function() { return new \BITCORE\Infrastructure\RateLimit\RateLimitManager(); });
        require_once BITCORE_PATH . 'infrastructure/webhooks/WebhookManager.php';
        $this->container->register('webhooks.manager', function() { return new \BITCORE\Infrastructure\Webhooks\WebhookManager(); });

        // === Hyperion: Wave 4 Security Services ===
        require_once BITCORE_PATH . 'infrastructure/security/middleware-pipeline.php';
        $this->container->register('security.middleware.pipeline', function() { return new \BITCORE\Infrastructure\Security\MiddlewarePipeline(); });

        require_once BITCORE_PATH . 'infrastructure/security/audit-logger.php';
        $this->container->register('security.audit.logger', function() { return new \BITCORE\Infrastructure\Security\AuditLogger(); });

        require_once BITCORE_PATH . 'infrastructure/security/hardening.php';
        $this->container->register('security.hardening.manager', function() { return new \BITCORE\Infrastructure\Security\HardeningManager(); });

        // === Hyperion: A2A Swarm Services ===
        require_once BITCORE_PATH . 'infrastructure/swarm/a2a-dispatcher.php';
        $this->container->register('swarm.dispatcher', function() { return new \BITCORE\Infrastructure\Swarm\A2ADispatcher(); });

        require_once BITCORE_PATH . 'infrastructure/swarm/swarm-coordinator.php';
        $this->container->register('swarm.coordinator', function() { return new \BITCORE\Infrastructure\Swarm\SwarmCoordinator(); });

        require_once BITCORE_PATH . 'infrastructure/swarm/health-monitor.php';
        $this->container->register('health.monitor', function() { return new \BITCORE\Infrastructure\Swarm\HealthMonitor(); });

        // Initialize Wave 4 Services
        add_action('bitcore_init', [$this, 'init_wave4_services']);
    }

    /**
     * Initialize Wave 4 security and swarm services
     */
    public function init_wave4_services(): void {
        // Apply hardening
        $hardening = $this->container->get('security.hardening.manager');
        if ($hardening) {
            $hardening->apply();
        }

        // Initialize health monitoring
        $monitor = $this->container->get('health.monitor');
        if ($monitor && class_exists('\BITCORE\Infrastructure\Swarm\DatabaseHealthChecker')) {
            $monitor->registerChecker(new \BITCORE\Infrastructure\Swarm\DatabaseHealthChecker());
            $cacheManager = $this->container->get('cache.manager');
            if ($cacheManager) {
                $monitor->registerChecker(new \BITCORE\Infrastructure\Swarm\CacheHealthChecker($cacheManager));
            }
        }
    }

    /**
     * Early REST API initialization (runs before plugins_loaded)
     */
    public function init_rest(): void {
        require_once BITCORE_PATH . 'classes/services/abstract-connector.php';
        require_once BITCORE_PATH . 'classes/services/connector-hermes.php';
        require_once BITCORE_PATH . 'rest/hermes-api.php';
        $this->connector_hermes = new BITCORE_Services_Connector_Hermes($this);
        $this->rest_hermes = new BITCORE_Rest_Hermes_Api($this);
    }

    public function init(): void {
        // Initialize Coraline pattern-based services
        $this->encrypted_options = $this->container->get('encrypted_options');
        $this->model_selector = $this->container->get('model_selector');

        // Initialize core services
        $this->init_services();

        // Initialize modules based on options
        $this->init_modules();

        // REST API with auth filter (Coraline) and security middleware (Hyperion)
        if ($this->is_rest) {
            $this->rest_auth_filter = new BITCORE_Rest_Auth_Filter();
            $this->rest = new BITCORE_Rest_API($this);
            $this->rest_tasks = new BITCORE_Rest_Tasks($this);
        // REST Hermes initialized in init_rest() for early hook timing
        require_once BITCORE_PATH . 'classes/services/connector-hermes.php';
        $this->connector_hermes = new BITCORE_Services_Connector_Hermes($this);
        }

        // Initialize Wave 4 security middleware
        $this->init_wave4();

        // Admin
        if (is_admin()) {
            $this->init_admin();
        }

        // Shortcodes
        add_shortcode('bitcore_chat', [$this, 'chatbot_shortcode']);
        add_shortcode('bitcore_agent', [$this, 'agent_shortcode']);
        add_shortcode('bitcore_artifacts', [$this, 'artifacts_shortcode']);
        add_shortcode('bitcore_feed', [$this, 'feed_shortcode']);
    }

    private function init_services(): void {
        $this->response_id_manager = $this->container->get('response_id_manager');
        $this->message_builder = $this->container->get('message_builder');
        $this->session_service = $this->container->get('session_service');
        $this->usage_stats = $this->container->get('usage_stats');
        $this->connector_n8n = $this->container->get('connector_n8n');
        $this->connector_bithub = $this->container->get('connector_bithub');
        $this->connector_bitwiki = $this->container->get('connector_bitwiki');
    }

    /**
     * Initialize Wave 4 security and swarm modules
     */
    public function init_wave4(): void {
        // Security middleware pipeline
        add_action('rest_api_init', [$this, 'register_security_middleware']);

        // Health check endpoint
        add_action('rest_api_init', [$this, 'register_health_endpoint']);
    }

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
    }

    public function admin_init(): void {}

    public function admin_menu(): void {
        add_menu_page('BITCORE', 'BITCORE', 'manage_options', 'bitcore', [$this, 'admin_page'], 'dashicons-art', 30);
        add_submenu_page('bitcore', 'Dashboard', 'Dashboard', 'manage_options', 'bitcore', [$this, 'admin_page']);
        add_submenu_page('bitcore', 'Agents', 'Agents', 'manage_options', 'bitcore-agents', [$this, 'agents_page']);
        add_submenu_page('bitcore', 'MCP Tools', 'MCP Tools', 'manage_options', 'bitcore-mcp', [$this, 'mcp_page']);
        add_submenu_page('bitcore', 'Settings', 'Settings', 'manage_options', 'bitcore-settings', [$this, 'settings_page']);
    }

    public function admin_page(): void { include BITCORE_PATH . '/admin/dashboard.php'; }
    public function agents_page(): void { include BITCORE_PATH . '/admin/agents.php'; }
    public function mcp_page(): void { include BITCORE_PATH . '/admin/mcp.php'; }
    public function settings_page(): void { include BITCORE_PATH . '/admin/settings.php'; }

    public function enqueue_scripts(): void {
        wp_enqueue_style('bitcore-base', BITCORE_URL . 'assets/css/bitcore-base.css', [], BITCORE_VERSION);
        wp_enqueue_script('bitcore-chat', BITCORE_URL . 'assets/js/bitcore-chat.js', ['jquery'], BITCORE_VERSION, true);
        wp_localize_script('bitcore-chat', 'bitcoreConfig', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('bitcore/v1/'),
            'nonce' => wp_create_nonce('bitcore_nonce'),
            'userId' => get_current_user_id(),
            'userName' => wp_get_current_user()->display_name,
        ]);
    }

    public function enqueue_admin_scripts(string $hook): void {
        if (strpos($hook, 'bitcore') !== false) {
            wp_enqueue_style('bitcore-admin', BITCORE_URL . 'assets/css/bitcore-admin.css', [], BITCORE_VERSION);
            wp_enqueue_script('bitcore-admin', BITCORE_URL . 'assets/js/bitcore-admin.js', ['jquery'], BITCORE_VERSION, true);
        }
    }

    // Shortcode handlers
    public function chatbot_shortcode(array $atts): string {
        $atts = shortcode_atts(['agent' => 'coraline', 'theme' => 'dark'], $atts);
        ob_start();
        include BITCORE_PATH . '/templates/chatbot.php';
        return ob_get_clean();
    }

    public function agent_shortcode(array $atts): string {
        $atts = shortcode_atts(['name' => 'coraline', 'show_avatar' => true], $atts);
        return '<div class="bitcore-agent" data-agent="' . esc_attr($atts['name']) . '"></div>';
    }

    public function artifacts_shortcode(): string { return '<div class="bitcore-artifacts"></div>'; }
    public function feed_shortcode(): string { return '<div class="bitcore-feed"></div>'; }

    // === Coraline: Encrypted options handling ===
    public function get_option(string $key, $default = false) {
        $encrypted_keys = ['api_key', 'secret', 'token', 'password'];
        foreach ($encrypted_keys as $encrypted_key) {
            if (strpos($key, $encrypted_key) !== false && $this->encrypted_options) {
                return $this->encrypted_options->get_encrypted_option($key, $default);
            }
        }
        $options = get_option('bitcore_options', []);
        return $options[$key] ?? $default;
    }

    public function update_option(string $key, $value): void {
        $encrypted_keys = ['api_key', 'secret', 'token', 'password'];
        foreach ($encrypted_keys as $encrypted_key) {
            if (strpos($key, $encrypted_key) !== false && $this->encrypted_options) {
                $this->encrypted_options->update_encrypted_option($key, $value);
                return;
            }
        }
        $options = get_option('bitcore_options', []);
        $options[$key] = $value;
        update_option('bitcore_options', $options);
    }

    /**
     * Get AI model for current user and task (Coraline)
     */
    public function get_model_for_task(string $task_type = 'chat') {
        if (!$this->model_selector) return false;
        return $this->model_selector->select_model_for_user(get_current_user_id(), $task_type);
    }

    /**
     * Get service from container
     */
    public function get_service(string $name) {
        return $this->container ? $this->container->get($name) : null;
    }

    private function is_rest_request(): bool {
        return defined('REST_REQUEST') && REST_REQUEST;
    }

    /**
     * Plugin activation handler
     */
    public static function activate(): void {
        if (class_exists('BITCORE_Modules_Capabilities')) {
            BITCORE_Modules_Capabilities::register();
        }
        flush_rewrite_rules();
        do_action('bitcore_activated');
    }

    /**
     * Plugin deactivation handler
     */
    public static function deactivate(): void {
        if (class_exists('BITCORE_Modules_Capabilities')) {
            BITCORE_Modules_Capabilities::unregister();
        }
        flush_rewrite_rules();
        do_action('bitcore_deactivated');
    }
}
