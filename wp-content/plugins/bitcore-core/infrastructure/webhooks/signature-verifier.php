<?php
/**
 * Webhook Signature Verifier
 *
 * HMAC-SHA256 signature generation and verification.
 *
 * @package BITCORE\Infrastructure\Webhooks
 * @since 1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Infrastructure\Webhooks;

/**
 * Signature Verifier
 *
 * Handles HMAC-SHA256 signature generation and verification for webhooks.
 *
 * @since 1.0.0
 */
class SignatureVerifier {
    /**
     * Algorithm used for HMAC signatures.
     */
    private const ALGORITHM = 'sha256';

    /**
     * Generate HMAC signature for payload.
     *
     * @since 1.0.0
     * @param string $payload Raw payload to sign
     * @param string $secret Signing secret
     * @return string Hex-encoded signature with prefix
     */
    public function generate(string $payload, string $secret): string {
        $signature = hash_hmac(self::ALGORITHM, $payload, $secret);
        return 'sha256=' . $signature;
    }

    /**
     * Verify webhook signature.
     *
     * Uses timing-safe comparison to prevent timing attacks.
     *
     * @since 1.0.0
     * @param string $payload Raw payload body
     * @param string $signature Signature header value
     * @param string $secret Webhook secret
     * @return bool True if signature is valid
     */
    public function verify(string $payload, string $signature, string $secret): bool {
        // Remove prefix if present
        $providedSignature = preg_replace('/^sha256=/', '', $signature);
        
        if (empty($providedSignature)) {
            return false;
        }
        
        $expectedSignature = hash_hmac(self::ALGORITHM, $payload, $secret);
        
        return hash_equals($expectedSignature, $providedSignature);
    }

    /**
     * Verify signature with timestamp for replay attack prevention.
     *
     * @since 1.0.0
     * @param string $payload Raw payload body
     * @param string $signature Signature header value
     * @param string $secret Webhook secret
     * @param int $timestamp Timestamp from webhook
     * @param int $toleranceSeconds Maximum age in seconds (default 300)
     * @return bool True if signature is valid and timestamp within tolerance
     */
    public function verifyWithTimestamp(
        string $payload,
        string $signature,
        string $secret,
        int $timestamp,
        int $toleranceSeconds = 300
    ): bool {
        // Check timestamp is within tolerance
        $now = time();
        if (abs($now - $timestamp) > $toleranceSeconds) {
            return false;
        }
        
        // Sign payload with timestamp
        $signedPayload = "{$timestamp}.{$payload}";
        return $this->verify($signedPayload, $signature, $secret);
    }
}
