<?php
/**
 * Security Audit Logger
 *
 * Provides comprehensive security event logging with structured data,
 * PSR-3 compatibility, and tamper-resistant log entries.
 * Ported from OpenClaw-OPS-Suite audit patterns.
 *
 * @package BITCORE\Infrastructure\Security
 * @since 1.0.0
 * @version     1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Infrastructure\Security;

use BITCORE\Infrastructure\Logging\Logger;
use BITCORE\Infrastructure\Auth\TenantContext;

// ============================================================================
// AUDIT EVENT TYPES
// ============================================================================

/**
 * Enumeration of audit event types.
 *
 * @package BITCORE\Infrastructure\Security
 * @since 1.0.0
 */
enum AuditEventType: string {
    case AUTH_SUCCESS = 'auth.success';
    case AUTH_FAILURE = 'auth.failure';
    case AUTH_LOGOUT = 'auth.logout';
    case ACCESS_DENIED = 'access.denied';
    case RATE_LIMIT_HIT = 'ratelimit.hit';
    case SUSPICIOUS_ACTIVITY = 'security.suspicious';
    case CONFIG_CHANGE = 'config.change';
    case DATA_EXPORT = 'data.export';
    case ADMIN_ACTION = 'admin.action';
    case API_KEY_ROTATION = 'apikey.rotation';
    case PERMISSION_CHANGE = 'permission.change';
    case FILE_ACCESS = 'file.access';
    case DATABASE_QUERY = 'db.query';
}

/**
 * Enumeration of audit severity levels.
 *
 * @package BITCORE\Infrastructure\Security
 * @since 1.0.0
 */
enum AuditSeverity: string {
    case CRITICAL = 'critical';
    case HIGH = 'high';
    case MEDIUM = 'medium';
    case LOW = 'low';
    case INFO = 'info';

    /**
     * Get numeric priority for sorting.
     *
     * @return int Priority value (lower = more severe).
     * @since 1.0.0
     */
    public function priority(): int {
        return match($this) {
            self::CRITICAL => 1,
            self::HIGH => 2,
            self::MEDIUM => 3,
            self::LOW => 4,
            self::INFO => 5,
        };
    }
}

// ============================================================================
// AUDIT ENTRY DTO
// ============================================================================

/**
 * Immutable audit log entry.
 *
 * @package BITCORE\Infrastructure\Security
 * @since 1.0.0
 */
readonly class AuditEntry {

    /**
     * Unique entry ID.
     *
     * @var string
     * @since 1.0.0
     */
    public string $id;

    /**
     * Event timestamp.
     *
     * @var \DateTimeImmutable
     * @since 1.0.0
     */
    public \DateTimeImmutable $timestamp;

    /**
     * Event type.
     *
     * @var AuditEventType
     * @since 1.0.0
     */
    public AuditEventType $type;

    /**
     * Severity level.
     *
     * @var AuditSeverity
     * @since 1.0.0
     */
    public AuditSeverity $severity;

    /**
     * Actor identifier (user ID, API key, etc.).
     *
     * @var string
     * @since 1.0.0
     */
    public string $actor;

    /**
     * Resource being accessed.
     *
     * @var string
     * @since 1.0.0
     */
    public string $resource;

    /**
     * Action performed.
     *
     * @var string
     * @since 1.0.0
     */
    public string $action;

    /**
     * Client IP address.
     *
     * @var string
     * @since 1.0.0
     */
    public string $ip;

    /**
     * User agent string.
     *
     * @var string|null
     * @since 1.0.0
     */
    public ?string $userAgent;

    /**
     * Additional context data.
     *
     * @var array<string, mixed>
     * @since 1.0.0
     */
    public array $context;

    /**
     * Entry hash for integrity verification.
     *
     * @var string
     * @since 1.0.0
     */
    public string $hash;

    /**
     * Constructor.
     *
     * @param string $id Entry ID.
     * @param \DateTimeImmutable $timestamp Event timestamp.
     * @param AuditEventType $type Event type.
     * @param AuditSeverity $severity Severity level.
     * @param string $actor Actor ID.
     * @param string $resource Resource path.
     * @param string $action Action description.
     * @param string $ip Client IP.
     * @param string|null $userAgent User agent.
     * @param array<string, mixed> $context Context data.
     * @param string $hash Integrity hash.
     * @since 1.0.0
     */
    public function __construct(
        string $id,
        \DateTimeImmutable $timestamp,
        AuditEventType $type,
        AuditSeverity $severity,
        string $actor,
        string $resource,
        string $action,
        string $ip,
        ?string $userAgent,
        array $context,
        string $hash
    ) {
        $this->id = $id;
        $this->timestamp = $timestamp;
        $this->type = $type;
        $this->severity = $severity;
        $this->actor = $actor;
        $this->resource = $resource;
        $this->action = $action;
        $this->ip = $ip;
        $this->userAgent = $userAgent;
        $this->context = $context;
        $this->hash = $hash;
    }

    /**
     * Serialize to array.
     *
     * @return array<string, mixed> Serialized entry.
     * @since 1.0.0
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'timestamp' => $this->timestamp->format('c'),
            'type' => $this->type->value,
            'severity' => $this->severity->value,
            'actor' => $this->actor,
            'resource' => $this->resource,
            'action' => $this->action,
            'ip' => $this->ip,
            'user_agent' => $this->userAgent,
            'context' => $this->context,
            'hash' => $this->hash,
        ];
    }

    /**
     * Serialize to JSON.
     *
     * @return string JSON representation.
     * @since 1.0.0
     */
    public function toJson(): string {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }
}
