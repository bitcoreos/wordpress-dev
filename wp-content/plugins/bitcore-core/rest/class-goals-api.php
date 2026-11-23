<?php
/**
 * Deprecated legacy goal REST controller compatibility shell.
 *
 * @package BITCORE\Rest
 * @deprecated Use the canonical /bitcore/v1/tasks endpoint with task_type=goal.
 * @since       1.0.0
 * @version     1.0.0
 */

namespace BITCORE\Rest;

if (!defined('ABSPATH')) exit;

/**
 * Deprecated compatibility shell.
 *
 * This class intentionally registers no routes. Task unification moved goals,
 * milestones, and sprints to the canonical /bitcore/v1/tasks endpoint.
 */
class Goals_Api {
    public const CANONICAL_ENDPOINT = '/bitcore/v1/tasks';

    public function __construct() {
        // Intentionally inert. Kept only to avoid fatal errors for stale includes.
    }

    public function register_routes(): void {
        // Intentionally inert. Legacy routes are not exposed.
    }

    public function canonical_endpoint(): string {
        return self::CANONICAL_ENDPOINT;
    }
}
