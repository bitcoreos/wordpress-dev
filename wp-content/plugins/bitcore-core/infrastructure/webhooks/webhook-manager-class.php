<?php
/**
 * Webhook Manager Class
 *
 * @package BITCORE\Infrastructure\Webhooks
 * @since 1.0.0
 */

declare(strict_types=1);

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
                new \Exception("Subscription not found: {$subscriptionId}")
            );
        }

        if (!$subscription->active) {
            return new WebhookResult(
                false,
                0,
                [],
                '',
                new \Exception("Subscription is inactive: {$subscriptionId}")
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
                new \Exception("Subscription not found: {$delivery->subscriptionId}")
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
                new \Exception("Delivery not found: {$deliveryId}")
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
