<?php
/**
 * BITCORE Hermes Connector Service
 * Handles communication with Hermes Agent HTTP Bridge
 *
 * @package BITCORE_Core
 * @since 1.4.0
 */

declare(strict_types=1);

/**
 * Class BITCORE_Services_Connector_Hermes
 * Hermes connector extending abstract base
 */
class BITCORE_Services_Connector_Hermes extends BITCORE_Abstract_Connector {

    /** @var string Callback URL for async responses */
    private string $callback_url = '';

    /** @var string Callback secret for HMAC verification */
    private string $callback_secret = '';

    /**
     * Constructor - extends parent and init callback settings
     */
    public function __construct(BITCORE_Core $core) {
        parent::__construct($core);
        $this->callback_url = rest_url('bitcore/v1/callback');
        $this->callback_secret = $this->get_callback_secret();
    }

    /**
     * Get API key from options
     */
    protected function get_api_key(): ?string {
        return get_option('bitcore_hermes_api_key') ?: null;
    }

    /**
     * Get API URL from options (Hermes Bridge endpoint)
     */
    protected function get_api_url(): string {
        return get_option('bitcore_hermes_url', 'http://127.0.0.1:8787');
    }

    /**
     * Get connector name
     */
    public function get_name(): string {
        return 'hermes';
    }

    /**
     * Get or generate callback secret for HMAC verification
     */
    private function get_callback_secret(): string {
        $secret = get_option('bitcore_callback_secret');
        if (!$secret) {
            $secret = wp_generate_password(32, true, true);
            update_option('bitcore_callback_secret', $secret);
        }
        return $secret;
    }

    /**
     * Get default headers with callback secret
     */
    protected function get_default_headers(): array {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Bitcore-Callback-Secret' => $this->callback_secret,
        ];
    }

    /**
     * Health check - verify Hermes is available
     */
    public function health_check(): bool {
        $response = $this->request('/api/v1/health', 'GET');
        return !is_wp_error($response) && isset($response['status']);
    }

    /**
     * Get system stats from Hermes
     */
    public function get_stats(): array|\WP_Error {
        return $this->request('/api/v1/stats', 'GET');
    }

    /**
     * Send synchronous chat message to Hermes
     *
     * @param string $message User message
     * @param string $session_id Optional session ID for context
     * @param array $toolsets Toolsets to enable (terminal, file, browser, etc.)
     * @return array|\WP_Error
     */
    public function chat(string $message, string $session_id = '', array $toolsets = ['terminal', 'file']): array|\WP_Error {
        $payload = [
            'message' => $message,
            'session_id' => $session_id,
            'toolsets' => $toolsets,
            'wp_user_id' => get_current_user_id(),
        ];

        $response = $this->request('/api/v1/chat', 'POST', $payload);

        if (is_wp_error($response)) {
            error_log('BITCORE Hermes chat error: ' . $response->get_error_message());
        }

        return $response;
    }

    /**
     * Submit async task to Hermes
     *
     * @param int $wp_task_id WordPress task post ID
     * @param string $prompt Task prompt
     * @param array $toolsets Toolsets to enable
     * @return array|\WP_Error Returns Hermes task ID
     */
    public function submit_task(int $wp_task_id, string $prompt, array $toolsets = ['terminal', 'file']): array|\WP_Error {
        $payload = [
            'wp_task_id' => $wp_task_id,
            'prompt' => $prompt,
            'toolsets' => $toolsets,
            'callback_url' => $this->callback_url,
            'callback_secret' => $this->callback_secret,
            'wp_user_id' => get_current_user_id(),
        ];

        $response = $this->request('/api/v1/task', 'POST', $payload);

        if (is_wp_error($response)) {
            error_log('BITCORE Hermes task error: ' . $response->get_error_message());
        }

        return $response;
    }

    /**
     * Get task status from Hermes
     *
     * @param string $hermes_task_id Hermes task ID
     * @return array|\WP_Error
     */
    public function get_task_status(string $hermes_task_id): array|\WP_Error {
        return $this->request('/api/v1/task/' . $hermes_task_id, 'GET');
    }

    /**
     * Cancel a running task
     *
     * @param string $hermes_task_id Hermes task ID
     * @return bool
     */
    public function cancel_task(string $hermes_task_id): bool {
        $response = $this->request('/api/v1/task/' . $hermes_task_id . '/cancel', 'POST');
        return !is_wp_error($response);
    }

    /**
     * List available skills from Hermes
     *
     * @return array|\WP_Error
     */
    public function list_skills(): array|\WP_Error {
        return $this->request('/api/v1/skills', 'GET');
    }

    /**
     * Execute a skill on Hermes
     *
     * @param string $skill_name Skill identifier
     * @param array $args Skill arguments
     * @return array|\WP_Error
     */
    public function run_skill(string $skill_name, array $args = []): array|\WP_Error {
        $payload = [
            'args' => $args,
            'wp_user_id' => get_current_user_id(),
        ];

        return $this->request('/api/v1/skill/' . $skill_name . '/run', 'POST', $payload);
    }

    /**
     * Get session data from Hermes
     *
     * @param string $session_id Session identifier
     * @return array|\WP_Error
     */
    public function get_session(string $session_id): array|\WP_Error {
        return $this->request('/api/v1/session/' . $session_id, 'GET');
    }

    /**
     * Verify callback signature (for webhook security)
     *
     * @param string $payload Request body
     * @param string $signature X-Hermes-Signature header
     * @return bool
     */
    public function verify_callback_signature(string $payload, string $signature): bool {
        $expected = hash_hmac('sha256', $payload, $this->callback_secret);
        return hash_equals($expected, $signature);
    }
}
