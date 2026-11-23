<?php
/**
 * Webhook dependency setters
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


trait BITCORE_Webhook_Settable_Trait {

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


}
