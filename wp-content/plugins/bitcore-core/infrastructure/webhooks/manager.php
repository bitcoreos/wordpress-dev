<?php
/**
 * Webhook Manager
 *
 * Enterprise-grade webhook subscription management, event dispatching,
 * and delivery with HMAC-SHA256 signatures and exponential backoff retry.
 *
 * @package BITCORE\Infrastructure\Webhooks
 * @since 1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Infrastructure\Webhooks;

use DateTimeImmutable;
use DateInterval;
use Throwable;
use BITCORE\Infrastructure\Queue\JobInterface;
use BITCORE\Infrastructure\Cache\CacheInterface;
use BITCORE\Infrastructure\Log\LoggerInterface;
use BITCORE\Infrastructure\Http\HttpClientInterface;

/**
 * Webhook Delivery Status Enumeration
 *
 * @since 1.0.0
 */
enum WebhookDeliveryStatus: string {
    case PENDING = 'pending';
    case DELIVERED = 'delivered';
    case FAILED = 'failed';
    case RETRYING = 'retrying';
}

/**
 * Webhook Subscription Data Object
 *
 * Immutable value object representing a webhook subscription.
 *
 * @since 1.0.0
 */
readonly class WebhookSubscription {
    /**
     * @param string $id Unique subscription identifier
     * @param string $url Target URL for webhook delivery
     * @param array $events Array of event types to subscribe to
     * @param string $secret HMAC signing secret
     * @param bool $active Whether the subscription is active
     * @param int|null $tenantId Optional tenant identifier for multi-tenancy
     * @param int $maxRetries Maximum retry attempts on failure
     * @param array $headers Custom headers to include in requests
     */
    public function __construct(
        public string $id,
        public string $url,
        public array $events,
        public string $secret,
        public bool $active = true,
        public ?int $tenantId = null,
        public int $maxRetries = 3,
        public array $headers = []
    ) {}

    /**
     * Check if subscription supports a specific event type.
     *
     * @since 1.0.0
     * @param string $event Event type to check
     * @return bool True if subscription supports the event
     */
    public function supports(string $event): bool {
        return in_array($event, $this->events, true) || in_array('*', $this->events, true);
    }

    /**
     * Get merged headers for webhook request.
     *
     * @since 1.0.0
     * @return array Complete header array
     */
    public function getHeaders(): array {
        return array_merge([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'BITCORE-Webhook/1.0',
        ], $this->headers);
    }
}

/**
 * Webhook Delivery Data Object
 *
 * Represents a single webhook delivery attempt.
 *
 * @since 1.0.0
 */
class WebhookDelivery {
    /**
     * @param string $id Unique delivery identifier
     * @param string $subscriptionId Associated subscription ID
     * @param string $event Event type being delivered
     * @param array $payload Event payload data
     * @param DateTimeImmutable $createdAt Creation timestamp
     * @param WebhookDeliveryStatus $status Current delivery status
     * @param int $attempts Number of delivery attempts made
     * @param DateTimeImmutable|null $deliveredAt Successful delivery timestamp
     * @param array $attemptHistory History of all delivery attempts
     */
    public function __construct(
        readonly public string $id,
        readonly public string $subscriptionId,
        readonly public string $event,
        readonly public array $payload,
        readonly public DateTimeImmutable $createdAt,
        public WebhookDeliveryStatus $status = WebhookDeliveryStatus::PENDING,
        public int $attempts = 0,
        public ?DateTimeImmutable $deliveredAt = null,
        public array $attemptHistory = []
    ) {}

    /**
     * Record a delivery attempt.
     *
     * @since 1.0.0
     * @param array $attempt Attempt details including timestamp, status, response
     * @return void
     */
    public function recordAttempt(array $attempt): void {
        $this->attemptHistory[] = $attempt;
        $this->attempts++;
    }
}

/**
 * Webhook Result Data Object
 *
 * Represents the result of a webhook delivery attempt.
 *
 * @since 1.0.0
 */
readonly class WebhookResult {
    /**
     * @param bool $success Whether delivery was successful
     * @param int $statusCode HTTP response status code
     * @param array $responseHeaders Response headers
     * @param string $responseBody Response body content
     * @param Throwable|null $error Error if delivery failed
     * @param int $duration Delivery duration in milliseconds
     */
    public function __construct(
        public bool $success,
        public int $statusCode,
        public array $responseHeaders,
        public string $responseBody,
        public ?Throwable $error = null,
        public int $duration = 0
    ) {}
}

