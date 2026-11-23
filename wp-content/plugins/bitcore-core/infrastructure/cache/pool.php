<?php
/**
 * Cache Pool Module
 *
 * Stub file that loads cache pool components.
 * Components are split into separate files for maintainability.
 *
 * @package BITCORE\Infrastructure\Cache
 * @since 1.0.0
 * @version     1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Infrastructure\Cache;

if (!defined('ABSPATH')) {
    exit;
}

// Load cache pool components
require_once __DIR__ . '/PoolStats.php';
require_once __DIR__ . '/CachePool.php';
require_once __DIR__ . '/TenantAwarePool.php';
