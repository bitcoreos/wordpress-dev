<?php
/**
 * A2A (Agent-to-Agent) Dispatcher
 *
 * Provides message dispatch and routing between distributed agents.
 * Ported from flux Data Bridge and OpenClaw Gateway patterns.
 *
 * @package BITCORE\Infrastructure\Swarm
 * @since 1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Infrastructure\Swarm;

use BITCORE\Infrastructure\Logging\Logger;
use BITCORE\Infrastructure\Cache\CacheManager;
use BITCORE\Infrastructure\RateLimit\RateLimitManager;

// ============================================================================
// MESSAGE TYPES
// ============================================================================

/**
 * Enumeration of A2A message types.
 *
 * @package BITCORE\Infrastructure\Swarm
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

// ============================================================================
// A2A DISPATCHER
// ============================================================================

/**
 * Agent-to-Agent message dispatcher.
 *
 * @package BITCORE\Infrastructure\Swarm
 * @since 1.0.0
 */
class A2ADispatcher {

    /**
     * Registered agents.
     *
     * @var array<string, AgentDescriptor>
     * @since 1.0.0
     */
    private array $agents = [];

    /**
     * Message queue by priority.
     *
     * @var array<int, array<A2AMessage>>
     * @since 1.0.0
     */
    private array $queues = [];

    /**
     * Message history.
     *
     * @var array<string>
     * @since 1.0.0
     */
    private array $history = [];

    /**
     * Logger.
     *
     * @var Logger
     * @since 1.0.0
     */
    private Logger $logger;

    /**
     * Cache manager.
     *
     * @var CacheManager
     * @since 1.0.0
     */
    private CacheManager $cache;

    /**
     * Rate limiter.
     *
     * @var RateLimitManager
     * @since 1.0.0
     */
    private RateLimitManager $rateLimiter;

    /**
     * HTTP timeout seconds.
     *
     * @var int
     * @since 1.0.0
     */
    private int $timeout;

    /**
     * Constructor.
     *
     * @param Logger $logger Logger instance.
     * @param CacheManager $cache Cache manager.
     * @param RateLimitManager $rateLimiter Rate limiter.
     * @param int $timeout HTTP timeout.
     * @since 1.0.0
     */
    public function __construct(
        Logger $logger,
        CacheManager $cache,
        RateLimitManager $rateLimiter,
        int $timeout = 30
    ) {
        $this->logger = $logger;
        $this->cache = $cache;
        $this->rateLimiter = $rateLimiter;
        $this->timeout = $timeout;

        // Initialize queues for each priority
        foreach (MessagePriority::cases() as $priority) {
            $this->queues[$priority->value] = [];
        }
    }

    /**
     * Register an agent.
     *
     * @param AgentDescriptor $agent Agent descriptor.
     * @return bool True if registered.
     * @since 1.0.0
     */
    public function registerAgent(AgentDescriptor $agent): bool {
        if (isset($this->agents[$agent->id])) {
            $this->logger->warning('Agent already registered', ['agent_id' => $agent->id]);
            return false;
        }

        $this->agents[$agent->id] = $agent;
        $this->logger->info('Agent registered', [
            'agent_id' => $agent->id,
            'endpoint' => $agent->endpoint,
        ]);

        // Cache registration
        $this->cache->set('agent_' . $agent->id, $agent->toArray(), 3600);

        return true;
    }

    /**
     * Unregister an agent.
     *
     * @param string $agentId Agent ID.
     * @return bool True if unregistered.
     * @since 1.0.0
     */
    public function unregisterAgent(string $agentId): bool {
        if (!isset($this->agents[$agentId])) {
            return false;
        }

        unset($this->agents[$agentId]);
        $this->cache->delete('agent_' . $agentId);
        $this->logger->info('Agent unregistered', ['agent_id' => $agentId]);

        return true;
    }

    /**
     * Send direct message.
     *
     * @param string $from Sender ID.
     * @param string $to Recipient ID.
     * @param array<string, mixed> $payload Message data.
     * @param MessagePriority $priority Priority.
     * @return A2AMessage|null Message or null on failure.
     * @since 1.0.0
     */
    public function sendDirect(
        string $from,
        string $to,
        array $payload,
        MessagePriority $priority = MessagePriority::NORMAL
    ): ?A2AMessage {
        if (!$this->canSend($from)) {
            return null;
        }

        $message = new A2AMessage(
            type: MessageType::DIRECT,
            from: $from,
            to: $to,
            payload: $payload,
            priority: $priority
        );

        return $this->dispatch($message) ? $message : null;
    }