/**
 * Retry Strategy Interface
 *
 * Defines contract for retry backoff strategies.
 *
 * @since 1.0.0
 */
interface RetryStrategyInterface {
    /**
     * Calculate delay before next retry attempt.
     *
     * @since 1.0.0
     * @param int $attempt Current attempt number (0-indexed)
     * @return int Delay in seconds
     */
    public function getDelay(int $attempt): int;

    /**
     * Determine if another retry should be attempted.
     *
     * @since 1.0.0
     * @param int $attempt Current attempt number
     * @param Throwable $error The error that caused failure
     * @return bool True if retry should be attempted
     */
    public function shouldRetry(int $attempt, Throwable $error): bool;
}

/**
 * Exponential Backoff Retry Strategy
 *
 * Implements exponential backoff with jitter and configurable limits.
 *
 * @since 1.0.0
 */
class ExponentialBackoffStrategy implements RetryStrategyInterface {
    private int $baseDelay;
    private int $maxDelay;
    private float $multiplier;

    /**
     * Constructor.
     *
     * @since 1.0.0
     * @param int $baseDelay Base delay in seconds (default: 60)
     * @param int $maxDelay Maximum delay in seconds (default: 3600)
     * @param float $multiplier Exponential multiplier (default: 2.0)
     */
    public function __construct(
        int $baseDelay = 60,
        int $maxDelay = 3600,
        float $multiplier = 2.0
    ) {
        $this->baseDelay = $baseDelay;
        $this->maxDelay = $maxDelay;
        $this->multiplier = $multiplier;
    }

    /**
     * Calculate delay with exponential backoff and jitter.
     *
     * @since 1.0.0
     * @param int $attempt Attempt number (0-indexed)
     * @return int Delay in seconds
     */
    public function getDelay(int $attempt): int {
        // Exponential: base * multiplier^attempt
        $delay = (int) ($this->baseDelay * pow($this->multiplier, $attempt));

        // Cap at max delay
        $delay = min($delay, $this->maxDelay);

        // Add ±20% jitter for thundering herd protection
        $jitter = (int) ($delay * 0.2);
        $delay += random_int(-$jitter, $jitter);

        return max(1, $delay);
    }

    /**
     * Determine if retry should be attempted based on error.
     *
     * 2xx = success, 4xx (except 429) = permanent failure, 5xx/429 = retry
     *
     * @since 1.0.0
     * @param int $attempt Current attempt number
     * @param Throwable $error The error
     * @return bool True if should retry
     */
    public function shouldRetry(int $attempt, Throwable $error): bool {
        // Check if it's a WebhookResult with status code
        if ($error instanceof WebhookResult) {
            $statusCode = $error->statusCode;

            // 2xx = success, no retry
            if ($statusCode >= 200 && $statusCode < 300) {
                return false;
            }

            // 4xx client errors (except 429) = permanent failure
            if ($statusCode >= 400 && $statusCode < 500 && $statusCode !== 429) {
                return false;
            }

            // 5xx server errors and 429 = retry
            return true;
        }

        // Network errors = retry
        return true;
    }
}

/**
 * HMAC-SHA256 Signature Verifier
 *
 * Handles webhook signature generation and verification.
 *
 * @since 1.0.0
 */
class SignatureVerifier {
    private string $algorithm;

    /**
     * Constructor.
     *
     * @since 1.0.0
     * @param string $algorithm Hash algorithm (default: sha256)
     */
    public function __construct(string $algorithm = 'sha256') {
        $this->algorithm = $algorithm;
    }

    /**
     * Verify webhook signature.
     *
     * Format: 'sha256=' . hash_hmac('sha256', $payload, $secret)
     *
     * @since 1.0.0
     * @param string $payload Raw payload body
     * @param string $signature Signature from X-Webhook-Signature header
     * @param string $secret Webhook secret
     * @return bool True if signature is valid
     */
    public function verify(string $payload, string $signature, string $secret): bool {
        $expected = $this->generate($payload, $secret);

        // Extract signature value if prefixed
        if (strpos($signature, '=') !== false) {
            [, $signature] = explode('=', $signature, 2);
        }

        return hash_equals($expected, $signature);
    }

