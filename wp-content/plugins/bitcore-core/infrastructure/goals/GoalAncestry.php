<?php
/**
 * Deprecated task ancestry compatibility shell.
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
 * This class intentionally computes no legacy tree state. Task-family structure
 * now belongs to canonical tasks through /bitcore/v1/tasks.
 */
class GoalAncestry {
    public const CANONICAL_ENDPOINT = '/bitcore/v1/tasks';

    private GoalHierarchy $hierarchy;

    public function __construct(GoalHierarchy $hierarchy) {
        $this->hierarchy = $hierarchy;
    }

    public function canonical_endpoint(): string {
        return self::CANONICAL_ENDPOINT;
    }

    public function get_root(int $goal_id): ?object {
        unset($goal_id);
        return null;
    }

    public function get_depth(int $goal_id): int {
        unset($goal_id);
        return 0;
    }

    public function is_descendant_of(int $potential_descendant, int $potential_ancestor): bool {
        unset($potential_descendant, $potential_ancestor);
        return false;
    }

    public function is_ancestor_of(int $potential_ancestor, int $potential_descendant): bool {
        unset($potential_ancestor, $potential_descendant);
        return false;
    }

    public function get_siblings(int $goal_id): array {
        unset($goal_id);
        return [];
    }

    public function get_tree(int $goal_id): array {
        unset($goal_id);
        return [];
    }

    public function get_path_string(int $goal_id, string $separator = ' > '): string {
        unset($goal_id, $separator);
        return '';
    }

    public function count_descendants(int $goal_id): int {
        unset($goal_id);
        return 0;
    }

    public function get_leaves(int $goal_id): array {
        unset($goal_id);
        return [];
    }
}
