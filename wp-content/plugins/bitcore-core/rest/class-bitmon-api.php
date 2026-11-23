<?php
/**
 * BITmon Arena REST API Controller.
 *
 * Stores public benchmark records in WordPress and proxies safe read/control
 * operations to the internal BITmon service. External automation clients use
 * webhook and heartbeat endpoints as the bidirectional game pulse.
 *
 * @package BITCORE\Rest
 * @since   2026.03.01
 * @version 1.3.0
 * @author  coraline
 */

declare(strict_types=1);

namespace BITCORE\Rest;

if (!defined('ABSPATH')) {
    exit;
}

class Bitmon_Api {
    private string $namespace = 'bitcore/v1';
    private bool $routes_registered = false;

    public function __construct() {
        $this->register_routes();
    }

    public function register_routes(): void {
        if ($this->routes_registered) {
            return;
        }
        $this->routes_registered = true;

        register_rest_route($this->namespace, '/bitmon/health', [[
            'methods' => 'GET',
            'callback' => [$this, 'health'],
            'permission_callback' => '__return_true',
        ]]);
        register_rest_route($this->namespace, '/bitmon/leaderboards', [[
            'methods' => 'GET',
            'callback' => [$this, 'leaderboards'],
            'permission_callback' => '__return_true',
        ]]);
        register_rest_route($this->namespace, '/bitmon/leaderboards/(?P<format>[a-zA-Z0-9_-]+)', [[
            'methods' => 'GET',
            'callback' => [$this, 'leaderboard'],
            'permission_callback' => '__return_true',
        ]]);
        register_rest_route($this->namespace, '/bitmon/battles', [[
            'methods' => 'GET',
            'callback' => [$this, 'battles'],
            'permission_callback' => '__return_true',
        ]]);
        register_rest_route($this->namespace, '/bitmon/battles/(?P<id>[a-zA-Z0-9_-]+)', [[
            'methods' => 'GET',
            'callback' => [$this, 'battle'],
            'permission_callback' => '__return_true',
        ]]);
        register_rest_route($this->namespace, '/bitmon/tournaments', [[
            'methods' => 'GET',
            'callback' => [$this, 'tournaments'],
            'permission_callback' => '__return_true',
        ]]);
        register_rest_route($this->namespace, '/bitmon/tournaments/(?P<id>[a-zA-Z0-9_-]+)', [[
            'methods' => 'GET',
            'callback' => [$this, 'tournament'],
            'permission_callback' => '__return_true',
        ]]);
        register_rest_route($this->namespace, '/bitmon/agents', [[
            'methods' => 'GET',
            'callback' => [$this, 'agents'],
            'permission_callback' => '__return_true',
        ]]);
        register_rest_route($this->namespace, '/bitmon/webhook', [[
            'methods' => 'POST',
            'callback' => [$this, 'ingest_webhook'],
            'permission_callback' => [$this, 'check_signed_request'],
        ]]);
        register_rest_route($this->namespace, '/bitmon/heartbeat', [[
            'methods' => 'POST',
            'callback' => [$this, 'heartbeat'],
            'permission_callback' => [$this, 'check_signed_request'],
        ]]);
        register_rest_route($this->namespace, '/bitmon/rooms', [[
            'methods' => 'POST',
            'callback' => [$this, 'create_room'],
            'permission_callback' => [$this, 'check_control_permission'],
        ]]);
        register_rest_route($this->namespace, '/bitmon/rooms/(?P<id>[a-zA-Z0-9_-]+)/join', [[
            'methods' => 'POST',
            'callback' => [$this, 'join_room'],
            'permission_callback' => [$this, 'check_control_permission'],
        ]]);
        register_rest_route($this->namespace, '/bitmon/rooms/(?P<id>[a-zA-Z0-9_-]+)/action', [[
            'methods' => 'POST',
            'callback' => [$this, 'submit_action'],
            'permission_callback' => [$this, 'check_control_permission'],
        ]]);
    }

    public function health(\WP_REST_Request $request): \WP_REST_Response {
        $proxy = $this->proxy('GET', '/health');
        $counts = [];
        foreach (['bitmon_battle', 'bitmon_tournament', 'bitmon_agent_profile', 'bitmon_result'] as $type) {
            $obj = wp_count_posts($type);
            $counts[$type] = $obj && isset($obj->publish) ? (int) $obj->publish : 0;
        }
        return rest_ensure_response([
            'success' => true,
            'service_available' => $proxy['ok'],
            'service' => $this->public_service_payload($proxy['body']),
            'last_heartbeat' => $this->public_heartbeat_payload(get_option('bitcore_bitmon_last_heartbeat', [])),
            'wordpress_counts' => $counts,
        ]);
    }