    /**
     * Generate HMAC signature.
     *
     * @since 1.0.0
     * @param string $payload Raw payload body
     * @param string $secret Webhook secret
     * @return string Signature with algorithm prefix
     */
    public function generate(string $payload, string $secret): string {
        $hash = hash_hmac($this->algorithm, $payload, $secret);
        return $this->algorithm . '=' . $hash;
    }
}

/**
 * Webhook Repository
 *
 * Handles database operations for webhook subscriptions and deliveries.
 *
 * @since 1.0.0
 */
class WebhookRepository {
    private \wpdb $db;
    private string $subscriptionsTable;
    private string $deliveriesTable;

    /**
     * Constructor.
     *
     * @since 1.0.0
     * @param \wpdb $db WordPress database instance
     */
    public function __construct(\wpdb $db) {
        $this->db = $db;
        $this->subscriptionsTable = $db->prefix . 'bitcore_webhooks';
        $this->deliveriesTable = $db->prefix . 'bitcore_webhook_deliveries';
    }

    /**
     * Save or update a subscription.
     *
     * @since 1.0.0
     * @param WebhookSubscription $sub Subscription to save
     * @return void
     */
    public function saveSubscription(WebhookSubscription $sub): void {
        $data = [
            'id' => $sub->id,
            'url' => $sub->url,
            'events' => wp_json_encode($sub->events),
            'secret' => $sub->secret,
            'active' => $sub->active ? 1 : 0,
            'tenant_id' => $sub->tenantId,
            'max_retries' => $sub->maxRetries,
            'headers' => wp_json_encode($sub->headers),
            'updated_at' => current_time('mysql'),
        ];

        $format = ['%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s'];

        // Check if exists
        $exists = $this->db->get_var($this->db->prepare(
            "SELECT id FROM {$this->subscriptionsTable} WHERE id = %s",
            $sub->id
        ));

        if ($exists) {
            $this->db->update($this->subscriptionsTable, $data, ['id' => $sub->id], $format, ['%s']);
        } else {
            $data['created_at'] = current_time('mysql');
            $format[] = '%s';
            $this->db->insert($this->subscriptionsTable, $data, $format);
        }
    }

    /**
     * Find subscription by ID.
     *
     * @since 1.0.0
     * @param string $id Subscription ID
     * @return WebhookSubscription|null Subscription or null if not found
     */
    public function findSubscription(string $id): ?WebhookSubscription {
        $row = $this->db->get_row($this->db->prepare(
            "SELECT * FROM {$this->subscriptionsTable} WHERE id = %s",
            $id
        ), ARRAY_A);

        if (!$row) {
            return null;
        }

        return new WebhookSubscription(
            $row['id'],
            $row['url'],
            json_decode($row['events'], true) ?: [],
            $row['secret'],
            (bool) $row['active'],
            $row['tenant_id'] ? (int) $row['tenant_id'] : null,
            (int) $row['max_retries'],
            json_decode($row['headers'], true) ?: []
        );
    }

    /**
     * Find subscriptions by event type.
     *
     * @since 1.0.0
     * @param string $event Event type to match
     * @return array Array of matching subscriptions
     */
    public function findByEvent(string $event): array {
        $results = $this->db->get_results($this->db->prepare(
            "SELECT * FROM {$this->subscriptionsTable} 
             WHERE active = 1 
             AND (events LIKE %s OR events LIKE '%\"*\"%')",
            '%"' . $this->db->esc_like($event) . '"%'
        ), ARRAY_A);

        return array_map(fn($row) => new WebhookSubscription(
            $row['id'],
            $row['url'],
            json_decode($row['events'], true) ?: [],
            $row['secret'],
            (bool) $row['active'],
            $row['tenant_id'] ? (int) $row['tenant_id'] : null,
            (int) $row['max_retries'],
            json_decode($row['headers'], true) ?: []
        ), $results);
    }

    /**
     * Delete a subscription.
     *
     * @since 1.0.0
     * @param string $id Subscription ID to delete
     * @return void
     */
    public function deleteSubscription(string $id): void {
        $this->db->delete($this->subscriptionsTable, ['id' => $id], ['%s']);
    }

