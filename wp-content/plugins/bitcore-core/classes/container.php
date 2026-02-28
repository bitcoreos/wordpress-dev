<?php
/**
 * BITCORE Service Container
 * Pattern: Dependency Injection Container
 * Source: AI Engine, AI Services, Orchestrator templates
 * 
 * Provides singleton service container with lazy instantiation
 * for decoupled dependency management across the plugin.
 *
 * @package BITCORE
 * @since 1.0.0
 */

class BITCORE_Container {
    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Registered services
     */
    private $services = [];

    /**
     * Instantiated services (singleton cache)
     */
    private $instances = [];

    /**
     * Private constructor for singleton pattern
     */
    private function __construct() {}

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register a service factory
     * 
     * @param string   $name    Service identifier
     * @param callable $factory Factory function returning service instance
     * @param bool     $shared  Whether to cache as singleton
     * @return $this
     */
    public function register( $name, $factory, $shared = true ) {
        $this->services[ $name ] = [
            'factory' => $factory,
            'shared'  => $shared,
        ];
        return $this;
    }

    /**
     * Get a service instance
     * 
     * @param string $name Service identifier
     * @return mixed Service instance
     * @throws Exception If service not found
     */
    public function get( $name ) {
        if ( ! $this->has( $name ) ) {
            throw new Exception( 'Service not found: ' . $name );
        }

        $service = $this->services[ $name ];

        // Return cached singleton if available
        if ( $service['shared'] && isset( $this->instances[ $name ] ) ) {
            return $this->instances[ $name ];
        }

        // Instantiate via factory
        $instance = call_user_func( $service['factory'], $this );

        // Cache singleton
        if ( $service['shared'] ) {
            $this->instances[ $name ] = $instance;
        }

        return $instance;
    }

    /**
     * Check if service is registered
     * 
     * @param string $name Service identifier
     * @return bool
     */
    public function has( $name ) {
        return isset( $this->services[ $name ] );
    }

    /**
     * Register a singleton instance directly
     * 
     * @param string $name     Service identifier
     * @param mixed  $instance Pre-instantiated service
     * @return $this
     */
    public function set( $name, $instance ) {
        $this->services[ $name ] = [
            'factory' => null,
            'shared'  => true,
        ];
        $this->instances[ $name ] = $instance;
        return $this;
    }

    /**
     * Unregister a service
     * 
     * @param string $name Service identifier
     * @return $this
     */
    public function unregister( $name ) {
        unset( $this->services[ $name ] );
        unset( $this->instances[ $name ] );
        return $this;
    }

    /**
     * Get all registered service names
     * 
     * @return array
     */
    public function get_registered() {
        return array_keys( $this->services );
    }

    /**
     * Clear all instances (for testing)
     */
    public function clear() {
        $this->instances = [];
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new Exception( 'Cannot unserialize singleton' );
    }
}
