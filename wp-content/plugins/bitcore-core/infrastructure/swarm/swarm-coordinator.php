<?php
/**
 * Swarm Coordinator Module
 *
 * Stub file that loads swarm coordination components.
 * Components are split into separate files for maintainability.
 *
 * @package BITCORE\Infrastructure\Swarm
 * @since 1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Infrastructure\Swarm;

if (!defined('ABSPATH')) {
    exit;
}

// Load swarm coordinator components
require_once __DIR__ . '/swarm-value-objects.php';
require_once __DIR__ . '/SwarmCoordinator.php';
