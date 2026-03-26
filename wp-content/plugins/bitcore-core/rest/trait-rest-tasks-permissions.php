<?php
/**
 * REST API Controller for Tasks
 *
 * @package Bitcore\Tasks
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Bitcore\Tasks;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class REST_Tasks
 *
 * Handles REST API endpoints for task management with security hardening.
trait REST_Tasks_Permissions {
    public function get_items_permissions_check($request) {
        $nonce = $request->get_header('X-WP-Nonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new \WP_Error(
                'rest_forbidden',
                __('Invalid or missing nonce.', 'bitcore-tasks'),
                array('status' => 403)
            );
        }
        return current_user_can('bitcore_read_tasks');
    }

    /**
     * Permission check for getting single item
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request Full request object
     * @return bool|\WP_Error True if allowed, error otherwise
     */
    public function get_item_permissions_check($request) {
        $nonce = $request->get_header('X-WP-Nonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new \WP_Error(
                'rest_forbidden',
                __('Invalid or missing nonce.', 'bitcore-tasks'),
                array('status' => 403)
            );
        }
        return current_user_can('bitcore_read_tasks');
    }

    /**
     * Permission check for creating items
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request Full request object
     * @return bool|\WP_Error True if allowed, error otherwise
     */
    public function create_item_permissions_check($request) {
        $nonce = $request->get_header('X-WP-Nonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new \WP_Error(
                'rest_forbidden',
                __('Invalid or missing nonce.', 'bitcore-tasks'),
                array('status' => 403)
            );
        }
        return current_user_can('bitcore_create_tasks');
    }

    /**
     * Permission check for updating items
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request Full request object
     * @return bool|\WP_Error True if allowed, error otherwise
     */
    public function update_item_permissions_check($request) {
        $nonce = $request->get_header('X-WP-Nonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new \WP_Error(
                'rest_forbidden',
                __('Invalid or missing nonce.', 'bitcore-tasks'),
                array('status' => 403)
            );
        }
        return current_user_can('bitcore_edit_tasks');
    }

    /**
     * Permission check for deleting items
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request Full request object
     * @return bool|\WP_Error True if allowed, error otherwise
     */
    public function delete_item_permissions_check($request) {
        $nonce = $request->get_header('X-WP-Nonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new \WP_Error(
                'rest_forbidden',
                __('Invalid or missing nonce.', 'bitcore-tasks'),
                array('status' => 403)
            );
        }
        return current_user_can('bitcore_delete_tasks');
    }

    /**
     * Permission check for dispatching tasks
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request Full request object
     * @return bool|\WP_Error True if allowed, error otherwise
     */
    public function dispatch_item_permissions_check($request) {
        $nonce = $request->get_header('X-WP-Nonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new \WP_Error(
                'rest_forbidden',
                __('Invalid or missing nonce.', 'bitcore-tasks'),
                array('status' => 403)
            );
        }
        return current_user_can('bitcore_dispatch_tasks');
    }

    /**
     * Prepare item for REST response
     *
     * @since 1.0.0
     * @param \WP_Post          $post    Post object
     * @param \WP_REST_Request $request Full request object
     * @return \WP_REST_Response Response object
     */
}
