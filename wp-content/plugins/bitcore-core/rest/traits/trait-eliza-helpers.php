<?php
/**
 * ElizaOS Bridge REST API — UUID resolution, error normalization and configured-agent fallback.
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

trait BITCORE_Rest_Eliza_Helpers_Trait {

    /**
     * Normalize a nested ElizaOS error payload into a safe WP_Error message.
     *
     * @param mixed $raw_error Raw upstream error payload.
     * @return string Human-readable error message.
     */
    private function normalize_eliza_error_message(mixed $raw_error): string {
        if (is_array($raw_error)) {
            foreach (['message', 'error', 'details', 'code'] as $key) {
                if (isset($raw_error[$key]) && is_scalar($raw_error[$key]) && (string) $raw_error[$key] !== '') {
                    return (string) $raw_error[$key];
                }
            }
            return __('Unknown ElizaOS error');
        }

        if (is_scalar($raw_error) && (string) $raw_error !== '') {
            return (string) $raw_error;
        }

        return __('Unknown ElizaOS error');
    }

    /**
     * Build a resilient configured-agent response when ElizaOS agent index fails.
     *
     * ElizaOS 1.7.2 can return 500 from /api/agents when its agents table is
     * degraded even while messaging and health routes are available. The ASHI UI
     * still needs the configured agent identities, but must not fake live status.
     *
     * @param WP_Error $error Upstream proxy error.
     * @return WP_REST_Response
     */
    private function get_configured_agents_fallback(WP_Error $error): WP_REST_Response {
        $agent_map = $this->get_agent_map();
        $profiles = [
            'aria' => [
                'name'        => 'Aria',
                'emoji'       => '🧭',
                'style'       => 'Strategic coordinator',
                'description' => 'Default planning and orchestration agent.',
            ],
            'forge' => [
                'name'        => 'Forge',
                'emoji'       => '⚒️',
                'style'       => 'Builder and systems engineer',
                'description' => 'Implementation-focused engineering agent.',
            ],
            'muse' => [
                'name'        => 'Muse',
                'emoji'       => '🎨',
                'style'       => 'Creative synthesis',
                'description' => 'Research, narrative, and synthesis agent.',
            ],
        ];

        $agents = [];
        foreach ($agent_map as $slug => $id) {
            $profile = $profiles[$slug] ?? [];
            $agents[] = [
                'id'          => (string) $id,
                'slug'        => (string) $slug,
                'name'        => $profile['name'] ?? ucfirst((string) $slug),
                'emoji'       => $profile['emoji'] ?? '🤖',
                'style'       => $profile['style'] ?? 'Configured agent',
                'description' => $profile['description'] ?? 'Configured ElizaOS agent.',
                'status'      => 'unknown',
                'source'      => 'wordpress_config',
            ];
        }

        return new WP_REST_Response([
            'status'         => 'degraded',
            'source'         => 'wordpress_config',
            'agents'         => $agents,
            'upstream_error' => [
                'code'      => $error->get_error_code(),
                'message'   => $error->get_error_message(),
                'status'    => (int) ($error->get_error_data()['status'] ?? 502),
                'raw_error' => $error->get_error_data()['raw_error'] ?? null,
            ],
            'timestamp'      => current_time('iso'),
        ], 200);
    }

    /**
     * Get or create a stable UUID for a WordPress user.
     *
     * Stored in user meta as 'bitcore_eliza_uuid' to provide a consistent
     * author_id across all ElizaOS messaging requests.
     *
     * @param int $user_id WordPress user ID
     * @return string UUID v4
     */
    private function get_user_uuid(int $user_id): string {
        $uuid = get_user_meta($user_id, 'bitcore_eliza_uuid', true);
        if (empty($uuid) || !wp_is_uuid($uuid)) {
            $uuid = wp_generate_uuid4();
            update_user_meta($user_id, 'bitcore_eliza_uuid', $uuid);
        }
        return $uuid;
    }

    /**
     * Get a valid author UUID for the current request.
     *
     * Logged-in users receive a stable UUID persisted in user meta.
     * Anonymous x402 requests receive a deterministic UUID derived from the
     * payment header or channel ID without writing user meta for user ID 0.
     *
     * @param int    $user_id    WordPress user ID, or 0 for anonymous requests.
     * @param string $channel_id ElizaOS channel/session ID.
     * @return string UUID v4-compatible value.
     */
    private function get_request_author_uuid(int $user_id, string $channel_id): string {
        if ($user_id > 0) {
            return $this->get_user_uuid($user_id);
        }

        $source = $this->get_payment_header();
        if (empty($source)) {
            $source = $channel_id;
        }

        return $this->uuid_from_hash('anonymous-eliza-author:' . $source);
    }

    /**
     * Convert a source string into a deterministic UUID v4-compatible value.
     *
     * @param string $source Stable source material.
     * @return string UUID v4-compatible value.
     */
    private function uuid_from_hash(string $source): string {
        $hex = hash('sha256', $source);

        return sprintf(
            '%s-%s-4%s-%s%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 13, 3),
            dechex((hexdec($hex[16]) & 0x3) | 0x8),
            substr($hex, 17, 3),
            substr($hex, 20, 12)
        );
    }
}
