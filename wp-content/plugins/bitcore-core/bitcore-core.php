<?php
/**
 * Plugin Name: BITCORE Core
 * Plugin URI: https://bitcore.org
 * Description: Core plugin for BITCORE Intelligence Factory. Modular AI system with MCP, REST API, and agent orchestration.
 * Version: 1.0.0
 * Author: BITCORE
 * Author URI: https://bitcore.org
 * Text Domain: bitcore
 * License: GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'BITCORE_VERSION', '1.0.0' );
define( 'BITCORE_PREFIX', 'bitcore' );
define( 'BITCORE_DOMAIN', 'bitcore' );
define( 'BITCORE_ENTRY', __FILE__ );
define( 'BITCORE_PATH', dirname( __FILE__ ) );
define( 'BITCORE_URL', plugin_dir_url( __FILE__ ) );
define( 'BITCORE_TIMEOUT', 300 );
define( 'BITCORE_FALLBACK_MODEL', 'coraline-v1' );
define( 'BITCORE_FALLBACK_MODEL_FAST', 'hyperion-v1' );

// Autoloader (from AI Engine pattern)
spl_autoload_register( function ( $class ) {
    $file = null;

    if ( strpos( $class, 'BITCORE_Modules' ) !== false ) {
        $filename = str_replace( 'BITCORE_Modules_', '', $class );
        $filename = strtolower( str_replace( '_', '-', $filename ) );
        $file = BITCORE_PATH . '/classes/modules/' . $filename . '.php';
    }
    else if ( strpos( $class, 'BITCORE_Services' ) !== false ) {
        $filename = str_replace( 'BITCORE_Services_', '', $class );
        $filename = strtolower( str_replace( '_', '-', $filename ) );
        $file = BITCORE_PATH . '/classes/services/' . $filename . '.php';
    }
    else if ( strpos( $class, 'BITCORE_Engines' ) !== false ) {
        $filename = str_replace( 'BITCORE_Engines_', '', $class );
        $filename = strtolower( str_replace( '_', '-', $filename ) );
        $file = BITCORE_PATH . '/classes/engines/' . $filename . '.php';
    }
    else if ( strpos( $class, 'BITCORE_Rest' ) !== false ) {
        $filename = str_replace( 'BITCORE_Rest_', '', $class );
        $filename = strtolower( str_replace( '_', '-', $filename ) );
        $file = BITCORE_PATH . '/rest/' . $filename . '.php';
    }
    else if ( strpos( $class, 'BITCORE_MCP' ) !== false ) {
        $filename = str_replace( 'BITCORE_MCP_', '', $class );
        $filename = strtolower( str_replace( '_', '-', $filename ) );
        $file = BITCORE_PATH . '/mcp/' . $filename . '.php';
    }
    else if ( strpos( $class, 'BITCORE' ) !== false ) {
        $filename = str_replace( 'BITCORE_', '', $class );
        $filename = strtolower( str_replace( '_', '-', $filename ) );
        $file = BITCORE_PATH . '/classes/' . $filename . '.php';
    }

    if ( $file && file_exists( $file ) ) {
        require_once( $file );
    }
} );

// Initialize
require_once( BITCORE_PATH . '/classes/core.php' );



// Activation/Deactivation Hooks
register_activation_hook( BITCORE_ENTRY, [ 'BITCORE_Core', 'activate' ] );
register_deactivation_hook( BITCORE_ENTRY, [ 'BITCORE_Core', 'deactivate' ] );

// REST API Routes
add_action( 'rest_api_init', [ 'BITCORE_Rest_Tasks', 'register_routes' ] );

// Admin Scripts
add_action( 'admin_enqueue_scripts', [ 'BITCORE_Integration', 'enqueue_rest_nonce' ] );

// Global instance
$GLOBALS['bitcore'] = new BITCORE_Core();

// SSE Events REST Routes
add_action( 'rest_api_init', [ 'BITCORE_Rest_Sse_Events', 'register_routes' ] );

// Enqueue SSE Client Script
add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_script(
        'bitcore-sse',
        BITCORE_URL . 'assets/js/bitcore-sse.js',
        array(),
        BITCORE_VERSION,
        true
    );
    
    // Localize script with endpoint
    wp_localize_script( 'bitcore-sse', 'bitcoreSSE', array(
        'endpoint' => rest_url( 'bitcore/v1/events/stream' ),
        'nonce' => wp_create_nonce( 'wp_rest' ),
    ) );
} );

// Enqueue admin SSE Client Script
add_action( 'admin_enqueue_scripts', function() {
    wp_enqueue_script(
        'bitcore-sse-admin',
        BITCORE_URL . 'assets/js/bitcore-sse.js',
        array(),
        BITCORE_VERSION,
        true
    );
    
    wp_localize_script( 'bitcore-sse-admin', 'bitcoreSSE', array(
        'endpoint' => rest_url( 'bitcore/v1/events/stream' ),
        'nonce' => wp_create_nonce( 'wp_rest' ),
    ) );
} );
