<?php
/**
 * BITCORE n8n Connector Service
 * Handles communication with n8n workflow automation
 */

class BITCORE_Services_Connector_N8N {
    private $core;
    private $webhook_url;
    private $api_key;

    public function __construct( $core ) {
        $this->core = $core;
        $this->webhook_url = get_option( 'bitcore_n8n_webhook_url', '' );
        $this->api_key = get_option( 'bitcore_n8n_api_key', '' );
    }

    /**
     * Send research intent to n8n
     */
    public function send_intent( $intent, $scope = 'quick', $user_id = 0 ) {
        $payload = [
            'intent' => $intent,
            'scope' => $scope,
            'user_id' => $user_id ?: get_current_user_id(),
            'timestamp' => time(),
            'correlation_id' => $this->generate_correlation_id(),
            'source' => 'wordpress',
        ];

        return $this->post_to_webhook( $payload );
    }

    /**
     * Query queue status from n8n
     */
    public function get_queue_status() {
        $payload = [
            'action' => 'queue_status',
            'timestamp' => time(),
        ];

        return $this->post_to_webhook( $payload );
    }

    /**
     * Dispatch to agent swarm
     */
    public function dispatch_to_swarm( $task, $agents = [] ) {
        $payload = [
            'action' => 'dispatch',
            'task' => $task,
            'agents' => $agents ?: [ 'coraline', 'hyperion' ],
            'timestamp' => time(),
            'correlation_id' => $this->generate_correlation_id(),
        ];

        return $this->post_to_webhook( $payload );
    }

    /**
     * Post to n8n webhook
     */
    private function post_to_webhook( $payload ) {
        if ( empty( $this->webhook_url ) ) {
            return new WP_Error( 'no_webhook', 'n8n webhook URL not configured' );
        }

        $args = [
            'body' => json_encode( $payload ),
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Bitcore-Key' => $this->api_key,
            ],
            'timeout' => BITCORE_TIMEOUT,
        ];

        $response = wp_remote_post( $this->webhook_url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = wp_remote_retrieve_body( $response );
        return json_decode( $body, true );
    }

    /**
     * Generate correlation ID for tracking
     */
    private function generate_correlation_id() {
        return 'bitcore_' . time() . '_' . wp_rand( 1000, 9999 );
    }
}
