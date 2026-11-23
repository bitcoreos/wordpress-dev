<?php
declare(strict_types=1);
/**
 * Capability Registration Module
 *
 * Custom capabilities have been removed in favor of standard WordPress capabilities.
 * This file is retained for autoloader compatibility and to provide cleanup
 * of any previously registered custom capabilities during deactivation.
 *
 * Replacement mapping:
 * - bitcore_read_tasks    → read
 * - bitcore_create_tasks → edit_posts
 * - bitcore_edit_tasks   → edit_posts
 * - bitcore_delete_tasks → delete_posts
 * - bitcore_dispatch_tasks → edit_posts
 * - bitcore_manage_tasks → manage_options
 *
 * @package BITCORE
 * @since 1.0.0
 * @author BITCORE Team
 * @version     1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BITCORE_Modules_Capabilities
 *
 * Retained for autoloader compatibility. Custom capabilities have been
 * replaced with standard WordPress capabilities.
 *
 * @since 1.0.0
 * @deprecated 1.4.0 Custom capabilities replaced with standard WP capabilities.
 */
class BITCORE_Modules_Capabilities {

	/**
	 * Custom capability definitions (DEPRECATED — now empty).
	 *
	 * @since 1.0.0
	 * @deprecated 1.4.0
	 * @var array<string, string>
	 */
	private const CAPABILITIES = array();

	/**
	 * Role to capability mapping (DEPRECATED — now empty).
	 *
	 * @since 1.0.0
	 * @deprecated 1.4.0
	 * @var array<string, array<string>>
	 */
	private const ROLE_CAPABILITY_MAP = array();

	/**
	 * Register capabilities — no-op since custom caps are removed.
	 *
	 * @since 1.0.0
	 * @deprecated 1.4.0
	 * @return void
	 */
	public static function register(): void {
		// No-op: custom capabilities replaced with standard WordPress capabilities.
	}

	/**
	 * Unregister all custom capabilities from WordPress roles.
	 *
	 * Retained for cleanup of previously registered custom capabilities
	 * on plugin deactivation.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function unregister(): void {
		$old_caps = array(
			'bitcore_read_tasks',
			'bitcore_create_tasks',
			'bitcore_edit_tasks',
			'bitcore_delete_tasks',
			'bitcore_dispatch_tasks',
			'bitcore_manage_tasks',
		);

		foreach ( array( 'administrator', 'editor', 'author', 'contributor' ) as $role_slug ) {
			$role = get_role( $role_slug );
			if ( ! $role ) {
				continue;
			}
			foreach ( $old_caps as $capability ) {
				$role->remove_cap( $capability );
			}
		}

		/**
		 * Fires after custom capabilities have been cleaned up.
		 *
		 * @since 1.4.0
		 * @param array $old_caps List of removed capabilities
		 */
		do_action( 'bitcore_capabilities_cleaned_up', $old_caps );
	}

	/**
	 * Check if a capability is a custom Bitcore capability.
	 *
	 * @since 1.0.0
	 * @deprecated 1.4.0 Always returns false — custom caps removed.
	 * @param string $capability Capability to check
	 * @return bool Always false
	 */
	public static function is_custom_capability( string $capability ): bool {
		return false;
	}

	/**
	 * Get all custom capabilities.
	 *
	 * @since 1.0.0
	 * @deprecated 1.4.0 Always returns empty array.
	 * @return array Empty array
	 */
	public static function get_capabilities(): array {
		return array();
	}

	/**
	 * Get capabilities assigned to a specific role.
	 *
	 * @since 1.0.0
	 * @deprecated 1.4.0 Always returns empty array.
	 * @param string $role_slug Role to check
	 * @return array Empty array
	 */
	public static function get_role_capabilities( string $role_slug ): array {
		return array();
	}

	/**
	 * Reset all capabilities — no-op since custom caps are removed.
	 *
	 * @since 1.0.0
	 * @deprecated 1.4.0
	 * @return void
	 */
	public static function reset(): void {
		// No-op: custom capabilities replaced with standard WordPress capabilities.
	}
}
