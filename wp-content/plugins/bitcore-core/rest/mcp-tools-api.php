<?php
/**
 * BITCORE MCP Tools REST API
 * Exposes WordPress abilities as MCP tools for n8n-mcp integration
 * 
 * New endpoints:
 * - GET  /bitcore/v1/mcp/tools     - List MCP tools (abilities as tools)
 * - POST /bitcore/v1/mcp/call      - Execute MCP tool (ability)
 */

class BITCORE_Rest_MCP_Tools_API {
    private $namespace = 'bitcore/v1';

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /**
     * Register REST routes for MCP tools
     */
    public function register_routes() {
        // List MCP tools
        register_rest_route( $this->namespace, '/mcp/tools', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_mcp_tools' ),
            'permission_callback' => '__return_true',
        ) );

        // Execute MCP tool
        register_rest_route( $this->namespace, '/mcp/call', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'execute_mcp_tool' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );
    }

    /**
     * Get MCP tools (abilities formatted as MCP tools)
     */
    public function get_mcp_tools() {
        $tools = array();
        
        // Content tools
        $tools[] = array(
            'name'        => 'bitcore_create_post',
            'description' => 'Create a new WordPress post',
            'inputSchema'   => array(
                'type'       => 'object',
                'properties' => array(
                    'title'   => array(
                        'type'        => 'string',
                        'description' => 'Post title',
                    ),
                    'content' => array(
                        'type'        => 'string',
                        'description' => 'Post content',
                    ),
                    'status'  => array(
                        'type'    => 'string',
                        'enum'    => array( 'draft', 'publish', 'private' ),
                        'default' => 'draft',
                        'description' => 'Post status',
                    ),
                ),
                'required' => array( 'title' ),
            ),
        );

        $tools[] = array(
            'name'        => 'bitcore_get_posts',
            'description' => 'Retrieve WordPress posts',
            'inputSchema'   => array(
                'type'       => 'object',
                'properties' => array(
                    'per_page' => array(
                        'type'    => 'integer',
                        'default' => 10,
                        'maximum' => 100,
                        'description' => 'Number of posts per page',
                    ),
                    'page' => array(
                        'type'    => 'integer',
                        'default' => 1,
                        'description' => 'Page number',
                    ),
                    'status' => array(
                        'type'    => 'string',
                        'enum'    => array( 'publish', 'draft', 'private', 'any' ),
                        'default' => 'publish',
                        'description' => 'Post status filter',
                    ),
                ),
            ),
        );

        // AI tools
        $tools[] = array(
            'name'        => 'bitcore_chat',
            'description' => 'Send message to BITCORE AI',
            'inputSchema'   => array(
                'type'       => 'object',
                'properties' => array(
                    'message' => array(
                        'type'        => 'string',
                        'description' => 'Message to send to AI',
                    ),
                    'model' => array(
                        'type'    => 'string',
                        'enum'    => array( 'coraline', 'hyperion' ),
                        'default' => 'coraline',
                        'description' => 'AI model to use',
                    ),
                ),
                'required' => array( 'message' ),
            ),
        );

        // Webhook tools
        $tools[] = array(
            'name'        => 'bitcore_create_webhook',
            'description' => 'Register a webhook for WordPress events',
            'inputSchema'   => array(
                'type'       => 'object',
                'properties' => array(
                    'url' => array(
                        'type'        => 'string',
                        'format'      => 'uri',
                        'description' => 'Webhook URL to call',
                    ),
                    'events' => array(
                        'type'        => 'array',
                        'items'       => array(
                            'type' => 'string',
                            'enum' => array( 'post_publish', 'post_update', 'post_delete', 'user_register', '*' ),
                        ),
                        'description' => 'Events to subscribe to',
                    ),
                ),
                'required' => array( 'url', 'events' ),
            ),
        );

        return rest_ensure_response( array(
            'tools' => $tools,
        ) );
    }

    /**
     * Execute MCP tool
     */
    public function execute_mcp_tool( $request ) {
        $input = $request->get_json_params();
        
        if ( ! isset( $input['name'] ) ) {
            return new WP_Error(
                'missing_tool_name',
                __( 'Tool name is required.', 'bitcore' ),
                array( 'status' => 400 )
            );
        }

        $tool_name = sanitize_text_field( $input['name'] );
        $params = isset( $input['parameters'] ) ? $input['parameters'] : array();

        // Route to appropriate handler
        switch ( $tool_name ) {
            case 'bitcore_create_post':
                return $this->create_post( $params );
            
            case 'bitcore_get_posts':
                return $this->get_posts( $params );
            
            case 'bitcore_chat':
                return $this->chat( $params );
            
            case 'bitcore_create_webhook':
                return $this->create_webhook( $params );
            
            default:
                return new WP_Error(
                    'tool_not_found',
                    __( 'Tool not found.', 'bitcore' ),
                    array( 'status' => 404 )
                );
        }
    }

    /**
     * Check permission
     */
    public function check_permission( $request ) {
        $input = $request->get_json_params();
        $tool_name = isset( $input['name'] ) ? sanitize_text_field( $input['name'] ) : '';

        // Public tools (read-only)
        $public_tools = array( 'bitcore_get_posts' );
        if ( in_array( $tool_name, $public_tools ) ) {
            return '__return_true';
        }

        // Tools requiring specific permissions
        $content_tools = array( 'bitcore_create_post' );
        if ( in_array( $tool_name, $content_tools ) ) {
            return current_user_can( 'edit_posts' );
        }

        $webhook_tools = array( 'bitcore_create_webhook' );
        if ( in_array( $tool_name, $webhook_tools ) ) {
            return current_user_can( 'manage_options' );
        }

        // Default: logged in users
        return is_user_logged_in();
    }

    // Tool implementations
    private function create_post( $params ) {
        $post_data = array(
            'post_title'   => isset( $params['title'] ) ? sanitize_text_field( $params['title'] ) : '',
            'post_content' => isset( $params['content'] ) ? wp_kses_post( $params['content'] ) : '',
            'post_status'  => isset( $params['status'] ) ? sanitize_key( $params['status'] ) : 'draft',
            'post_type'    => 'post',
        );

        $post_id = wp_insert_post( $post_data, true );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        return rest_ensure_response( array(
            'content' => array(
                array(
                    'type' => 'text',
                    'text' => json_encode( array(
                        'id'     => $post_id,
                        'title'  => $post_data['post_title'],
                        'status' => $post_data['post_status'],
                        'url'    => get_permalink( $post_id ),
                    ) ),
                ),
            ),
        ) );
    }

    private function get_posts( $params ) {
        $args = array(
            'posts_per_page' => isset( $params['per_page'] ) ? intval( $params['per_page'] ) : 10,
            'paged'          => isset( $params['page'] ) ? intval( $params['page'] ) : 1,
            'post_status'    => isset( $params['status'] ) ? sanitize_key( $params['status'] ) : 'publish',
        );

        $query = new WP_Query( $args );
        $posts = array();

        foreach ( $query->posts as $post ) {
            $posts[] = array(
                'id'     => $post->ID,
                'title'  => $post->post_title,
                'status' => $post->post_status,
                'date'   => $post->post_date,
                'url'    => get_permalink( $post->ID ),
            );
        }

        return rest_ensure_response( array(
            'content' => array(
                array(
                    'type' => 'text',
                    'text' => json_encode( array(
                        'posts' => $posts,
                        'total' => $query->found_posts,
                        'page'  => $args['paged'],
                    ) ),
                ),
            ),
        ) );
    }

    private function chat( $params ) {
        $message = isset( $params['message'] ) ? sanitize_text_field( $params['message'] ) : '';
        $model = isset( $params['model'] ) ? sanitize_key( $params['model'] ) : 'coraline';

        // Placeholder - integrate with actual AI engine
        $response = 'BITCORE AI (' . $model . '): Received your message: ' . $message;

        return rest_ensure_response( array(
            'content' => array(
                array(
                    'type' => 'text',
                    'text' => $response,
                ),
            ),
        ) );
    }

    private function create_webhook( $params ) {
        $url = isset( $params['url'] ) ? esc_url_raw( $params['url'] ) : '';
        $events = isset( $params['events'] ) ? array_map( 'sanitize_key', $params['events'] ) : array();

        if ( empty( $url ) || empty( $events ) ) {
            return new WP_Error(
                'invalid_webhook',
                __( 'Webhook requires URL and events.', 'bitcore' ),
                array( 'status' => 400 )
            );
        }

        $webhook = array(
            'id'        => uniqid( 'wh_' ),
            'url'       => $url,
            'events'    => $events,
            'active'    => true,
            'created'   => current_time( 'mysql' ),
        );

        $webhooks = get_option( 'bitcore_webhooks', array() );
        $webhooks[] = $webhook;
        update_option( 'bitcore_webhooks', $webhooks );

        return rest_ensure_response( array(
            'content' => array(
                array(
                    'type' => 'text',
                    'text' => json_encode( $webhook ),
                ),
            ),
        ) );
    }
}

// Initialize
new BITCORE_Rest_MCP_Tools_API();
