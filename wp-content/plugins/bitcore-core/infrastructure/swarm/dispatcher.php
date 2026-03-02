<?php
/**
 * BITCORE_Swarm_AgentInterface implementation
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

/**
 * BITCORE A2A Swarm Dispatcher
 * Agent-to-agent message dispatch and coordination
 * @package BITCORE_Core
 * @since 1.0.0
 */
interface BITCORE_Swarm_AgentInterface {
    public function getId(): string;
    public function getCapabilities(): array;
    public function receiveMessage(array $message): void;
}

class BITCORE_Swarm_Dispatcher {
    private array $agents = [];
    private array $registry = [];
    private BITCORE_Logging_Logger $logger;
    private BITCORE_Ratelimit_Manager $ratelimit;

    public function __construct(BITCORE_Logging_Logger $logger, BITCORE_Ratelimit_Manager $ratelimit) {
        $this->logger = $logger;
        $this->ratelimit = $ratelimit;
    }

    public function registerAgent(BITCORE_Swarm_AgentInterface $agent): void {
        $this->agents[$agent->getId()] = $agent;
        $this->registry[$agent->getId()] = [
            'capabilities' => $agent->getCapabilities(),
            'registered' => current_time('mysql'),
            'last_seen' => current_time('mysql'),
        ];
        $this->logger->info('Agent registered', ['agent_id' => $agent->getId()]);
    }

    public function dispatch(string $agentId, array $message): bool {
        if (!$this->ratelimit->check('swarm_dispatch_' . $agentId, 100, 60)->isAllowed()) {
            $this->logger->warning('Rate limit exceeded for agent', ['agent_id' => $agentId]);
            return false;
        }

        if (!isset($this->agents[$agentId])) {
            $this->logger->error('Agent not found', ['agent_id' => $agentId]);
            return false;
        }

        $message['timestamp'] = current_time('mysql');
        $message['dispatched'] = true;

        try {
            $this->agents[$agentId]->receiveMessage($message);
            $this->registry[$agentId]['last_seen'] = current_time('mysql');
            $this->logger->info('Message dispatched', ['agent_id' => $agentId, 'message_type' => $message['type'] ?? 'unknown']);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Dispatch failed', ['agent_id' => $agentId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public function broadcast(array $capability, array $message): int {
        $count = 0;
        foreach ($this->agents as $id => $agent) {
            if (empty($capability) || array_intersect($capability, $agent->getCapabilities())) {
                if ($this->dispatch($id, $message)) {
                    $count++;
                }
            }
        }
        $this->logger->info('Broadcast complete', ['recipients' => $count, 'capability_filter' => $capability]);
        return $count;
    }

    public function getRegistry(): array {
        return $this->registry;
    }
}

class BITCORE_Swarm_Coordinator {
    private BITCORE_Swarm_Dispatcher $dispatcher;
    private BITCORE_Cache_Manager $cache;

    public function __construct(BITCORE_Swarm_Dispatcher $dispatcher, BITCORE_Cache_Manager $cache) {
        $this->dispatcher = $dispatcher;
        $this->cache = $cache;
    }

    public function coordinateTask(string $taskId, array $requirements, int $agentCount = 1): array {
        $available = [];
        foreach ($this->dispatcher->getRegistry() as $id => $info) {
            if (empty($requirements) || array_intersect($requirements, $info['capabilities'])) {
                $available[] = $id;
            }
        }

        if (count($available) < $agentCount) {
            return ['status' => 'error', 'message' => 'Insufficient agents available'];
        }

        shuffle($available);
        $selected = array_slice($available, 0, $agentCount);

        foreach ($selected as $agentId) {
            $this->dispatcher->dispatch($agentId, [
                'type' => 'task_assignment',
                'task_id' => $taskId,
                'requirements' => $requirements,
            ]);
        }

        $this->cache->set('swarm_task_' . $taskId, [
            'assigned_to' => $selected,
            'requirements' => $requirements,
            'assigned_at' => current_time('mysql'),
        ], 3600);

        return ['status' => 'assigned', 'agents' => $selected];
    }
}
