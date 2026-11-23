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
    // Explicit requires for classes not matching BITCORE_Rest_ autoloader prefix
    require_once BITCORE_PATH . '/rest/class-checkout-api.php';
    require_once BITCORE_PATH . '/rest/class-org-chart-api.php';
    require_once BITCORE_PATH . '/rest/class-credits-api.php';
    require_once BITCORE_PATH . '/rest/class-approvals-api.php';
    require_once BITCORE_PATH . '/rest/class-sessions-api.php';
    require_once BITCORE_PATH . '/rest/class-kanban-api.php';
    require_once BITCORE_PATH . '/rest/class-my-posts-api.php';
    require_once BITCORE_PATH . '/rest/class-bitmon-api.php';
    $apis = [
        'BITCORE\Rest\Checkout_Api',
        'BITCORE\Rest\Org_Chart_Api',
        'BITCORE\Rest\Credits_Api',
        'BITCORE\Rest\Approvals_Api',
        'BITCORE\Rest\Sessions_Api',
        'BITCORE_Rest_Kanban_API',
        'BITCORE_Rest_My_Posts_API',
        'BITCORE\Rest\Bitmon_Api',
    ];
    foreach ($apis as $api_class) {
        if (class_exists($api_class)) {
            $instance = new $api_class();
            $instance->register_routes();
        }
    }
});
