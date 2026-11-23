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
 *  - GET    /bitcore/v1/eliza/sessions/{id}/messages → Session messages
 *  - GET    /bitcore/v1/eliza/rate-limit             → Rate limit status
 *
 * @package BITCORE
 * @since 1.2.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/traits/trait-eliza-auth.php';
require_once __DIR__ . '/traits/trait-eliza-rate-limit.php';
require_once __DIR__ . '/traits/trait-eliza-agents.php';
require_once __DIR__ . '/traits/trait-eliza-sessions.php';
require_once __DIR__ . '/traits/trait-eliza-helpers.php';

class BITCORE_Rest_Eliza_Api {

    use BITCORE_Rest_Eliza_Auth_Trait;
    use BITCORE_Rest_Eliza_Rate_Limit_Trait;
    use BITCORE_Rest_Eliza_Agents_Trait;
    use BITCORE_Rest_Eliza_Sessions_Trait;
    use BITCORE_Rest_Eliza_Helpers_Trait;

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

        // Session messages — fetch actual assistant replies after submit acknowledgement.
        register_rest_route($this->namespace, '/eliza/sessions/(?P<id>[a-zA-Z0-9_-]+)/messages', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_session_messages'],
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
                'limit' => [
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'after' => [
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'before' => [
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
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

}
