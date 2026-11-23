<?php
/**
 * A2A Dispatcher Module
 *
 * Stub file that loads A2A components.
 * Components are split into separate files for maintainability.
 *
 * @package BITCORE\Infrastructure\Swarm
 * @since 1.0.0
 * @version     1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Infrastructure\Swarm;

if (!defined('ABSPATH')) {
    exit;
}

// Load A2A components
require_once __DIR__ . '/a2a-value-objects.php';
require_once __DIR__ . '/A2ADispatcher.php';
