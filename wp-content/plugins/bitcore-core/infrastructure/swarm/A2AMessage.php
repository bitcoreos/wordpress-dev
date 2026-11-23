<?php
/**
 * A2A Message Types and Structures
 *
 * Provides message types, priorities, and data structures for A2A communication.
 *
 * @package BITCORE\Infrastructure\Swarm
 * @author hyperion
 * @since 1.0.0
 * @version     1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Infrastructure\Swarm;

// ============================================================================
// MESSAGE TYPES
// ============================================================================

/**
 * Enumeration of A2A message types.
 *
 * @package BITCORE\Infrastructure\Swarm
 * @author hyperion
 * @since 1.0.0
 */
enum MessageType: string {
    case DIRECT = 'direct';           // One-to-one message
    case BROADCAST = 'broadcast';       // One-to-many message
    case REQUEST = 'request';           // RPC-style request
    case RESPONSE = 'response';       // RPC-style response
    case HEARTBEAT = 'heartbeat';       // Health check ping
    case STATUS = 'status';             // Status update
    case TASK_ASSIGN = 'task_assign';   // Task delegation
    case TASK_RESULT = 'task_result';   // Task completion
    case ERROR = 'error';               // Error notification
}

/**
 * Enumeration of message priorities.
 *
 * @package BITCORE\Infrastructure\Swarm
 * @author hyperion
 * @since 1.0.0
 */
enum MessagePriority: int {
    case CRITICAL = 1;
    case HIGH = 2;
    case NORMAL = 3;
    case LOW = 4;
    case BACKGROUND = 5;
}

// ============================================================================
// AGENT DESCRIPTOR
// ============================================================================

/**
 * Agent registration descriptor.
 *
 * @package BITCORE\Infrastructure\Swarm
 * @author hyperion
 * @since 1.0.0
 */
