<?php
/**
 * ElizaOS Bridge REST API — Eliza sessions, messages, chat send and upstream proxy transport.
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

trait BITCORE_Rest_Eliza_Sessions_Trait {

    /**
     * Proxy a request to ElizaOS.
     *
     * @param string $method HTTP method (GET, POST, DELETE)
     * @param string $path   API path (e.g. '/server/health')
     * @param array  $body   Optional JSON body to send
     * @return WP_REST_Response|WP_Error
     */
    private function proxy_request(string $method, string $path, ?array $body = null) {
        $url     = $this->eliza_base_url . $path;
        $user_id = get_current_user_id();

        $args = [
            'method'  => $method,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Entity-Id'  => (string) $user_id,
            ],
            'timeout' => $this->request_timeout,
        ];

        if ($this->eliza_api_key !== '') {
            $args['headers']['X-API-KEY'] = $this->eliza_api_key;
        }

        // Pass X-Payment header through to ElizaOS if present
        $payment = $this->get_payment_header();
        if (!empty($payment)) {
            $args['headers']['X-Payment'] = $payment;
        }

        if ($body !== null) {
            $args['body'] = wp_json_encode($body);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return new WP_Error(
                'eliza_connection_error',
                sprintf(__('ElizaOS connection error: %s'), $response->get_error_message()),
                ['status' => 502]
            );
        }

        $code = wp_remote_retrieve_response_code($response);
        $data = wp_remote_retrieve_body($response);

        // Connection refused or timeout
        if ($code === 0) {
            return new WP_Error(
                'eliza_unavailable',
                __('ElizaOS server is unavailable. Please try again later.'),
                ['status' => 503]
            );
        }

        $decoded = json_decode($data, true);

        // Pass through ElizaOS errors gracefully. ElizaOS 1.7.x may return
        // nested error objects; WP_Error requires a scalar message string.
        if ($code >= 400) {
            $raw_error = is_array($decoded) ? ($decoded['error'] ?? ($decoded['message'] ?? null)) : null;
            $error_message = $this->normalize_eliza_error_message($raw_error);

            return new WP_Error(
                'eliza_error',
                $error_message,
                [
                    'status'    => $code,
                    'raw_error' => $raw_error,
                ]
            );
        }

        return new WP_REST_Response(
            is_array($decoded) ? $decoded : ['raw' => $data],
            $code
        );
    }

    // =========================================================================
    // Endpoint Callbacks
    // =========================================================================

    /**
     * POST /eliza/chat — Send a message to an ElizaOS session.
     *
     * Uses the documented ElizaOS Sessions API for user-to-agent chat:
     *   1. POST /api/messaging/sessions when the client has no sessionId.
     *   2. POST /api/messaging/sessions/{sessionId}/messages for message delivery.
     *
     * This avoids the lower-level channel endpoint, which requires pre-existing
     * server/channel/participant state and was returning vague 400 errors in
     * production.
     */
    public function send_message(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $params     = $request->get_json_params();
        $message    = wp_kses_post($params['message'] ?? '');
        $session_id = isset($params['sessionId']) ? sanitize_text_field($params['sessionId']) : '';
        $agent_id   = isset($params['agentId']) ? sanitize_text_field($params['agentId']) : '';

        if (empty($message)) {
            return new WP_Error(
                'no_message',
                __('Message is required.'),
                ['status' => 400]
            );
        }

        $user_id         = get_current_user_id();
        $target_agent_id = $this->resolve_message_agent_id($agent_id, $user_id);
        if (empty($target_agent_id)) {
            return new WP_Error(
                'invalid_agent_id',
                __('A valid ElizaOS agent ID is required.'),
                ['status' => 400]
            );
        }
        $author_id       = $this->get_request_author_uuid($user_id, $session_id ?: $target_agent_id);

        if (empty($session_id)) {
            $session_result = $this->create_eliza_session($target_agent_id, $author_id, $user_id);
            if (is_wp_error($session_result)) {
                return $session_result;
            }

            $session_data = $session_result->get_data();
            $session_id   = sanitize_text_field($session_data['sessionId'] ?? ($session_data['id'] ?? ''));

            if (empty($session_id)) {
                return new WP_Error(
                    'eliza_session_error',
                    __('ElizaOS did not return a session ID.'),
                    ['status' => 502]
                );
            }
        }

        $body = [
            'content'  => $message,
            'metadata' => [
                'platform'      => 'ashi',
                'source_type'   => 'user_message',
                'author_id'     => $author_id,
                'targetAgentId' => $target_agent_id,
                'raw_message'   => ['text' => $message],
            ],
        ];

        $result = $this->proxy_request('POST', '/messaging/sessions/' . $session_id . '/messages', $body);

        if (!is_wp_error($result)) {
            $data = $result->get_data();
            if (is_array($data)) {
                $data['session_id']     = $session_id;
                $data['channel_id']     = $session_id; // Backward-compatible frontend key.
                $data['agent_id']       = $target_agent_id;
                $data['response_type']  = 'acknowledgement';
                $data['reply_pending']  = true;
                $data['submitted_only'] = true;
            }
            return new WP_REST_Response($data, $result->get_status());
        }

        return $result;
    }

    /**
     * GET /eliza/sessions — List user's chat sessions.
     */
    public function get_sessions(): WP_REST_Response|WP_Error {
        return $this->proxy_request('GET', '/messaging/sessions');
    }

    /**
     * POST /eliza/sessions — Create a new chat session.
     */
    public function create_session(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $params          = $request->get_json_params();
        $requested_agent = isset($params['agentId']) ? sanitize_text_field($params['agentId']) : '';
        $user_id         = get_current_user_id();
        $target_agent_id = $this->resolve_message_agent_id($requested_agent, $user_id);
        if (empty($target_agent_id)) {
            return new WP_Error(
                'invalid_agent_id',
                __('A valid ElizaOS agent ID is required.'),
                ['status' => 400]
            );
        }
        $author_id       = $this->get_request_author_uuid($user_id, $target_agent_id);

        return $this->create_eliza_session($target_agent_id, $author_id, $user_id);
    }

    /**
     * GET /eliza/sessions/{id}/messages — Fetch session messages.
     *
     * ElizaOS Sessions API message submission returns a user-message
     * acknowledgement. Actual assistant replies are fetched from session
     * messages or real-time events.
     */
    public function get_session_messages(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $session_id = sanitize_text_field($request['id']);

        if (empty($session_id)) {
            return new WP_Error(
                'missing_session_id',
                __('Session ID is required.'),
                ['status' => 400]
            );
        }

        $limit = isset($request['limit']) ? absint($request['limit']) : 20;
        $query = [
            'limit' => max(1, min($limit, 100)),
        ];

        foreach (['after', 'before'] as $cursor) {
            if (isset($request[$cursor]) && (int) $request[$cursor] > 0) {
                $query[$cursor] = (int) $request[$cursor];
            }
        }

        $path = '/messaging/sessions/' . rawurlencode($session_id) . '/messages?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        return $this->proxy_request('GET', $path);
    }

    /**
     * Create an ElizaOS session using the documented required fields.
     */
    private function create_eliza_session(string $agent_id, string $author_id, int $wp_user_id): WP_REST_Response|WP_Error {
        $body = [
            'agentId' => $agent_id,
            'userId'  => $author_id,
            'metadata' => [
                'platform'      => 'ashi',
                'wp_user_id'    => $wp_user_id,
                'targetAgentId' => $agent_id,
            ],
            'timeoutConfig' => [
                'timeoutMinutes'          => 60,
                'autoRenew'               => true,
                'maxDurationMinutes'      => 720,
                'warningThresholdMinutes' => 10,
            ],
        ];

        return $this->proxy_request('POST', '/messaging/sessions', $body);
    }

    /**
     * DELETE /eliza/sessions/{id} — Delete a chat session.
     */
    public function delete_session(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $session_id = sanitize_text_field($request['id']);

        if (empty($session_id)) {
            return new WP_Error(
                'missing_session_id',
                __('Session ID is required.'),
                ['status' => 400]
            );
        }

        return $this->proxy_request('DELETE', '/messaging/sessions/' . $session_id);
    }

}
