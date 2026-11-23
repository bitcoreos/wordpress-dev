<?php
/**
 * Multi-Tenancy Tenant Management
 *
 * Stub file that loads tenant components.
 * Components are split into separate files for maintainability.
 *
 * @package BITCORE
 * @subpackage Infrastructure\MultiTenancy
 * @since 1.0.0
 * @version     1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Infrastructure\MultiTenancy;

if (!defined('ABSPATH')) {
    exit;
}

// Load tenant components
require_once __DIR__ . '/tenant-value-objects.php';
require_once __DIR__ . '/TenantRepository.php';
require_once __DIR__ . '/TenantResolver.php';