    /**
     * Save or update a delivery.
     *
     * @since 1.0.0
     * @param WebhookDelivery $delivery Delivery to save
     * @return void
     */
    public function saveDelivery(WebhookDelivery $delivery): void {
        $data = [
            'id' => $delivery->id,
            'subscription_id' => $delivery->subscriptionId,
            'event' => $delivery->event,
            'payload' => wp_json_encode($delivery->payload),
            'status' => $delivery->status->value,
            'attempts' => $delivery->attempts,
            'attempt_history' => wp_json_encode($delivery->attemptHistory),
            'delivered_at' => $delivery->deliveredAt ? $delivery->deliveredAt->format('Y-m-d H:i:s') : null,
            'updated_at' => current_time('mysql'),
        ];

        $format = ['%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s'];

        // Check if exists
        $exists = $this->db->get_var($this->db->prepare(
            "SELECT id FROM {$this->deliveriesTable} WHERE id = %s",
            $delivery->id
        ));

        if ($exists) {
            $this->db->update($this->deliveriesTable, $data, ['id' => $delivery->id], $format, ['%s']);
        } else {
            $data['created_at'] = $delivery->createdAt->format('Y-m-d H:i:s');
            $format[] = '%s';
            $this->db->insert($this->deliveriesTable, $data, $format);
        }
    }

    /**
     * Find pending deliveries.
     *
     * @since 1.0.0
     * @return array Array of pending deliveries
     */
    public function findPendingDeliveries(): array {
        $results = $this->db->get_results(
            "SELECT * FROM {$this->deliveriesTable} 
             WHERE status IN ('pending', 'retrying')
             ORDER BY created_at ASC",
            ARRAY_A
        );

        return array_map(fn($row) => new WebhookDelivery(
            $row['id'],
            $row['subscription_id'],
            $row['event'],
            json_decode($row['payload'], true) ?: [],
            new DateTimeImmutable($row['created_at']),
            WebhookDeliveryStatus::from($row['status']),
            (int) $row['attempts'],
            $row['delivered_at'] ? new DateTimeImmutable($row['delivered_at']) : null,
            json_decode($row['attempt_history'], true) ?: []
        ), $results);
    }

    /**
     * Update delivery status.
     *
     * @since 1.0.0
     * @param string $id Delivery ID
     * @param WebhookDeliveryStatus $status New status
     * @return void
     */
    public function updateDeliveryStatus(string $id, WebhookDeliveryStatus $status): void {
        $this->db->update(
            $this->deliveriesTable,
            ['status' => $status->value, 'updated_at' => current_time('mysql')],
            ['id' => $id],
            ['%s', '%s'],
            ['%s']
        );
    }

    /**
     * Prune old delivered deliveries.
     *
     * @since 1.0.0
     * @param DateTimeImmutable $before Cutoff date
     * @return int Number of records deleted
     */
    public function pruneOldDeliveries(DateTimeImmutable $before): int {
        return $this->db->query($this->db->prepare(
            "DELETE FROM {$this->deliveriesTable} 
             WHERE status = 'delivered' 
             AND delivered_at < %s",
            $before->format('Y-m-d H:i:s')
        ));
    }
}

/**
 * Webhook Manager
 *
 * Central orchestrator for webhook subscriptions, event dispatching,
 * and delivery management.
 *
 * @since 1.0.0
 */
class WebhookManager {
    private WebhookRepository $repository;
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private SignatureVerifier $signatureVerifier;
    private ?CacheInterface $cache = null;
    private ?JobInterface $queue = null;
    private RetryStrategyInterface $retryStrategy;

    /**
     * Constructor.
     *
     * @since 1.0.0
     * @param WebhookRepository $repository Webhook repository
     * @param HttpClientInterface $httpClient HTTP client for deliveries
     * @param LoggerInterface $logger Logger for delivery attempts
     * @param SignatureVerifier $signatureVerifier Signature verifier
     */
    public function __construct(
        WebhookRepository $repository,
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        SignatureVerifier $signatureVerifier
    ) {
        $this->repository = $repository;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->signatureVerifier = $signatureVerifier;
        $this->retryStrategy = new ExponentialBackoffStrategy();
    }

    /**
     * Set cache for deduplication.
     *
     * @since 1.0.0
     * @param CacheInterface $cache Cache instance
     * @return void
     */
    public function setCache(CacheInterface $cache): void {
        $this->cache = $cache;
    }

    /**
     * Set queue for async delivery.
     *
     * @since 1.0.0
     * @param JobInterface $queue Job queue
     * @return void
     */
    public function setQueue(JobInterface $queue): void {
        $this->queue = $queue;
    }

