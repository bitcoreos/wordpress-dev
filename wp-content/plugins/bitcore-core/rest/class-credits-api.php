<?php
/**
 * Credits REST API Controller.
 *
 * Unified REST surface for subscription quotas, daily usage, and x402 paid access.
 *
 * @package BITCORE\Rest
 * @since       1.0.0
 * @version     1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Rest;

if (!defined('ABSPATH')) {
    exit;
}

use BITCORE\Infrastructure\Credits_Manager;

class Credits_Api {
    private string $namespace = 'bitcore/v1';
    private Credits_Manager $credits;
    private bool $routes_registered = false;

    public function __construct(?Credits_Manager $credits = null) {
        if (!class_exists(Credits_Manager::class)) {
            $path = dirname(__DIR__) . '/infrastructure/class-credits.php';
            if (is_file($path)) {
                require_once $path;
            }
        }

        $this->credits = $credits ?? new Credits_Manager();
        $this->register_routes();
    }

    public function register_routes(): void {
        if ($this->routes_registered) {
            return;
        }
        $this->routes_registered = true;

        register_rest_route($this->namespace, '/credits/tiers', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_tiers'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route($this->namespace, '/credits/(?P<agent_id>[\\w-]+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_status'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);

        register_rest_route($this->namespace, '/credits/(?P<agent_id>[\\w-]+)/check', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'check'],
                'permission_callback' => [$this, 'check_permission_or_payment'],
                'args' => [
                    'amount' => ['type' => 'integer', 'default' => 1],
                    'resource_type' => ['type' => 'string', 'default' => 'api_calls'],
                ],
            ],
        ]);

        register_rest_route($this->namespace, '/credits/(?P<agent_id>[\\w-]+)/consume', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'consume'],
                'permission_callback' => [$this, 'check_permission_or_payment'],
                'args' => [
                    'amount' => ['type' => 'integer', 'default' => 1],
                    'resource_type' => ['type' => 'string', 'default' => 'api_calls'],
                    'operation' => ['type' => 'string', 'default' => ''],
                    'metadata' => ['type' => 'object', 'default' => []],
                ],
            ],
        ]);
    }

    public function get_tiers(\WP_REST_Request $request): \WP_REST_Response {
        return rest_ensure_response([
            'tiers' => [
                'free' => $this->credits->get_tier_limits('free'),
                'core' => $this->credits->get_tier_limits('core'),
                'pro' => $this->credits->get_tier_limits('pro'),
            ],
        ]);
    }

    public function get_status(\WP_REST_Request $request): \WP_REST_Response {
        $user_id = get_current_user_id();
        $agent_id = $this->agent_id($request);
        $resource_type = $this->resource_type($request);
        return rest_ensure_response($this->credits->get_status($user_id, $agent_id, $resource_type));
    }

    public function check(\WP_REST_Request $request): \WP_REST_Response {
        $payment = $this->payment_header($request);
        $paid = $this->credits->verify_x402_payment($payment);
        $decision = $this->credits->can_consume(
            get_current_user_id(),
            $this->agent_id($request),
            $this->amount($request),
            $this->resource_type($request),
            $paid
        );

        return $this->decision_response($decision + ['success' => (bool) ($decision['allowed'] ?? false)]);
    }

    public function consume(\WP_REST_Request $request): \WP_REST_Response {
        $payment = $this->payment_header($request);
        $paid = $this->credits->verify_x402_payment($payment);
        $user_id = get_current_user_id();
        $agent_id = $this->agent_id($request);
        $amount = $this->amount($request);
        $resource_type = $this->resource_type($request);
        $decision = $this->credits->can_consume($user_id, $agent_id, $amount, $resource_type, $paid);

        if (!($decision['allowed'] ?? false)) {
            return $this->decision_response($decision + ['success' => false]);
        }

        if (!empty($decision['paid'])) {
            return rest_ensure_response([
                'success' => true,
                'paid' => true,
                'reason' => $decision['reason'] ?? 'x402_paid',
                'status' => $this->credits->get_status($user_id, $agent_id, $resource_type),
            ]);
        }

        $metadata = $request->get_param('metadata');
        $success = $this->credits->consume(
            $user_id,
            $agent_id,
            $amount,
            $resource_type,
            sanitize_text_field((string) ($request->get_param('operation') ?? '')),
            is_array($metadata) ? $metadata : []
        );

        return rest_ensure_response([
            'success' => $success,
            'paid' => false,
            'reason' => $decision['reason'] ?? 'within_quota',
            'status' => $this->credits->get_status($user_id, $agent_id, $resource_type),
        ]);
    }

    public function check_permission(): bool {
        return is_user_logged_in();
    }

    public function check_permission_or_payment(\WP_REST_Request $request): bool {
        if (is_user_logged_in()) {
            return true;
        }
        return $this->payment_header($request) !== '';
    }

    /** @param array<string,mixed> $payload */
    private function decision_response(array $payload): \WP_REST_Response {
        $status = (int) ($payload['status'] ?? 200);
        unset($payload['status']);
        return new \WP_REST_Response($payload, $status);
    }

    private function agent_id(\WP_REST_Request $request): string {
        $agent_id = sanitize_text_field((string) ($request->get_param('agent_id') ?? 'default'));
        return $agent_id !== '' ? $agent_id : 'default';
    }

    private function resource_type(\WP_REST_Request $request): string {
        $resource_type = sanitize_text_field((string) ($request->get_param('resource_type') ?? 'api_calls'));
        return $resource_type !== '' ? $resource_type : 'api_calls';
    }

    private function amount(\WP_REST_Request $request): int {
        return max(1, absint($request->get_param('amount') ?? 1));
    }

    private function payment_header(\WP_REST_Request $request): string {
        return trim((string) $request->get_header('X-Payment'));
    }
}
