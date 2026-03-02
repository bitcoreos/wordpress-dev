<?php
/**
 * Tenant Context
 *
 * Provides basic tenant context management for multi-tenancy support.
 * Phase 1 implementation - basic tenant ID tracking.
 *
 * @package BITCORE
 * @subpackage Infrastructure\Auth
 * @since 1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Infrastructure\Auth;

/**
 * Tenant Context
 *
 * Manages current tenant context for multi-tenant SaaS operations.
 * Provides tenant isolation at the application level.
 *
 * @since 1.0.0
 */
class TenantContext {

    /**
     * Current tenant ID
     *
     * @var int|null
     * @since 1.0.0
     */
    private ?int $currentTenantId = null;

    /**
     * Constructor
     *
     * @param int|null $tenantId Optional tenant ID to set initially
     * @since 1.0.0
     */
    public function __construct(?int $tenantId = null) {
        if ($tenantId !== null) {
            $this->setTenant($tenantId);
        }
    }

    /**
     * Get current tenant ID
     *
     * Returns the currently active tenant ID or null if no tenant context.
     *
     * @return int|null Current tenant ID or null
     * @since 1.0.0
     */
    public function getCurrentTenant(): ?int {
        return $this->currentTenantId;
    }

    /**
     * Set tenant context
     *
     * Sets the current tenant ID for subsequent operations.
     *
     * @param int $tenantId The tenant ID to set
     * @since 1.0.0
     */
    public function setTenant(int $tenantId): void {
        $this->currentTenantId = $tenantId;
    }

    /**
     * Clear tenant context
     *
     * Removes the current tenant context, returning to global context.
     *
     * @since 1.0.0
     */
    public function clearTenant(): void {
        $this->currentTenantId = null;
    }

    /**
     * Check if tenant context is active
     *
     * @return bool True if tenant context is set
     * @since 1.0.0
     */
    public function hasTenant(): bool {
        return $this->currentTenantId !== null;
    }
}

/*
 * Test Scaffold:
 * TODO: Implement unit tests
 * - Test constructor with null tenant
 * - Test constructor with initial tenant
 * - Test getCurrentTenant() returns null initially
 * - Test setTenant() updates current tenant
 * - Test clearTenant() resets to null
 * - Test hasTenant() returns correct boolean
 * - Test tenant isolation in multi-tenant scenarios
 */