    /**
     * Set custom retry strategy.
     *
     * @since 1.0.0
     * @param RetryStrategyInterface $strategy Retry strategy
     * @return void
     */
    public function setRetryStrategy(RetryStrategyInterface $strategy): void {
        $this->retryStrategy = $strategy;
    }

    /**
     * Subscribe to webhook events.
     *
     * @since 1.0.0
     * @param WebhookSubscription $subscription Subscription to create
     * @return string Subscription ID
     */
    public function subscribe(WebhookSubscription $subscription): string {
        $this->repository->saveSubscription($subscription);

        $this->logger->info('Webhook subscription created', [
            'subscription_id' => $subscription->id,
            'url' => $subscription->url,
            'events' => $subscription->events,
        ]);

        return $subscription->id;
    }

    /**
     * Unsubscribe from webhook events.
     *
     * @since 1.0.0
     * @param string $id Subscription ID to remove
     * @return void
     */
    public function unsubscribe(string $id): void {
        $this->repository->deleteSubscription($id);

        $this->logger->info('Webhook subscription removed', [
            'subscription_id' => $id,
        ]);
    }

    /**
     * Get subscription by ID.
     *
     * @since 1.0.0
     * @param string $id Subscription ID
     * @return WebhookSubscription|null Subscription or null
     */
    public function getSubscription(string $id): ?WebhookSubscription {
        return $this->repository->findSubscription($id);
    }

    /**
     * List subscriptions, optionally filtered by event.
     *
     * @since 1.0.0
     * @param string|null $event Optional event filter
     * @return array Array of subscriptions
     */
    public function listSubscriptions(?string $event = null): array {
        if ($event !== null) {
            return $this->repository->findByEvent($event);
        }

        // Get all from database
        global $wpdb;
        $table = $wpdb->prefix . 'bitcore_webhooks';
        $results = $wpdb->get_results("SELECT * FROM {$table}", ARRAY_A);

        return array_map(fn($row) => new WebhookSubscription(
            $row['id'],
            $row['url'],
            json_decode($row['events'], true) ?: [],
            $row['secret'],
            (bool) $row['active'],
            $row['tenant_id'] ? (int) $row['tenant_id'] : null,
            (int) $row['max_retries'],
            json_decode($row['headers'], true) ?: []
        ), $results);
    }

    /**
     * Dispatch event to all matching subscriptions.
     *
     * @since 1.0.0
     * @param string $event Event type
     * @param array $payload Event payload
     * @return void
     */
    public function dispatch(string $event, array $payload): void {
        $subscriptions = $this->repository->findByEvent($event);

        foreach ($subscriptions as $subscription) {
            if (!$subscription->active) {
                continue;
            }

            $deliveryId = $this->generateDeliveryId();
            $delivery = new WebhookDelivery(
                $deliveryId,
                $subscription->id,
                $event,
                $payload,
                new DateTimeImmutable()
            );

            $this->repository->saveDelivery($delivery);

            if ($this->queue !== null) {
                // Async delivery via queue
                $this->queue->dispatch('webhook.deliver', [
                    'delivery_id' => $deliveryId,
                ]);

                $this->logger->info('Webhook queued for async delivery', [
                    'delivery_id' => $deliveryId,
                    'subscription_id' => $subscription->id,
                    'event' => $event,
                ]);
            } else {
                // Synchronous delivery
                $this->deliver($delivery);
            }
        }

        $this->logger->info('Webhook event dispatched', [
            'event' => $event,
            'subscription_count' => count($subscriptions),
        ]);
    }

    /**
     * Dispatch event to specific subscription.
     *
     * @since 1.0.0
     * @param string $subscriptionId Target subscription ID
     * @param string $event Event type
     * @param array $payload Event payload
     * @return WebhookResult Delivery result
     */
    public function dispatchTo(string $subscriptionId, string $event, array $payload): WebhookResult {
        $subscription = $this->repository->findSubscription($subscriptionId);

        if (!$subscription) {
            return new WebhookResult(
                false,
                0,
                [],
                '',
                new \\Exception("Subscription not found: {$subscriptionId}")
            );
        }

        if (!$subscription->active) {
            return new WebhookResult(
                false,
                0,
                [],
                '',
                new \\Exception("Subscription is inactive: {$subscriptionId}")
            );
        }

        $deliveryId = $this->generateDeliveryId();
        $delivery = new WebhookDelivery(
            $deliveryId,
            $subscription->id,
            $event,
            $payload,
            new DateTimeImmutable()
        );

        $this->repository->saveDelivery($delivery);

        return $this->deliver($delivery);
    }

