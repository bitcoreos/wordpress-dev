<?php
/**
 * BITCORE Test Bootstrap — WordPress-backed suites (PHPUnit 9 engine).
 *
 * Runs the six WP_UnitTestCase classes against the WordPress test library.
 * WP-free suites use tests/bootstrap-standalone.php on the PHPUnit 10 engine.
 *
 * @package BITCORE\Tests
 * @since 1.0.0
 * @version    1.0.0
 * @author     coraline
 */

$plugin_dir = dirname(__DIR__);

// The WordPress test library aborts unless the Yoast PHPUnit Polyfills
// autoloader is reachable. It treats a relative constant as relative to the
// current working directory, so resolve absolute candidates here.
if (!defined('WP_TESTS_PHPUNIT_POLYFILLS_PATH')) {
    $polyfill_candidates = [
        $plugin_dir . '/vendor/yoast/phpunit-polyfills',
        dirname($plugin_dir, 2) . '/vendor/yoast/phpunit-polyfills',
    ];
    foreach ($polyfill_candidates as $candidate) {
        if (file_exists($candidate . '/phpunitpolyfills-autoload.php')) {
            define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', $candidate);
            break;
        }
    }
}

// Resolve a test config only when one exists on disk. Credentials are never
// authored here; the WordPress test library config is the single source.
if (!defined('WP_TESTS_CONFIG_FILE_PATH')) {
    foreach ([$plugin_dir . '/tests/wp-config.php', '/tmp/wordpress-tests-lib/wp-tests-config.php'] as $candidate) {
        if (file_exists($candidate)) {
            define('WP_TESTS_CONFIG_FILE_PATH', $candidate);
            break;
        }
    }
}

$wp_tests_dir = getenv('WP_TESTS_DIR') ?: '/tmp/wordpress-tests-lib';

if (!file_exists($wp_tests_dir . '/includes/functions.php')) {
    fwrite(STDERR, "BITCORE tests: WordPress test library missing at {$wp_tests_dir}. Set WP_TESTS_DIR.\n");
    exit(1);
}

require_once $wp_tests_dir . '/includes/functions.php';

tests_add_filter('muplugins_loaded', function () use ($plugin_dir) {
    require $plugin_dir . '/bitcore-core.php';
});

// Provision the plugin schema into the WordPress test database. Production runs
// this through bitcore_activate()/PatternAutoloader::activate(); the PHPUnit
// harness never fires the activation hook, so run the same SchemaManager here.
// This touches only the test database selected by the WP test library.
tests_add_filter('plugins_loaded', function () use ($plugin_dir) {
    require_once $plugin_dir . '/database/schema.php';
    if (!class_exists('BITCORE\\Database\\SchemaManager')) {
        return;
    }

    // Mark the schema current first: install() creates every table with dbDelta
    // (idempotent) and then skips the versioned ALTER migrations, which would
    // otherwise re-add columns dbDelta already created.
    $version = defined('BITCORE_DB_VERSION') ? BITCORE_DB_VERSION : '1.3.0';
    if (get_option('bitcore_db_version') !== $version) {
        update_option('bitcore_db_version', $version);
    }

    (new \BITCORE\Database\SchemaManager())->install();
}, 99);


require $wp_tests_dir . '/includes/bootstrap.php';

// Shared PHPUnit base class, when present (the WP-free engine supplies its own).
if (file_exists(__DIR__ . '/TestCase.php')) {
    require_once __DIR__ . '/TestCase.php';
}
