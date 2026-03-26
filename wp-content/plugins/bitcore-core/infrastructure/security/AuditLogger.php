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
 */

declare(strict_types=1);

namespace BITCORE\Infrastructure\Security;


// ============================================================================
// AUDIT LOGGER
// ============================================================================

/**
 * Security audit logger with integrity verification.
 *
 * @package BITCORE\Infrastructure\Security
 * @since 1.0.0
 */
class AuditLogger {

    /**
     * Logger instance.
     *
     * @var Logger
     * @since 1.0.0
     */
    private Logger $logger;

    /**
     * Secret key for hash generation.
     *
     * @var string
     * @since 1.0.0
     */
    private string $secretKey;

    /**
     * Previous entry hash for chain integrity.
     *
     * @var string|null
     * @since 1.0.0
     */
    private ?string $lastHash = null;

    /**
     * Buffered entries for batch insert.
     *
     * @var array<AuditEntry>
     * @since 1.0.0
     */
    private array $buffer = [];

    /**
     * Buffer size limit.
     *
     * @var int
     * @since 1.0.0
     */
    private int $bufferLimit;

    /**
     * Constructor.
     *
     * @param Logger $logger Logger instance.
     * @param string|null $secretKey Secret for hashing.
     * @param int $bufferLimit Buffer size limit.
     * @since 1.0.0
     */
    public function __construct(
        Logger $logger,
        ?string $secretKey = null,
        int $bufferLimit = 100
    ) {
        $this->logger = $logger;
        $this->secretKey = $secretKey ?? $this->generateSecret();
        $this->bufferLimit = $bufferLimit;
    }

    /**
     * Generate a secret key.
     *
     * @return string Generated secret.
     * @since 1.0.0
     */
    private function generateSecret(): string {
        return hash('sha256', wp_rand() . microtime() . AUTH_SALT);
    }

    /**
     * Log authentication success.
     *
     * @param string $actor Actor ID.
     * @param string $resource Resource accessed.
     * @param array<string, mixed> $context Additional context.
     * @return AuditEntry Created entry.
     * @since 1.0.0
     */
    public function logAuthSuccess(
        string $actor,
        string $resource,
        array $context = []
    ): AuditEntry {
        return $this->log(
            AuditEventType::AUTH_SUCCESS,
            AuditSeverity::INFO,
            $actor,
            $resource,
            'authenticated',
            $context
        );
    }

    /**
     * Log authentication failure.
     *
     * @param string $actor Actor ID.
     * @param string $resource Resource attempted.
     * @param string $reason Failure reason.
     * @param array<string, mixed> $context Additional context.
     * @return AuditEntry Created entry.
     * @since 1.0.0
     */
    public function logAuthFailure(
        string $actor,
        string $resource,
        string $reason,
        array $context = []
    ): AuditEntry {
        $context['failure_reason'] = $reason;
        return $this->log(
            AuditEventType::AUTH_FAILURE,
            AuditSeverity::MEDIUM,
            $actor,
            $resource,
            'authentication_failed',
            $context
        );
    }

    /**
     * Log access denied.
     *
     * @param string $actor Actor ID.
     * @param string $resource Resource attempted.
     * @param array<string, mixed> $context Additional context.
     * @return AuditEntry Created entry.
     * @since 1.0.0
     */
    public function logAccessDenied(
        string $actor,
        string $resource,
        array $context = []
    ): AuditEntry {
        return $this->log(
            AuditEventType::ACCESS_DENIED,
            AuditSeverity::HIGH,
            $actor,
            $resource,
            'access_denied',
            $context
        );
    }

    /**
     * Log rate limit hit.
     *
     * @param string $actor Actor ID.
     * @param string $resource Resource attempted.
     * @param int $attempts Number of attempts.
     * @param int $window Time window in seconds.
     * @return AuditEntry Created entry.
     * @since 1.0.0
     */
    public function logRateLimitHit(
        string $actor,
        string $resource,
        int $attempts,
        int $window
    ): AuditEntry {
        return $this->log(
            AuditEventType::RATE_LIMIT_HIT,
            AuditSeverity::MEDIUM,
            $actor,
            $resource,
            'rate_limit_exceeded',
            ['attempts' => $attempts, 'window' => $window]
        );
    }

    /**
     * Log suspicious activity.
     *
     * @param string $actor Actor ID.
     * @param string $resource Resource.
     * @param string $description Activity description.
     * @param array<string, mixed> $context Additional context.
     * @return AuditEntry Created entry.
     * @since 1.0.0
     */
    public function logSuspiciousActivity(
        string $actor,
        string $resource,
        string $description,
        array $context = []
    ): AuditEntry {
        $context['description'] = $description;
        return $this->log(
            AuditEventType::SUSPICIOUS_ACTIVITY,
            AuditSeverity::HIGH,
            $actor,
            $resource,
            'suspicious_activity',
            $context
        );
    }

    /**
     * Log configuration change.
     *
     * @param string $actor Actor ID.
     * @param string $configKey Configuration key.
     * @param mixed $oldValue Old value.
     * @param mixed $newValue New value.
     * @return AuditEntry Created entry.
     * @since 1.0.0
     */
    public function logConfigChange(
        string $actor,
        string $configKey,
        mixed $oldValue,
        mixed $newValue
    ): AuditEntry {
        return $this->log(
            AuditEventType::CONFIG_CHANGE,
            AuditSeverity::MEDIUM,
            $actor,
            $configKey,
            'configuration_changed',
            [
                'old_value' => $this->sanitize($oldValue),
                'new_value' => $this->sanitize($newValue),
            ]
        );
    }

