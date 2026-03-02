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
class HealthMonitor {

    /**
     * Registered health checkers.
     *
     * @var array<string, HealthCheckerInterface>
     * @since 1.0.0
     */
    private array $checkers = [];

    /**
     * Cached health statuses.
     *
     * @var array<string, AgentHealthStatus>
     * @since 1.0.0
     */
    private array $statuses = [];

    /**
     * Logger.
     *
     * @var Logger
     * @since 1.0.0
     */
    private Logger $logger;

    /**
     * Cache manager.
     *
     * @var CacheManager
     * @since 1.0.0
     */
    private CacheManager $cache;

    /**
     * A2A dispatcher.
     *
     * @var A2ADispatcher
     * @since 1.0.0
     */
    private A2ADispatcher $dispatcher;

    /**
     * Check interval in seconds.
     *
     * @var int
     * @since 1.0.0
     */
    private int $checkInterval;

    /**
     * Response time threshold (ms).
     *
     * @var int
     * @since 1.0.0
     */
    private int $responseThreshold;

    /**
     * Constructor.
     *
     * @param Logger $logger Logger instance.
     * @param CacheManager $cache Cache manager.
     * @param A2ADispatcher $dispatcher A2A dispatcher.
     * @param int $checkInterval Check interval seconds.
     * @param int $responseThreshold Response threshold ms.
     * @since 1.0.0
     */
    public function __construct(
        Logger $logger,
        CacheManager $cache,
        A2ADispatcher $dispatcher,
        int $checkInterval = 60,
        int $responseThreshold = 1000
    ) {
        $this->logger = $logger;
        $this->cache = $cache;
        $this->dispatcher = $dispatcher;
        $this->checkInterval = $checkInterval;
        $this->responseThreshold = $responseThreshold;
    }

    /**
     * Register health checker.
     *
     * @param HealthCheckerInterface $checker Checker to register.
     * @return self Fluent interface.
     * @since 1.0.0
     */
    public function registerChecker(HealthCheckerInterface $checker): self {
        $this->checkers[$checker->getType()->value] = $checker;
        return $this;
    }

    /**
     * Perform local health check.
     *
     * @return AgentHealthStatus Local health status.
     * @since 1.0.0
     */
    public function checkLocal(): AgentHealthStatus {
        $results = [];

        foreach ($this->checkers as $checker) {
            $start = hrtime(true);
            try {
                $result = $checker->check();
                $end = hrtime(true);
                $result = new HealthCheckResult(
                    $result->type,
                    $result->passed,
                    (int) round(($end - $start) / 1e6), // Convert to ms
                    $result->message,
                    $result->data
                );
            } catch (\Throwable $e) {
                $end = hrtime(true);
                $result = HealthCheckResult::failure(
                    $checker->getType(),
                    $e->getMessage(),
                    (int) round(($end - $start) / 1e6)
                );
                $this->logger->error('Health check failed', [
                    'type' => $checker->getType()->value,
                    'error' => $e->getMessage(),
                ]);
            }
            $results[] = $result;
        }

        // Determine overall state
        $passed = count(array_filter($results, fn($r) => $r->passed));
        $total = count($results);

        $state = match(true) {
            $passed === $total => HealthState::HEALTHY,
            $passed >= $total * 0.5 => HealthState::DEGRADED,
            $passed > 0 => HealthState::UNHEALTHY,
            default => HealthState::UNKNOWN,
        };

        $status = new AgentHealthStatus(
            agentId: $this->getLocalAgentId(),
            state: $state,
            checks: $results
        );

        $this->statuses[$status->agentId] = $status;
        $this->cache->set('health_local', $status->toArray(), $this->checkInterval);

        return $status;
    }

    /**
     * Check health of remote agent.
     *
     * @param string $agentId Agent ID.
     * @return AgentHealthStatus|null Status or null.
     * @since 1.0.0
     */
    public function checkAgent(string $agentId): ?AgentHealthStatus {
        $start = microtime(true);

        // Send health check request
        $response = $this->dispatcher->request(
            from: $this->getLocalAgentId(),
            to: $agentId,
            payload: ['action' => 'health_check'],
            timeout: 10
        );

        $elapsed = (int) round((microtime(true) - $start) * 1000);

        if ($response === null) {
            $status = new AgentHealthStatus(
                agentId: $agentId,
                state: HealthState::OFFLINE,
                checks: [
                    HealthCheckResult::failure(
                        CheckType::CONNECTIVITY,
                        'Agent did not respond',
                        $elapsed
                    ),
                ]
            );
        } else {
            // Parse response
            $data = $response['body'] ?? [];
            $checks = [];

            foreach ($data['checks'] ?? [] as $check) {
                $checks[] = new HealthCheckResult(
                    CheckType::from($check['type']),
                    $check['passed'],
                    $check['response_time_ms'] ?? 0,
                    $check['message'] ?? null,
                    $check['data'] ?? []
                );
            }

            $state = HealthState::from($data['state'] ?? 'unknown');
            $status = new AgentHealthStatus(
                agentId: $agentId,
                state: $state,
                checks: $checks,
                timestamp: $data['timestamp'] ?? null
            );
        }

        $this->statuses[$agentId] = $status;
        $this->cache->set('health_' . $agentId, $status->toArray(), $this->checkInterval);

        return $status;
    }

    /**
     * Check health of all registered agents.
     *
     * @return array<string, AgentHealthStatus> Statuses by agent ID.
     * @since 1.0.0
     */
    public function checkAll(): array {
        $this->checkLocal();

        foreach ($this->dispatcher->getAgents() as $agentId => $agent) {
            if ($agentId !== $this->getLocalAgentId()) {
                $this->checkAgent($agentId);
            }
        }

        return $this->statuses;
    }

