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

    /** @var int Daily call limit per non-admin user */
    private int $daily_limit;

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
        $this->eliza_api_key   = get_option('bitcore_eliza_auth_token', 'bitcore-eliza-os-auth-token-2026');
        $this->daily_limit     = (int) get_option('bitcore_eliza_daily_limit', 100);
        $this->x402_wallet     = get_option('bitcore_eliza_x402_wallet', '');
        $this->anonymous_access = (bool) get_option('bitcore_eliza_anonymous_access', false);

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
            'permission_callback' => 'is_user_logged_in',
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
            'permission_callback' => [$this, 'check_permission'],
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
        return current_user_can('read');
    }

    /**
     * Check auth with rate limiting for expensive endpoints.
     * Falls back to check_permission for logged-in users without rate limit.
     *
     * @return bool|WP_Error
     */
    public function check_auth_and_rate_limit() {
        if (!is_user_logged_in()) {
            return new WP_Error(
                'rest_not_logged_in',
                __('You must be logged in to use this endpoint.'),
                ['status' => 401]
            );
        }

        if (!current_user_can('read')) {
            return new \WP_Error(
                'rest_forbidden',
                __('You do not have permission to use this endpoint.'),
                ['status' => 403]
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
        // Case 1: Anonymous user
        if (!is_user_logged_in()) {
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

            // Verify x402 payment via filter (placeholder)
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

        // Case 2: Logged-in user — check capabilities
        if (!current_user_can('read')) {
            return new \WP_Error(
                'rest_forbidden',
                __('You do not have permission to use this endpoint.'),
                ['status' => 403]
            );
        }

        // Admins bypass rate limiting
        if (current_user_can('manage_options')) {
            return true;
        }

        // Check rate limit with x402 bypass option
        return $this->check_rate_limit_with_x402();
    }

    // =========================================================================
    // Rate Limiting
    // =========================================================================

    /**
     * Check daily rate limit using wp_usermeta.
     *
     * @return bool|WP_Error
     */
    private function check_rate_limit() {
        $user_id = get_current_user_id();
        $key     = 'bitcore_eliza_calls_' . gmdate('Y-m-d');
        $count   = (int) get_user_meta($user_id, $key, true);

        if ($count >= $this->daily_limit) {
            return new WP_Error(
                'rate_limit_exceeded',
                sprintf(
                    __('Daily call limit of %d reached. Limit resets at midnight UTC.'),
                    $this->daily_limit
                ),
                ['status' => 429]
            );
        }

        // Increment counter
        update_user_meta($user_id, $key, $count + 1);

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
        $user_id = get_current_user_id();
        $key     = 'bitcore_eliza_calls_' . gmdate('Y-m-d');
        $count   = (int) get_user_meta($user_id, $key, true);

        if ($count >= $this->daily_limit) {
            // Rate limit exceeded — check for x402 payment
            $payment = $this->get_payment_header();
            if (!empty($payment)) {
                $verified = apply_filters('bitcore_eliza_x402_verify', false, $payment);
                if ($verified) {
                    // x402 payment bypasses rate limit
                    $this->x402_bypass = true;
                    return true;
                }
            }

            // No valid payment — return 402 Payment Required
            return new WP_Error(
                'x402_payment_required',
                sprintf(
                    __('Daily call limit of %d reached. Include X-Payment header to continue or wait for reset.'),
                    $this->daily_limit
                ),
                ['status' => 402]
            );
        }

        // Increment counter
        update_user_meta($user_id, $key, $count + 1);

        return true;
    }

    /**
     * Get rate limit info for the current user.
     *
     * @param int $user_id WordPress user ID
     * @return array{limit: int, remaining: int, reset: int, plan: string}
     */
    private function get_rate_limit_info(int $user_id): array {
        $key   = 'bitcore_eliza_calls_' . gmdate('Y-m-d');
        $count = (int) get_user_meta($user_id, $key, true);

        // Calculate midnight UTC timestamp for reset
        $tomorrow = strtotime('tomorrow 00:00:00 UTC');

        return [
            'limit'      => $this->daily_limit,
            'remaining'  => max(0, $this->daily_limit - $count),
            'reset'      => $tomorrow,
            'plan'       => current_user_can('manage_options') ? 'admin' : 'standard',
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
     * Checks for X-Payment header in request. Applies filter for actual
     * payment verification (placeholder for future implementation).
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

        // Add rate limit headers for logged-in users (non-admins)
        if (is_user_logged_in() && !current_user_can('manage_options')) {
            $user_id = get_current_user_id();
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
                'X-API-KEY'    => $this->eliza_api_key,
                'X-Entity-Id'  => (string) $user_id,
            ],
            'timeout' => $this->request_timeout,
        ];

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

        // Pass through ElizaOS errors gracefully
        if ($code >= 400) {
            $error_message = is_array($decoded) && !empty($decoded['error'])
                ? $decoded['error']
                : ($decoded['message'] ?? __('Unknown ElizaOS error'));

            return new WP_Error(
                'eliza_error',
                $error_message,
                ['status' => $code]
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
    public function get_health() {
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
    public function get_agents(): WP_REST_Response {
        return $this->proxy_request('GET', '/agents');
    }

    /**
     * POST /eliza/agents/{id}/activate — Start or switch to an agent.
     *
     * Enforces single-active-agent policy: stops any currently active agent
     * before starting the requested one.
     */
    public function activate_agent(WP_REST_Request $request) {
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
    public function deactivate_agent(WP_REST_Request $request) {
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
     */
    public function send_message(WP_REST_Request $request) {
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

        // Try ElizaOS proxy first
        $body = [
            'content'   => $message,
            'sessionId' => $session_id,
        ];
        if (!empty($agent_id)) {
            $body['agentId'] = $agent_id;
        }

        $result = $this->proxy_request('POST', '/messaging/submit', $body);

        // If ElizaOS fails, fall back to OpenRouter direct
        if (is_wp_error($result)) {
            $openrouter_response = $this->openrouter_chat($message, $agent_id);
            if (!is_wp_error($openrouter_response)) {
                return $openrouter_response;
            }
            // Return original ElizaOS error if OpenRouter also fails
        }

        return $result;
    }

    /**
     * OpenRouter direct chat fallback.
     */
    private function openrouter_chat(string $message, string $agent_id = '') {
        $api_key = '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';
        $url = 'https://openrouter.ai/api/v1/chat/completions';

        $system_prompt = 'You are an AI assistant for the BITCORE ecosystem. Be helpful, concise, and accurate.';
        if (!empty($agent_id)) {
            $system_prompt .= ' You are currently acting as agent ' . $agent_id . '.';
        }

        $args = [
            'method'  => 'POST',
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
                'HTTP-Referer'  => 'https://ashi.bitwiki.org',
                'X-Title'       => 'ASHI Chat',
            ],
            'body'    => wp_json_encode([
                'model'    => 'openrouter/free',
                'messages' => [
                    ['role' => 'system', 'content' => $system_prompt],
                    ['role' => 'user', 'content' => $message],
                ],
            ]),
            'timeout' => 30,
        ];

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $data = wp_remote_retrieve_body($response);
        $decoded = json_decode($data, true);

        if ($code >= 400 || empty($decoded['choices'][0]['message']['content'])) {
            return new WP_Error('openrouter_error', __('AI service temporarily unavailable.'), ['status' => 503]);
        }

        $reply = $decoded['choices'][0]['message']['content'];

        return new WP_REST_Response([
            'success' => true,
            'response' => $reply,
            'sessionId' => uniqid('session_', true),
            'agentId' => $agent_id,
            'fallback' => true,
        ], 200);
    }

    /**
     * GET /eliza/sessions — List user's chat sessions.
     */
    public function get_sessions(): WP_REST_Response {
        return $this->proxy_request('GET', '/messaging/sessions');
    }

    /**
     * POST /eliza/sessions — Create a new chat session.
     */
    public function create_session(WP_REST_Request $request) {
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
    public function delete_session(WP_REST_Request $request) {
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
}
