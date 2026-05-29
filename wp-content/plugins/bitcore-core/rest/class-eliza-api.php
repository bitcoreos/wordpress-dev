<?php
/**
 * ElizaOS Bridge REST API
 *
 * REST proxy endpoints forwarding WordPress user requests to ElizaOS
 * headless instance running on localhost:3000.
 *
 * Endpoints:
 *  - GET    /bitcore/v1/eliza/health              → Server health
 *  - GET    /bitcore/v1/eliza/agents               → List agents + status
 *  - POST   /bitcore/v1/eliza/agents/{id}/activate  → Start/switch agent
 *  - POST   /bitcore/v1/eliza/agents/{id}/deactivate → Stop agent
 *  - POST   /bitcore/v1/eliza/chat                 → Send message
 *  - GET    /bitcore/v1/eliza/sessions              → User sessions
 *  - POST   /bitcore/v1/eliza/sessions              → Create session
 *  - DELETE /bitcore/v1/eliza/sessions/{id}          → Delete session
 *  - GET    /bitcore/v1/eliza/rate-limit             → Rate limit status
 *
 * @package BITCORE
 * @since 1.2.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class BITCORE_Rest_Eliza_Api {

    /** @var BITCORE_Core Reference to core plugin instance */
    private $core;

    /** @var string REST namespace */
    private string $namespace = 'bitcore/v1';

    /** @var string ElizaOS API base URL */
    private string $eliza_base_url;

    /** @var string ElizaOS authentication key */
    private string $eliza_api_key;

    /** @var int Daily call limit per non-admin user; retained as legacy fallback only. */
    private int $daily_limit;

    /** @var \BITCORE\Infrastructure\Credits_Manager Unified credits/quota service */
    private $credits_manager;

    /** @var string Credits agent scope for ElizaOS bridge usage */
    private string $credits_agent_id = 'eliza';

    /** @var string Credits resource scope for ElizaOS bridge usage */
    private string $credits_resource_type = 'api_calls';

    /** @var int Request timeout in seconds */
    private int $request_timeout = 30;

    /** @var string x402 wallet address for receiving payments */
    private string $x402_wallet;

    /** @var bool Whether anonymous access via x402 is enabled */
    private bool $anonymous_access;

    /** @var bool Whether rate limit was bypassed by x402 payment this request */
    private bool $x402_bypass = false;

    /** @var int|null User ID for rate limit tracking (0 for anonymous x402) */
    private $rate_limit_user_id = null;

    /**
     * Constructor.
     *
     * @param BITCORE_Core $core Core plugin instance
     */
    public function __construct($core) {
        $this->core = $core;

        // Load configuration from WordPress options
        $this->eliza_base_url = get_option('bitcore_eliza_server_url', 'http://172.17.0.1:3000/api');
        $this->eliza_api_key   = (string) get_option('bitcore_eliza_auth_token', '');
        $this->daily_limit     = (int) get_option('bitcore_eliza_daily_limit', 100);
        $this->x402_wallet     = get_option('bitcore_eliza_x402_wallet', '');
        $this->anonymous_access = (bool) get_option('bitcore_eliza_anonymous_access', false);
        $this->credits_manager = $this->create_credits_manager();

        $this->register_routes();

        // Add rate limit headers to all Eliza proxy responses (3 params: result, server, request)
        add_filter('rest_post_dispatch', [$this, 'add_rate_limit_headers'], 10, 3);
    }

    /**
     * Register all ElizaOS proxy routes.
     */
    public function register_routes(): void {
        // Health check — public, no auth required
        register_rest_route($this->namespace, '/eliza/health', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_health'],
            'permission_callback' => '__return_true',
        ]);

        // List agents
        register_rest_route($this->namespace, '/eliza/agents', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_agents'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        // Activate (start) agent
        register_rest_route($this->namespace, '/eliza/agents/(?P<id>[a-zA-Z0-9_-]+)/activate', [
            'methods'             => 'POST',
            'callback'            => [$this, 'activate_agent'],
            'permission_callback' => [$this, 'check_permission'],
            'args'                => [
                'id' => [
                    'type'              => 'string',
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback'  => function ($value) {
                        return preg_match('/^[a-zA-Z0-9_-]+$/', $value);
                    },
                ],
            ],
        ]);

        // Deactivate (stop) agent
        register_rest_route($this->namespace, '/eliza/agents/(?P<id>[a-zA-Z0-9_-]+)/deactivate', [
            'methods'             => 'POST',
            'callback'            => [$this, 'deactivate_agent'],
            'permission_callback' => [$this, 'check_permission'],
            'args'                => [
                'id' => [
                    'type'              => 'string',
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback'  => function ($value) {
                        return preg_match('/^[a-zA-Z0-9_-]+$/', $value);
                    },
                ],
            ],
        ]);

        // Send chat message — supports anonymous x402 access
        register_rest_route($this->namespace, '/eliza/chat', [
            'methods'             => 'POST',
            'callback'            => [$this, 'send_message'],
            'permission_callback' => [$this, 'check_anonymous_access'],
            'args'                => [
                'message' => [
                    'type'              => 'string',
                    'required'          => true,
                    'sanitize_callback' => 'wp_kses_post',
                ],
                'sessionId' => [
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'agentId' => [
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // Sessions — supports anonymous x402 access
        register_rest_route($this->namespace, '/eliza/sessions', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'get_sessions'],
                'permission_callback' => [$this, 'check_anonymous_access'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'create_session'],
                'permission_callback' => [$this, 'check_anonymous_access'],
                'args'                => [
                    'agentId' => [
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ],
        ]);

        // Delete session
        register_rest_route($this->namespace, '/eliza/sessions/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'delete_session'],
            'permission_callback' => [$this, 'check_anonymous_access'],
            'args'                => [
                'id' => [
                    'type'              => 'string',
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback'  => function ($value) {
                        return preg_match('/^[a-zA-Z0-9_-]+$/', $value);
                    },
                ],
            ],
        ]);

        // Rate limit info endpoint
        register_rest_route($this->namespace, '/eliza/rate-limit', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_rate_limit'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
    }

    // =========================================================================
    // Permission Callbacks
    // =========================================================================

    /**
     * Check basic permission: logged-in user with read or bitcore_access capability.
     */
    public function check_permission(): bool {
        $is_logged_in = is_user_logged_in();
        $this->rate_limit_user_id = $is_logged_in ? get_current_user_id() : null;

        if ($is_logged_in) {
            return true;
        }

        return $this->has_valid_inbound_api_key();
    }

    /**
     * Validate inbound API access for service/API clients.
     *
     * This intentionally reuses the configured ElizaOS auth token because the
     * bridge already exposes it as the administrative API credential. An empty
     * configured token never authorizes anonymous access.
     */
    private function has_valid_inbound_api_key(): bool {
        $configured = trim((string) $this->eliza_api_key);
        if ($configured === '') {
            return false;
        }

        $provided = '';
        if (isset($_SERVER['HTTP_X_API_KEY'])) {
            $provided = trim((string) wp_unslash($_SERVER['HTTP_X_API_KEY']));
        } elseif (isset($_SERVER['REDIRECT_HTTP_X_API_KEY'])) {
            $provided = trim((string) wp_unslash($_SERVER['REDIRECT_HTTP_X_API_KEY']));
        }

        return $provided !== '' && hash_equals($configured, $provided);
    }

    /**
     * Check auth with rate limiting for expensive endpoints.
     * Falls back to check_permission for logged-in users without rate limit.
     *
     * @return bool|WP_Error
     */
    public function check_auth_and_rate_limit() {
        $is_logged_in = is_user_logged_in();
        $this->rate_limit_user_id = $is_logged_in ? get_current_user_id() : null;

        if (!$is_logged_in) {
            return new WP_Error(
                'rest_not_logged_in',
                __('You must be logged in to use this endpoint.'),
                ['status' => 401]
            );
        }

        // Admins bypass rate limiting
        if (current_user_can('manage_options')) {
            return true;
        }

        return $this->check_rate_limit();
    }

    /**
     * Check anonymous access: supports both logged-in users and x402 anonymous access.
     *
     * - Logged in: normal auth + rate limiting (admins bypass)
     * - Not logged in + X-Payment header: x402 access (bypasses rate limit)
     * - Not logged in + no X-Payment: 401 unauthorized
     *
     * @return bool|WP_Error
     */
    public function check_anonymous_access() {
        $is_logged_in = is_user_logged_in();
        $this->rate_limit_user_id = $is_logged_in ? get_current_user_id() : null;

        // Case 1: Anonymous user
        if (!$is_logged_in) {
            // Check if anonymous access is enabled
            if (!$this->anonymous_access) {
                return new WP_Error(
                    'rest_not_logged_in',
                    __('Authentication required. Include X-Payment header for x402 access.'),
                    ['status' => 401]
                );
            }

            // Check for x402 payment header
            $payment = $this->get_payment_header();
            if (empty($payment)) {
                return new WP_Error(
                    'rest_not_logged_in',
                    __('Authentication required. Include X-Payment header for x402 access.'),
                    ['status' => 401]
                );
            }

            // Verify x402 payment via registered verifier/facilitator filters
            $verified = apply_filters('bitcore_eliza_x402_verify', false, $payment);
            if (!$verified) {
                return new WP_Error(
                    'x402_payment_invalid',
                    __('x402 payment verification failed.'),
                    ['status' => 402]
                );
            }

            // x402 payment accepted — mark bypass for rate limit headers
            $this->x402_bypass = true;
            return true;
        }

        // Case 2: Logged-in user — admins bypass rate limiting
        if (current_user_can('manage_options')) {
            return true;
        }

        // Non-admin logged-in user — check rate limit with x402 bypass option
        return $this->check_rate_limit_with_x402();
    }

    // =========================================================================
    // Rate Limiting
    // =========================================================================

    /**
     * Create unified credits manager with a local require guard for standalone tests.
     *
     * @return \BITCORE\Infrastructure\Credits_Manager
     */
    private function create_credits_manager() {
        if (!class_exists('BITCORE\\Infrastructure\\Credits_Manager')) {
            $path = dirname(__DIR__) . '/infrastructure/class-credits.php';
            if (is_file($path)) {
                require_once $path;
            }
        }

        return new \BITCORE\Infrastructure\Credits_Manager();
    }

    /**
     * Convert a credits decision into a REST permission error.
     *
     * @param array<string,mixed> $decision Credits manager decision payload.
     */
    private function credits_error(array $decision): WP_Error {
        $status = (int) ($decision['status'] ?? 402);
        $code = sanitize_text_field((string) ($decision['code'] ?? 'x402_payment_required'));
        $message = $code === 'x402_payment_required'
            ? __('Daily call limit reached. Include X-Payment header to continue or wait for reset.')
            : __('Credit authorization failed.');

        return new WP_Error($code, $message, ['status' => $status]);
    }

    /**
     * Check daily rate limit using wp_usermeta.
     *
     * @return bool|WP_Error
     */
    private function check_rate_limit() {
        $user_id = (int) ($this->rate_limit_user_id ?? get_current_user_id());
        $this->rate_limit_user_id = $user_id > 0 ? $user_id : null;

        $decision = $this->credits_manager->can_consume(
            $user_id,
            $this->credits_agent_id,
            1,
            $this->credits_resource_type,
            false
        );

        if (!($decision['allowed'] ?? false)) {
            return $this->credits_error($decision);
        }

        $this->credits_manager->consume(
            $user_id,
            $this->credits_agent_id,
            1,
            $this->credits_resource_type,
            'eliza_proxy',
            ['endpoint' => 'eliza']
        );

        return true;
    }

    /**
     * Check rate limit with x402 payment bypass.
     * If rate limit exceeded and X-Payment header present, bypass rate limit.
     * If rate limit exceeded and no X-Payment, return 402 Payment Required.
     *
     * @return bool|WP_Error
     */
    private function check_rate_limit_with_x402() {
        $user_id = (int) ($this->rate_limit_user_id ?? get_current_user_id());
        $this->rate_limit_user_id = $user_id > 0 ? $user_id : null;

        $payment = $this->get_payment_header();
        $verified = $payment !== '' && $this->credits_manager->verify_x402_payment($payment);
        $decision = $this->credits_manager->can_consume(
            $user_id,
            $this->credits_agent_id,
            1,
            $this->credits_resource_type,
            $verified
        );

        if (!($decision['allowed'] ?? false)) {
            return $this->credits_error($decision);
        }

        if (!empty($decision['paid'])) {
            $this->x402_bypass = true;
            return true;
        }

        $this->credits_manager->consume(
            $user_id,
            $this->credits_agent_id,
            1,
            $this->credits_resource_type,
            'eliza_proxy',
            ['endpoint' => 'eliza']
        );

        return true;
    }

    /**
     * Get rate limit info for the current user.
     *
     * @param int $user_id WordPress user ID
     * @return array{limit: int, remaining: int, reset: int, plan: string}
     */
    private function get_rate_limit_info(int $user_id): array {
        $status = $this->credits_manager->get_status(
            $user_id,
            $this->credits_agent_id,
            $this->credits_resource_type
        );

        // Calculate midnight UTC timestamp for reset
        $tomorrow = strtotime('tomorrow 00:00:00 UTC');

        return [
            'limit'      => (int) ($status['daily_limit'] ?? 0),
            'remaining'  => (int) ($status['remaining_today'] ?? 0),
            'reset'      => $tomorrow,
            'plan'       => current_user_can('manage_options') ? 'admin' : (string) ($status['tier'] ?? 'standard'),
        ];
    }

    // =========================================================================
    // x402 Payment Middleware
    // =========================================================================

    /**
     * Get X-Payment header from the current request.
     *
     * @return string Payment token/value or empty string if not present
     */
    private function get_payment_header(): string {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        $payment = $_SERVER['HTTP_X_PAYMENT'] ?? '';
        return sanitize_text_field($payment);
    }

    /**
     * Check x402 payment validity.
     *
     * Checks for X-Payment header in request and delegates verification to
     * registered x402 verifier/facilitator filters.
     *
     * @return bool Whether payment is valid
     */
    public function check_x402_payment(): bool {
        $payment = $this->get_payment_header();

        if (empty($payment)) {
            return false;
        }

        /**
         * Filter to verify x402 payment.
         *
         * @param bool   $verified Whether payment is verified (default: false)
         * @param string $payment  The payment token/value from X-Payment header
         */
        return (bool) apply_filters('bitcore_eliza_x402_verify', false, $payment);
    }

    // =========================================================================
    // Response Headers
    // =========================================================================

    /**
     * Add rate limit headers to all Eliza proxy responses.
     *
     * Hooks into rest_post_dispatch to inject X-RateLimit-* headers
     * and X-Wallet-Address header when appropriate.
     *
     * @param WP_REST_Response|WP_Error $response Response object
     * @param WP_REST_Server             $server   Server object
     * @param WP_REST_Request           $request  Request object
     * @return WP_REST_Response|WP_Error Modified response
     */
    public function add_rate_limit_headers($response, $server, $request) {
        // Only process Eliza endpoints
        $route = $request->get_route();
        if (strpos($route, '/bitcore/v1/eliza') === false) {
            return $response;
        }

        // Only add headers on WP_REST_Response objects
        if (!is_object($response) || !method_exists($response, 'header')) {
            return $response;
        }

        // Add rate limit headers for authenticated non-admin users without
        // repeating is_user_logged_in(); permission callbacks cache this context.
        $user_id = (int) ($this->rate_limit_user_id ?? get_current_user_id());
        if ($user_id > 0 && !current_user_can('manage_options')) {
            $this->rate_limit_user_id = $user_id;
            $info = $this->get_rate_limit_info($user_id);

            $response->header('X-RateLimit-Limit', (string) $info['limit']);
            $response->header('X-RateLimit-Remaining', (string) $info['remaining']);
            $response->header('X-RateLimit-Reset', (string) $info['reset']);
        }

        // Add X-Wallet-Address header for 402 responses
        if (is_object($response) && method_exists($response, 'get_status')) {
            if ($response->get_status() === 402) {
                $wallet = get_option('bitcore_eliza_x402_wallet', '');
                if (!empty($wallet)) {
                    $response->header('X-Wallet-Address', $wallet);
                }
            }
        }

        return $response;
    }

    // =========================================================================
    // Proxy Helper
    // =========================================================================

    /**
     * Proxy a request to ElizaOS.
     *
     * @param string $method HTTP method (GET, POST, DELETE)
     * @param string $path   API path (e.g. '/server/health')
     * @param array  $body   Optional JSON body to send
     * @return WP_REST_Response|WP_Error
     */
    private function proxy_request(string $method, string $path, ?array $body = null) {
        $url     = $this->eliza_base_url . $path;
        $user_id = get_current_user_id();

        $args = [
            'method'  => $method,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Entity-Id'  => (string) $user_id,
            ],
            'timeout' => $this->request_timeout,
        ];

        if ($this->eliza_api_key !== '') {
            $args['headers']['X-API-KEY'] = $this->eliza_api_key;
        }

        // Pass X-Payment header through to ElizaOS if present
        $payment = $this->get_payment_header();
        if (!empty($payment)) {
            $args['headers']['X-Payment'] = $payment;
        }

        if ($body !== null) {
            $args['body'] = wp_json_encode($body);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return new WP_Error(
                'eliza_connection_error',
                sprintf(__('ElizaOS connection error: %s'), $response->get_error_message()),
                ['status' => 502]
            );
        }

        $code = wp_remote_retrieve_response_code($response);
        $data = wp_remote_retrieve_body($response);

        // Connection refused or timeout
        if ($code === 0) {
            return new WP_Error(
                'eliza_unavailable',
                __('ElizaOS server is unavailable. Please try again later.'),
                ['status' => 503]
            );
        }

        $decoded = json_decode($data, true);

        // Pass through ElizaOS errors gracefully. ElizaOS 1.7.x may return
        // nested error objects; WP_Error requires a scalar message string.
        if ($code >= 400) {
            $raw_error = is_array($decoded) ? ($decoded['error'] ?? ($decoded['message'] ?? null)) : null;
            $error_message = $this->normalize_eliza_error_message($raw_error);

            return new WP_Error(
                'eliza_error',
                $error_message,
                [
                    'status'    => $code,
                    'raw_error' => $raw_error,
                ]
            );
        }

        return new WP_REST_Response(
            is_array($decoded) ? $decoded : ['raw' => $data],
            $code
        );
    }

    // =========================================================================
    // Endpoint Callbacks
    // =========================================================================

    /**
     * GET /eliza/health — Public health check.
     */
    public function get_health(): WP_REST_Response {
        $result = $this->proxy_request('GET', '/server/health');

        if (is_wp_error($result)) {
            // Return degraded status instead of error for health endpoint
            return new WP_REST_Response([
                'status'     => 'degraded',
                'bridge'     => 'connected',
                'eliza'      => 'unreachable',
                'message'    => $result->get_error_message(),
                'timestamp'  => current_time('iso'),
            ], 200);
        }

        // Inject bridge metadata
        $data = $result->get_data();
        if (is_array($data)) {
            $data['bridge']    = 'connected';
            $data['timestamp'] = current_time('iso');
        }

        return new WP_REST_Response($data, 200);
    }

    /**
     * GET /eliza/agents — List available agents.
     */
    public function get_agents(): WP_REST_Response|WP_Error {
        $result = $this->proxy_request('GET', '/agents');

        if (!is_wp_error($result)) {
            return $result;
        }

        return $this->get_configured_agents_fallback($result);
    }


    /**
     * Normalize a nested ElizaOS error payload into a safe WP_Error message.
     *
     * @param mixed $raw_error Raw upstream error payload.
     * @return string Human-readable error message.
     */
    private function normalize_eliza_error_message(mixed $raw_error): string {
        if (is_array($raw_error)) {
            foreach (['message', 'error', 'details', 'code'] as $key) {
                if (isset($raw_error[$key]) && is_scalar($raw_error[$key]) && (string) $raw_error[$key] !== '') {
                    return (string) $raw_error[$key];
                }
            }
            return __('Unknown ElizaOS error');
        }

        if (is_scalar($raw_error) && (string) $raw_error !== '') {
            return (string) $raw_error;
        }

        return __('Unknown ElizaOS error');
    }

    /**
     * POST /eliza/agents/{id}/activate — Start or switch to an agent.
     *
     * Enforces single-active-agent policy: stops any currently active agent
     * before starting the requested one.
     */
    public function activate_agent(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $agent_id = sanitize_text_field($request['id']);

        if (empty($agent_id)) {
            return new WP_Error(
                'missing_agent_id',
                __('Agent ID is required.'),
                ['status' => 400]
            );
        }

        // Single-active-agent policy: stop current agent first
        $active_key = 'bitcore_eliza_active_agent';
        $user_id     = get_current_user_id();
        $current     = get_user_meta($user_id, $active_key, true);

        if (!empty($current) && $current !== $agent_id) {
            // Stop current agent before switching
            $this->proxy_request('POST', '/agents/' . sanitize_text_field($current) . '/stop');
        }

        // Start requested agent
        $result = $this->proxy_request('POST', '/agents/' . $agent_id . '/start');

        if (!is_wp_error($result)) {
            update_user_meta($user_id, $active_key, $agent_id);

            // Also update the option-stored default agent if this is the initial activation
            $default_agent = get_option('bitcore_eliza_default_agent', 'aria');
            $agent_map = $this->get_agent_map();
            if (isset($agent_map[$default_agent]) && $agent_map[$default_agent] === $agent_id) {
                update_user_meta($user_id, 'bitcore_eliza_active_agent_name', $default_agent);
            }
        }

        return $result;
    }

    /**
     * POST /eliza/agents/{id}/deactivate — Stop an agent.
     */
    public function deactivate_agent(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $agent_id = sanitize_text_field($request['id']);

        if (empty($agent_id)) {
            return new WP_Error(
                'missing_agent_id',
                __('Agent ID is required.'),
                ['status' => 400]
            );
        }

        $result = $this->proxy_request('POST', '/agents/' . $agent_id . '/stop');

        if (!is_wp_error($result)) {
            // Clear active agent tracking if this was the active agent
            $user_id    = get_current_user_id();
            $active_key = 'bitcore_eliza_active_agent';
            $current    = get_user_meta($user_id, $active_key, true);
            if ($current === $agent_id) {
                delete_user_meta($user_id, $active_key);
                delete_user_meta($user_id, 'bitcore_eliza_active_agent_name');
            }
        }

        return $result;
    }

    /**
     * POST /eliza/chat — Send a message to the active agent.
     *
     * Uses ElizaOS 1.7.2 messaging API:
     * POST /api/messaging/channels/{channelId}/messages
     *
     * Payload shape:
     *   message_server_id: 00000000-0000-0000-0000-000000000000 (fixed server ID)
     *   author_id: UUID from user meta (stable per user)
     *   raw_message: {text: message} (object, not string)
     *   transport: websocket (immediate 201 response)
     *   metadata.targetAgentId: agent UUID for routing
     */
    public function send_message(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $params   = $request->get_json_params();
        $message  = wp_kses_post($params['message'] ?? '');
        $session_id = isset($params['sessionId']) ? sanitize_text_field($params['sessionId']) : '';
        $agent_id   = isset($params['agentId']) ? sanitize_text_field($params['agentId']) : '';

        if (empty($message)) {
            return new WP_Error(
                'no_message',
                __('Message is required.'),
                ['status' => 400]
            );
        }

        // ElizaOS 1.7.2: /api/messaging/channels/{channelId}/messages
        $channel_id = !empty($session_id) ? $session_id : wp_generate_uuid4();
        $user_id    = get_current_user_id();
        $author_id  = $this->get_request_author_uuid($user_id, $channel_id);
        $body = [
            'channelId'         => $channel_id,
            'message_server_id' => '00000000-0000-0000-0000-000000000000',
            'author_id'         => $author_id,
            'content'           => $message,
            'raw_message'       => ['text' => $message],
            'transport'         => 'websocket',
            'metadata'          => ['isDm' => true],
        ];

        // Route to specific agent via metadata.targetAgentId
        if (!empty($agent_id)) {
            $body['metadata']['targetAgentId'] = $agent_id;
        }
        $result = $this->proxy_request('POST', '/messaging/channels/' . $channel_id . '/messages', $body);

        // Attach the channel_id to the response for client session tracking
        if (!is_wp_error($result)) {
            $data = $result->get_data();
            if (is_array($data)) {
                $data['channel_id'] = $channel_id;
            }
            return new WP_REST_Response($data, $result->get_status());
        }

        return $result;
    }

    /**
     * GET /eliza/sessions — List user's chat sessions.
     */
    public function get_sessions(): WP_REST_Response|WP_Error {
        return $this->proxy_request('GET', '/messaging/sessions');
    }

    /**
     * POST /eliza/sessions — Create a new chat session.
     */
    public function create_session(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $params   = $request->get_json_params();
        $agent_id = isset($params['agentId']) ? sanitize_text_field($params['agentId']) : '';

        $body = [];
        if (!empty($agent_id)) {
            $body['agentId'] = $agent_id;
        }

        return $this->proxy_request('POST', '/messaging/sessions', $body);
    }

    /**
     * DELETE /eliza/sessions/{id} — Delete a chat session.
     */
    public function delete_session(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $session_id = sanitize_text_field($request['id']);

        if (empty($session_id)) {
            return new WP_Error(
                'missing_session_id',
                __('Session ID is required.'),
                ['status' => 400]
            );
        }

        return $this->proxy_request('DELETE', '/messaging/sessions/' . $session_id);
    }

    /**
     * GET /eliza/rate-limit — Get rate limit status for the current user.
     */
    public function get_rate_limit(): WP_REST_Response {
        $user_id = get_current_user_id();
        $info    = $this->get_rate_limit_info($user_id);

        return new WP_REST_Response([
            'limit'      => $info['limit'],
            'remaining'  => $info['remaining'],
            'reset'      => gmdate('Y-m-d\TH:i:s\Z', $info['reset']),
            'plan'       => $info['plan'],
        ], 200);
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Get agent name-to-ID mapping from WordPress options.
     *
     * @return array<string, string> Agent name => UUID mapping
     */
    private function get_agent_map(): array {
        return [
            'aria'  => get_option('bitcore_eliza_agent_aria', 'eac677dd-94c4-02ab-bfea-bc554e52b2a3'),
            'forge' => get_option('bitcore_eliza_agent_forge', '3ab11b75-ca36-0cee-a3d7-7bfcab3c27cd'),
            'muse'  => get_option('bitcore_eliza_agent_muse', 'cf0dee2b-9fc6-041b-83a0-90a644d26362'),
        ];
    }


    /**
     * Build a resilient configured-agent response when ElizaOS agent index fails.
     *
     * ElizaOS 1.7.2 can return 500 from /api/agents when its agents table is
     * degraded even while messaging and health routes are available. The ASHI UI
     * still needs the configured agent identities, but must not fake live status.
     *
     * @param WP_Error $error Upstream proxy error.
     * @return WP_REST_Response
     */
    private function get_configured_agents_fallback(WP_Error $error): WP_REST_Response {
        $agent_map = $this->get_agent_map();
        $profiles = [
            'aria' => [
                'name'        => 'Aria',
                'emoji'       => '🧭',
                'style'       => 'Strategic coordinator',
                'description' => 'Default planning and orchestration agent.',
            ],
            'forge' => [
                'name'        => 'Forge',
                'emoji'       => '⚒️',
                'style'       => 'Builder and systems engineer',
                'description' => 'Implementation-focused engineering agent.',
            ],
            'muse' => [
                'name'        => 'Muse',
                'emoji'       => '🎨',
                'style'       => 'Creative synthesis',
                'description' => 'Research, narrative, and synthesis agent.',
            ],
        ];

        $agents = [];
        foreach ($agent_map as $slug => $id) {
            $profile = $profiles[$slug] ?? [];
            $agents[] = [
                'id'          => (string) $id,
                'slug'        => (string) $slug,
                'name'        => $profile['name'] ?? ucfirst((string) $slug),
                'emoji'       => $profile['emoji'] ?? '🤖',
                'style'       => $profile['style'] ?? 'Configured agent',
                'description' => $profile['description'] ?? 'Configured ElizaOS agent.',
                'status'      => 'unknown',
                'source'      => 'wordpress_config',
            ];
        }

        return new WP_REST_Response([
            'status'         => 'degraded',
            'source'         => 'wordpress_config',
            'agents'         => $agents,
            'upstream_error' => [
                'code'      => $error->get_error_code(),
                'message'   => $error->get_error_message(),
                'status'    => (int) ($error->get_error_data()['status'] ?? 502),
                'raw_error' => $error->get_error_data()['raw_error'] ?? null,
            ],
            'timestamp'      => current_time('iso'),
        ], 200);
    }

    /**
     * Get or create a stable UUID for a WordPress user.
     *
     * Stored in user meta as 'bitcore_eliza_uuid' to provide a consistent
     * author_id across all ElizaOS messaging requests.
     *
     * @param int $user_id WordPress user ID
     * @return string UUID v4
     */
    private function get_user_uuid(int $user_id): string {
        $uuid = get_user_meta($user_id, 'bitcore_eliza_uuid', true);
        if (empty($uuid) || !wp_is_uuid($uuid)) {
            $uuid = wp_generate_uuid4();
            update_user_meta($user_id, 'bitcore_eliza_uuid', $uuid);
        }
        return $uuid;
    }

    /**
     * Get a valid author UUID for the current request.
     *
     * Logged-in users receive a stable UUID persisted in user meta.
     * Anonymous x402 requests receive a deterministic UUID derived from the
     * payment header or channel ID without writing user meta for user ID 0.
     *
     * @param int    $user_id    WordPress user ID, or 0 for anonymous requests.
     * @param string $channel_id ElizaOS channel/session ID.
     * @return string UUID v4-compatible value.
     */
    private function get_request_author_uuid(int $user_id, string $channel_id): string {
        if ($user_id > 0) {
            return $this->get_user_uuid($user_id);
        }

        $source = $this->get_payment_header();
        if (empty($source)) {
            $source = $channel_id;
        }

        return $this->uuid_from_hash('anonymous-eliza-author:' . $source);
    }

    /**
     * Convert a source string into a deterministic UUID v4-compatible value.
     *
     * @param string $source Stable source material.
     * @return string UUID v4-compatible value.
     */
    private function uuid_from_hash(string $source): string {
        $hex = hash('sha256', $source);

        return sprintf(
            '%s-%s-4%s-%s%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 13, 3),
            dechex((hexdec($hex[16]) & 0x3) | 0x8),
            substr($hex, 17, 3),
            substr($hex, 20, 12)
        );
    }
}
