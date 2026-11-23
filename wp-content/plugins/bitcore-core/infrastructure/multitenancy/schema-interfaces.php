<?php
/**
 * Schema Interfaces
 *
 * Contract definitions for multi-tenancy schema migrations.
 *
 * @package BITCORE\Infrastructure\MultiTenancy
 * @since 1.0.0
 * @version     1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Infrastructure\MultiTenancy;

use RuntimeException;

/**
 * Migration Interface
 *
 * Contract for database migrations in multi-tenant environment.
 * Implementations define upgrade and rollback operations.
 *
 * @since 1.0.0
 */
interface MigrationInterface {

    /**
     * Apply migration
     *
     * Executes forward migration to upgrade schema.
     *
     * @return void
     * @throws RuntimeException If migration fails
     * @since 1.0.0
     */
    public function up(): void;

    /**
     * Revert migration
     *
     * Executes rollback to downgrade schema.
     *
     * @return void
     * @throws RuntimeException If rollback fails
     * @since 1.0.0
     */
    public function down(): void;

    /**
     * Get migration version
     *
     * @return string Semantic version string (e.g., "1.0.0")
     * @since 1.0.0
     */
    public function getVersion(): string;

    /**
     * Get migration description
     *
     * @return string Human-readable description of changes
     * @since 1.0.0
     */
    public function getDescription(): string;
}