    /**
     * Get health status.
     *
     * @param string|null $agentId Agent ID (null for local).
     * @return AgentHealthStatus|null Status or null.
     * @since 1.0.0
     */
    public function getStatus(?string $agentId = null): ?AgentHealthStatus {
        $agentId ??= $this->getLocalAgentId();

        if (!isset($this->statuses[$agentId])) {
            $cached = $this->cache->get('health_' . $agentId);
            if ($cached) {
                $this->statuses[$agentId] = $this->statusFromArray($cached);
            }
        }

        return $this->statuses[$agentId] ?? null;
    }

    /**
     * Get swarm health summary.
     *
     * @return array<string, mixed> Summary.
     * @since 1.0.0
     */
    public function getSummary(): array {
        $agents = $this->dispatcher->getAgents();
        $agentCount = count($agents);

        $healthy = 0;
        $degraded = 0;
        $unhealthy = 0;
        $offline = 0;
        $totalScore = 0;

        foreach ($this->statuses as $status) {
            match($status->state) {
                HealthState::HEALTHY => $healthy++,
                HealthState::DEGRADED => $degraded++,
                HealthState::UNHEALTHY => $unhealthy++,
                HealthState::OFFLINE => $offline++,
                default => null,
            };
            $totalScore += $status->score;
        }

        return [
            'total_agents' => $agentCount,
            'monitored' => count($this->statuses),
            'healthy' => $healthy,
            'degraded' => $degraded,
            'unhealthy' => $unhealthy,
            'offline' => $offline,
            'unknown' => $agentCount - count($this->statuses),
            'average_score' => count($this->statuses) > 0
                ? (int) round($totalScore / count($this->statuses))
                : 0,
            'last_check' => time(),
        ];
    }

    /**
     * Reconstruct status from array.
     *
     * @param array<string, mixed> $data Status data.
     * @return AgentHealthStatus Status.
     * @since 1.0.0
     */
    private function statusFromArray(array $data): AgentHealthStatus {
        $checks = [];
        foreach ($data['checks'] ?? [] as $check) {
            $checks[] = new HealthCheckResult(
                CheckType::from($check['type']),
                $check['passed'],
                $check['response_time_ms'] ?? 0,
                $check['message'] ?? null,
                $check['data'] ?? []
            );
        }

        return new AgentHealthStatus(
            agentId: $data['agent_id'],
            state: HealthState::from($data['state']),
            checks: $checks,
            timestamp: $data['last_update'] ?? null
        );
    }

    /**
     * Get local agent ID.
     *
     * @return string Agent ID.
     * @since 1.0.0
     */
    private function getLocalAgentId(): string {
        return get_option('bitcore_agent_id', 'bitcore_' . get_current_blog_id());
    }
}

// ============================================================================
// STANDARD CHECKERS
// ============================================================================

/**
 * Database connectivity checker.
 *
 * @package BITCORE\Infrastructure\Swarm
 * @since 1.0.0
 */
class DatabaseHealthChecker implements HealthCheckerInterface {

    /**
     * Perform database check.
     *
     * @return HealthCheckResult Result.
     * @since 1.0.0
     */
    public function check(): HealthCheckResult {
        $start = hrtime(true);

        try {
            global $wpdb;
            $result = $wpdb->get_var('SELECT 1');
            $end = hrtime(true);

            if ($result === '1') {
                return HealthCheckResult::success(
                    $this->getType(),
                    (int) round(($end - $start) / 1e6),
                    'Database connected'
                );
            }

            return HealthCheckResult::failure(
                $this->getType(),
                'Unexpected query result',
                (int) round(($end - $start) / 1e6)
            );
        } catch (\Throwable $e) {
            $end = hrtime(true);
            return HealthCheckResult::failure(
                $this->getType(),
                $e->getMessage(),
                (int) round(($end - $start) / 1e6)
            );
        }
    }

    /**
     * Get check type.
     *
     * @return CheckType Type.
     * @since 1.0.0
     */
    public function getType(): CheckType {
        return CheckType::DATABASE;
    }
}

/**
 * Cache connectivity checker.
 *
 * @package BITCORE\Infrastructure\Swarm
 * @since 1.0.0
 */
class CacheHealthChecker implements HealthCheckerInterface {

    /**
     * Cache manager.
     *
     * @var CacheManager
     * @since 1.0.0
     */
    private CacheManager $cache;

    /**
     * Constructor.
     *
     * @param CacheManager $cache Cache manager.
     * @since 1.0.0
     */
    public function __construct(CacheManager $cache) {
        $this->cache = $cache;
    }

    /**
     * Perform cache check.
     *
     * @return HealthCheckResult Result.
     * @since 1.0.0
     */
    public function check(): HealthCheckResult {
        $start = hrtime(true);
        $key = 'health_check_' . time();

        try {
            $this->cache->set($key, 'test', 10);
            $value = $this->cache->get($key);
            $this->cache->delete($key);
            $end = hrtime(true);

            if ($value === 'test') {
                return HealthCheckResult::success(
                    $this->getType(),
                    (int) round(($end - $start) / 1e6),
                    'Cache operational'
                );
            }

            return HealthCheckResult::failure(
                $this->getType(),
                'Cache read/write mismatch',
                (int) round(($end - $start) / 1e6)
            );
        } catch (\Throwable $e) {
            $end = hrtime(true);
            return HealthCheckResult::failure(
                $this->getType(),
                $e->getMessage(),
                (int) round(($end - $start) / 1e6)
            );
        }
    }

    /**
     * Get check type.
     *
     * @return CheckType Type.
     * @since 1.0.0
     */
    public function getType(): CheckType {
        return CheckType::CACHE;
    }
}
