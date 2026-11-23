<?php
/**
 * Webhook subscription lifecycle
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


trait BITCORE_Webhook_Subscriptions_Trait {

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


}
