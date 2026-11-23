<?php
/**
 * Deprecated task hierarchy compatibility shell.
 *
 * @package BITCORE\Infrastructure\Goals
 * @deprecated Use the canonical /bitcore/v1/tasks endpoint and task_type taxonomy.
 * @since       1.0.0
 * @version     1.0.0
 */

namespace BITCORE\Infrastructure\Goals;

if (!defined('ABSPATH')) exit;

/**
 * Deprecated compatibility shell.
 *
 * This class intentionally performs no storage reads or writes. Hierarchy data
 * now belongs to canonical tasks through /bitcore/v1/tasks.
 */
class GoalHierarchy {
    public const CANONICAL_ENDPOINT = '/bitcore/v1/tasks';

    public function __construct() {
        // Intentionally inert. Kept only to avoid fatal errors for stale includes.
    }

    public function canonical_endpoint(): string {
        return self::CANONICAL_ENDPOINT;
    }

    public function create(array $data) {
        unset($data);
        return false;
    }

    public function update(int $goal_id, array $data): bool {
        unset($goal_id, $data);
        return false;
    }

    public function get(int $goal_id): ?object {
        unset($goal_id);
        return null;
    }

    public function get_children(int $parent_id): array {
        unset($parent_id);
        return [];
    }

    public function get_ancestry(int $goal_id): array {
        unset($goal_id);
        return [];
    }

    public function get_descendants(int $goal_id): array {
        unset($goal_id);
        return [];
    }

    public function get_roots(?string $status = null): array {
        unset($status);
        return [];
    }

    public function get_by_level(string $level, ?string $status = null): array {
        unset($level, $status);
        return [];
    }

    public function get_assigned(string $agent_id, ?string $status = null): array {
        unset($agent_id, $status);
        return [];
    }

    public function delete(int $goal_id, bool $cascade = false): bool {
        unset($goal_id, $cascade);
        return false;
    }
}
