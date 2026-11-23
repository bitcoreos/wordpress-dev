<?php
/**
 * Deprecated legacy long-running work module compatibility shell.
 *
 * @package BITCORE_Core
 * @since 1.3.0
 * @deprecated Use the canonical /bitcore/v1/tasks endpoint with task_type=milestone.
 * @version     1.0.0
 */

declare(strict_types=1);

/**
 * Deprecated compatibility shell.
 *
 * This class intentionally registers no hooks, post types, taxonomies, or REST
 * bases. Task unification moved this task-family concept to the canonical
 * /bitcore/v1/tasks endpoint.
 */
class BITCORE_Modules_Marathons {
    public const CANONICAL_ENDPOINT = '/bitcore/v1/tasks';

    /** @var mixed */
    private $core;

    public function __construct($core = null) {
        $this->core = $core;
    }

    public function canonical_endpoint(): string {
        return self::CANONICAL_ENDPOINT;
    }

    public function is_active(): bool {
        return false;
    }
}
