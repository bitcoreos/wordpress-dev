<?php
/**
 * Cache Manager Module
 *
 * Stub file that loads cache components.
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

// Load cache components
require_once __DIR__ . '/CacheManager.php';
require_once __DIR__ . '/cache-tiers.php';