    /**
     * Log generic audit event.
     *
     * @param AuditEventType $type Event type.
     * @param AuditSeverity $severity Severity level.
     * @param string $actor Actor ID.
     * @param string $resource Resource.
     * @param string $action Action.
     * @param array<string, mixed> $context Context.
     * @return AuditEntry Created entry.
     * @since 1.0.0
     */
    public function log(
        AuditEventType $type,
        AuditSeverity $severity,
        string $actor,
        string $resource,
        string $action,
        array $context = []
    ): AuditEntry {
        $entry = $this->createEntry($type, $severity, $actor, $resource, $action, $context);
        $this->buffer[] = $entry;

        if (count($this->buffer) >= $this->bufferLimit) {
            $this->flush();
        }

        return $entry;
    }

    /**
     * Create audit entry.
     *
     * @param AuditEventType $type Event type.
     * @param AuditSeverity $severity Severity level.
     * @param string $actor Actor ID.
     * @param string $resource Resource.
     * @param string $action Action.
     * @param array<string, mixed> $context Context.
     * @return AuditEntry Created entry.
     * @since 1.0.0
     */
    private function createEntry(
        AuditEventType $type,
        AuditSeverity $severity,
        string $actor,
        string $resource,
        string $action,
        array $context
    ): AuditEntry {
        $timestamp = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $id = $this->generateId($timestamp);
        $ip = $this->getClientIp();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $hash = $this->calculateHash($id, $timestamp, $type, $severity, $actor, $resource, $action);

        $entry = new AuditEntry(
            id: $id,
            timestamp: $timestamp,
            type: $type,
            severity: $severity,
            actor: $actor,
            resource: $resource,
            action: $action,
            ip: $ip,
            userAgent: $userAgent,
            context: $context,
            hash: $hash
        );

        $this->lastHash = $hash;
        return $entry;
    }

    /**
     * Generate unique ID.
     *
     * @param \DateTimeImmutable $timestamp Event timestamp.
     * @return string Unique ID.
     * @since 1.0.0
     */
    private function generateId(\DateTimeImmutable $timestamp): string {
        return sprintf(
            '%s-%s-%s',
            $timestamp->format('YmdHis'),
            substr(uniqid(), -6),
            substr(md5(microtime()), 0, 8)
        );
    }

    /**
     * Get client IP address.
     *
     * @return string Client IP.
     * @since 1.0.0
     */
    private function getClientIp(): string {
        return $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['HTTP_X_REAL_IP']
            ?? $_SERVER['REMOTE_ADDR']
            ?? 'unknown';
    }

    /**
     * Calculate entry hash.
     *
     * @param string $id Entry ID.
     * @param \DateTimeImmutable $timestamp Timestamp.
     * @param AuditEventType $type Event type.
     * @param AuditSeverity $severity Severity.
     * @param string $actor Actor.
     * @param string $resource Resource.
     * @param string $action Action.
     * @return string Hash value.
     * @since 1.0.0
     */
    private function calculateHash(
        string $id,
        \DateTimeImmutable $timestamp,
        AuditEventType $type,
        AuditSeverity $severity,
        string $actor,
        string $resource,
        string $action
    ): string {
        $data = [
            $id,
            $timestamp->format('c'),
            $type->value,
            $severity->value,
            $actor,
            $resource,
            $action,
            $this->lastHash ?? '',
        ];

        return hash_hmac('sha256', implode('|', $data), $this->secretKey);
    }

    /**
     * Sanitize sensitive values for logging.
     *
     * @param mixed $value Value to sanitize.
     * @return mixed Sanitized value.
     * @since 1.0.0
     */
    private function sanitize(mixed $value): mixed {
        if (is_string($value) && strlen($value) > 100) {
            return substr($value, 0, 100) . '...[truncated]';
        }
        if (is_array($value)) {
            return array_map([$this, 'sanitize'], $value);
        }
        return $value;
    }

    /**
     * Flush buffered entries to log.
     *
     * @return int Number of entries flushed.
     * @since 1.0.0
     */
    public function flush(): int {
        $count = count($this->buffer);
        if ($count === 0) {
            return 0;
        }

        foreach ($this->buffer as $entry) {
            $this->logger->info('audit_log', $entry->toArray());
        }

        $this->buffer = [];
        return $count;
    }

    /**
     * Verify entry integrity.
     *
     * @param AuditEntry $entry Entry to verify.
     * @return bool True if valid.
     * @since 1.0.0
     */
    public function verifyIntegrity(AuditEntry $entry): bool {
        $expected = $this->calculateHash(
            $entry->id,
            $entry->timestamp,
            $entry->type,
            $entry->severity,
            $entry->actor,
            $entry->resource,
            $entry->action
        );

        return hash_equals($expected, $entry->hash);
    }

    /**
     * Get current buffer size.
     *
     * @return int Buffer size.
     * @since 1.0.0
     */
    public function bufferSize(): int {
        return count($this->buffer);
    }
}
