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
        return current_user_can('bitcore_delete_tasks');
    }
}
