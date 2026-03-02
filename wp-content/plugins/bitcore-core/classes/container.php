<?php
/**
 * BITCORE_Container implementation
 *
 * @package    Bitcore
 * @since      2026.03.01
 * @version    1.3.0
 * @author     hyperion
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * BITCORE Service Container
 * PSR-11 inspired dependency injection container
 *
 * @package BITCORE_Core
 * @since 1.3.0
 */

/**
 * Class BITCORE_Container
 * Service container with factory and singleton patterns
 */
class BITCORE_Container {

    /** @var array Service definitions */
    private array $definitions = [];

    /** @var array Service instances */
    private array $instances = [];

    /**
     * Register a service
     */
    public function set(string $id, callable $factory, bool $shared = true): void {
        $this->definitions[$id] = [
            'factory' => $factory,
            'shared'  => $shared,
        ];
    }

    /**
     * Get a service
     */
    public function get(string $id) {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (!isset($this->definitions[$id])) {
            throw new \RuntimeException("Service not found: {$id}");
        }

        $definition = $this->definitions[$id];
        $instance = $definition['factory']($this);

        if ($definition['shared']) {
            $this->instances[$id] = $instance;
        }

        return $instance;
    }

    /**
     * Check if service exists
     */
    public function has(string $id): bool {
        return isset($this->definitions[$id]);
    }

    /**
     * Register core services
     */
    public function register_core_services(BITCORE_Core $core): void {
        // Core service (not shared - direct reference)
        $this->set('core', fn() => $core, false);
        
        // Response ID Manager
        $this->set('response_id_manager', fn($c) => 
            new BITCORE_Services_Response_ID_Manager($c->get('core'))
        );
        
        // Message Builder
        $this->set('message_builder', fn($c) => 
            new BITCORE_Services_Message_Builder($c->get('core'))
        );
        
        // Session Service
        $this->set('session_service', fn($c) => 
            new BITCORE_Services_Session($c->get('core'))
        );
        
        // Usage Stats
        $this->set('usage_stats', fn($c) => 
            new BITCORE_Services_Usage_Stats($c->get('core'))
        );
        
        // Connectors
        $this->set('connector_n8n', fn($c) => 
            new BITCORE_Services_Connector_N8N($c->get('core'))
        );
        
        $this->set('connector_bithub', fn($c) => 
            new BITCORE_Services_Connector_BIThub($c->get('core'))
        );
        
        $this->set('connector_bitwiki', fn($c) => 
            new BITCORE_Services_Connector_BITwiki($c->get('core'))
        );
    }
}