    public function leaderboards(\WP_REST_Request $request): \WP_REST_Response {
        $proxy = $this->proxy('GET', '/leaderboards');
        if ($proxy['ok']) {
            return rest_ensure_response($proxy['body']);
        }
        return rest_ensure_response(['leaderboards' => $this->leaderboard_from_posts('')]);
    }

    public function leaderboard(\WP_REST_Request $request): \WP_REST_Response {
        $format = sanitize_key((string) $request->get_param('format'));
        $proxy = $this->proxy('GET', '/leaderboards/' . rawurlencode($format));
        if ($proxy['ok']) {
            return rest_ensure_response($proxy['body']);
        }
        return rest_ensure_response(['format' => $format, 'entries' => $this->leaderboard_from_posts($format)]);
    }

    public function battles(\WP_REST_Request $request): \WP_REST_Response {
        return rest_ensure_response(['battles' => $this->posts('bitmon_battle')]);
    }

    public function battle(\WP_REST_Request $request): \WP_REST_Response {
        return $this->single('bitmon_battle', sanitize_text_field((string) $request->get_param('id')));
    }

    public function tournaments(\WP_REST_Request $request): \WP_REST_Response {
        return rest_ensure_response(['tournaments' => $this->posts('bitmon_tournament')]);
    }

    public function tournament(\WP_REST_Request $request): \WP_REST_Response {
        return $this->single('bitmon_tournament', sanitize_text_field((string) $request->get_param('id')));
    }

    public function agents(\WP_REST_Request $request): \WP_REST_Response {
        return rest_ensure_response(['agents' => $this->posts('bitmon_agent_profile')]);
    }

    public function ingest_webhook(\WP_REST_Request $request): \WP_REST_Response {
        $payload = $this->payload($request);
        $event = $this->normalize_event((string) ($payload['event'] ?? $payload['type'] ?? 'bitmon.event.received'));
        $payload['event'] = $event;
        $event_hash = hash('sha256', $request->get_body());

        if ($this->event_exists($event_hash)) {
            return rest_ensure_response(['success' => true, 'duplicate' => true, 'event_hash' => $event_hash]);
        }

        $this->store_event($event, $payload, $event_hash, 'webhook');
        $this->upsert_domain_record($payload, $event);

        return rest_ensure_response([
            'success' => true,
            'event' => $event,
            'event_hash' => $event_hash,
            'received_at' => current_time('mysql'),
        ]);
    }

    public function heartbeat(\WP_REST_Request $request): \WP_REST_Response {
        $payload = $this->payload($request);
        $payload['event'] = $this->normalize_event((string) ($payload['event'] ?? 'bitmon.heartbeat.received'));
        $event_hash = hash('sha256', $request->get_body() . '|heartbeat');
        update_option('bitcore_bitmon_last_heartbeat', [
            'event' => $payload['event'],
            'event_hash' => $event_hash,
            'received_at' => current_time('mysql'),
            'payload' => $payload,
        ], false);
        $this->prune_heartbeat_events();
        if ((bool) get_option('bitcore_bitmon_store_heartbeat_events', false) && !$this->event_exists($event_hash)) {
            $this->store_event($payload['event'], $payload, $event_hash, 'heartbeat');
            $this->prune_heartbeat_events();
        }
        return rest_ensure_response([
            'success' => true,
            'ack' => 'heartbeat_received',
            'event_hash' => $event_hash,
            'received_at' => current_time('mysql'),
        ]);
    }

    public function create_room(\WP_REST_Request $request): \WP_REST_Response {
        return $this->proxy_response('POST', '/rooms', $this->payload($request));
    }

    public function join_room(\WP_REST_Request $request): \WP_REST_Response {
        return $this->proxy_response('POST', '/rooms/' . rawurlencode((string) $request->get_param('id')) . '/join', $this->payload($request));
    }

    public function submit_action(\WP_REST_Request $request): \WP_REST_Response {
        return $this->proxy_response('POST', '/rooms/' . rawurlencode((string) $request->get_param('id')) . '/action', $this->payload($request));
    }

    public function check_control_permission(\WP_REST_Request $request): bool {
        if (is_user_logged_in() && current_user_can('read')) {
            return true;
        }
        $expected = (string) get_option('bitcore_bitmon_control_key', '');
        $provided = (string) $request->get_header('x-api-key');
        return $expected !== '' && hash_equals($expected, $provided);
    }

