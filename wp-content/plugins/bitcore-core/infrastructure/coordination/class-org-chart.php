<?php
/**
 * Org Chart Hierarchy Management
 * 
 * Provides agent hierarchy management with reportsTo relationships.
 * Ported from Paperclip's org chart patterns.
 *
 * @package BITCORE\Infrastructure\Coordination
 */

namespace BITCORE\Infrastructure\Coordination;

if (!defined('ABSPATH')) exit;

/**
 * Class OrgChart
 * 
 * Manages hierarchical agent relationships for swarm coordination.
 */
class OrgChart {
    /** @var array<string, string> agent_id => boss_id */
    private array $hierarchy = [];

    /** @var array<string, string> agent_id => role */
    private array $roles = [];

    /** @var array<string, array> boss_id => [subordinate_ids] */
    private array $reports = [];

    const ROLE_CEO = 'ceo';
    const ROLE_MANAGER = 'manager';
    const ROLE_WORKER = 'worker';
    const ROLE_SPECIALIST = 'specialist';

    /**
     * Assign role and reporting relationship to agent
     *
     * @param string $agent_id Agent identifier
     * @param string $role Role constant (ceo, manager, worker, specialist)
     * @param string|null $boss_id Optional boss/manager ID
     * @return bool Success status
     */
    public function assign_role(string $agent_id, string $role, ?string $boss_id = null): bool {
        // Validate role
        if (!in_array($role, [self::ROLE_CEO, self::ROLE_MANAGER, self::ROLE_WORKER, self::ROLE_SPECIALIST], true)) {
            return false;
        }

        $this->roles[$agent_id] = $role;

        if ($boss_id !== null) {
            $this->hierarchy[$agent_id] = $boss_id;
            if (!isset($this->reports[$boss_id])) {
                $this->reports[$boss_id] = [];
            }
            $this->reports[$boss_id][] = $agent_id;
        }

        return true;
    }

    /**
     * Get direct reports for a manager
     *
     * @param string $agent_id Manager agent ID
     * @return array List of direct report IDs
     */
    public function get_reports(string $agent_id): array {
        return $this->reports[$agent_id] ?? [];
    }

    /**
     * Get boss for an agent
     *
     * @param string $agent_id Agent identifier
     * @return string|null Boss ID or null if none
     */
    public function get_boss(string $agent_id): ?string {
        return $this->hierarchy[$agent_id] ?? null;
    }

    /**
     * Get chain of command from agent to CEO
     *
     * @param string $agent_id Starting agent ID
     * @return array Chain of command (agent first, CEO last)
     */
    public function get_chain_of_command(string $agent_id): array {
        $chain = [$agent_id];
        $current = $agent_id;
        $visited = [$agent_id];

        while (isset($this->hierarchy[$current])) {
            $boss = $this->hierarchy[$current];
            if (in_array($boss, $visited, true)) {
                break; // Prevent cycles
            }
            $chain[] = $boss;
            $visited[] = $boss;
            $current = $boss;
        }

        return $chain;
    }

    /**
     * Get all subordinates (recursive)
     *
     * @param string $agent_id Manager agent ID
     * @return array All subordinate IDs (direct and indirect)
     */
    public function get_all_subordinates(string $agent_id): array {
        $subordinates = [];
        $queue = $this->reports[$agent_id] ?? [];

        while (!empty($queue)) {
            $sub = array_shift($queue);
            $subordinates[] = $sub;
            if (isset($this->reports[$sub])) {
                $queue = array_merge($queue, $this->reports[$sub]);
            }
        }

        return $subordinates;
    }

    /**
     * Broadcast message to all reports
     *
     * @param string $boss_id Originating manager ID
     * @param array $message Message payload
     * @return array Results keyed by subordinate ID
     */
    public function broadcast_to_reports(string $boss_id, array $message): array {
        $results = [];
        $reports = $this->get_all_subordinates($boss_id);

        foreach ($reports as $subordinate_id) {
            /**
             * Filter: bitcore_org_broadcast
             * 
             * @param array $message Original message
             * @param string $subordinate_id Target agent ID
             * @param string $boss_id Originating manager ID
             */
            $results[$subordinate_id] = apply_filters(
                'bitcore_org_broadcast',
                $message,
                $subordinate_id,
                $boss_id
            );
        }

        return $results;
    }

    /**
     * Get agent role
     *
     * @param string $agent_id Agent identifier
     * @return string|null Role constant or null
     */
    public function get_role(string $agent_id): ?string {
        return $this->roles[$agent_id] ?? null;
    }

    /**
     * Check if agent is manager
     *
     * @param string $agent_id Agent identifier
     * @return bool True if has direct reports
     */
    public function is_manager(string $agent_id): bool {
        return isset($this->reports[$agent_id]) && !empty($this->reports[$agent_id]);
    }

    /**
     * Remove agent from hierarchy
     *
     * @param string $agent_id Agent to remove
     * @return bool Success status
     */
    public function remove_agent(string $agent_id): bool {
        if (!isset($this->roles[$agent_id])) {
            return false;
        }

        // Remove from boss's reports
        if (isset($this->hierarchy[$agent_id])) {
            $boss_id = $this->hierarchy[$agent_id];
            if (isset($this->reports[$boss_id])) {
                $this->reports[$boss_id] = array_values(
                    array_diff($this->reports[$boss_id], [$agent_id])
                );
            }
        }

        // Reassign subordinates to boss (if any)
        if (isset($this->reports[$agent_id]) && !empty($this->reports[$agent_id])) {
            $new_boss = $this->hierarchy[$agent_id] ?? null;
            foreach ($this->reports[$agent_id] as $subordinate) {
                unset($this->hierarchy[$subordinate]);
                if ($new_boss !== null) {
                    $this->hierarchy[$subordinate] = $new_boss;
                    if (!isset($this->reports[$new_boss])) {
                        $this->reports[$new_boss] = [];
                    }
                    $this->reports[$new_boss][] = $subordinate;
                }
            }
        }

        unset($this->roles[$agent_id]);
        unset($this->hierarchy[$agent_id]);
        unset($this->reports[$agent_id]);

        return true;
    }

    /**
     * Get all agents in the org
     *
     * @return array Agent IDs
     */
    public function get_all_agents(): array {
        return array_keys($this->roles);
    }

    /**
     * Get agents by role
     *
     * @param string $role Role constant
     * @return array Agent IDs with matching role
     */
    public function get_agents_by_role(string $role): array {
        return array_keys($this->roles, $role, true);
    }

    /**
     * Check if agent exists in org
     *
     * @param string $agent_id Agent identifier
     * @return bool Existence status
     */
    public function has_agent(string $agent_id): bool {
        return isset($this->roles[$agent_id]);
    }
}
