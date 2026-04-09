<?php
/**
 * Checkout REST API Controller
 *
 * REST endpoints for exclusive task checkout system.
 *
 * @package BITCORE\Rest
 */

namespace BITCORE\Rest;

if (!defined('ABSPATH')) exit;

use BITCORE\Infrastructure\Coordination\CheckoutLock;

/**
 * Class Checkout_Api
 */
class Checkout_Api {
    /** @var string Route namespace */
    private string $namespace = 'bitcore/v1';

    /** @var CheckoutLock */
    private CheckoutLock $checkout_lock;

    /**
     * Constructor
     */
    public function __construct() {
        $this->checkout_lock = new CheckoutLock();
    }

    /**
     * Register routes
     */
    public function register_routes(): void {
        register_rest_route($this->namespace, '/checkout', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'checkout_issue'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'issue_id' => ['type' => 'integer', 'required' => true],
                    'agent_id' => ['type' => 'string', 'required' => true],
                    'ttl' => ['type' => 'integer', 'default' => 3600]
                ]
            ]
        ]);

        register_rest_route($this->namespace, '/checkout/(?P<issue_id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_status'],
                'permission_callback' => '__return_true'
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'release_checkout'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'agent_id' => ['type' => 'string', 'required' => true]
                ]
            ]
        ]);

        register_rest_route($this->namespace, '/checkout/(?P<issue_id>\d+)/complete', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'complete_checkout'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'agent_id' => ['type' => 'string', 'required' => true]
                ]
            ]
        ]);

        register_rest_route($this->namespace, '/checkout/(?P<issue_id>\d+)/extend', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'extend_checkout'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'agent_id' => ['type' => 'string', 'required' => true],
                    'additional_seconds' => ['type' => 'integer', 'required' => true]
                ]
            ]
        ]);
    }

    /**
     * Checkout an issue
     */
    public function checkout_issue(\WP_REST_Request $request): \WP_REST_Response {
        $result = $this->checkout_lock->checkout(
            $request->get_param('issue_id'),
            $request->get_param('agent_id'),
            null,
            $request->get_param('ttl')
        );

        return rest_ensure_response($result);
    }

    /**
     * Get checkout status
     */
    public function get_status(\WP_REST_Request $request): \WP_REST_Response {
        $status = $this->checkout_lock->get_status($request->get_param('issue_id'));
        return rest_ensure_response($status);
    }

    /**
     * Release a checkout
     */
    public function release_checkout(\WP_REST_Request $request): \WP_REST_Response {
        $success = $this->checkout_lock->release(
            $request->get_param('issue_id'),
            $request->get_param('agent_id')
        );

        return rest_ensure_response(['success' => $success]);
    }

    /**
     * Complete a checkout
     */
    public function complete_checkout(\WP_REST_Request $request): \WP_REST_Response {
        $success = $this->checkout_lock->complete(
            $request->get_param('issue_id'),
            $request->get_param('agent_id')
        );

        return rest_ensure_response(['success' => $success]);
    }

    /**
     * Extend a checkout
     */
    public function extend_checkout(\WP_REST_Request $request): \WP_REST_Response {
        $success = $this->checkout_lock->extend(
            $request->get_param('issue_id'),
            $request->get_param('agent_id'),
            $request->get_param('additional_seconds')
        );

        return rest_ensure_response(['success' => $success]);
    }

    /**
     * Permission check
     */
    public function check_permission(): bool {
        return current_user_can('edit_posts');
    }
}
