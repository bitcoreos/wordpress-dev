<?php
/**
 * BITCORE REST Authentication Filter
 * Pattern: REST Auth Filter with API Key Support
 * Source: Site Secrets template
 * 
 * Provides centralized authentication filtering for REST endpoints
 * with API key support and capability-based access control.
 *
 * @package BITCORE
 * @since 1.0.0
 */

class BITCORE_Rest_Auth_Filter {
    /**
     * API key option name
     */
    const API_KEYS_OPTION = 'bitcore_api_keys';

    /**
     * Header name for API key
     */
    const API_KEY_HEADER = 'X-BITCORE-API-Key';

    /**
     * Header name for API secret
     */
    const API_SECRET_HEADER = 'X-BITCORE-API-Secret';

    /**
     * Registered endpoint capabilities
     */
    private $endpoint_caps = [];

    /**
     * Constructor
     */
    public function __construct() {
        // Register authentication filter
        add_filter( 'rest_authentication_errors', [ $this, 'authenticate_request' ], 10 );
        
        // Parse API key from headers
        add_filter( 'determine_current_user', [ $this, 'determine_user_from_api_key' ], 20 );
        
        // Log REST requests for audit
        add_action( 'rest_api_init', [ $this, 'setup_request_logging' ], 1 );
    }

    /**
     * Register capability requirement for an endpoint
     * 
     * @param string $route    REST route pattern (e.g., '/bitcore/v1/*')
     * @param string $capability Required capability
     * @param array  $methods  HTTP methods (default: all)
     * @return $this
     */
    public function register_endpoint_cap( $route, $capability, $methods = [] ) {
        $this->endpoint_caps[] = [
            'route'      => $route,
            'capability' => $capability,
            'methods'    => $methods,
        ];
        return $this;
    }

    /**
     * Authenticate REST request
     * 
     * @param WP_Error|null $result Current authentication result
     * @return WP_Error|null|true Authentication result
     */
    public function authenticate_request( $result ) {
        // Don't override previous errors
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        // Get current request
        $request = $this->get_current_request();
        
        if ( ! $request ) {
            return $result;
        }

        $route = $request->get_route();
        $method = $request->get_method();

        // Check if this endpoint requires special handling
        $required_cap = $this->get_required_capability( $route, $method );

        if ( ! $required_cap ) {
            // No special requirements, use default WordPress auth
            return $result;
        }

        // Check if user has required capability
        if ( ! current_user_can( $required_cap ) ) {
            return new WP_Error(
                'rest_forbidden',
                __( 'You do not have permission to access this endpoint.', 'bitcore' ),
                [ 'status' => 403 ]
            );
        }

        // Check rate limits
        $rate_limit_check = $this->check_rate_limit( $route );
        if ( is_wp_error( $rate_limit_check ) ) {
            return $rate_limit_check;
        }

        return true;
    }

    /**
     * Determine user from API key
     * 
     * @param int|false $user_id Current user ID
     * @return int|false User ID or false
     */
    public function determine_user_from_api_key( $user_id ) {
        // If already authenticated, return that user
        if ( $user_id ) {
            return $user_id;
        }

        // Check for API key in headers
        $api_key = $this->get_api_key_from_request();
        
        if ( ! $api_key ) {
            return false;
        }

        // Validate API key
        $key_data = $this->validate_api_key( $api_key );
        
        if ( ! $key_data ) {
            return false;
        }

        // Log API key usage
        $this->log_api_key_usage( $api_key, $key_data );

        return $key_data['user_id'];
    }

    /**
     * Get API key from request headers
     * 
     * @return string|false API key or false
     */
    private function get_api_key_from_request() {
        // Check Authorization header
        if ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
            $auth = $_SERVER['HTTP_AUTHORIZATION'];
            if ( strpos( $auth, 'Bearer ' ) === 0 ) {
                return sanitize_text_field( substr( $auth, 7 ) );
            }
        }

        // Check custom header
        if ( isset( $_SERVER['HTTP_X_BITCORE_API_KEY'] ) ) {
            return sanitize_text_field( $_SERVER['HTTP_X_BITCORE_API_KEY'] );
        }

        // Check query parameter (less secure, but useful for testing)
        if ( isset( $_GET['bitcore_api_key'] ) ) {
            return sanitize_text_field( $_GET['bitcore_api_key'] );
        }

