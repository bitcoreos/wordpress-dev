<?php
/**
 * Paperclip integration REST route registration
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

// Paperclip Integration REST API Routes (Instance-based registration)
add_action('rest_api_init', function() {
    // Explicit requires for classes not matching BITCORE_Rest_ autoloader prefix.
    // File/class pairs come from BITCORE_REST_API_REGISTRY in the entry file so
    // REST registration has a single source of truth.
    foreach (BITCORE_REST_API_REGISTRY as $relative_path => $api_class) {
        require_once BITCORE_PATH . $relative_path;
    }

    foreach (BITCORE_REST_API_REGISTRY as $api_class) {
        if (class_exists($api_class)) {
            $instance = new $api_class();
            $instance->register_routes();
        }
    }
});
