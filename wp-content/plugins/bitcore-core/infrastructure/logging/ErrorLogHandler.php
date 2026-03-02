<?php

declare(strict_types=1);

/**
 * BITCORE Logging Infrastructure - Error Log Handler
 *
 * @package BITCORE\Infrastructure\Logging
 * @since 1.0.0
 */

namespace BITCORE\Infrastructure\Logging;

use DateTimeImmutable;

/**
 * Error Log Handler
 *
 * Writes log records to PHP's error_log() function.
 * Suitable for capturing critical and error level messages
 * in environments without file write access.
 *
 * @since 1.0.0
 */
class ErrorLogHandler implements HandlerInterface
{
    /** @var array<string, int> Log level priority mapping */
    private const LEVELS = [
        'debug'     => 100,
        'info'      => 200,
        'notice'    => 250,
        'warning'   => 300,
        'error'     => 400,
        'critical'  => 500,
        'alert'     => 550,
        'emergency' => 600,
    ];

    /** @var int Minimum level priority to handle */
    private int $level;

    /**
     * Constructor.
     *
     * @param string $level Minimum level to handle (default: 'error').
     * @since 1.0.0
     */
    public function __construct(string $level = 'error')
    {
        $this->level = self::LEVELS[$level] ?? self::LEVELS['error'];
    }

    /**
     * Check if this handler handles the given record level.
     *
     * @param array<string, mixed> $record The log record to check.
     * @return bool True if the handler should process this record.
     * @since 1.0.0
     */
    public function isHandling(array $record): bool
    {
        $recordLevel = self::LEVELS[$record['level'] ?? 'debug'] ?? self::LEVELS['debug'];
        return $recordLevel >= $this->level;
    }

    /**
     * Handle a log record via error_log().
     *
     * @param array<string, mixed> $record The log record to handle.
     * @return bool True if the record was handled successfully.
     * @since 1.0.0
     */
    public function handle(array $record): bool
    {
        if (!$this->isHandling($record)) {
            return false;
        }

        $message = $this->formatRecord($record);
        error_log($message);

        return true;
    }

    /**
     * Format a log record for error_log.
     *
     * @param array<string, mixed> $record The log record.
     * @return string Formatted message.
     * @since 1.0.0
     */
    private function formatRecord(array $record): string
    {
        $channel = $record['channel'] ?? 'bitcore';
        $level   = strtoupper($record['level'] ?? 'INFO');
        $message = $record['message'] ?? '';
        
        $datetime = ($record['datetime'] instanceof DateTimeImmutable)
            ? $record['datetime']->format('Y-m-d H:i:s')
            : (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $formatted = sprintf(
            '[%s] %s.%s: %s',
            $datetime,
            $channel,
            $level,
            $message
        );

        // Add context if present
        if (!empty($record['context'])) {
            $context = json_encode($record['context'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $formatted .= ' | Context: ' . $context;
        }

        // Add tenant_id if present
        if (!empty($record['tenant_id'])) {
            $formatted .= ' | Tenant: ' . $record['tenant_id'];
        }

        return $formatted;
    }
}
