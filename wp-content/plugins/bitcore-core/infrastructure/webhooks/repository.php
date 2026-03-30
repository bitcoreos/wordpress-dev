<?php
/**
 * Webhook Repository
 *
 * Database operations for webhook subscriptions and deliveries.
 *
 * @package BITCORE\Infrastructure\Webhooks
 * @since 1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Infrastructure\Webhooks;

use DateTimeImmutable;

/**
 * Webhook Repository
 *
 * Handles CRUD operations for webhook subscriptions and deliveries.
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

    /**
     * Get table names for setup.
     *
     * @since 1.0.0
     * @return array Array with 'subscriptions' and 'deliveries' table names
     */
    public function getTableNames(): array {
        return [
            'subscriptions' => $this->subscriptionsTable,
            'deliveries' => $this->deliveriesTable,
        ];
    }
}
