<?php
/**
 * BITCORE n8n Connector Service
 * Handles communication with n8n workflow automation
 *
 * @package BITCORE_Core
 * @since 1.0.0
 */

declare(strict_types=1);

/**
 * Class BITCORE_Services_Connector_N8N
 * n8n connector extending abstract base
 */
class BITCORE_Services_Connector_N8N extends BITCORE_Abstract_Connector {

    /** @var string Webhook URL (backward compat) */
    protected string $webhook_url = '';

    /**
     * Constructor - extends parent and init webhook URL
     */
    public function __construct(BITCORE_Core $core) {
        parent::__construct($core);
        // Backward compat: webhook URL may differ from API URL
        $this->webhook_url = get_option('bitcore_n8n_webhook_url', '');
        if (empty($this->api_url)) {
            $this->api_url = $this->webhook_url;
        }
    }

    /**
     * Get API key from options
     */
    protected function get_api_key(): ?string {
        return get_option('bitcore_n8n_api_key') ?: null;
    }

    /**
     * Get API URL from options
     */
    protected function get_api_url(): string {
        return get_option('bitcore_n8n_webhook_url', '');
    }

    /**
     * Get connector name
     */
    public function get_name(): string {
        return 'n8n';
    }

    /**
     * Send research intent to n8n
     */
    public function send_intent(string $intent, string $scope = 'quick', int $user_id = 0): array|\WP_Error {
        $payload = [
            'intent' => $intent,
            'scope' => $scope,
            'user_id' => $user_id ?: get_current_user_id(),
            'timestamp' => time(),
            'correlation_id' => $this->generate_correlation_id(),
            'source' => 'wordpress',
        ];

        return $this->post_to_webhook($payload);
    }

    /**
     * Query queue status from n8n
     */
    public function get_queue_status(): array|\WP_Error {
        $payload = [
            'action' => 'queue_status',
            'timestamp' => time(),
        ];

        return $this->post_to_webhook($payload);
    }

    /**
     * Dispatch to agent swarm
     */
    public function dispatch_to_swarm(array $task, array $agents = []): array|\WP_Error {
        $payload = [
            'action' => 'dispatch',
            'task' => $task,
            'agents' => $agents ?: ['coraline', 'hyperion'],
            'timestamp' => time(),
            'correlation_id' => $this->generate_correlation_id(),
        ];

        return $this->post_to_webhook($payload);
    }

    /**
     * Post to n8n webhook (backward compatible)
     */
    private function post_to_webhook(array $payload): array|\WP_Error {
        if (empty($this->webhook_url)) {
            return new \WP_Error('no_webhook', 'n8n webhook URL not configured');
        }

        $args = [
            'body' => wp_json_encode($payload),
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Bitcore-Key' => $this->api_key ?: '',
            ],
            'timeout' => $this->timeout,
        ];

        $response = wp_remote_post($this->webhook_url, $args);

        if (is_wp_error($response)) {
            $this->last_error = $response->get_error_message();
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        return $data !== null ? $data : ['raw' => $body];
    }

    /**
     * Generate correlation ID for tracking
     */
    private function generate_correlation_id(): string {
        return 'bitcore_' . time() . '_' . wp_rand(1000, 9999);
    }
}
