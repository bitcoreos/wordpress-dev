<?php
/**
 * Goal Hierarchy Management
 *
 * Manages hierarchical goal structures: Mission -> Objective -> Task.
 * Ported from Paperclip's goal ancestry patterns.
 *
 * @package BITCORE\Infrastructure\Goals
 */

namespace BITCORE\Infrastructure\Goals;

if (!defined('ABSPATH')) exit;

/**
 * Class GoalHierarchy
 *
 * Manages goal trees with ancestry tracking.
 */
class GoalHierarchy {
    /** @var string Goals table name */
    private string $table_name;

    const LEVEL_MISSION = 'mission';
    const LEVEL_OBJECTIVE = 'objective';
    const LEVEL_TASK = 'task';

    const STATUS_DRAFT = 'draft';
    const STATUS_ACTIVE = 'active';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_BLOCKED = 'blocked';

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'bitcore_goals';
    }

    /**
     * Create a new goal
     *
     * @param array $data Goal data
     * @return int|false Goal ID or false on failure
     */
    public function create(array $data) {
        global $wpdb;

        $defaults = [
            'title' => '',
            'description' => '',
            'level' => self::LEVEL_TASK,
            'status' => self::STATUS_DRAFT,
            'parent_id' => null,
            'assigned_agent_id' => null,
            'priority' => 5,
            'metadata' => '{}',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];

        $data = array_merge($defaults, $data);

        $result = $wpdb->insert($this->table_name, $data);

        if ($result === false) {
            return false;
        }

        $goal_id = $wpdb->insert_id;

        // Update ancestry path
        $this->update_ancestry($goal_id, $data['parent_id']);

        return $goal_id;
    }

    /**
     * Update goal
     *
     * @param int $goal_id Goal ID
     * @param array $data Update data
     * @return bool Success status
     */
    public function update(int $goal_id, array $data): bool {
        global $wpdb;

        $data['updated_at'] = current_time('mysql');

        // If parent changed, update ancestry
        if (isset($data['parent_id'])) {
            $this->update_ancestry($goal_id, $data['parent_id']);
        }

        $result = $wpdb->update(
            $this->table_name,
            $data,
            ['id' => $goal_id]
        );

        return $result !== false;
    }

    /**
     * Get goal by ID
     *
     * @param int $goal_id Goal ID
     * @return object|null Goal record or null
     */
    public function get(int $goal_id): ?object {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $goal_id
        ));
    }

    /**
     * Get children of a goal
     *
     * @param int $parent_id Parent goal ID
     * @return array List of child goals
     */
    public function get_children(int $parent_id): array {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE parent_id = %d ORDER BY priority ASC, created_at ASC",
            $parent_id
        ));
    }

    /**
     * Get full ancestry chain
     *
     * @param int $goal_id Goal ID
     * @return array Ancestry chain (goal first, root last)
     */
    public function get_ancestry(int $goal_id): array {
        global $wpdb;

        $ancestry = [];
        $current_id = $goal_id;
        $visited = [];

        while ($current_id !== null) {
            if (in_array($current_id, $visited, true)) {
                break; // Prevent infinite loops
            }
            $visited[] = $current_id;

            $goal = $this->get($current_id);
            if (!$goal) {
                break;
            }

            $ancestry[] = $goal;
            $current_id = $goal->parent_id;
        }

        return $ancestry;
    }

    /**
     * Get all descendants
     *
     * @param int $goal_id Goal ID
     * @return array All descendant goals
     */
    public function get_descendants(int $goal_id): array {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE ancestry_path LIKE %s ORDER BY level ASC, priority ASC",
            '%"' . $goal_id . '"%'
        ));
    }

    /**
     * Update ancestry path for a goal
     *
     * @param int $goal_id Goal ID
     * @param int|null $parent_id Parent goal ID
     */
    private function update_ancestry(int $goal_id, ?int $parent_id): void {
        global $wpdb;

        $ancestry_path = '[]';
        $depth = 0;

        if ($parent_id !== null) {
            $parent = $this->get($parent_id);
            if ($parent) {
                $parent_path = json_decode($parent->ancestry_path ?? '[]', true);
                $parent_path[] = $parent_id;
                $ancestry_path = json_encode($parent_path);
                $depth = ($parent->depth ?? 0) + 1;
            }
        }

        $wpdb->update(
            $this->table_name,
            [
                'ancestry_path' => $ancestry_path,
                'depth' => $depth
            ],
            ['id' => $goal_id]
        );
    }

    /**
     * Get root goals (missions)
     *
     * @param string|null $status Optional status filter
     * @return array List of root goals
     */
    public function get_roots(?string $status = null): array {
        global $wpdb;

        if ($status) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE parent_id IS NULL AND status = %s ORDER BY priority ASC",
                $status
            ));
        }

        return $wpdb->get_results(
            "SELECT * FROM {$this->table_name} WHERE parent_id IS NULL ORDER BY priority ASC"
        );
    }

    /**
     * Get goals by level
     *
     * @param string $level Level constant
     * @param string|null $status Optional status filter
     * @return array List of goals
     */
    public function get_by_level(string $level, ?string $status = null): array {
        global $wpdb;

        if ($status) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE level = %s AND status = %s ORDER BY priority ASC",
                $level, $status
            ));
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE level = %s ORDER BY priority ASC",
            $level
        ));
    }

    /**
     * Get goals assigned to agent
     *
     * @param string $agent_id Agent identifier
     * @param string|null $status Optional status filter
     * @return array List of assigned goals
     */
    public function get_assigned(string $agent_id, ?string $status = null): array {
        global $wpdb;

        if ($status) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE assigned_agent_id = %s AND status = %s ORDER BY priority ASC",
                $agent_id, $status
            ));
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE assigned_agent_id = %s ORDER BY priority ASC",
            $agent_id
        ));
    }

    /**
     * Calculate completion percentage
     *
     * @param int $goal_id Goal ID
     * @return float Completion percentage
     */
    public function get_completion_percentage(int $goal_id): float {
        $children = $this->get_children($goal_id);

        if (empty($children)) {
            $goal = $this->get($goal_id);
            return ($goal && $goal->status === self::STATUS_COMPLETED) ? 100.0 : 0.0;
        }

        $completed = 0;
        foreach ($children as $child) {
            if ($child->status === self::STATUS_COMPLETED) {
                $completed++;
            }
        }

        return ($completed / count($children)) * 100;
    }

    /**
     * Delete goal (and optionally descendants)
     *
     * @param int $goal_id Goal ID
     * @param bool $cascade Delete descendants too
     * @return bool Success status
     */
    public function delete(int $goal_id, bool $cascade = false): bool {
        global $wpdb;

        if ($cascade) {
            $descendants = $this->get_descendants($goal_id);
            foreach ($descendants as $descendant) {
                $wpdb->delete($this->table_name, ['id' => $descendant->id]);
            }
        }

        $result = $wpdb->delete($this->table_name, ['id' => $goal_id]);

        return $result !== false;
    }
}
