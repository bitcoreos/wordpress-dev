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
 * @version     1.0.0
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
    public $eliza = null;
    public $eliza_settings = null;
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
        // admin_menu handled by admin/menu-registration.php
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
        // NOTE: Six infrastructure files are MISSING - commented out until built.
        // Missing: Auth/AuthManager.php, Auth/CapabilityManager.php, MultiTenancy/TenantResolver.php,
        //          Cache/CacheManager.php, Logging/Logger.php, RateLimit/RateLimitManager.php
        // $this->container->register('auth.manager', function() { return new \BITCORE\Infrastructure\Auth\AuthManager(); });
        // $this->container->register('auth.capability', function() { return new \BITCORE\Infrastructure\Auth\CapabilityManager(); });
        // $this->container->register('tenant.resolver', function() { return new \BITCORE\Infrastructure\MultiTenancy\TenantResolver(); });
        // $this->container->register('cache.manager', function() { return new \BITCORE\Infrastructure\Cache\CacheManager(); });
        // QueueWorker requires Logger which is missing — commented out until built.
        // require_once BITCORE_PATH . 'infrastructure/queue/QueueWorker.php';
        // $this->container->register('queue.worker', function($c) {
        //     require_once BITCORE_PATH . 'infrastructure/queue/JobRepository.php';
        //     return new \BITCORE\Infrastructure\Queue\QueueWorker(
        //         $c->get('job.repository'),
        //         $c->get('logger')
        //     );
        // });
        // $this->container->register('logger', function() { return new \BITCORE\Infrastructure\Logging\Logger(); });
        // $this->container->register('ratelimit.manager', function() { return new \BITCORE\Infrastructure\RateLimit\RateLimitManager(); });
        require_once BITCORE_PATH . 'infrastructure/webhooks/WebhookManager.php';
        // DISABLED: WebhookManager requires 4 constructor args (repository, httpClient, logger, signatureVerifier)
        // which are not registered in the container. Re-enable after wiring dependencies.
        // $this->container->register('webhooks.manager', function() { return new \BITCORE\Infrastructure\Webhooks\WebhookManager(); });

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

        // x402 Payment Verification
        require_once BITCORE_PATH . 'infrastructure/class-x402.php';
        require_once BITCORE_PATH . 'infrastructure/class-x402-facilitator.php';
        $this->container->register('x402.verifier', function() {
            return new \BITCORE\Infrastructure\X402_Verifier();
        });
        $this->container->register('x402.facilitator', function() {
            return new \BITCORE\Infrastructure\X402_Facilitator_Client();
        });

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

        // Sessions API
        require_once BITCORE_PATH . 'rest/class-sessions-api.php';
        new \BITCORE\Rest\Sessions_Api();

        // ElizaOS Bridge — constructor calls register_routes()
        require_once BITCORE_PATH . 'rest/class-eliza-api.php';  // loaded once
        new BITCORE_Rest_Eliza_Api($this);

        // Knowledge API — constructor calls register_routes()
        require_once BITCORE_PATH . 'rest/class-knowledge-api.php';
        new \BITCORE\Rest\Knowledge_Api();

        // Abstract connector for services
        require_once BITCORE_PATH . 'classes/services/abstract-connector.php';
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
        }

        // Initialize Wave 4 security middleware
        $this->init_wave4();

        // ElizaOS provisioning hooks (global — registration/login can happen outside admin)
        add_action('user_register', [$this, 'eliza_user_registered']);
        add_action('wp_login', [$this, 'eliza_user_login'], 10, 2);

        // Subscription provisioning hook. Payment integrations must verify purchase first.
        add_action('bitcore_subscription_purchase', [$this, 'provision_subscription_purchase'], 10, 3);

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
        // x402 Payment Verification — instantiate to register filter hooks
        $this->container->get('x402.verifier');
        $this->container->get('x402.facilitator');
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

        // ElizaOS Settings (admin context only)
        require_once BITCORE_PATH . 'admin/class-eliza-settings.php';
        $this->eliza_settings = new BITCORE_Eliza_Settings();
    }

    /**
     * Provision new user with default ElizaOS session on registration.
     *
     * @param int $user_id WordPress user ID of newly registered user
     */
    public function eliza_user_registered(int $user_id): void {
        // Skip if user has no read capability
        if (!user_can($user_id, 'read')) {
            return;
        }

        // Provision once only. Existing active-agent choices must survive re-fired hooks.
        $active_agent = get_user_meta($user_id, 'bitcore_eliza_active_agent', true);
        if (!empty($active_agent)) {
            return;
        }

        [$default_agent, $agent_id] = $this->get_default_eliza_agent_assignment();

        // Set active agent for the new user
        update_user_meta($user_id, 'bitcore_eliza_active_agent', $agent_id);
        update_user_meta($user_id, 'bitcore_eliza_active_agent_name', $default_agent);

        // Credits usage is lazily initialized by Credits_Manager.
    }

    /**
     * Resume user's active ElizaOS agent session on login.
     *
     * @param string $user_login Username
     * @param WP_User $user User object
     */
    public function eliza_user_login(string $user_login, WP_User $user): void {
        $user_id = $user->ID;

        // Check if user has an active agent
        $active_agent = get_user_meta($user_id, 'bitcore_eliza_active_agent', true);

        if (empty($active_agent)) {
            [$default_agent, $agent_id] = $this->get_default_eliza_agent_assignment();

            update_user_meta($user_id, 'bitcore_eliza_active_agent', $agent_id);
            update_user_meta($user_id, 'bitcore_eliza_active_agent_name', $default_agent);
        }

        // Credits usage is lazily initialized by Credits_Manager.
    }

    /**
     * Provision subscription-derived agent quota after verified purchase.
     *
     * This method is deliberately provider-agnostic. Checkout, WooCommerce,
     * PMPro, EDD, or x402 adapters should call `do_action(
     * 'bitcore_subscription_purchase', $user_id, $tier, $metadata )` only
     * after their own payment verification succeeds.
     *
     * @param array<string,mixed> $metadata Purchase metadata sanitized by Credits_Manager.
     */
    public function provision_subscription_purchase(int $user_id, string $tier, array $metadata = []): bool {
        require_once BITCORE_PATH . 'infrastructure/class-credits.php';

        if (!class_exists('\BITCORE\Infrastructure\Credits_Manager')) {
            return false;
        }

        $credits = new \BITCORE\Infrastructure\Credits_Manager();
        return $credits->provision_subscription_purchase($user_id, $tier, $metadata);
    }

    /**
     * Return a normalized default ElizaOS agent assignment.
     *
     * @return array{0:string,1:string} Tuple of agent slug and agent UUID.
     */
    private function get_default_eliza_agent_assignment(): array {
        $default_agent = (string) get_option('bitcore_eliza_default_agent', 'aria');
        $agent_map = [
            'aria'  => (string) get_option('bitcore_eliza_agent_aria', 'eac677dd-94c4-02ab-bfea-bc554e52b2a3'),
            'forge' => (string) get_option('bitcore_eliza_agent_forge', '3ab11b75-ca36-0cee-a3d7-7bfcab3c27cd'),
            'muse'  => (string) get_option('bitcore_eliza_agent_muse', 'cf0dee2b-9fc6-041b-83a0-90a644d26362'),
        ];

        if (!array_key_exists($default_agent, $agent_map)) {
            $default_agent = 'aria';
        }

        return [$default_agent, $agent_map[$default_agent]];
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
