<?php
/**
 * Webhook Manager
 *
 * Enterprise-grade webhook subscription management, event dispatching,
 * and delivery with HMAC-SHA256 signatures and exponential backoff retry.
 *
 * @package BITCORE\Infrastructure\Webhooks
 * @since 1.0.0
 * @version     1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Infrastructure\Webhooks;

require_once __DIR__ . '/trait-webhook-repository-methods.php';

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

    use Webhook_Repository_Methods;
    private \wpdb $db;
    private string $subscriptionsTable;
    private string $deliveriesTable;









}

/**
 * Webhook Manager
 *
 * Central orchestrator for webhook subscriptions, event dispatching,
 * and delivery management.
 *
 * @since 1.0.0
 */