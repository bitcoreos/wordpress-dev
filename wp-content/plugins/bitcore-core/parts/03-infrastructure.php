<?php
/**
 * Infrastructure boot, CLI, Discourse and admin menu wiring
 *
 * Extracted verbatim from bitcore-core.php; require_once order is the original
 * execution order. Bootstrap remains the plugin entry point.
 *
 * @package    Bitcore
 * @since      2026.03.01
 * @version    1.3.0
 * @author     coraline
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

function bitcore_init_infrastructure(): void {
    if (!isset($GLOBALS['bitcore'])) {
        require_once BITCORE_PLUGIN_DIR . 'classes/core.php';
        $GLOBALS['bitcore'] = new BITCORE_Core();
    }
}
add_action('plugins_loaded', 'bitcore_init_infrastructure', 15);

// === FROM HYPERION: WP-CLI Commands ===
function bitcore_cli_init(): void {
    if (defined('WP_CLI') && WP_CLI) {
        require_once BITCORE_PLUGIN_DIR . 'infrastructure/cli/commands.php';
        \WP_CLI::add_command('bitcore', \BITCORE\Infrastructure\CLI\BitcoreCommand::class);
    }
}
add_action('init', 'bitcore_cli_init');

// === FROM HYPERION: WP Discourse Adapter ===
require_once BITCORE_PLUGIN_DIR . 'infrastructure/auth/wp-discourse-adapter.php';

// === FROM HYPERION: Discourse SSO Client Redirect Fix ===
// Validates redirect_to parameter during SSO flow to ensure
// return_sso_url is always a WordPress domain URL, not Discourse.
// Two-layer defense: init priority 3 (before WP-Discourse) + wpdc_sso_client_redirect_url filter.
require_once BITCORE_PLUGIN_DIR . 'infrastructure/auth/discourse-sso-fix.php';
require_once BITCORE_PLUGIN_DIR . 'infrastructure/auth/discourse-permission-capture.php';

// === FROM HYPERION: Admin Menu Registration ===
require_once BITCORE_PLUGIN_DIR . 'admin/menu-registration.php';
