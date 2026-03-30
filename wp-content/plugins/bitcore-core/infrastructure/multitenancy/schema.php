<?php
/**
 * Multi-Tenancy Schema Management
 *
 * Provides schema isolation, migration system, and database abstraction
 * for multi-tenant SaaS operations in WordPress environment.
 *
 * @package BITCORE
 * @subpackage Infrastructure\MultiTenancy
 * @since 1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Infrastructure\MultiTenancy;

// Load component files
require_once __DIR__ . '/schema-value-objects.php';
require_once __DIR__ . '/schema-interfaces.php';
require_once __DIR__ . '/class-abstract-migration.php';
require_once __DIR__ . '/class-schema-manager.php';

// All classes are now loaded from component files:
// - SchemaVersion (value-objects.php)
// - MigrationInterface (interfaces.php)
// - AbstractMigration (class-abstract-migration.php)
// - SchemaManager (class-schema-manager.php)