    public function check_signed_request(\WP_REST_Request $request): bool|\WP_Error {
        $secret = (string) get_option('bitcore_bitmon_webhook_secret', '');
        if ($secret === '') {
            return new \WP_Error('bitmon_secret_missing', 'BITmon webhook secret is not configured.', ['status' => 403]);
        }
        $signature = trim((string) $request->get_header('x-bitmon-signature'));
        if ($signature === '') {
            return new \WP_Error('bitmon_signature_missing', 'Missing BITmon signature.', ['status' => 403]);
        }
        $timestamp = trim((string) $request->get_header('x-bitmon-timestamp'));
        if ($timestamp === '' || !ctype_digit($timestamp)) {
            return new \WP_Error('bitmon_timestamp_missing', 'Missing BITmon timestamp.', ['status' => 403]);
        }
        $skew = abs(time() - (int) $timestamp);
        if ($skew > 300) {
            return new \WP_Error('bitmon_signature_expired', 'Expired BITmon signature.', ['status' => 403]);
        }
        $signed_body = $timestamp . '.' . $request->get_body();
        $expected = 'sha256=' . hash_hmac('sha256', $signed_body, $secret);
        if (!hash_equals($expected, $signature) && !hash_equals(substr($expected, 7), $signature)) {
            return new \WP_Error('bitmon_signature_invalid', 'Invalid BITmon signature.', ['status' => 403]);
        }
        return true;
    }


    private function public_service_payload(array $payload): array {
        return [
            'ok' => (bool) ($payload['ok'] ?? false),
            'service' => sanitize_text_field((string) ($payload['service'] ?? 'bitmon-brawler')),
            'version' => sanitize_text_field((string) ($payload['version'] ?? '')),
        ];
    }

    private function public_heartbeat_payload(mixed $heartbeat): array {
        if (!is_array($heartbeat)) {
            return [];
        }
        $payload = isset($heartbeat['payload']) && is_array($heartbeat['payload']) ? $heartbeat['payload'] : [];
        $allowed_payload_keys = [
            'event', 'type', 'timestamp', 'service', 'status', 'phase', 'format',
            'room_id', 'roomId', 'battle_id', 'battleId', 'tournament_id', 'tournamentId',
            'match_id', 'matchId', 'round', 'turn', 'winner', 'p1', 'p2', 'players', 'agents',
        ];
        $safe_payload = [];
        foreach ($allowed_payload_keys as $key) {
            if (array_key_exists($key, $payload)) {
                $safe_payload[$key] = $this->sanitize_public_value($payload[$key]);
            }
        }
        return [
            'event' => sanitize_text_field((string) ($heartbeat['event'] ?? '')),
            'event_hash' => sanitize_text_field((string) ($heartbeat['event_hash'] ?? '')),
            'received_at' => sanitize_text_field((string) ($heartbeat['received_at'] ?? '')),
            'payload' => $safe_payload,
        ];
    }

