<?php
/**
 * BITCORE Test Bootstrap
 *
 * @package BITCORE\Tests
 * @since 1.0.0
 */

// Load WordPress test environment
if (!defined('WP_TESTS_CONFIG_FILE_PATH')) {
    define('WP_TESTS_CONFIG_FILE_PATH', __DIR__ . '/wp-config.php');
}

// WordPress test bootstrap
$wp_tests_dir = getenv('WP_TESTS_DIR') ?: '/tmp/wordpress-tests-lib';

if (!file_exists($wp_tests_dir . '/includes/functions.php')) {
    throw new \Exception(
        "WordPress tests could not be found. Set WP_TESTS_DIR environment variable."
    );
}

require_once $wp_tests_dir . '/includes/functions.php';

tests_add_filter('muplugins_loaded', function () {
    // Load the plugin
    require dirname(__DIR__) . '/bitcore-core.php';
});

// Start up the WP testing environment
require $wp_tests_dir . '/includes/bootstrap.php';

// Load test utilities
require_once __DIR__ . '/TestCase.php';
