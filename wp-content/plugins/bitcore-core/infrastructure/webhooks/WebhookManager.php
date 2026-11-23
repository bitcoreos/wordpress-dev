<?php
/**
 * Main webhook manager class for Bitcore webhook processing
 *
 * @package    Bitcore
 * @since      2026.03.01
 * @version    1.3.0
 * @author     hyperion
 */

declare(strict_types=1);

namespace BITCORE\Infrastructure\Webhooks;

require_once __DIR__ . '/traits/trait-webhook-settable.php';
require_once __DIR__ . '/traits/trait-webhook-subscriptions.php';
require_once __DIR__ . '/traits/trait-webhook-dispatch.php';
require_once __DIR__ . '/traits/trait-webhook-delivery.php';
require_once __DIR__ . '/traits/trait-webhook-signature.php';

use Psr\Log\LoggerInterface;
if (!defined('ABSPATH')) {
    exit;

}

class WebhookManager {
    use BITCORE_Webhook_Settable_Trait;
    use BITCORE_Webhook_Subscriptions_Trait;
    use BITCORE_Webhook_Dispatch_Trait;
    use BITCORE_Webhook_Delivery_Trait;
    use BITCORE_Webhook_Signature_Trait;
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
