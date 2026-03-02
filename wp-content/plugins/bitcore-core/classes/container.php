<?php
/**
 * BITCORE Service Container
 * PSR-11 inspired dependency injection container
 *
 * Merged from Hyperion (instance-based) + Coraline (utility methods)
 *
 * @package BITCORE
 * @since 1.2.0
 */

declare(strict_types=1);

class BITCORE_Container {
    /** @var array Service definitions */
    private array $definitions = [];

    /** @var array Service instances (singleton cache) */
    private array $instances = [];

    /**
     * Register a service factory
     *
     * @param string   $id      Service identifier
     * @param callable $factory Factory function returning service instance
     * @param bool     $shared  Whether to cache as singleton (default: true)
     * @return $this
     */
    public function set(string $id, callable $factory, bool $shared = true): self {
        $this->definitions[$id] = [
            'factory' => $factory,
            'shared'  => $shared,
        ];
        return $this;
    }

    /**
     * Alias for set() - Coraline compatibility
     *
     * @param string   $name    Service identifier
     * @param callable $factory Factory function
     * @param bool     $shared  Whether to cache as singleton
     * @return $this
     */
    public function register(string $name, callable $factory, bool $shared = true): self {
        return $this->set($name, $factory, $shared);
    }

    /**
     * Get a service instance
     *
     * @param string $id Service identifier
     * @return mixed Service instance
     * @throws \RuntimeException If service not found
     */
    public function get(string $id): mixed {
        // Return cached singleton if available
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (!isset($this->definitions[$id])) {
            throw new \RuntimeException("Service not found: {$id}");
        }

        $definition = $this->definitions[$id];
        $instance = $definition['factory']($this);

        // Cache singleton
        if ($definition['shared']) {
            $this->instances[$id] = $instance;
        }

        return $instance;
    }

    /**
     * Check if service is registered
     *
     * @param string $id Service identifier
     * @return bool
     */
    public function has(string $id): bool {
        return isset($this->definitions[$id]);
    }

    /**
     * Unregister a service (Coraline utility)
     *
     * @param string $id Service identifier
     * @return bool True if service was removed
     */
    public function unregister(string $id): bool {
        if (!isset($this->definitions[$id])) {
            return false;
        }
        unset($this->definitions[$id]);
        unset($this->instances[$id]);
        return true;
    }

    /**
     * Get all registered service names (Coraline utility)
     *
     * @return array
     */
    public function get_registered(): array {
        return array_keys($this->definitions);
    }

    /**
     * Clear all instances (Coraline utility - for testing)
     *
     * @return void
     */
    public function clear(): void {
        $this->instances = [];
    }

    /**
     * Register core services
     *
     * @param BITCORE_Core $core Core instance
     * @return void
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
