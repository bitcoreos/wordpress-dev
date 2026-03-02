<?php

declare(strict_types=1);

/**
 * BITCORE Logging Infrastructure - Handler Interface
 *
 * @package BITCORE\Infrastructure\Logging
 * @since 1.0.0
 */

namespace BITCORE\Infrastructure\Logging;

/**
 * Log Handler Interface
 *
 * Defines the contract for log handlers that process log records.
 * Handlers are responsible for persisting or outputting log entries.
 *
 * @since 1.0.0
 */
interface HandlerInterface
{
    /**
     * Handle a log record.
     *
     * @param array<string, mixed> $record The log record to handle.
     *                                   Contains: level, message, context, datetime, channel, etc.
     * @return bool True if the record was handled, false otherwise.
     * @since 1.0.0
     */
    public function handle(array $record): bool;

    /**
     * Check if this handler handles the given record level.
     *
     * @param array<string, mixed> $record The log record to check.
     * @return bool True if the handler should process this record.
     * @since 1.0.0
     */
    public function isHandling(array $record): bool;
}
