<?php
/**
 * Distributed Health Monitor Module
 *
 * Stub file that loads health monitoring components.
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

// Load health monitor components
require_once __DIR__ . '/health-value-objects.php';
require_once __DIR__ . '/HealthMonitor.php';
require_once __DIR__ . '/health-checkers.php';