        return false;
    }

    /**
     * Validate API key
     * 
     * @param string $api_key API key to validate
     * @return array|false Key data or false
     */
    private function validate_api_key( $api_key ) {
        $api_keys = get_option( self::API_KEYS_OPTION, [] );

        if ( ! isset( $api_keys[ $api_key ] ) ) {
            return false;
        }

        $key_data = $api_keys[ $api_key ];

        // Check if key is expired
        if ( isset( $key_data['expires'] ) && $key_data['expires'] < time() ) {
            return false;
        }

        // Check if key is revoked
        if ( ! empty( $key_data['revoked'] ) ) {
            return false;
        }

        return $key_data;
    }

    /**
     * Generate new API key
     * 
     * @param int    $user_id User ID
     * @param string $name    Key name/description
     * @param array  $scopes  Allowed scopes
     * @param int    $expires Expiration timestamp (0 = never)
     * @return array Key data
     */
    public function generate_api_key( $user_id, $name, $scopes = [], $expires = 0 ) {
        $api_key = 'bitcore_' . wp_generate_password( 32, false );
        $api_secret = wp_generate_password( 64, true, true );

        $key_data = [
            'user_id'    => (int) $user_id,
            'name'       => sanitize_text_field( $name ),
            'scopes'     => $scopes,
            'created'    => time(),
            'expires'    => $expires,
            'last_used'  => 0,
            'revoked'    => false,
        ];

        $api_keys = get_option( self::API_KEYS_OPTION, [] );
        $api_keys[ $api_key ] = $key_data;
        update_option( self::API_KEYS_OPTION, $api_keys );

        return [
            'key'    => $api_key,
            'secret' => $api_secret,
            'data'   => $key_data,
        ];
    }

    /**
     * Revoke API key
     * 
     * @param string $api_key API key to revoke
     * @return bool Success
     */
    public function revoke_api_key( $api_key ) {
        $api_keys = get_option( self::API_KEYS_OPTION, [] );

        if ( ! isset( $api_keys[ $api_key ] ) ) {
            return false;
        }

        $api_keys[ $api_key ]['revoked'] = true;
        update_option( self::API_KEYS_OPTION, $api_keys );

        return true;
    }

    /**
     * Get user's API keys
     * 
     * @param int $user_id User ID
     * @return array List of API keys
     */
    public function get_user_api_keys( $user_id ) {
        $api_keys = get_option( self::API_KEYS_OPTION, [] );
        $user_keys = [];

        foreach ( $api_keys as $key => $data ) {
            if ( $data['user_id'] === (int) $user_id && empty( $data['revoked'] ) ) {
                $user_keys[ $key ] = $data;
            }
        }

        return $user_keys;
    }

    /**
     * Get required capability for endpoint
     * 
     * @param string $route  REST route
     * @param string $method HTTP method
     * @return string|false Capability or false
     */
    private function get_required_capability( $route, $method ) {
        foreach ( $this->endpoint_caps as $endpoint ) {
            // Simple wildcard matching
            $pattern = str_replace( '*', '.*', preg_quote( $endpoint['route'], '/' ) );
            
            if ( preg_match( '/^' . $pattern . '$/i', $route ) ) {
                // Check method restriction
                if ( ! empty( $endpoint['methods'] ) && ! in_array( $method, $endpoint['methods'], true ) ) {
                    continue;
                }
                
                return $endpoint['capability'];
            }
        }

        return false;
    }

    /**
     * Check rate limit for request
     * 
     * @param string $route REST route
     * @return true|WP_Error True if allowed, WP_Error if rate limited
     */
    private function check_rate_limit( $route ) {
        $user_id = get_current_user_id();
        $ip = $this->get_client_ip();
        
        // Build rate limit key
        $key = 'bitcore_rate_' . md5( $route . '_' . $user_id . '_' . $ip );
        
        // Get current count
        $count = (int) get_transient( $key );
        $limit = apply_filters( 'bitcore_rate_limit', 100, $route );
        $window = apply_filters( 'bitcore_rate_window', HOUR_IN_SECONDS, $route );

        if ( $count >= $limit ) {
            return new WP_Error(
                'rest_rate_limited',
                __( 'API rate limit exceeded. Please try again later.', 'bitcore' ),
                [ 'status' => 429 ]
            );
        }

        // Increment count
        set_transient( $key, $count + 1, $window );

        return true;
    }

    /**
     * Log API key usage
     * 
     * @param string $api_key  API key used
     * @param array  $key_data Key data
     */
    private function log_api_key_usage( $api_key, $key_data ) {
        $api_keys = get_option( self::API_KEYS_OPTION, [] );
        $api_keys[ $api_key ]['last_used'] = time();
        update_option( self::API_KEYS_OPTION, $api_keys );

        /**
         * Fired when API key is used
         * 
         * @param string $api_key  API key
         * @param array  $key_data Key data
         * @param string $route    Request route
         */
        do_action( 'bitcore_api_key_used', $api_key, $key_data, $this->get_current_request()->get_route() );
    }

    /**
     * Get current REST request
     * 
     * @return WP_REST_Request|false Request or false
     */
    private function get_current_request() {
        if ( ! did_action( 'rest_api_init' ) ) {
            return false;
        }

        // Try to get from REST server
        if ( function_exists( 'rest_get_server' ) ) {
            $server = rest_get_server();
            if ( method_exists( $server, 'get_raw_data' ) ) {
                return $server->get_raw_data();
            }
        }

        return false;
    }

    /**
     * Setup request logging
     */
    public function setup_request_logging() {
        /**
         * Filter to enable/disable REST request logging
         * 
         * @param bool $enabled Whether logging is enabled
         */
        if ( ! apply_filters( 'bitcore_rest_logging_enabled', true ) ) {
            return;
        }

        add_action( 'rest_api_init', function() {
            // Log after request is processed
            add_filter( 'rest_pre_serve_request', function( $served, $result, $request ) {
                $this->log_request( $request, $result );
                return $served;
            }, 10, 3 );
        }, 99 );
    }

    /**
     * Log REST request
     * 
     * @param WP_REST_Request $request Request
     * @param WP_REST_Response $result Response
     */
    private function log_request( $request, $result ) {
        $log = [
            'timestamp' => current_time( 'mysql' ),
            'route'     => $request->get_route(),
            'method'    => $request->get_method(),
            'user_id'   => get_current_user_id(),
            'status'    => $result->get_status(),
            'ip'        => $this->get_client_ip(),
        ];

        /**
         * Fired when REST request is logged
         * 
         * @param array $log Log entry
         * @param WP_REST_Request $request Request
         * @param WP_REST_Response $result Response
         */
        do_action( 'bitcore_rest_request_logged', $log, $request, $result );
    }

    /**
     * Get client IP address
     * 
     * @return string IP address
     */
    private function get_client_ip() {
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        ];

        foreach ( $headers as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                $ips = explode( ',', $_SERVER[ $header ] );
                $ip = trim( $ips[0] );
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }
}
