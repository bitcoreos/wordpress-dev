<?php
/**
 * Plugin Integration Functions
 *
 * Handles activation, deactivation, and script integration hooks.
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
 * Plugin activation hook handler
 *
 * Registers custom capabilities on plugin activation.
 *
 * @since 1.0.0
 * @return void
 */
function activate_plugin(): void {
    // Register custom capabilities for roles
    Capabilities::register();

    // Flush rewrite rules for custom post types
    flush_rewrite_rules();

    /**
     * Fires after plugin activation
     *
     * @since 1.0.0
     */
    do_action('bitcore_tasks_activated');
}

/**
 * Plugin deactivation hook handler
 *
 * Cleans up capabilities on plugin deactivation.
 *
 * @since 1.0.0
 * @return void
 */
function deactivate_plugin(): void {
    // Unregister custom capabilities
    Capabilities::unregister();

    // Flush rewrite rules
    flush_rewrite_rules();

    /**
     * Fires after plugin deactivation
     *
     * @since 1.0.0
     */
    do_action('bitcore_tasks_deactivated');
}

/**
 * Enqueue REST API nonce for frontend scripts
 *
 * Makes nonce available for authenticated requests.
 *
 * @since 1.0.0
 * @return void
 */
function enqueue_rest_nonce(): void {
    $script_handle = 'bitcore-tasks-nonce';

    wp_register_script(
        $script_handle,
        '',
        array(),
        '1.0.0',
        false
    );

    wp_enqueue_script($script_handle);

    // Localize script with nonce and API URL
    wp_localize_script(
        $script_handle,
        'bitcoreTasks',
        array(
            'restNonce' => wp_create_nonce('wp_rest'),
            'restUrl'   => esc_url_raw(rest_url('bitcore/v1')),
        )
    );
}

/**
 * Admin initialization hook
 *
 * Sets up admin-specific functionality.
 *
 * @since 1.0.0
 * @return void
 */
function admin_init(): void {
    // Check user capabilities for admin access
    if (!current_user_can('bitcore_manage_tasks')) {
        return;
    }

    // Admin-specific initialization
    do_action('bitcore_tasks_admin_init');
}

/**
 * Register REST API routes
 *
 * @since 1.0.0
 * @return void
 */
function register_rest_routes(): void {
    $controller = new REST_Tasks();
    $controller->register_routes();
}

