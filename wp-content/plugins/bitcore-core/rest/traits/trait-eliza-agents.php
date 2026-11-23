<?php
/**
 * ElizaOS Bridge REST API — Agent map resolution, activation, deactivation and health endpoints.
 *
 * @package    Bitcore
 * @since      2026.03.01
 * @version    2026.03.01
 * @author     coraline
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

trait BITCORE_Rest_Eliza_Agents_Trait {

    /**
     * GET /eliza/health — Public health check.
     */
    public function get_health(): WP_REST_Response {
        $result = $this->proxy_request('GET', '/server/health');

        if (is_wp_error($result)) {
            // Return degraded status instead of error for health endpoint
            return new WP_REST_Response([
                'status'     => 'degraded',
                'bridge'     => 'connected',
                'eliza'      => 'unreachable',
                'message'    => $result->get_error_message(),
                'timestamp'  => current_time('iso'),
            ], 200);
        }

        // Inject bridge metadata
        $data = $result->get_data();
        if (is_array($data)) {
            $data['bridge']    = 'connected';
            $data['timestamp'] = current_time('iso');
        }

        return new WP_REST_Response($data, 200);
    }

    /**
     * GET /eliza/agents — List available agents.
     */
    public function get_agents(): WP_REST_Response|WP_Error {
        $result = $this->proxy_request('GET', '/agents');

        if (!is_wp_error($result)) {
            return $result;
        }

        return $this->get_configured_agents_fallback($result);
    }


    /**
     * POST /eliza/agents/{id}/activate — Start or switch to an agent.
     *
     * Enforces single-active-agent policy: stops any currently active agent
     * before starting the requested one.
     */
    public function activate_agent(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $agent_id = sanitize_text_field($request['id']);

        if (empty($agent_id)) {
            return new WP_Error(
                'missing_agent_id',
                __('Agent ID is required.'),
                ['status' => 400]
            );
        }

        // Single-active-agent policy: stop current agent first
        $active_key = 'bitcore_eliza_active_agent';
        $user_id     = get_current_user_id();
        $current     = get_user_meta($user_id, $active_key, true);

        if (!empty($current) && $current !== $agent_id) {
            // Stop current agent before switching
            $this->proxy_request('POST', '/agents/' . sanitize_text_field($current) . '/stop');
        }

        // Start requested agent
        $result = $this->proxy_request('POST', '/agents/' . $agent_id . '/start');

        if (!is_wp_error($result)) {
            update_user_meta($user_id, $active_key, $agent_id);

            // Also update the option-stored default agent if this is the initial activation
            $default_agent = get_option('bitcore_eliza_default_agent', 'aria');
            $agent_map = $this->get_agent_map();
            if (isset($agent_map[$default_agent]) && $agent_map[$default_agent] === $agent_id) {
                update_user_meta($user_id, 'bitcore_eliza_active_agent_name', $default_agent);
            }
        }

        return $result;
    }

    /**
     * POST /eliza/agents/{id}/deactivate — Stop an agent.
     */
    public function deactivate_agent(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $agent_id = sanitize_text_field($request['id']);

        if (empty($agent_id)) {
            return new WP_Error(
                'missing_agent_id',
                __('Agent ID is required.'),
                ['status' => 400]
            );
        }

        $result = $this->proxy_request('POST', '/agents/' . $agent_id . '/stop');

        if (!is_wp_error($result)) {
            // Clear active agent tracking if this was the active agent
            $user_id    = get_current_user_id();
            $active_key = 'bitcore_eliza_active_agent';
            $current    = get_user_meta($user_id, $active_key, true);
            if ($current === $agent_id) {
                delete_user_meta($user_id, $active_key);
                delete_user_meta($user_id, 'bitcore_eliza_active_agent_name');
            }
        }

        return $result;
    }

    /**
     * Get agent name-to-ID mapping from WordPress options.
     *
     * @return array<string, string> Agent name => UUID mapping
     */
    private function get_agent_map(): array {
        return [
            'aria'  => get_option('bitcore_eliza_agent_aria', 'eac677dd-94c4-02ab-bfea-bc554e52b2a3'),
            'forge' => get_option('bitcore_eliza_agent_forge', '3ab11b75-ca36-0cee-a3d7-7bfcab3c27cd'),
            'muse'  => get_option('bitcore_eliza_agent_muse', 'cf0dee2b-9fc6-041b-83a0-90a644d26362'),
        ];
    }

    /**
     * Resolve the target ElizaOS agent UUID for a chat message.
     *
     * Precedence:
     * 1. Explicit request agentId, accepting either UUID or configured slug.
     * 2. Logged-in user's active agent usermeta, accepting UUID or configured slug.
     * 3. Configured Aria UUID as the safe default.
     *
     * @param string $requested_agent_id Raw request agentId value.
     * @param int    $user_id            WordPress user ID, or 0 for anonymous.
     * @return string Agent UUID or empty string if no valid configured value exists.
     */
    private function resolve_message_agent_id(string $requested_agent_id, int $user_id): string {
        $agent_map = $this->get_agent_map();

        $candidate = trim($requested_agent_id);
        if ($candidate === '' && $user_id > 0) {
            $candidate = trim((string) get_user_meta($user_id, 'bitcore_eliza_active_agent', true));
        }
        if ($candidate === '') {
            $candidate = (string) ($agent_map['aria'] ?? '');
        }

        $slug = strtolower($candidate);
        if (isset($agent_map[$slug])) {
            $candidate = (string) $agent_map[$slug];
        }

        return $this->is_eliza_agent_uuid($candidate) ? $candidate : '';
    }

    /**
     * Check ElizaOS agent IDs using UUID-shaped validation.
     *
     * ElizaOS 1.7.2 agent IDs are UUID-shaped but are not guaranteed to be
     * RFC v4 UUIDs, so WordPress wp_is_uuid() is too strict for configured
     * agent IDs such as eac677dd-94c4-02ab-bfea-bc554e52b2a3.
     *
     * @param string $value Candidate agent ID.
     * @return bool
     */
    private function is_eliza_agent_uuid(string $value): bool {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $value
        );
    }


}
