<?php

declare(strict_types=1);

/**
 * BITCORE Logging Infrastructure - Processor Interface
 *
 * @package BITCORE\Infrastructure\Logging
 * @since 1.0.0
 * @version     1.0.0
 */

namespace BITCORE\Infrastructure\Logging;

/**
 * Log Processor Interface
 *
 * Processors modify log records by adding extra contextual data.
 * They are invoked in sequence before the record reaches handlers.
 *
 * @since 1.0.0
 */
interface ProcessorInterface
{
    /**
     * Process a log record.
     *
     * @param array<string, mixed> $record The log record to process.
     * @return array<string, mixed> The modified log record.
     * @since 1.0.0
     */
    public function __invoke(array $record): array;
}
