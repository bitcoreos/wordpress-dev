<?php
/**
 * Deprecated goals table compatibility shell.
 *
 * @package BITCORE\Database
 * @deprecated Use the canonical /bitcore/v1/tasks endpoint and agent_task storage.
 */

namespace BITCORE\Database;

if (!defined('ABSPATH')) exit;

/**
 * Deprecated compatibility shell.
 *
 * This class intentionally creates or drops no tables. Task unification moved
 * task-family persistence to canonical tasks through /bitcore/v1/tasks.
 */
class Goals_Table {
    public const CANONICAL_ENDPOINT = '/bitcore/v1/tasks';

    public function __construct() {
        // Intentionally inert. Kept only to avoid fatal errors for stale includes.
    }

    public function canonical_endpoint(): string {
        return self::CANONICAL_ENDPOINT;
    }

    public function create(): bool {
        return false;
    }

    public function drop(): bool {
        return false;
    }
}
