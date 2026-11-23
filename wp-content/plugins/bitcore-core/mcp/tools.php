<?php
/**
 * BITCORE MCP Tools
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

trait BITCORE_MCP_Tools {

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
}
