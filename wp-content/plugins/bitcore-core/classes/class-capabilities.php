<?php
/**
 * Capability Registration Class
 *
 * Handles registration and cleanup of custom capabilities for WordPress roles.
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
 * Class Capabilities
 *
 * Manages custom capability registration for the Bitcore Tasks system.
 *
 * @since 1.0.0
 */
class Capabilities {

    /**
     * Custom capability definitions
     *
     * @since 1.0.0
     * @var array<string, string>
     */
    private const CAPABILITIES = array(
        'bitcore_read_tasks',
        'bitcore_create_tasks',
        'bitcore_edit_tasks',
        'bitcore_delete_tasks',
        'bitcore_dispatch_tasks',
        'bitcore_manage_tasks',
    );

    /**
     * Role to capability mapping
     *
     * @since 1.0.0
     * @var array<string, array<string>>
     */
    private const ROLE_CAPABILITY_MAP = array(
        'administrator' => array(
            'bitcore_read_tasks',
            'bitcore_create_tasks',
            'bitcore_edit_tasks',
            'bitcore_delete_tasks',
            'bitcore_dispatch_tasks',
            'bitcore_manage_tasks',
        ),
        'editor' => array(
            'bitcore_read_tasks',
            'bitcore_create_tasks',
            'bitcore_edit_tasks',
            'bitcore_delete_tasks',
            'bitcore_dispatch_tasks',
        ),
        'author' => array(
            'bitcore_read_tasks',
            'bitcore_create_tasks',
            'bitcore_edit_tasks',
        ),
        'contributor' => array(
            'bitcore_read_tasks',
            'bitcore_create_tasks',
        ),
    );

    /**
     * Register all custom capabilities to WordPress roles
     *
     * This method adds custom capabilities to specified roles
     * during plugin activation or setup.
     *
     * @since 1.0.0
     * @return void
     */
    public static function register(): void {
        foreach (self::ROLE_CAPABILITY_MAP as $role_slug => $capabilities) {
            $role = get_role($role_slug);
            if (!$role) {
                continue;
            }

            foreach ($capabilities as $capability) {
                $role->add_cap($capability, true);
            }
        }

        // Grant all capabilities to administrators
        $admin_role = get_role('administrator');
        if ($admin_role) {
            foreach (self::CAPABILITIES as $capability) {
                $admin_role->add_cap($capability, true);
            }
        }

        /**
         * Fires after capabilities have been registered
         *
         * @since 1.0.0
         * @param array $capabilities List of registered capabilities
         */
        do_action('bitcore_tasks_capabilities_registered', self::CAPABILITIES);
    }

    /**
     * Unregister all custom capabilities from WordPress roles
     *
     * This method removes custom capabilities during plugin
     * deactivation or cleanup.
     *
     * @since 1.0.0
     * @return void
     */
    public static function unregister(): void {
        foreach (self::ROLE_CAPABILITY_MAP as $role_slug => $capabilities) {
            $role = get_role($role_slug);
            if (!$role) {
                continue;
            }

            foreach ($capabilities as $capability) {
                $role->remove_cap($capability);
            }
        }

        // Remove all capabilities from administrators
        $admin_role = get_role('administrator');
        if ($admin_role) {
            foreach (self::CAPABILITIES as $capability) {
                $admin_role->remove_cap($capability);
            }
        }

        /**
         * Fires after capabilities have been unregistered
         *
         * @since 1.0.0
         * @param array $capabilities List of unregistered capabilities
         */
        do_action('bitcore_tasks_capabilities_unregistered', self::CAPABILITIES);
    }

    /**
     * Check if a specific capability is a custom Bitcore capability
     *
     * @since 1.0.0
     * @param string $capability Capability to check
     * @return bool True if it's a custom capability
     */
    public static function is_custom_capability(string $capability): bool {
        return in_array($capability, self::CAPABILITIES, true);
    }

    /**
     * Get all custom capabilities
     *
     * @since 1.0.0
     * @return array List of all custom capabilities
     */
    public static function get_capabilities(): array {
        return self::CAPABILITIES;
    }

    /**
     * Get capabilities assigned to a specific role
     *
     * @since 1.0.0
     * @param string $role_slug Role to check
     * @return array List of capabilities for the role
     */
    public static function get_role_capabilities(string $role_slug): array {
        return self::ROLE_CAPABILITY_MAP[$role_slug] ?? array();
    }

    /**
     * Reset all capabilities (unregister and re-register)
     *
     * Useful for resetting capabilities to default state.
     *
     * @since 1.0.0
     * @return void
     */
    public static function reset(): void {
        self::unregister();
        self::register();
    }
}

















