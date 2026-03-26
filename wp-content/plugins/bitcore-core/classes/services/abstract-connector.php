<?php
/**
 * for implementation
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
 * BITCORE Abstract Connector Base
 * Base class for all external service connectors
 *
 * @package BITCORE_Core
 * @since 1.3.0
 */

/**
 * Abstract class BITCORE_Abstract_Connector
 * Base connector with common HTTP, caching, and logging patterns
 */
abstract class BITCORE_Abstract_Connector {

    /** @var BITCORE_Core Core instance */
    protected BITCORE_Core $core;

    /** @var string API base URL */
    protected string $api_url;

    /** @var string|null API key */
    protected ?string $api_key;

    /** @var int Request timeout */
    protected int $timeout = 30;

    /** @var string Last error message */
    protected string $last_error = '';

    /** @var array Request log for debugging */
    protected array $request_log = [];

    /**
     * Constructor
     */
    public function __construct(BITCORE_Core $core) {
        $this->core = $core;
        $this->api_key = $this->get_api_key();
        $this->api_url = $this->get_api_url();
    }

    /**
     * Get API key (must implement)
     */
    abstract protected function get_api_key(): ?string;

    /**
     * Get API URL (must implement)
     */
    abstract protected function get_api_url(): string;

    /**
     * Get connector name
     */
    abstract public function get_name(): string;

    /**
     * Make authenticated HTTP request
     */
    protected function request(
        string $endpoint,
        string $method = 'GET',
        array $body = [],
        array $headers = []
    ): array|\WP_Error {
        
        $url = trailingslashit($this->api_url) . ltrim($endpoint, '/');
        
        $args = [
            'method'  => $method,
            'timeout' => $this->timeout,
            'headers' => array_merge($this->get_default_headers(), $headers),
        ];

        if (!empty($body)) {
            $args['body'] = wp_json_encode($body);
        }

        $start_time = microtime(true);
        $response = wp_remote_request($url, $args);
        $duration = round((microtime(true) - $start_time) * 1000, 2);

        $this->log_request($endpoint, $method, $duration, is_wp_error($response));

        if (is_wp_error($response)) {
            $this->last_error = $response->get_error_message();
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            $this->last_error = "HTTP {$code}: " . wp_remote_retrieve_body($response);
            return new \WP_Error('http_error', $this->last_error);
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        return $data !== null ? $data : ['raw' => $body];
    }

    /**
     * Get default headers
     */
    protected function get_default_headers(): array {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];

        if ($this->api_key) {
            $headers['Authorization'] = 'Bearer ' . $this->api_key;
        }

        return $headers;
    }

    /**
     * Log request for debugging
     */
    protected function log_request(string $endpoint, string $method, float $duration, bool $error): void {
        $this->request_log[] = [
            'timestamp' => current_time('mysql'),
            'endpoint'  => $endpoint,
            'method'    => $method,
            'duration'  => $duration,
            'error'     => $error,
        ];

        // Keep only last 100 requests
        if (count($this->request_log) > 100) {
            array_shift($this->request_log);
        }
    }

    /**
     * Get last error
     */
    public function get_last_error(): string {
        return $this->last_error;
    }

    /**
     * Get request log
     */
    public function get_request_log(): array {
        return $this->request_log;
    }

    /**
     * Clear request log
     */
    public function clear_request_log(): void {
        $this->request_log = [];
    }

    /**
     * Check if connector is configured
     */
    public function is_configured(): bool {
        return !empty($this->api_url) && !empty($this->api_key);
    }
}