    /**
     * Execute webhook delivery.
     *
     * @since 1.0.0
     * @param WebhookDelivery $delivery Delivery to execute
     * @return WebhookResult Delivery result
     */
    public function deliver(WebhookDelivery $delivery): WebhookResult {
        $subscription = $this->repository->findSubscription($delivery->subscriptionId);

        if (!$subscription) {
            $this->repository->updateDeliveryStatus($delivery->id, WebhookDeliveryStatus::FAILED);
            return new WebhookResult(
                false,
                0,
                [],
                '',
                new \\Exception("Subscription not found: {$delivery->subscriptionId}")
            );
        }

        // Check for deduplication (60s TTL)
        if ($this->cache !== null) {
            $dedupKey = "webhook:dedup:{$delivery->id}";
            if ($this->cache->has($dedupKey)) {
                return new WebhookResult(
                    true,
                    200,
                    [],
                    '',
                    null,
                    0
                );
            }
            $this->cache->set($dedupKey, true, 60);
        }

        $payload = wp_json_encode($delivery->payload);
        $signature = $this->generateSignature($payload, $subscription->secret);
        $timestamp = time();

        $headers = array_merge($subscription->getHeaders(), [
            'X-Webhook-Signature' => $signature,
            'X-Webhook-Event' => $delivery->event,
            'X-Webhook-Id' => $delivery->id,
            'X-Webhook-Timestamp' => (string) $timestamp,
        ]);

        $startTime = hrtime(true);

        try {
            $response = $this->httpClient->request('POST', $subscription->url, [
                'headers' => $headers,
                'body' => $payload,
                'timeout' => 30,
            ]);

            $duration = (int) ((hrtime(true) - $startTime) / 1e6); // Convert to ms

            $statusCode = $response->getStatusCode();
            $success = $statusCode >= 200 && $statusCode < 300;

            $result = new WebhookResult(
                $success,
                $statusCode,
                $response->getHeaders(),
                (string) $response->getBody(),
                null,
                $duration
            );

            // Record attempt
            $delivery->recordAttempt([
                'timestamp' => time(),
                'status_code' => $statusCode,
                'success' => $success,
                'duration' => $duration,
            ]);

            // Update delivery status
            $newStatus = $success ? WebhookDeliveryStatus::DELIVERED : 
                ($this->shouldRetry($statusCode) ? WebhookDeliveryStatus::RETRYING : WebhookDeliveryStatus::FAILED);

            $delivery->status = $newStatus;

            if ($success) {
                $delivery->deliveredAt = new DateTimeImmutable();
            }

            $this->repository->saveDelivery($delivery);

            // Log delivery
            $logLevel = $success ? 'info' : ($newStatus === WebhookDeliveryStatus::RETRYING ? 'warning' : 'error');
            $this->logger->$logLevel('Webhook delivery attempt', [
                'delivery_id' => $delivery->id,
                'subscription_id' => $subscription->id,
                'url' => $subscription->url,
                'event' => $delivery->event,
                'status_code' => $statusCode,
                'success' => $success,
                'duration_ms' => $duration,
                'attempt' => $delivery->attempts,
            ]);

            // Schedule retry if needed
            if (!$success && $newStatus === WebhookDeliveryStatus::RETRYING && $delivery->attempts < $subscription->maxRetries) {
                $delay = $this->retryStrategy->getDelay($delivery->attempts);
                // Schedule via WordPress cron or queue
                if ($this->queue !== null) {
                    $this->queue->dispatch('webhook.retry', [
                        'delivery_id' => $delivery->id,
                    ], $delay);
                }
            }

            return $result;

        } catch (Throwable $e) {
            $duration = (int) ((hrtime(true) - $startTime) / 1e6);

            $delivery->recordAttempt([
                'timestamp' => time(),
                'error' => $e->getMessage(),
                'success' => false,
                'duration' => $duration,
            ]);

            $delivery->status = $delivery->attempts < $subscription->maxRetries 
                ? WebhookDeliveryStatus::RETRYING 
                : WebhookDeliveryStatus::FAILED;

            $this->repository->saveDelivery($delivery);

            $this->logger->error('Webhook delivery failed', [
                'delivery_id' => $delivery->id,
                'subscription_id' => $subscription->id,
                'url' => $subscription->url,
                'event' => $delivery->event,
                'error' => $e->getMessage(),
                'attempt' => $delivery->attempts,
            ]);

            // Schedule retry
            if ($delivery->status === WebhookDeliveryStatus::RETRYING && $this->queue !== null) {
                $delay = $this->retryStrategy->getDelay($delivery->attempts);
                $this->queue->dispatch('webhook.retry', [
                    'delivery_id' => $delivery->id,
                ], $delay);
            }

            return new WebhookResult(
                false,
                0,
                [],
                '',
                $e,
                $duration
            );
        }
    }

