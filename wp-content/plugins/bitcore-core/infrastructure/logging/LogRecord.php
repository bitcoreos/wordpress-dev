<?php

declare(strict_types=1);

/**
 * BITCORE Logging Infrastructure - Log Record DTO
 *
 * @package BITCORE\Infrastructure\Logging
 * @since 1.0.0
 */

namespace BITCORE\Infrastructure\Logging;

use DateTimeImmutable;
use JsonException;

/**
 * Immutable Log Record Data Transfer Object
 *
 * Represents a single log entry with all contextual information.
 * Uses readonly properties for immutability.
 *
 * @since 1.0.0
 */
final class LogRecord
{
    /**
     * @param string $level Log level (debug, info, notice, warning, error, critical, alert, emergency).
     * @param string $message The log message, may contain placeholders.
     * @param array<string, mixed> $context Contextual data for message interpolation.
     * @param DateTimeImmutable $datetime When the log was created.
     * @param string $channel The logging channel.
     * @param int|null $tenantId Optional tenant ID for multi-tenant context.
     * @param array<string, mixed> $extra Additional metadata added by processors.
     */
    public function __construct(
        public readonly string $level,
        public readonly string $message,
        public readonly array $context,
        public readonly DateTimeImmutable $datetime,
        public readonly string $channel,
        public readonly ?int $tenantId = null,
        public readonly array $extra = []
    ) {
    }

    /**
     * Convert the log record to a JSON string.
     *
     * @param bool $prettyPrint Whether to format with indentation (default: false).
     * @return string JSON representation of the log record.
     * @throws JsonException If JSON encoding fails.
     * @since 1.0.0
     */
    public function toJson(bool $prettyPrint = false): string
    {
        $data = [
            'datetime' => $this->datetime->format('c'),
            'channel'  => $this->channel,
            'level'    => $this->level,
            'message'  => $this->message,
            'context'  => $this->context,
        ];

        if ($this->tenantId !== null) {
            $data['tenant_id'] = $this->tenantId;
        }

        if (!empty($this->extra)) {
            $data['extra'] = $this->extra;
        }

        $flags = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        
        if ($prettyPrint) {
            $flags |= JSON_PRETTY_PRINT;
        }

        return json_encode($data, $flags);
    }

    /**
     * Convert the log record to an array.
     *
     * @return array<string, mixed> Array representation of the log record.
     * @since 1.0.0
     */
    public function toArray(): array
    {
        return [
            'level'     => $this->level,
            'message'   => $this->message,
            'context'   => $this->context,
            'datetime'  => $this->datetime,
            'channel'   => $this->channel,
            'tenant_id' => $this->tenantId,
            'extra'     => $this->extra,
        ];
    }
}
