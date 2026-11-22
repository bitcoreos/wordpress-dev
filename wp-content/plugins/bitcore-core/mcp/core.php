<?php
declare(strict_types=1);
/**
 * BITCORE MCP (Model Context Protocol) Core
 * Pattern: Enhanced MCP Protocol Implementation
 * Source: MCP Adapter template
 * 
 * Provides AI tool calling, resource access, and streaming responses
 * following the Model Context Protocol specification.
 *
 * @package BITCORE
 * @since 1.0.0
 */

class BITCORE_MCP_Core {
    private $core;
    private $tools = [];
    private $resources = [];
    private $streams = [];

    public function __construct( $core ) {
        $this->core = $core;

        $this->register_core_tools();
        $this->register_core_resources();
    }

    /**
     * Register core MCP tools
     */
    private function register_core_tools() {
        // Search tool
        $this->register_tool( 'search_bitwiki', [
            'description' => 'Search BITwiki knowledge base',
            'parameters' => [
                'query' => [ 'type' => 'string', 'required' => true, 'description' => 'Search query' ],
                'limit' => [ 'type' => 'integer', 'default' => 5, 'description' => 'Max results to return' ],
            ],
            'callback' => [ $this, 'tool_search_bitwiki' ],
            'streaming' => false,
        ] );

        // Get artifacts tool
        $this->register_tool( 'get_artifacts', [
            'description' => 'Get available artifacts from BIThub',
            'parameters' => [],
            'callback' => [ $this, 'tool_get_artifacts' ],
            'streaming' => false,
        ] );

        // Submit intent tool
        $this->register_tool( 'submit_research_intent', [
            'description' => 'Submit a research intent to the librarian',
            'parameters' => [
                'intent' => [ 'type' => 'string', 'required' => true, 'description' => 'Research intent' ],
                'scope' => [ 'type' => 'string', 'enum' => [ 'quick', 'deep' ], 'default' => 'quick', 'description' => 'Research scope' ],
            ],
            'callback' => [ $this, 'tool_submit_intent' ],
            'streaming' => false,
        ] );

        // Get queue status
        $this->register_tool( 'get_queue_status', [
            'description' => 'Get current research queue status',
            'parameters' => [],
            'callback' => [ $this, 'tool_get_queue' ],
            'streaming' => false,
        ] );

        // Dispatch to swarm
        $this->register_tool( 'dispatch_to_swarm', [
            'description' => 'Dispatch a task to the agent swarm',
            'parameters' => [
                'task' => [ 'type' => 'string', 'required' => true, 'description' => 'Task description' ],
                'agents' => [ 'type' => 'array', 'default' => [], 'description' => 'Specific agents to dispatch' ],
            ],
            'callback' => [ $this, 'tool_dispatch_swarm' ],
            'streaming' => true,
        ] );

        // Streaming chat tool
        $this->register_tool( 'stream_chat', [
            'description' => 'Chat with streaming response',
            'parameters' => [
                'message' => [ 'type' => 'string', 'required' => true, 'description' => 'Message to send' ],
                'context' => [ 'type' => 'string', 'default' => '', 'description' => 'Conversation context' ],
            ],
            'callback' => [ $this, 'tool_stream_chat' ],
            'streaming' => true,
        ] );
    }

    /**
     * Register core MCP resources
     */
    private function register_core_resources() {
        // BITwiki knowledge base
        $this->register_resource( 'bitwiki', [
            'name' => 'BITwiki Knowledge Base',
            'description' => 'Access to BITwiki documentation and articles',
            'mimeType' => 'application/json',
            'uri' => 'bitcore://bitwiki/{path}',
            'callback' => [ $this, 'resource_bitwiki' ],
        ] );

        // BIThub discussions
        $this->register_resource( 'bithub', [
            'name' => 'BIThub Discussions',
            'description' => 'Access to BIThub forum discussions',
            'mimeType' => 'application/json',
            'uri' => 'bitcore://bithub/{topic_id}',
            'callback' => [ $this, 'resource_bithub' ],
        ] );

        // Agent swarm status
        $this->register_resource( 'swarm', [
            'name' => 'Agent Swarm Status',
            'description' => 'Current status of agent swarm nodes',
            'mimeType' => 'application/json',
            'uri' => 'bitcore://swarm/{agent_id}',
            'callback' => [ $this, 'resource_swarm' ],
        ] );

        // Research artifacts
        $this->register_resource( 'artifacts', [
            'name' => 'Research Artifacts',
            'description' => 'Access to research artifacts and outputs',
            'mimeType' => 'application/json',
            'uri' => 'bitcore://artifacts/{artifact_id}',
            'callback' => [ $this, 'resource_artifacts' ],
        ] );
    }

