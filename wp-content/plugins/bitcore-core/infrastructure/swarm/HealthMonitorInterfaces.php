<?php
/**
 * Distributed Health Monitor
 *
 * Provides health checking, status aggregation, and monitoring for
 * multi-agent swarms. Ported from flux dashboard patterns.
 *
 * @package BITCORE\Infrastructure\Swarm
 * @since 1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Infrastructure\Swarm;

use BITCORE\Infrastructure\Logging\Logger;
use BITCORE\Infrastructure\Cache\CacheManager;

// ============================================================================
// HEALTH STATUS
// ============================================================================

/**
 * Enumeration of health states.
 *
 * @package BITCORE\Infrastructure\Swarm
 * @since 1.0.0
 */

/**
 * Enumeration of check types.
 *
 * @package BITCORE\Infrastructure\Swarm
 * @since 1.0.0
 */

// ============================================================================
// HEALTH CHECK RESULT
// ============================================================================

/**
 * Individual health check result.
 *
 * @package BITCORE\Infrastructure\Swarm
 * @since 1.0.0
 */

// ============================================================================
// AGENT HEALTH STATUS
// ============================================================================

/**
 * Aggregated health status for an agent.
 *
 * @package BITCORE\Infrastructure\Swarm
 * @since 1.0.0
 */

// ============================================================================
// HEALTH CHECKER INTERFACE
// ============================================================================

/**
 * Health checker interface.
 *
 * @package BITCORE\Infrastructure\Swarm
 * @since 1.0.0
 */
interface HealthCheckerInterface {

    /**
     * Perform health check.
     *
     * @return HealthCheckResult Check result.
     * @since 1.0.0
     */
    public function check(): HealthCheckResult;

    /**
     * Get check type.
     *
     * @return CheckType Check type.
     * @since 1.0.0
     */
    public function getType(): CheckType;
}

// ============================================================================
// HEALTH MONITOR
// ============================================================================

/**
 * Distributed health monitoring system.
 *
 * @package BITCORE\Infrastructure\Swarm
 * @since 1.0.0
 */