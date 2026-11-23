<?php
/**
 * Swarm Monitor - Agent health and status monitoring
 *
 * @package BITCORE_Core\CommandCenter
 * @since 1.4.0
 * @version     1.0.0
 */

declare(strict_types=1);

class BITCORE_Swarm_Monitor {

    private array $agents = [];
    private string $health_table;

    public function __construct() {
        global $wpdb;
        $this->health_table = $wpdb->prefix . 'bitcore_agent_health';
    }

    public function register_agent(string $id, string $name, array $capabilities): void {
        $this->agents[$id] = [
            'id' => $id,
            'name' => $name,
            'capabilities' => $capabilities,
            'status' => 'idle',
            'last_seen' => time(),
            'tasks_completed' => 0,
            'tasks_failed' => 0,
        ];
    }

    public function update_status(string $agent_id, string $status): void {
        if (isset($this->agents[$agent_id])) {
            $this->agents[$agent_id]['status'] = $status;
            $this->agents[$agent_id]['last_seen'] = time();
        }
    }

    public function get_agent(string $id): ?array {
        return $this->agents[$id] ?? null;
    }

    public function get_all_agents(): array {
        return $this->agents;
    }

    public function get_agents_by_capability(string $capability): array {
        return array_filter($this->agents, fn($a) => in_array($capability, $a['capabilities']));
    }

    public function get_health_summary(): array {
        $total = count($this->agents);
        $active = count(array_filter($this->agents, fn($a) => $a['status'] === 'active'));
        $idle = count(array_filter($this->agents, fn($a) => $a['status'] === 'idle'));
        $error = count(array_filter($this->agents, fn($a) => $a['status'] === 'error'));

        return [
            'total_agents' => $total,
            'active' => $active,
            'idle' => $idle,
            'error' => $error,
            'health_score' => $total > 0 ? round(($active + $idle) / $total * 100, 1) : 0,
        ];
    }

    public function record_task_result(string $agent_id, bool $success): void {
        if (isset($this->agents[$agent_id])) {
            if ($success) {
                $this->agents[$agent_id]['tasks_completed']++;
            } else {
                $this->agents[$agent_id]['tasks_failed']++;
            }
        }
    }
}
