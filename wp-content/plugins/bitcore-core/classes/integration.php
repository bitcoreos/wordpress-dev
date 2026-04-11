<?php
/**
 * BITCORE_Integration implementation
 *
 * @package    Bitcore
 * @since      2026.03.01
 * @version    1.3.0
 * @author     hyperion
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Integration Class
 *
 * Handles admin scripts, nonce enqueuing, and REST route registration.
 *
 * Based on patterns from bitcore/wordpress-security
 *
 * @package BITCORE
 * @since 1.0.0
 * @author BITCORE Team
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class BITCORE_Integration
 *
 * Handles integration hooks for the BITCORE system.
 *
 * @since 1.0.0
 */
class BITCORE_Integration {

    /**
     * Enqueue REST API nonce for frontend scripts
     *
     * Makes nonce available for authenticated requests.
     *
     * @since 1.0.0
     * @return void
     */
    public static function enqueue_rest_nonce(): void {
        $script_handle = 'bitcore-rest-nonce';

        wp_register_script(
            $script_handle,
            '',
            array(),
            BITCORE_VERSION,
            false
        );

        wp_enqueue_script( $script_handle );

        // Localize script with nonce and API URL
        wp_localize_script(
            $script_handle,
            'bitcoreConfig',
            array(
                'restNonce' => wp_create_nonce( 'wp_rest' ),
                'restUrl'   => esc_url_raw( rest_url( 'bitcore/v1' ) ),
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
    public static function admin_init(): void {
        // Check user capabilities for admin access
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Admin-specific initialization
        do_action( 'bitcore_admin_init' );
    }

    /**
     * Register REST API routes
     *
     * @since 1.0.0
     * @return void
     */
    public static function register_rest_routes(): void {
        if ( class_exists( 'BITCORE_Rest_Tasks' ) ) {
            BITCORE_Rest_Tasks::register_routes();
        }
    }
}
