<?php
/**
 * Main health monitor class for Bitcore swarm
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