    /**
     * Send broadcast to all agents.
     *
     * @param string $from Sender ID.
     * @param array<string, mixed> $payload Message data.
     * @param MessagePriority $priority Priority.
     * @return int Number of recipients.
     * @since 1.0.0
     */
    public function broadcast(
        string $from,
        array $payload,
        MessagePriority $priority = MessagePriority::NORMAL
    ): int {
        if (!$this->canSend($from)) {
            return 0;
        }

        $message = new A2AMessage(
            type: MessageType::BROADCAST,
            from: $from,
            to: null,
            payload: $payload,
            priority: $priority
        );

        $count = 0;
        foreach ($this->agents as $agentId => $agent) {
            if ($agentId !== $from) {
                $direct = new A2AMessage(
                    type: MessageType::DIRECT,
                    from: $from,
                    to: $agentId,
                    payload: $payload,
                    priority: $priority,
                    correlationId: $message->id
                );
                if ($this->dispatch($direct)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Send request and wait for response.
     *
     * @param string $from Sender ID.
     * @param string $to Recipient ID.
     * @param array<string, mixed> $payload Request data.
     * @param int $timeout Response timeout.
     * @return array<string, mixed>|null Response data or null.
     * @since 1.0.0
     */
    public function request(
        string $from,
        string $to,
        array $payload,
        int $timeout = 30
    ): ?array {
        if (!$this->canSend($from)) {
            return null;
        }

        $message = new A2AMessage(
            type: MessageType::REQUEST,
            from: $from,
            to: $to,
            payload: $payload,
            priority: MessagePriority::HIGH,
            ttl: $timeout
        );

        if (!$this->dispatch($message)) {
            return null;
        }

        // Store pending request
        $this->cache->set('pending_' . $message->id, $message->toArray(), $timeout);

        return ['message_id' => $message->id, 'status' => 'pending'];
    }

    /**
     * Dispatch message to recipient.
     *
     * @param A2AMessage $message Message to dispatch.
     * @return bool True if dispatched.
     * @since 1.0.0
     */
    private function dispatch(A2AMessage $message): bool {
        $this->queues[$message->priority->value][] = $message;
        $this->history[] = $message->id;

        // Limit history
        if (count($this->history) > 1000) {
            $this->history = array_slice($this->history, -500);
        }

        // If has specific recipient, deliver immediately
        if ($message->to !== null) {
            return $this->deliver($message);
        }

        return true;
    }

    /**
     * Deliver message to agent endpoint.
     *
     * @param A2AMessage $message Message to deliver.
     * @return bool True if delivered.
     * @since 1.0.0
     */
    private function deliver(A2AMessage $message): bool {
        if (!isset($this->agents[$message->to])) {
            $this->logger->error('Agent not found', ['agent_id' => $message->to]);
            return false;
        }

        $agent = $this->agents[$message->to];

        if ($agent->status !== 'active') {
            $this->logger->warning('Agent not active', [
                'agent_id' => $message->to,
                'status' => $agent->status,
            ]);
            return false;
        }

        // HTTP POST to agent endpoint
        $response = wp_remote_post($agent->endpoint . '/a2a/receive', [
            'body' => json_encode($message->toArray()),
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'timeout' => $this->timeout,
        ]);

        if (is_wp_error($response)) {
            $this->logger->error('Delivery failed', [
                'agent_id' => $message->to,
                'error' => $response->get_error_message(),
            ]);
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        $this->logger->info('Message delivered', [
            'message_id' => $message->id,
            'to' => $message->to,
            'status_code' => $code,
        ]);

        return $code >= 200 && $code < 300;
    }

    /**
     * Check if sender can send messages.
     *
     * @param string $agentId Sender ID.
     * @return bool True if allowed.
     * @since 1.0.0
     */
    private function canSend(string $agentId): bool {
        $key = 'a2a_rate_' . $agentId;
        return $this->rateLimiter->attempt($key);
    }

    /**
     * Get registered agents.
     *
     * @return array<string, AgentDescriptor> Agents.
     * @since 1.0.0
     */
    public function getAgents(): array {
        return $this->agents;
    }

    /**
     * Get agent by ID.
     *
     * @param string $agentId Agent ID.
     * @return AgentDescriptor|null Agent or null.
     * @since 1.0.0
     */
    public function getAgent(string $agentId): ?AgentDescriptor {
        return $this->agents[$agentId] ?? null;
    }

    /**
     * Get queue statistics.
     *
     * @return array<string, int> Queue counts by priority.
     * @since 1.0.0
     */
    public function getQueueStats(): array {
        $stats = [];
        foreach (MessagePriority::cases() as $priority) {
            $stats[$priority->name] = count($this->queues[$priority->value]);
        }
        return $stats;
    }

    /**
     * Clear processed messages.
     *
     * @return int Number cleared.
     * @since 1.0.0
     */
    public function clearExpired(): int {
        $cleared = 0;
        foreach ($this->queues as $priority => &$queue) {
            $original = count($queue);
            $queue = array_filter($queue, fn($msg) => !$msg->isExpired());
            $cleared += $original - count($queue);
            $queue = array_values($queue);
        }
        return $cleared;
    }
}
