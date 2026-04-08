<?php
declare(strict_types=1);
/**
 * BITCORE REST API
 * Full REST API for external integrations
 */

class BITCORE_Rest_API {
    private $core;
    private $namespace = 'bitcore/v1';

    public function __construct( $core ) {
        $this->core = $core;

        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Register REST routes
     */
    public function register_routes() {
        // Chat endpoint
        register_rest_route( $this->namespace, '/chat', [
            'methods' => 'POST',
            'callback' => [ $this, 'chat' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );

        // Intent endpoint
        register_rest_route( $this->namespace, '/intent', [
            'methods' => 'POST',
            'callback' => [ $this, 'submit_intent' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );

        // Queue status
        register_rest_route( $this->namespace, '/queue', [
            'methods' => 'GET',
            'callback' => [ $this, 'get_queue' ],
            'permission_callback' => '__return_true',
        ] );

        // Artifacts
        register_rest_route( $this->namespace, '/artifacts', [
            'methods' => 'GET',
            'callback' => [ $this, 'get_artifacts' ],
            'permission_callback' => '__return_true',
        ] );

        // Feed
        register_rest_route( $this->namespace, '/feed', [
            'methods' => 'GET',
            'callback' => [ $this, 'get_feed' ],
            'permission_callback' => '__return_true',
        ] );

        // Search
        register_rest_route( $this->namespace, '/search', [
            'methods' => 'GET',
            'callback' => [ $this, 'search' ],
            'permission_callback' => '__return_true',
        ] );

        // MCP Tools endpoint
        register_rest_route( $this->namespace, '/mcp/tools', [
            'methods' => 'GET',
            'callback' => [ $this, 'get_mcp_tools' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( $this->namespace, '/mcp/call', [
            'methods' => 'POST',
            'callback' => [ $this, 'call_mcp_tool' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );
    }

    /**
     * Chat endpoint
     */
    public function chat( $request ) {
        $params = $request->get_json_params();
        $message = sanitize_text_field( $params['message'] ?? '' );

        if ( empty( $message ) ) {
            return new WP_Error( 'no_message', 'Message is required', [ 'status' => 400 ] );
        }

        $response = $this->core->connector_n8n->send_intent( $message, 'chat', get_current_user_id() );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return rest_ensure_response( $response );
    }

    /**
     * Submit intent endpoint
     */
    public function submit_intent( $request ) {
        $params = $request->get_json_params();
        $intent = sanitize_text_field( $params['intent'] ?? '' );
        $scope = sanitize_text_field( $params['scope'] ?? 'quick' );

        if ( empty( $intent ) ) {
            return new WP_Error( 'no_intent', 'Intent is required', [ 'status' => 400 ] );
        }

        $response = $this->core->connector_n8n->send_intent( $intent, $scope, get_current_user_id() );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return rest_ensure_response( $response );
    }

    /**
     * Get queue status
     */
    public function get_queue( $request ) {
        $response = $this->core->connector_n8n->get_queue_status();

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return rest_ensure_response( $response );
    }

    /**
     * Get artifacts
     */
    public function get_artifacts( $request ) {
        $artifacts = $this->core->connector_bithub->get_artifacts();
        return rest_ensure_response( $artifacts );
    }

    /**
     * Get feed
     */
    public function get_feed( $request ) {
        $topics = $this->core->connector_bithub->get_latest_topics( 10 );
        return rest_ensure_response( $topics );
    }

    /**
     * Search
     */
    public function search( $request ) {
        $query = sanitize_text_field( $request->get_param( 'q' ) );

        if ( empty( $query ) ) {
            return new WP_Error( 'no_query', 'Query is required', [ 'status' => 400 ] );
        }

        $results = $this->core->connector_bitwiki->search( $query, 10 );
        return rest_ensure_response( $results );
    }

    /**
     * Get MCP tools
     */
    public function get_mcp_tools( $request ) {
        if ( $this->core->mcp ) {
            $tools = $this->core->mcp->get_tools();
            return rest_ensure_response( $tools );
        }
        return rest_ensure_response( [] );
    }

    /**
     * Call MCP tool
     */
    public function call_mcp_tool( $request ) {
        $params = $request->get_json_params();
        $tool = sanitize_text_field( $params['tool'] ?? '' );
        $args = $params['args'] ?? [];

        if ( empty( $tool ) ) {
            return new WP_Error( 'no_tool', 'Tool name is required', [ 'status' => 400 ] );
        }

        if ( $this->core->mcp ) {
            $result = $this->core->mcp->call_tool( $tool, $args );
            return rest_ensure_response( $result );
        }

        return new WP_Error( 'mcp_disabled', 'MCP module is disabled', [ 'status' => 503 ] );
    }

    /**
     * Check permission
     */
    public function check_permission() {
        return current_user_can( 'read' );
    }
}
