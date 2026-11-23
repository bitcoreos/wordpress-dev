<?php
/**
 * Webhook delivery and retry
 *
 * Extracted verbatim from wp-content/plugins/bitcore-core/infrastructure/webhooks/WebhookManager.php (BITCORE 500-LOC refactor); logic unchanged.
 *
 * @package    Bitcore
 * @since      2026.03.01
 * @version    1.3.0
 * @author     coraline
 */

declare(strict_types=1);


namespace BITCORE\Infrastructure\Webhooks;

use Throwable;


if (!defined('ABSPATH')) {
    exit;
}


trait BITCORE_Webhook_Delivery_Trait {

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


}