    private function sanitize_public_value(mixed $value): mixed {
        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            return $value;
        }
        if (is_string($value)) {
            return sanitize_text_field($value);
        }
        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                if (is_string($key) && preg_match('/host|port|path|dir|url|ip|pid|secret|token|key/i', $key)) {
                    continue;
                }
                $out[is_string($key) ? sanitize_key($key) : $key] = $this->sanitize_public_value($item);
            }
            return $out;
        }
        return sanitize_text_field((string) $value);
    }

    private function prune_heartbeat_events(): void {
        $max = max(0, (int) get_option('bitcore_bitmon_heartbeat_event_keep', 25));
        $q = new \WP_Query([
            'post_type' => 'bitmon_result',
            'post_status' => 'publish',
            'meta_key' => '_bitmon_source',
            'meta_value' => 'heartbeat',
            'fields' => 'ids',
            'posts_per_page' => 500,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);
        $ids = array_map('intval', $q->posts);
        foreach (array_slice($ids, $max) as $id) {
            wp_delete_post($id, true);
        }
    }

    private function proxy_response(string $method, string $path, array $payload = []): \WP_REST_Response {
        $result = $this->proxy($method, $path, $payload);
        return new \WP_REST_Response($result['body'], $result['status']);
    }

    private function proxy(string $method, string $path, array $payload = []): array {
        $url = $this->base_url(true) . $path;
        $args = ['timeout' => 8, 'headers' => ['Accept' => 'application/json']];
        if ($method === 'POST') {
            $args['headers']['Content-Type'] = 'application/json';
            $args['body'] = wp_json_encode($payload);
            $response = wp_remote_post($url, $args);
        } else {
            $response = wp_remote_get($url, $args);
        }
        if (is_wp_error($response)) {
            return ['ok' => false, 'status' => 502, 'body' => ['success' => false, 'error' => $response->get_error_message()]];
        }
        $status = (int) wp_remote_retrieve_response_code($response);
        $raw = (string) wp_remote_retrieve_body($response);
        $body = json_decode($raw, true);
        return ['ok' => $status >= 200 && $status < 300, 'status' => $status ?: 502, 'body' => is_array($body) ? $body : ['raw' => $raw]];
    }

    private function base_url(bool $with_v1): string {
        $url = esc_url_raw((string) get_option('bitcore_bitmon_base_url', 'http://127.0.0.1:3100/v1'));
        $url = rtrim($url, '/');
        if ($with_v1 || !str_ends_with($url, '/v1')) {
            return $url;
        }
        return preg_replace('#/v1$#', '', $url) ?: $url;
    }

    private function payload(\WP_REST_Request $request): array {
        $params = $request->get_json_params();
        return is_array($params) ? $this->clean($params) : [];
    }

    private function clean(array $value): array {
        $out = [];
        foreach ($value as $key => $item) {
            $key = sanitize_key((string) $key);
            if (is_array($item)) {
                $out[$key] = $this->clean($item);
            } elseif (is_scalar($item) || $item === null) {
                $out[$key] = sanitize_text_field((string) $item);
            }
        }
        return $out;
    }

    private function normalize_event(string $event): string {
        $event = sanitize_text_field($event) ?: 'event.received';
        return str_starts_with($event, 'bitmon.') ? $event : 'bitmon.' . $event;
    }

    private function event_exists(string $hash): bool {
        $q = new \WP_Query(['post_type' => 'bitmon_result', 'meta_key' => '_bitmon_event_hash', 'meta_value' => $hash, 'fields' => 'ids', 'posts_per_page' => 1]);
        return $q->have_posts();
    }

    private function store_event(string $event, array $payload, string $hash, string $source): int {
        $post_id = wp_insert_post([
            'post_type' => 'bitmon_result',
            'post_status' => 'publish',
            'post_title' => $event . ' — ' . current_time('mysql'),
            'post_content' => wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ], true);
        if (!is_wp_error($post_id)) {
            update_post_meta($post_id, '_bitmon_event', $event);
            update_post_meta($post_id, '_bitmon_event_hash', $hash);
            update_post_meta($post_id, '_bitmon_source', $source);
            update_post_meta($post_id, '_bitmon_payload', $payload);
        }
        return is_wp_error($post_id) ? 0 : (int) $post_id;
    }

    private function upsert_domain_record(array $payload, string $event): void {
        $type = str_contains($event, 'tournament') ? 'bitmon_tournament' : (str_contains($event, 'agent') ? 'bitmon_agent_profile' : 'bitmon_battle');
        $external_id = sanitize_text_field((string) ($payload['battle_id'] ?? $payload['room_id'] ?? $payload['tournament_id'] ?? $payload['agent_id'] ?? ''));
        if ($external_id === '') {
            return;
        }
        $existing = new \WP_Query(['post_type' => $type, 'meta_key' => '_bitmon_external_id', 'meta_value' => $external_id, 'fields' => 'ids', 'posts_per_page' => 1]);
        $title = strtoupper(str_replace('_', ' ', $type)) . ' ' . $external_id;
        $post_id = $existing->have_posts() ? (int) $existing->posts[0] : (int) wp_insert_post(['post_type' => $type, 'post_status' => 'publish', 'post_title' => $title]);
        if ($post_id > 0) {
            update_post_meta($post_id, '_bitmon_external_id', $external_id);
            update_post_meta($post_id, '_bitmon_last_event', $event);
            update_post_meta($post_id, '_bitmon_payload', $payload);
            update_post_meta($post_id, '_bitmon_updated_at', current_time('mysql'));
        }
    }

    private function posts(string $type): array {
        $q = new \WP_Query(['post_type' => $type, 'post_status' => 'publish', 'posts_per_page' => 50, 'orderby' => 'date', 'order' => 'DESC']);
        return array_map(fn($id) => $this->post_payload((int) $id), $q->posts);
    }

    private function single(string $type, string $external_id): \WP_REST_Response {
        $q = new \WP_Query(['post_type' => $type, 'meta_key' => '_bitmon_external_id', 'meta_value' => $external_id, 'posts_per_page' => 1]);
        if (!$q->have_posts()) {
            return new \WP_REST_Response(['success' => false, 'error' => 'not_found'], 404);
        }
        return rest_ensure_response($this->post_payload((int) $q->posts[0]->ID));
    }

    private function post_payload(int $post_id): array {
        return ['id' => $post_id, 'title' => get_the_title($post_id), 'external_id' => get_post_meta($post_id, '_bitmon_external_id', true), 'last_event' => get_post_meta($post_id, '_bitmon_last_event', true), 'payload' => get_post_meta($post_id, '_bitmon_payload', true), 'updated_at' => get_post_meta($post_id, '_bitmon_updated_at', true)];
    }

    private function leaderboard_from_posts(string $format): array {
        return [];
    }
}
