<?php
/**
 * Flat agent role registry.
 *
 * P3.1 intentionally removes manager/reporting hierarchy for the current
 * Aria/Forge/Muse agent set. Role assignment remains available for access
 * control and UI labeling; tree operations are inert compatibility methods.
 *
 * @package BITCORE\Infrastructure\Coordination
 */

namespace BITCORE\Infrastructure\Coordination;

if (!defined('ABSPATH')) exit;

/**
 * Class OrgChart
 *
 * Stores flat agent roles only. No boss, reports, chain-of-command, or
 * recursive broadcast topology is maintained.
 */
class OrgChart {
    /** @var array<string, string> agent_id => role */
    private array $roles = [];

    const ROLE_CEO = 'ceo';
    const ROLE_MANAGER = 'manager';
    const ROLE_WORKER = 'worker';
    const ROLE_SPECIALIST = 'specialist';

    /**
     * Assign a flat role to an agent.
     *
     * The third parameter is accepted for backward call compatibility but is
     * deliberately ignored. P3.1 forbids hierarchy state.
     */
    public function assign_role(string $agent_id, string $role, ?string $boss_id = null): bool {
        if (!in_array($role, [self::ROLE_CEO, self::ROLE_MANAGER, self::ROLE_WORKER, self::ROLE_SPECIALIST], true)) {
            return false;
        }

        $this->roles[$agent_id] = $role;
        return true;
    }

    /**
     * Flat roles have no direct reports.
     */
    public function get_reports(string $agent_id): array {
        return [];
    }

    /**
     * Flat roles have no boss.
     */
    public function get_boss(string $agent_id): ?string {
        return null;
    }

    /**
     * Flat roles have no command chain beyond the agent itself.
     */
    public function get_chain_of_command(string $agent_id): array {
        return [$agent_id];
    }

    /**
     * Flat roles have no recursive subordinates.
     */
    public function get_all_subordinates(string $agent_id): array {
        return [];
    }

    /**
     * Flat roles do not support hierarchy broadcast targets.
     */
    public function broadcast_to_reports(string $boss_id, array $message): array {
        return [];
    }

    /**
     * Get agent role.
     */
    public function get_role(string $agent_id): ?string {
        return $this->roles[$agent_id] ?? null;
    }

    /**
     * Flat roles are never managers by report count.
     */
    public function is_manager(string $agent_id): bool {
        return false;
    }

    /**
     * Remove an agent role assignment.
     */
    public function remove_agent(string $agent_id): bool {
        if (!isset($this->roles[$agent_id])) {
            return false;
        }

        unset($this->roles[$agent_id]);
        return true;
    }

    /**
     * Get all agent IDs with assigned roles.
     */
    public function get_all_agents(): array {
        return array_keys($this->roles);
    }

    /**
     * Get agent IDs by flat role.
     */
    public function get_agents_by_role(string $role): array {
        return array_keys($this->roles, $role, true);
    }

    /**
     * Check if an agent has a role assignment.
     */
    public function has_agent(string $agent_id): bool {
        return isset($this->roles[$agent_id]);
    }
}
