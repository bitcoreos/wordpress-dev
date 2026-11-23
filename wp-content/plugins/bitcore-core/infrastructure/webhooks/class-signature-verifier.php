<?php
/**
 * HMAC-SHA256 Signature Verifier
 *
 * Handles webhook signature generation and verification.
 *
 * @package BITCORE\Infrastructure\Webhooks
 * @since 1.0.0
 * @version     1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Infrastructure\Webhooks;

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
