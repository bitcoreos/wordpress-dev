<?php
/**
 * BITCORE MCP Resources
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
        // Parse bitcore://resource/params format
        if ( ! preg_match( '/^bitcore:\/\/([^\/]+)\/(.*)$/', $uri, $matches ) ) {
            return new WP_Error( 'invalid_uri', 'Invalid resource URI format', [ 'status' => 400 ] );
        }

        $resource = $matches[1];
        $path = $matches[2];

        // Parse path params
        $params = [];
        if ( strpos( $path, '/' ) !== false ) {
            $parts = explode( '/', $path );
            $params['path'] = $path;
            $params['segments'] = $parts;
            
            // Map common patterns
            if ( is_numeric( $parts[0] ) ) {
                $params['id'] = (int) $parts[0];
            }
        } else {
            $params['path'] = $path;
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
                [ 'type' => 'text', 'text' => 'Streaming started. Use stream_id: ' . $stream_id ],
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

