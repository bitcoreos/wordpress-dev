<?php
/**
 * Main A2A dispatcher class for Bitcore
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
