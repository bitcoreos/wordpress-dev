<?php
/**
 * Webhook event dispatch
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


if (!defined('ABSPATH')) {
    exit;
}


trait BITCORE_Webhook_Dispatch_Trait {

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


}
