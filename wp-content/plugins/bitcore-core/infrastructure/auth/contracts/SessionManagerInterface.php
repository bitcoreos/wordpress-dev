<?php
/**
 * SessionManagerInterface implementation
 *
 * @package    Bitcore
 * @since      2026.03.01
 * @version    1.3.0
 * @author     hyperion
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

namespace BITCORE\Infrastructure\Auth\Contracts;

/**
 * Interface for session management operations.
 *
 * Provides an adapter pattern wrapper around WordPress session handling
 * with additional typing and safety guarantees.
 *
 * @since 1.0.0
 * @package BITCORE\Infrastructure\Auth\Contracts
 */
interface SessionManagerInterface {

/**
 * Start a new session or resume existing session.
 *
 * @since 1.0.0
 * @return void
 * @throws \RuntimeException If session cannot be started.
 */
public function start(): void;

/**
 * Destroy the current session and all its data.
 *
 * @since 1.0.0
 * @return void
 */
public function destroy(): void;

/**
 * Get a value from the session.
 *
 * @since 1.0.0
 * @param string $key The key to retrieve.
 * @param mixed  $default Default value if key does not exist.
 * @return mixed The session value or default.
 */
public function get(string $key, mixed $default = null): mixed;

/**
 * Set a value in the session.
 *
 * @since 1.0.0
 * @param string $key The key to set.
 * @param mixed  $value The value to store.
 * @return void
 */
public function set(string $key, mixed $value): void;

/**
 * Check if a key exists in the session.
 *
 * @since 1.0.0
 * @param string $key The key to check.
 * @return bool True if key exists, false otherwise.
 */
public function has(string $key): bool;

/**
 * Regenerate the session ID.
 *
 * @since 1.0.0
 * @return string The new session ID.
 * @throws \RuntimeException If session cannot be regenerated.
 */
public function regenerate(): string;
}