    /**
     * Retry a failed delivery.
     *
     * @since 1.0.0
     * @param string $deliveryId Delivery ID to retry
     * @return WebhookResult Retry result
     */
    public function retry(string $deliveryId): WebhookResult {
        // Find delivery - need to query directly since we don't have findDelivery method
        global $wpdb;
        $table = $wpdb->prefix . 'bitcore_webhook_deliveries';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %s",
            $deliveryId
        ), ARRAY_A);

        if (!$row) {
            return new WebhookResult(
                false,
                0,
                [],
                '',
                new \\Exception("Delivery not found: {$deliveryId}")
            );
        }

        $delivery = new WebhookDelivery(
            $row['id'],
            $row['subscription_id'],
            $row['event'],
            json_decode($row['payload'], true) ?: [],
            new DateTimeImmutable($row['created_at']),
            WebhookDeliveryStatus::RETRYING,
            (int) $row['attempts'],
            $row['delivered_at'] ? new DateTimeImmutable($row['delivered_at']) : null,
            json_decode($row['attempt_history'], true) ?: []
        );

        return $this->deliver($delivery);
    }

    /**
     * Verify webhook signature.
     *
     * @since 1.0.0
     * @param string $payload Raw payload body
     * @param string $signature Signature header value
     * @param string $secret Webhook secret
     * @return bool True if valid
     */
    public function verifySignature(string $payload, string $signature, string $secret): bool {
        return $this->signatureVerifier->verify($payload, $signature, $secret);
    }

    /**
     * Generate webhook signature.
     *
     * @since 1.0.0
     * @param string $payload Raw payload body
     * @param string $secret Webhook secret
     * @return string Generated signature
     */
    public function generateSignature(string $payload, string $secret): string {
        return $this->signatureVerifier->generate($payload, $secret);
    }

    /**
     * Determine if status code should trigger retry.
     *
     * @param int $statusCode HTTP status code
     * @return bool True if should retry
     */
    private function shouldRetry(int $statusCode): bool {
        // 5xx server errors and 429 rate limit = retry
        if ($statusCode >= 500 || $statusCode === 429) {
            return true;
        }

        // 4xx client errors = permanent failure (don't retry)
        if ($statusCode >= 400 && $statusCode < 500) {
            return false;
        }

        // 2xx and 3xx = success
        return false;
    }

    /**
     * Generate unique delivery ID.
     *
     * @return string UUID
     */
    private function generateDeliveryId(): string {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff), random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
        );
    }
}

// Test scaffold comments
/**
 * TEST SCAFFOLD
 *
 * @test WebhookSubscription creation and event matching
 * @test WebhookDelivery status transitions
 * @test WebhookDeliveryStatus enum values
 * @test WebhookResult success/failure states
 * @test WebhookRepository CRUD operations
 * @test SignatureVerifier HMAC generation and verification
 * @test ExponentialBackoffStrategy delay calculation with jitter
 * @test RetryStrategyInterface implementation
 * @test WebhookManager subscription lifecycle
 * @test WebhookManager dispatch to multiple subscribers
 * @test WebhookManager sync delivery with HTTP client
 * @test WebhookManager async delivery via queue
 * @test WebhookManager retry mechanism
 * @test WebhookManager signature generation/verification
 * @test WebhookManager deduplication with cache
 * @test WebhookManager delivery logging
 * @test WebhookManager HTTP status code retry logic (2xx, 4xx, 5xx, 429)
 * @test WebhookManager tenant-aware filtering
 * @test Database table creation for subscriptions and deliveries
 * @test Prune old deliveries functionality
 */
