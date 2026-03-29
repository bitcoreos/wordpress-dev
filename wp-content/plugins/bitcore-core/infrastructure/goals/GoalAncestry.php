<?php
/**
 * Goal Ancestry Utilities
 *
 * Provides utilities for traversing and querying goal ancestry.
 * Ported from Paperclip's goal ancestry patterns.
 *
 * @package BITCORE\Infrastructure\Goals
 */

namespace BITCORE\Infrastructure\Goals;

if (!defined('ABSPATH')) exit;

/**
 * Class GoalAncestry
 *
 * Utility class for ancestry path operations.
 */
class GoalAncestry {
    /** @var GoalHierarchy */
    private GoalHierarchy $hierarchy;

    /**
     * Constructor
     *
     * @param GoalHierarchy $hierarchy Hierarchy manager instance
     */
    public function __construct(GoalHierarchy $hierarchy) {
        $this->hierarchy = $hierarchy;
    }

    /**
     * Get root ancestor (top-level mission)
     *
     * @param int $goal_id Goal ID
     * @return object|null Root goal or null
     */
    public function get_root(int $goal_id): ?object {
        $ancestry = $this->hierarchy->get_ancestry($goal_id);
        return !empty($ancestry) ? end($ancestry) : null;
    }

    /**
     * Get depth in hierarchy
     *
     * @param int $goal_id Goal ID
     * @return int Depth (0 = root)
     */
    public function get_depth(int $goal_id): int {
        $goal = $this->hierarchy->get($goal_id);
        return $goal ? ($goal->depth ?? 0) : 0;
    }

    /**
     * Check if goal is descendant of another
     *
     * @param int $potential_descendant Goal ID to check
     * @param int $potential_ancestor Potential ancestor ID
     * @return bool True if descendant
     */
    public function is_descendant_of(int $potential_descendant, int $potential_ancestor): bool {
        $ancestry = $this->hierarchy->get_ancestry($potential_descendant);

        foreach ($ancestry as $ancestor) {
            if ($ancestor->id === $potential_ancestor) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if goal is ancestor of another
     *
     * @param int $potential_ancestor Goal ID to check
     * @param int $potential_descendant Potential descendant ID
     * @return bool True if ancestor
     */
    public function is_ancestor_of(int $potential_ancestor, int $potential_descendant): bool {
        return $this->is_descendant_of($potential_descendant, $potential_ancestor);
    }

    /**
     * Get siblings (goals with same parent)
     *
     * @param int $goal_id Goal ID
     * @return array Sibling goals
     */
    public function get_siblings(int $goal_id): array {
        $goal = $this->hierarchy->get($goal_id);

        if (!$goal || $goal->parent_id === null) {
            return [];
        }

        $siblings = $this->hierarchy->get_children($goal->parent_id);

        return array_filter($siblings, function($sibling) use ($goal_id) {
            return $sibling->id !== $goal_id;
        });
    }

    /**
     * Get full tree from root
     *
     * @param int $goal_id Goal ID
     * @return array Nested tree structure
     */
    public function get_tree(int $goal_id): array {
        $root = $this->get_root($goal_id);

        if (!$root) {
            return [];
        }

        return $this->build_tree($root);
    }

    /**
     * Build nested tree structure
     *
     * @param object $goal Goal record
     * @return array Tree node
     */
    private function build_tree(object $goal): array {
        $node = [
            'id' => $goal->id,
            'title' => $goal->title,
            'level' => $goal->level,
            'status' => $goal->status,
            'children' => []
        ];

        $children = $this->hierarchy->get_children($goal->id);

        foreach ($children as $child) {
            $node['children'][] = $this->build_tree($child);
        }

        return $node;
    }

    /**
     * Get ancestry path as string
     *
     * @param int $goal_id Goal ID
     * @param string $separator Path separator
     * @return string Path string
     */
    public function get_path_string(int $goal_id, string $separator = ' > '): string {
        $ancestry = $this->hierarchy->get_ancestry($goal_id);

        $titles = array_map(function($goal) {
            return $goal->title;
        }, array_reverse($ancestry));

        return implode($separator, $titles);
    }

    /**
     * Count all descendants
     *
     * @param int $goal_id Goal ID
     * @return int Descendant count
     */
    public function count_descendants(int $goal_id): int {
        return count($this->hierarchy->get_descendants($goal_id));
    }

    /**
     * Get leaf nodes (goals with no children)
     *
     * @param int $goal_id Starting goal ID
     * @return array Leaf goal IDs
     */
    public function get_leaves(int $goal_id): array {
        $descendants = $this->hierarchy->get_descendants($goal_id);
        $leaves = []
;        
        foreach ($descendants as $descendant) {
            $children = $this->hierarchy->get_children($descendant->id);
            if (empty($children)) {
                $leaves[] = $descendant;
            }
        }

        // Check if starting goal itself is a leaf
        $root_children = $this->hierarchy->get_children($goal_id);
        if (empty($root_children)) {
            $root = $this->hierarchy->get($goal_id);
            if ($root) {
                $leaves[] = $root;
            }
        }

        return $leaves;
    }
}
