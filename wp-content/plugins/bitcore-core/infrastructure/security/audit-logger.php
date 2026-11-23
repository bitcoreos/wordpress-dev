<?php
/**
 * Security Audit Logger Module
 *
 * Stub file that loads audit logging components.
 * Components are split into separate files for maintainability.
 *
 * @package BITCORE\Infrastructure\Security
 * @since 1.0.0
 * @version     1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Infrastructure\Security;

if (!defined('ABSPATH')) {
    exit;
}

// Load audit logger components
require_once __DIR__ . '/audit-value-objects.php';
require_once __DIR__ . '/AuditLogger.php';
