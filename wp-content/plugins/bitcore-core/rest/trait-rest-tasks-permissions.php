<?php
/**
 * REST API Tasks Permissions Trait
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
 * Trait REST_Tasks_Permissions
 * 
 * Permission checks for task REST API endpoints.
 */
trait REST_Tasks_Permissions {
    
    /**
     * Permission check for getting items
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request Full request object
     * @return bool|\WP_Error True if allowed, error otherwise
     */
    public function get_items_permissions_check($request) {
        $nonce = $request->get_header('X-WP-Nonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new \WP_Error(
                'rest_forbidden',
                __('Invalid or missing nonce.', 'bitcore-tasks'),
                array('status' => 403)
            );
        }
        return current_user_can('read');
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
        return current_user_can('read');
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
        return current_user_can('edit_posts');
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
        return current_user_can('edit_posts');
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
        return current_user_can('delete_posts');
    }
}
