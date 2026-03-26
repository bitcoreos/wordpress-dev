<?php
/**
 * Health checker interfaces for Bitcore swarm monitoring
 *
 * @package    Bitcore
 * @since      2026.03.01
 * @version    1.3.0
 * @author     hyperion
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

<?php
/**
 * Main health monitoring for Bitcore
 *
 * @package    Bitcore
 * @since      2026.03.01
 * @version    1.3.0
 * @author     hyperion
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

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
enum HealthState: string {
    case HEALTHY = 'healthy';
    case DEGRADED = 'degraded';
    case UNHEALTHY = 'unhealthy';
    case UNKNOWN = 'unknown';
    case OFFLINE = 'offline';
}

/**
 * Enumeration of check types.
 *
 * @package BITCORE\Infrastructure\Swarm
 * @since 1.0.0
 */
enum CheckType: string {
    case CONNECTIVITY = 'connectivity';
    case RESPONSE_TIME = 'response_time';
    case CAPACITY = 'capacity';
    case MEMORY = 'memory';
    case DATABASE = 'database';
    case CACHE = 'cache';
    case QUEUE = 'queue';
    case DISK = 'disk';
    case CUSTOM = 'custom';
}

// ============================================================================
// HEALTH CHECK RESULT
// ============================================================================

/**
 * Individual health check result.
 *
 * @package BITCORE\Infrastructure\Swarm
 * @since 1.0.0
 */
readonly class HealthCheckResult {

    /**
     * Check type.
     *
     * @var CheckType
     * @since 1.0.0
     */
    public CheckType $type;

    /**
     * Whether check passed.
     *
     * @var bool
     * @since 1.0.0
     */
    public bool $passed;

    /**
     * Response time in ms.
     *
     * @var int
     * @since 1.0.0
     */
    public int $responseTime;

    /**
     * Check message.
     *
     * @var string|null
     * @since 1.0.0
     */
    public ?string $message;

    /**
     * Additional data.
     *
     * @var array<string, mixed>
     * @since 1.0.0
     */
    public array $data;

    /**
     * Timestamp.
     *
     * @var int
     * @since 1.0.0
     */
    public int $timestamp;

    /**
     * Constructor.
     *
     * @param CheckType $type Check type.
     * @param bool $passed Whether passed.
     * @param int $responseTime Response time ms.
     * @param string|null $message Message.
     * @param array<string, mixed> $data Additional data.
     * @since 1.0.0
     */
    public function __construct(
        CheckType $type,
        bool $passed,
        int $responseTime = 0,
        ?string $message = null,
        array $data = []
    ) {
        $this->type = $type;
        $this->passed = $passed;
        $this->responseTime = $responseTime;
        $this->message = $message;
        $this->data = $data;
        $this->timestamp = time();
    }

    /**
     * Create successful result.
     *
     * @param CheckType $type Check type.
     * @param int $responseTime Response time.
     * @param string|null $message Message.
     * @param array<string, mixed> $data Data.
     * @return self Result.
     * @since 1.0.0
     */
    public static function success(
        CheckType $type,
        int $responseTime = 0,
        ?string $message = null,
        array $data = []
    ): self {
        return new self($type, true, $responseTime, $message, $data);
    }

    /**
     * Create failed result.
     *
     * @param CheckType $type Check type.
     * @param string $message Error message.
     * @param int $responseTime Response time.
     * @param array<string, mixed> $data Data.
     * @return self Result.
     * @since 1.0.0
     */
    public static function failure(
        CheckType $type,
        string $message,
        int $responseTime = 0,
        array $data = []
    ): self {
        return new self($type, false, $responseTime, $message, $data);
    }

    /**
     * Serialize to array.
     *
     * @return array<string, mixed> Serialized.
     * @since 1.0.0
     */
    public function toArray(): array {
        return [
            'type' => $this->type->value,
            'passed' => $this->passed,
            'response_time_ms' => $this->responseTime,
            'message' => $this->message,
            'data' => $this->data,
            'timestamp' => $this->timestamp,
        ];
    }
}

// ============================================================================
// AGENT HEALTH STATUS
// ============================================================================

/**
 * Aggregated health status for an agent.
 *
 * @package BITCORE\Infrastructure\Swarm
 * @since 1.0.0
 */
readonly class AgentHealthStatus {

    /**
     * Agent ID.
     *
     * @var string
     * @since 1.0.0
     */
    public string $agentId;

    /**
     * Overall health state.
     *
     * @var HealthState
     * @since 1.0.0
     */
    public HealthState $state;

    /**
     * Individual check results.
     *
     * @var array<HealthCheckResult>
     * @since 1.0.0
     */
    public array $checks;

    /**
     * Last update timestamp.
     *
     * @var int
     * @since 1.0.0
     */
    public int $lastUpdate;

    /**
     * Health score (0-100).
     *
     * @var int
     * @since 1.0.0
     */
    public int $score;

    /**
     * Constructor.
     *
     * @param string $agentId Agent ID.
     * @param HealthState $state Overall state.
     * @param array<HealthCheckResult> $checks Check results.
     * @param int|null $timestamp Update time.
     * @since 1.0.0
     */
    public function __construct(
        string $agentId,
        HealthState $state,
        array $checks = [],
        ?int $timestamp = null
    ) {
        $this->agentId = $agentId;
        $this->state = $state;
        $this->checks = $checks;
        $this->lastUpdate = $timestamp ?? time();
        $this->score = $this->calculateScore($checks);
    }

    /**
     * Calculate health score.
     *
     * @param array<HealthCheckResult> $checks Check results.
     * @return int Score 0-100.
     * @since 1.0.0
     */
    private function calculateScore(array $checks): int {
        if (empty($checks)) {
            return 0;
        }

        $passed = count(array_filter($checks, fn($c) => $c->passed));
        $total = count($checks);
        $baseScore = (int) round(($passed / $total) * 100);

        // Penalize slow responses
        $avgResponseTime = array_sum(array_map(fn($c) => $c->responseTime, $checks)) / $total;
        if ($avgResponseTime > 1000) {
            $baseScore -= 10;
        } elseif ($avgResponseTime > 500) {
            $baseScore -= 5;
        }

        return max(0, min(100, $baseScore));
    }

    /**
     * Serialize to array.
     *
     * @return array<string, mixed> Serialized.
     * @since 1.0.0
     */
    public function toArray(): array {
        return [
            'agent_id' => $this->agentId,
            'state' => $this->state->value,
            'score' => $this->score,
            'last_update' => $this->lastUpdate,
            'checks' => array_map(fn($c) => $c->toArray(), $this->checks),
        ];
    }
}

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