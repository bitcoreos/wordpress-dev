<?php
/**
 * Health Monitor Orchestrator
 *
 * Thin loader file that imports split components.
 * Maintains backward compatibility for includes.
 *
 * @package BITCORE\Infrastructure\Swarm
 * @since 1.0.0
 * @version     1.0.0
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load value objects (enums, DTOs)
require_once __DIR__ . '/health-value-objects.php';

// Load interfaces
require_once __DIR__ . '/HealthMonitorInterfaces.php';

// Load health checkers
require_once __DIR__ . '/health-checkers.php';

// Load main HealthMonitor class
require_once __DIR__ . '/HealthMonitorClass.php';
