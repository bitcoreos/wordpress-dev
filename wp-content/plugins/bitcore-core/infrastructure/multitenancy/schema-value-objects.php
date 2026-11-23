<?php
/**
 * Schema Value Objects
 *
 * Data structures for multi-tenancy schema management.
 *
 * @package BITCORE\Infrastructure\MultiTenancy
 * @since 1.0.0
 * @version     1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Infrastructure\MultiTenancy;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Schema Version DTO
 *
 * Immutable value object representing a migration schema version entry.
 * Tracks application metadata including timing and errors.
 *
 * @since 1.0.0
 */
final class SchemaVersion {

    /**
     * Migration version identifier
     *
     * @var string
     * @since 1.0.0
     */
    public readonly string $version;

    /**
     * Timestamp when migration was applied
     *
     * @var DateTimeImmutable|null
     * @since 1.0.0
     */
    public readonly ?DateTimeImmutable $appliedAt;

    /**
     * Duration of migration execution in milliseconds
     *
     * @var int
     * @since 1.0.0
     */
    public readonly int $duration;

    /**
     * Error message if migration failed
     *
     * @var string|null
     * @since 1.0.0
     */
    public readonly ?string $error;

    /**
     * Constructor
     *
     * @param string $version Migration version identifier
     * @param DateTimeImmutable|null $appliedAt Timestamp when applied
     * @param int $duration Execution duration in milliseconds
     * @param string|null $error Error message if failed
     * @since 1.0.0
     */
    public function __construct(
        string $version,
        ?DateTimeImmutable $appliedAt = null,
        int $duration = 0,
        ?string $error = null
    ) {
        if (empty($version)) {
            throw new InvalidArgumentException('Schema version cannot be empty');
        }

        $this->version = $version;
        $this->appliedAt = $appliedAt;
        $this->duration = $duration;
        $this->error = $error;
    }

    /**
     * Check if migration was successful
     *
     * @return bool True if no error occurred
     * @since 1.0.0
     */
    public function isSuccessful(): bool {
        return $this->error === null;
    }

    /**
     * Check if migration has been applied
     *
     * @return bool True if appliedAt is set
     * @since 1.0.0
     */
    public function isApplied(): bool {
        return $this->appliedAt !== null;
    }

    /**
     * Convert to array
     *
     * @return array<string, mixed> Array representation
     * @since 1.0.0
     */
    public function toArray(): array {
        return [
            'version' => $this->version,
            'appliedAt' => $this->appliedAt?->format('c'),
            'duration' => $this->duration,
            'error' => $this->error,
        ];
    }
}
