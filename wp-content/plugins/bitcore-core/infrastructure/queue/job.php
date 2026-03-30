<?php
/**
 * Job Queue Module
 *
 * Stub file that loads job queue components.
 * Components are split into separate files for maintainability.
 *
 * @package BITCORE\Infrastructure\Queue
 * @since 1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Infrastructure\Queue;

if (!defined('ABSPATH')) {
    exit;
}

// Load job queue components
require_once __DIR__ . '/job-interface.php';
require_once __DIR__ . '/Job.php';
require_once __DIR__ . '/JobRepository.php';
require_once __DIR__ . '/job-middleware.php';
