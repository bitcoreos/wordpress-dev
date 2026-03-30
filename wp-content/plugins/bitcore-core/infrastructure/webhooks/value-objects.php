<?php
/**
 * Webhook Value Objects
 *
 * Data structures for webhook subscriptions, deliveries, and results.
 *
 * @package BITCORE\Infrastructure\Webhooks
 * @since 1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Infrastructure\Webhooks;

use DateTimeImmutable;
use Throwable;

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