    /**
     * Register a tool
     * 
     * @param string $name   Tool identifier
     * @param array  $config Tool configuration
     * @return $this
     */
    public function register_tool( $name, $config ) {
        $this->tools[ $name ] = wp_parse_args( $config, [
            'description' => '',
            'parameters' => [],
            'callback' => null,
            'streaming' => false,
        ] );
        return $this;
    }

    /**
     * Register a resource
     * 
     * @param string $name   Resource identifier
     * @param array  $config Resource configuration
     * @return $this
     */
    public function register_resource( $name, $config ) {
        $this->resources[ $name ] = wp_parse_args( $config, [
            'name' => $name,
            'description' => '',
            'mimeType' => 'application/json',
            'uri' => '',
            'callback' => null,
        ] );
        return $this;
    }

    /**
     * List all available tools
     * 
     * @return array List of tool definitions
     */
    public function list_tools() {
        $tools = [];
        foreach ( $this->tools as $name => $config ) {
            $tools[] = [
                'name' => $name,
                'description' => $config['description'],
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => $this->format_parameters_for_schema( $config['parameters'] ),
                    'required' => $this->get_required_parameters( $config['parameters'] ),
                ],
                'streaming' => $config['streaming'],
            ];
        }
        return $tools;
    }

    /**
     * Call a tool with parameter validation
     * 
     * @param string $name    Tool name
     * @param array  $args    Tool arguments
     * @param bool   $stream  Whether to stream response
     * @return array|WP_Error Tool result or error
     */
    public function call_tool( $name, $args = [], $stream = false ) {
        if ( ! isset( $this->tools[ $name ] ) ) {
            return new WP_Error( 'tool_not_found', 'Tool not found: ' . $name, [ 'status' => 404 ] );
        }

        $tool = $this->tools[ $name ];

        // Validate parameters
        $validation = $this->validate_parameters( $args, $tool['parameters'] );
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }

        // Apply defaults
        $args = $this->apply_defaults( $args, $tool['parameters'] );

        // Call the callback
        if ( $stream && $tool['streaming'] ) {
            return $this->call_tool_streaming( $tool['callback'], $args );
        }

        $result = call_user_func( $tool['callback'], $args );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return [
            'content' => [
                [ 'type' => 'string', 'text' => $this->format_result( $result ) ],
            ],
            'isError' => false,
        ];
    }

    /**
     * List all available resources
     * 
     * @return array List of resource definitions
     */
    public function list_resources() {
        $resources = [];
        foreach ( $this->resources as $name => $config ) {
            $resources[] = [
                'uri' => $config['uri'],
                'name' => $config['name'],
                'description' => $config['description'],
                'mimeType' => $config['mimeType'],
            ];
        }
        return $resources;
    }

    /**
     * Read a resource
     * 
     * @param string $uri Resource URI
     * @return array|WP_Error Resource content or error
     */
    public function read_resource( $uri ) {
        // Parse URI
        $parsed = $this->parse_resource_uri( $uri );
        
        if ( is_wp_error( $parsed ) ) {
            return $parsed;
        }

        $resource_name = $parsed['resource'];
        $params = $parsed['params'];

        if ( ! isset( $this->resources[ $resource_name ] ) ) {
            return new WP_Error( 'resource_not_found', 'Resource not found: ' . $resource_name, [ 'status' => 404 ] );
        }

        $resource = $this->resources[ $resource_name ];
        $content = call_user_func( $resource['callback'], $params );

        if ( is_wp_error( $content ) ) {
            return $content;
        }

        return [
            'contents' => [
                [
                    'uri' => $uri,
                    'mimeType' => $resource['mimeType'],
                    'text' => is_string( $content ) ? $content : wp_json_encode( $content ),
                ],
            ],
        ];
    }

    /**
     * Get all tools (legacy format)
     * 
     * @return array Tools in legacy format
     */
    public function get_tools() {
        $tools = [];
        foreach ( $this->tools as $name => $config ) {
            $tools[] = [
                'name' => $name,
                'description' => $config['description'],
                'parameters' => $config['parameters'],
            ];
        }
        return $tools;
    }

    // Tool implementations
    private function tool_search_bitwiki( $args ) {
        $results = $this->core->connector_bitwiki->search( $args['query'], $args['limit'] ?? 5 );
        return $results;
    }

    private function tool_get_artifacts() {
        return $this->core->connector_bithub->get_artifacts();
    }

    private function tool_submit_intent( $args ) {
        return $this->core->connector_n8n->send_intent( $args['intent'], $args['scope'], get_current_user_id() );
    }

    private function tool_get_queue() {
        return $this->core->connector_n8n->get_queue_status();
    }

    private function tool_dispatch_swarm( $args ) {
        return $this->core->connector_n8n->dispatch_to_swarm( $args['task'], $args['agents'] ?? [] );
    }

    private function tool_stream_chat( $args ) {
        // Initiate streaming chat
        $stream_id = $this->initiate_stream( 'chat', $args );
        
        return [
            'stream_id' => $stream_id,
            'status' => 'started',
            'message' => $args['message'],
        ];
    }

    // Resource implementations
    private function resource_bitwiki( $params ) {
        $path = $params['path'] ?? '';
        
        if ( empty( $path ) ) {
            // Return index
            return $this->core->connector_bitwiki->get_index();
        }
        
        return $this->core->connector_bitwiki->get_article( $path );
    }

    private function resource_bithub( $params ) {
        $topic_id = $params['topic_id'] ?? null;
        
        if ( $topic_id ) {
            return $this->core->connector_bithub->get_topic( $topic_id );
        }
        
        return $this->core->connector_bithub->get_latest_topics( 20 );
    }

    private function resource_swarm( $params ) {
        $agent_id = $params['agent_id'] ?? null;
        
        if ( $agent_id ) {
            return $this->core->connector_n8n->get_agent_status( $agent_id );
        }
        
        return $this->core->connector_n8n->get_swarm_status();
    }

    private function resource_artifacts( $params ) {
        $artifact_id = $params['artifact_id'] ?? null;
        
        if ( $artifact_id ) {
            return $this->core->connector_bithub->get_artifact( $artifact_id );
        }
        
        return $this->core->connector_bithub->get_artifacts();
    }

    // Helper methods
    private function format_parameters_for_schema( $parameters ) {
        $properties = [];
        foreach ( $parameters as $name => $config ) {
            $properties[ $name ] = [
                'type' => $config['type'],
                'description' => $config['description'] ?? '',
            ];
            if ( isset( $config['enum'] ) ) {
                $properties[ $name ]['enum'] = $config['enum'];
            }
        }
        return $properties;
    }

    private function get_required_parameters( $parameters ) {
        $required = [];
        foreach ( $parameters as $name => $config ) {
            if ( ! empty( $config['required'] ) ) {
                $required[] = $name;
            }
        }
        return $required;
    }

    private function validate_parameters( $args, $parameters ) {
        foreach ( $parameters as $name => $config ) {
            // Check required
            if ( ! empty( $config['required'] ) && ! isset( $args[ $name ] ) ) {
                return new WP_Error( 'missing_parameter', 'Missing required parameter: ' . $name, [ 'status' => 400 ] );
            }

            // Validate type
            if ( isset( $args[ $name ] ) ) {
                $valid = $this->validate_type( $args[ $name ], $config['type'] );
                if ( ! $valid ) {
                    return new WP_Error( 'invalid_type', 'Invalid type for parameter ' . $name . ': expected ' . $config['type'], [ 'status' => 400 ] );
                }

                // Validate enum
                if ( isset( $config['enum'] ) && ! in_array( $args[ $name ], $config['enum'], true ) ) {
                    return new WP_Error( 'invalid_value', 'Invalid value for parameter ' . $name . ': must be one of ' . implode( ', ', $config['enum'] ), [ 'status' => 400 ] );
                }
            }
        }
        return true;
    }

    private function validate_type( $value, $type ) {
        switch ( $type ) {
            case 'string':
                return is_string( $value );
            case 'integer':
                return is_int( $value ) || ( is_numeric( $value ) && (int) $value == $value );
            case 'number':
                return is_numeric( $value );
            case 'boolean':
                return is_bool( $value );
            case 'array':
                return is_array( $value );
            case 'object':
                return is_array( $value ) || is_object( $value );
            default:
                return true;
        }
    }

    private function apply_defaults( $args, $parameters ) {
        foreach ( $parameters as $name => $config ) {
            if ( ! isset( $args[ $name ] ) && isset( $config['default'] ) ) {
                $args[ $name ] = $config['default'];
            }
        }
        return $args;
    }

    private function format_result( $result ) {
        if ( is_string( $result ) ) {
            return $result;
        }
        return wp_json_encode( $result, JSON_PRETTY_PRINT );
    }

    private function parse_resource_uri( $uri ) {
        // Parse bitcore://resource/params format. The path part is optional so
        // bitcore://bitwiki can resolve to an index resource.
        if ( ! preg_match( '/^bitcore:\/\/([^\/]+)(?:\/(.*))?$/', $uri, $matches ) ) {
            return new WP_Error( 'invalid_uri', 'Invalid resource URI format', [ 'status' => 400 ] );
        }

        $resource = $matches[1];
        $path = $matches[2] ?? '';
        $path = trim( $path, '/' );

        $params = [
            'path' => $path,
        ];

        if ( $path !== '' ) {
            $parts = explode( '/', $path );
            $params['segments'] = $parts;
            $params['id'] = is_numeric( $parts[0] ) ? (int) $parts[0] : $parts[0];

            // Resource callbacks expect semantic identifiers, not only a
            // generic path. Map the first URI segment to the callback contract.
            switch ( $resource ) {
                case 'bithub':
                    $params['topic_id'] = $params['id'];
                    break;
                case 'artifacts':
                    $params['artifact_id'] = $params['id'];
                    break;
                case 'swarm':
                    $params['agent_id'] = $params['id'];
                    break;
            }
        }

        return [
            'resource' => $resource,
            'params' => $params,
        ];
    }

    // Streaming support
    private function initiate_stream( $type, $args ) {
        $stream_id = wp_generate_password( 16, false );
        
        $this->streams[ $stream_id ] = [
            'type' => $type,
            'args' => $args,
            'started' => time(),
            'status' => 'active',
        ];
        
        return $stream_id;
    }

    private function call_tool_streaming( $callback, $args ) {
        // For WordPress, we simulate streaming with chunked response
        // In practice, this would use Server-Sent Events or WebSockets
        
        $stream_id = $this->initiate_stream( 'tool_call', $args );
        
        return [
            'content' => [
                [ 'type' => 'string', 'text' => 'Streaming started. Use stream_id: ' . $stream_id ],
            ],
            'stream_id' => $stream_id,
            'isError' => false,
        ];
    }

    /**
     * Get stream status
     * 
     * @param string $stream_id Stream identifier
     * @return array|false Stream status or false
     */
    public function get_stream_status( $stream_id ) {
        if ( ! isset( $this->streams[ $stream_id ] ) ) {
            return false;
        }
        
        return $this->streams[ $stream_id ];
    }

    /**
     * Close stream
     * 
     * @param string $stream_id Stream identifier
     * @return bool Success
     */
    public function close_stream( $stream_id ) {
        if ( isset( $this->streams[ $stream_id ] ) ) {
            $this->streams[ $stream_id ]['status'] = 'closed';
            $this->streams[ $stream_id ]['ended'] = time();
            return true;
        }
        return false;
    }
}
