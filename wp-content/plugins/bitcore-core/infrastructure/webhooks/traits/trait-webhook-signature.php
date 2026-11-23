<?php
/**
 * Webhook signature verification and id helpers
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


trait BITCORE_Webhook_Signature_Trait {

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
