<?php
/**
 * WP-free PHPUnit bootstrap for bitcore-core unit suites.
 *
 * Used by phpunit.xml.dist under PHPUnit 10. Loads only the plugin classes the
 * WP-independent suites touch: BITCORE_Container and BITCORE_Abstract_Connector.
 *
 * @package BITCORE\Tests
 * @since 1.4.0
 * @version    1.0.0
 * @author     coraline
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

require_once dirname(__DIR__) . '/classes/container.php';
require_once dirname(__DIR__) . '/classes/services/abstract-connector.php';
