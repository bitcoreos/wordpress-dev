<?php
/**
 * Widgets, REST route registration and asset enqueue hooks
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

// === FROM HYPERION: Register Widgets ===
function bitcore_register_widgets(): void {
    if (class_exists('\BITCORE\Patterns\PatternAutoloader')) {
        $autoloader = \BITCORE\Patterns\PatternAutoloader::instance();
        $autoloader->registerWidgets();
    }
}
add_action('widgets_init', 'bitcore_register_widgets');

// === FROM CORALINE: REST API Routes Registration ===
add_action('rest_api_init', function() {
    require_once BITCORE_PATH . '/rest/class-rest-tasks.php';
    if (class_exists('REST_Tasks')) {
        (new REST_Tasks())->register_routes();
    }
});
add_action('rest_api_init', ['BITCORE_Rest_Health_Knowledge', 'register_routes']);
add_action('rest_api_init', ['BITCORE_Rest_Research_Intelligence', 'register_routes']);
add_action('rest_api_init', ['BITCORE_Rest_Sse_Events', 'register_routes']);

// === RESTORED (April 29): Route registrations from April 23 repair (verified class names) ===
add_action('rest_api_init', function() {
    if (class_exists('BITCORE_Rest_MCP_Tools_API')) {
        (new BITCORE_Rest_MCP_Tools_API())->register_routes();
    }
    if (class_exists('BITCORE_Coordination_API')) {
        (new BITCORE_Coordination_API())->register_routes();
    }
});
// === FROM CORALINE: Admin Scripts ===
add_action('admin_enqueue_scripts', ['BITCORE_Integration', 'enqueue_rest_nonce']);

// === FROM CORALINE: SSE Client Scripts ===
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_script(
        'bitcore-sse',
        BITCORE_URL . 'assets/js/bitcore-sse.js',
        [],
        BITCORE_VERSION,
        true
    );
    wp_localize_script('bitcore-sse', 'bitcoreSSE', [
        'endpoint' => rest_url('bitcore/v1/events/stream'),
        'nonce'    => wp_create_nonce('wp_rest'),
    ]);
});

add_action('admin_enqueue_scripts', function() {
    wp_enqueue_script(
        'bitcore-sse-admin',
        BITCORE_URL . 'assets/js/bitcore-sse.js',
        [],
        BITCORE_VERSION,
        true
    );
    wp_localize_script('bitcore-sse-admin', 'bitcoreSSE', [
        'endpoint' => rest_url('bitcore/v1/events/stream'),
        'nonce'    => wp_create_nonce('wp_rest'),
    ]);
});