readonly class AgentDescriptor {

    /**
     * Unique agent ID.
     *
     * @var string
     * @since 1.0.0
     */
    public string $id;

    /**
     * Agent name.
     *
     * @var string
     * @since 1.0.0
     */
    public string $name;

    /**
     * Agent type.
     *
     * @var string
     * @since 1.0.0
     */
    public string $type;

    /**
     * A2A endpoint URL.
     *
     * @var string
     * @since 1.0.0
     */
    public string $endpoint;

    /**
     * Agent capabilities.
     *
     * @var array<string>
     * @since 1.0.0
     */
    public array $capabilities;

    /**
     * Last heartbeat timestamp.
     *
     * @var int
     * @since 1.0.0
     */
    public int $lastSeen;

    /**
     * Agent status.
     *
     * @var string
     * @since 1.0.0
     */
    public string $status;

    /**
     * Metadata.
     *
     * @var array<string, mixed>
     * @since 1.0.0
     */
    public array $metadata;

    /**
     * Constructor.
     *
     * @param string $id Agent ID.
     * @param string $name Agent name.
     * @param string $type Agent type.
     * @param string $endpoint A2A endpoint.
     * @param array<string> $capabilities Capabilities.
     * @param array<string, mixed> $metadata Metadata.
     * @param string $status Initial status.
     * @since 1.0.0
     */
    public function __construct(
        string $id,
        string $name,
        string $type,
        string $endpoint,
        array $capabilities = [],
        array $metadata = [],
        string $status = 'active'
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->type = $type;
        $this->endpoint = $endpoint;
        $this->capabilities = $capabilities;
        $this->metadata = $metadata;
        $this->status = $status;
        $this->lastSeen = time();
    }

    /**
     * Update last seen timestamp.
     *
     * @return self Updated descriptor.
     * @since 1.0.0
     */
    public function touch(): self {
        return new self(
            $this->id,
            $this->name,
            $this->type,
            $this->endpoint,
            $this->capabilities,
            $this->metadata,
            $this->status
        );
    }

    /**
     * Serialize to array.
     *
     * @return array<string, mixed> Serialized.
     * @since 1.0.0
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'endpoint' => $this->endpoint,
            'capabilities' => $this->capabilities,
            'last_seen' => $this->lastSeen,
            'status' => $this->status,
            'metadata' => $this->metadata,
        ];
    }
}

// ============================================================================
// A2A MESSAGE
// ============================================================================

/**
 * A2A message structure.
 *
 * @package BITCORE\Infrastructure\Swarm
 * @author hyperion
 * @since 1.0.0
 */
readonly class A2AMessage {

    /**
     * Message ID.
     *
     * @var string
     * @since 1.0.0
     */
    public string $id;

    /**
     * Message type.
     *
     * @var MessageType
     * @since 1.0.0
     */
    public MessageType $type;

    /**
     * Sender agent ID.
     *
     * @var string
     * @since 1.0.0
     */
    public string $from;

    /**
     * Recipient agent ID (null for broadcast).
     *
     * @var string|null
     * @since 1.0.0
     */
    public ?string $to;

    /**
     * Message payload.
     *
     * @var array<string, mixed>
     * @since 1.0.0
     */
    public array $payload;

    /**
     * Message priority.
     *
     * @var MessagePriority
     * @since 1.0.0
     */
    public MessagePriority $priority;

    /**
     * Creation timestamp.
     *
     * @var int
     * @since 1.0.0
     */
    public int $timestamp;

    /**
     * TTL in seconds (0 = infinite).
     *
     * @var int
     * @since 1.0.0
     */
    public int $ttl;

    /**
     * Correlation ID for request/response.
     *
     * @var string|null
     * @since 1.0.0
     */
    public ?string $correlationId;

    /**
     * Constructor.
     *
     * @param MessageType $type Message type.
     * @param string $from Sender ID.
     * @param string|null $to Recipient ID.
     * @param array<string, mixed> $payload Message data.
     * @param MessagePriority $priority Priority level.
     * @param int $ttl TTL seconds.
     * @param string|null $correlationId Correlation ID.
     * @since 1.0.0
     */
    public function __construct(
        MessageType $type,
        string $from,
        ?string $to,
        array $payload,
        MessagePriority $priority = MessagePriority::NORMAL,
        int $ttl = 300,
        ?string $correlationId = null
    ) {
        $this->id = $this->generateId();
        $this->type = $type;
        $this->from = $from;
        $this->to = $to;
        $this->payload = $payload;
        $this->priority = $priority;
        $this->timestamp = time();
        $this->ttl = $ttl;
        $this->correlationId = $correlationId;
    }

    /**
     * Generate unique message ID.
     *
     * @return string Message ID.
     * @since 1.0.0
     */
    private function generateId(): string {
        return sprintf(
            'msg_%s_%s_%s',
            $this->from,
            microtime(true),
            bin2hex(random_bytes(4))
        );
    }

    /**
     * Check if message is expired.
     *
     * @return bool True if expired.
     * @since 1.0.0
     */
    public function isExpired(): bool {
        if ($this->ttl === 0) {
            return false;
        }
        return (time() - $this->timestamp) > $this->ttl;
    }

    /**
     * Serialize to array.
     *
     * @return array<string, mixed> Serialized message.
     * @since 1.0.0
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'from' => $this->from,
            'to' => $this->to,
            'payload' => $this->payload,
            'priority' => $this->priority->value,
            'timestamp' => $this->timestamp,
            'ttl' => $this->ttl,
            'correlation_id' => $this->correlationId,
        ];
    }

    /**
     * Create response message.
     *
     * @param string $sender Responder agent ID.
     * @param array<string, mixed> $payload Response data.
     * @return self Response message.
     * @since 1.0.0
     */
    public function createResponse(string $sender, array $payload): self {
        return new self(
            type: MessageType::RESPONSE,
            from: $sender,
            to: $this->from,
            payload: $payload,
            priority: $this->priority,
            ttl: $this->ttl,
            correlationId: $this->correlationId ?? $this->id
        );
    }
}
