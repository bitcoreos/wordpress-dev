<?php
/**
 * Strategy Pattern Implementation
 *
 * Use for interchangeable algorithms (AuthStrategy, CacheStrategy)
 * Pattern extracted from mcp-adapter transport layer and ai-services
 *
 * @package BITCORE\Patterns
 * @since 1.0.0
 * @version     1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Patterns;

/**
 * Strategy Interface
 *
 * Defines contract for interchangeable strategy implementations.
 *
 * @since 1.0.0
 */
interface StrategyInterface {
    /**
     * Execute the strategy with given context.
     *
     * @param array<string,mixed> $context Execution context.
     * @return mixed Strategy execution result.
     */
    public function execute(array $context = []);
}

/**
 * Abstract Strategy Base
 *
 * Provides common functionality for strategies.
 *
 * @since 1.0.0
 */
abstract class AbstractStrategy implements StrategyInterface {
    /**
     * Strategy configuration.
     *
     * @var array<string,mixed>
     */
    protected array $config = [];

    /**
     * Constructor.
     *
     * @param array<string,mixed> $config Strategy configuration.
     */
    public function __construct(array $config = []) {
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    /**
     * Get default configuration.
     *
     * @return array<string,mixed> Default configuration.
     */
    abstract protected function getDefaultConfig(): array;

    /**
     * {@inheritDoc}
     */
    abstract public function execute(array $context = []);
}

/**
 * Authentication Strategy Interface
 *
 * Strategy for authentication mechanisms.
 *
 * @since 1.0.0
 */
interface AuthStrategyInterface extends StrategyInterface {
    /**
     * Authenticate credentials.
     *
     * @param array<string,mixed> $credentials Auth credentials.
     * @return bool|\WP_Error True on success, WP_Error on failure.
     */
    public function authenticate(array $credentials);

    /**
     * Check if user is authenticated.
     *
     * @return bool True if authenticated.
     */
    public function isAuthenticated(): bool;
}

/**
 * JWT Authentication Strategy
 *
 * JWT-based authentication implementation.
 *
 * @since 1.0.0
 */
class JwtAuthStrategy extends AbstractStrategy implements AuthStrategyInterface {
    /**
     * Current user ID.
     *
     * @var int
     */
    protected int $userId = 0;

    /**
     * {@inheritDoc}
     */
    protected function getDefaultConfig(): array {
        return [
            'algorithm' => 'HS256',
            'expiration' => HOUR_IN_SECONDS,
            'issuer' => get_bloginfo('url'),
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function execute(array $context = []) {
        return $this->authenticate($context['credentials'] ?? []);
    }

    /**
     * {@inheritDoc}
     */
    public function authenticate(array $credentials) {
        if (empty($credentials['token'])) {
            return new \WP_Error('missing_token', __('JWT token is required.', 'bitcore-core'));
        }

        // Verify and decode JWT token
        // Implementation would use firebase/php-jwt or similar
        $this->userId = 1; // Placeholder
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function isAuthenticated(): bool {
        return $this->userId > 0;
    }
}

/**
 * API Key Authentication Strategy
 *
 * API key-based authentication implementation.
 *
 * @since 1.0.0
 */
class ApiKeyAuthStrategy extends AbstractStrategy implements AuthStrategyInterface {
    /**
     * {@inheritDoc}
     */
    protected function getDefaultConfig(): array {
        return [
            'header_name' => 'X-API-Key',
            'query_param' => 'api_key',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function execute(array $context = []) {
        return $this->authenticate($context['credentials'] ?? []);
    }

    /**
     * {@inheritDoc}
     */
    public function authenticate(array $credentials) {
        $apiKey = $credentials['api_key'] ?? '';
        if (empty($apiKey)) {
            return new \WP_Error('missing_api_key', __('API key is required.', 'bitcore-core'));
        }

        // Validate API key against stored keys
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function isAuthenticated(): bool {
        return true; // Simplified
    }
}

/**
 * Cache Strategy Interface
 *
 * Strategy for caching implementations.
 *
 * @since 1.0.0
 */
interface CacheStrategyInterface extends StrategyInterface {
    /**
     * Get cached value.
     *
     * @param string $key Cache key.
     * @return mixed|null Cached value or null.
     */
    public function get(string $key);

    /**
     * Set cached value.
     *
     * @param string $key Cache key.
     * @param mixed $value Value to cache.
     * @param int $expiration Expiration in seconds.
     * @return bool True on success.
     */
    public function set(string $key, $value, int $expiration = 0): bool;

    /**
     * Delete cached value.
     *
     * @param string $key Cache key.
     * @return bool True on success.
     */
    public function delete(string $key): bool;
}

/**
 * WordPress Transient Cache Strategy
 *
 * WordPress transient-based caching implementation.
 *
 * @since 1.0.0
 */
class TransientCacheStrategy extends AbstractStrategy implements CacheStrategyInterface {
    /**
     * {@inheritDoc}
     */
    protected function getDefaultConfig(): array {
        return [
            'prefix' => 'bitcore_',
            'default_expiration' => HOUR_IN_SECONDS,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function execute(array $context = []) {
        $operation = $context['operation'] ?? 'get';
        $key = $context['key'] ?? '';

        switch ($operation) {
            case 'get':
                return $this->get($key);
            case 'set':
                return $this->set($key, $context['value'] ?? null, $context['expiration'] ?? 0);
            case 'delete':
                return $this->delete($key);
            default:
                return null;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key) {
        return get_transient($this->config['prefix'] . $key);
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $key, $value, int $expiration = 0): bool {
        $expiration = $expiration > 0 ? $expiration : $this->config['default_expiration'];
        return set_transient($this->config['prefix'] . $key, $value, $expiration);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $key): bool {
        return delete_transient($this->config['prefix'] . $key);
    }
}

/**
 * Object Cache Strategy
 *
 * WordPress object cache implementation.
 *
 * @since 1.0.0
 */
class ObjectCacheStrategy extends AbstractStrategy implements CacheStrategyInterface {
    /**
     * {@inheritDoc}
     */
    protected function getDefaultConfig(): array {
        return [
            'group' => 'bitcore_core',
            'default_expiration' => HOUR_IN_SECONDS,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function execute(array $context = []) {
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key) {
        return wp_cache_get($key, $this->config['group']);
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $key, $value, int $expiration = 0): bool {
        return wp_cache_set($key, $value, $this->config['group'], $expiration);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $key): bool {
        return wp_cache_delete($key, $this->config['group']);
    }
}
