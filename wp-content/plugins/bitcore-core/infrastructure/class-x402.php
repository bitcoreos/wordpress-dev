<?php
/**
 * x402 Payment Verification
 *
 * Validates structured payment proof headers for x402-gated API access.
 *
 * This verifier does not pretend local string parsing proves settlement.
 * It performs deterministic local requirement checks, then delegates
 * cryptographic/on-chain verification and settlement to a facilitator filter.
 *
 * Expected payment header shape: Base64-encoded JSON payment payload.
 * Legacy comma claims like `x402,amount=...,wallet=...` are rejected.
 *
 * @package BITCORE
 * @subpackage Infrastructure
 * @since 1.2.0
 */

declare(strict_types=1);

namespace BITCORE\Infrastructure;

if (!defined('ABSPATH')) {
    exit;
}

class X402_Verifier {

    /** @var string Configured receiver wallet address. */
    private string $wallet = '';

    /** @var int Minimum accepted payment amount in smallest configured units. */
    private int $min_amount = 1;

    /** @var string Expected payment network, if configured. */
    private string $network = '';

    /** @var string Expected payment asset, if configured. */
    private string $asset = '';

    /**
     * Register the x402 verification filter on construction.
     */
    public function __construct() {
        $this->wallet     = sanitize_text_field((string) get_option('bitcore_eliza_x402_wallet', ''));
        $this->min_amount = max(1, (int) get_option('bitcore_eliza_x402_min_amount', 1));
        $this->network    = sanitize_text_field((string) get_option('bitcore_eliza_x402_network', ''));
        $this->asset      = sanitize_text_field((string) get_option('bitcore_eliza_x402_asset', ''));

        add_filter('bitcore_eliza_x402_verify', [$this, 'verify_payment'], 10, 2);
    }

    /**
     * Verify an x402 payment header.
     *
     * @param bool   $default Default verification result.
     * @param string $payment Raw payment header value.
     * @return bool True only when local requirements pass and facilitator approves.
     */
    public function verify_payment(bool $default, string $payment): bool {
        $payment = trim($payment);
        if ($payment === '') {
            $this->log('rejected: empty payment header');
            return false;
        }

        $payload = $this->decode_payment_payload($payment);
        if ($payload === null) {
            $this->log('rejected: payment header is not valid Base64 JSON');
            return false;
        }

        $normalized = $this->normalize_payload($payload);
        if ($normalized === null) {
            $this->log('rejected: payment payload missing required fields');
            return false;
        }

        if (!$this->matches_local_requirements($normalized)) {
            return false;
        }

        $requirements = $this->payment_requirements();

        /**
         * Verifies cryptographic payment authorization and settlement.
         *
         * A production callback must validate signature, balance/allowance,
         * amount, destination, network, asset, freshness, replay protection,
         * and settlement/facilitator response before returning true.
         *
         * @param bool  $verified     Default false.
         * @param array $payload      Decoded original payment payload.
         * @param array $requirements Local payment requirements.
         * @param array $normalized   Normalized local fields.
         */
        $verified = (bool) apply_filters(
            'bitcore_x402_facilitator_verify',
            false,
            $payload,
            $requirements,
            $normalized,
            $payment
        );

        if (!$verified) {
            $this->log('rejected: facilitator verification failed');
            return false;
        }

        $this->log('accepted: facilitator verified payment');
        return true;
    }

    /**
     * Decode a Base64 JSON x402 payment payload.
     *
     * @param string $payment Raw payment header.
     * @return array<string,mixed>|null
     */
    private function decode_payment_payload(string $payment): ?array {
        $decoded = base64_decode($payment, true);
        if ($decoded === false) {
            return null;
        }

        try {
            $payload = json_decode($decoded, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return null;
        }

        return is_array($payload) ? $payload : null;
    }

    /**
     * Normalize flat or nested x402 payloads into locally checkable fields.
     *
     * @param array<string,mixed> $payload Decoded payload.
     * @return array<string,mixed>|null
     */
    private function normalize_payload(array $payload): ?array {
        $nested = is_array($payload['payload'] ?? null) ? $payload['payload'] : [];
        $authorization = is_array($nested['authorization'] ?? null) ? $nested['authorization'] : [];

        $signature = $this->first_non_empty([
            $payload['signature'] ?? null,
            $nested['signature'] ?? null,
            $authorization['signature'] ?? null,
        ]);
        if ($signature === '') {
            return null;
        }

        $destination = $this->first_non_empty([
            $payload['destination'] ?? null,
            $payload['to'] ?? null,
            $payload['wallet'] ?? null,
            $nested['destination'] ?? null,
            $nested['to'] ?? null,
            $authorization['to'] ?? null,
            $authorization['destination'] ?? null,
        ]);

        $amount = $this->first_numeric([
            $payload['amount'] ?? null,
            $payload['value'] ?? null,
            $payload['maxAmountRequired'] ?? null,
            $nested['amount'] ?? null,
            $nested['value'] ?? null,
            $authorization['value'] ?? null,
            $authorization['amount'] ?? null,
        ]);

        if ($destination === '' || $amount === null) {
            return null;
        }

        return [
            'signature'   => $signature,
            'destination' => $destination,
            'amount'      => $amount,
            'network'     => $this->first_non_empty([$payload['network'] ?? null, $nested['network'] ?? null]),
            'asset'       => $this->first_non_empty([$payload['asset'] ?? null, $payload['token'] ?? null, $nested['asset'] ?? null, $nested['token'] ?? null]),
            'scheme'      => $this->first_non_empty([$payload['scheme'] ?? null, $nested['scheme'] ?? null]),
            'version'     => $payload['x402Version'] ?? $payload['version'] ?? null,
        ];
    }

    /**
     * Check local deterministic requirements before facilitator verification.
     *
     * @param array<string,mixed> $normalized Normalized payload fields.
     */
    private function matches_local_requirements(array $normalized): bool {
        $destination = strtolower((string) $normalized['destination']);
        if ($this->wallet !== '' && $destination !== strtolower($this->wallet)) {
            $this->log('rejected: destination mismatch');
            return false;
        }

        if ((int) $normalized['amount'] < $this->min_amount) {
            $this->log('rejected: amount below minimum');
            return false;
        }

        if ($this->network !== '') {
            if (empty($normalized['network'])) {
                $this->log('rejected: required network missing');
                return false;
            }
            if (strtolower((string) $normalized['network']) !== strtolower($this->network)) {
                $this->log('rejected: network mismatch');
                return false;
            }
        }

        if ($this->asset !== '') {
            if (empty($normalized['asset'])) {
                $this->log('rejected: required asset missing');
                return false;
            }
            if (strtoupper((string) $normalized['asset']) !== strtoupper($this->asset)) {
                $this->log('rejected: asset mismatch');
                return false;
            }
        }

        return true;
    }

    /**
     * Build payment requirements for facilitator callbacks.
     *
     * @return array<string,mixed>
     */
    private function payment_requirements(): array {
        return [
            'destination' => $this->wallet,
            'min_amount'  => $this->min_amount,
            'network'     => $this->network,
            'asset'       => $this->asset,
        ];
    }

    /**
     * Return the first sanitized non-empty scalar value.
     *
     * @param array<int,mixed> $values Candidate values.
     */
    private function first_non_empty(array $values): string {
        foreach ($values as $value) {
            if (!is_scalar($value) || $value === '') {
                continue;
            }
            $clean = sanitize_text_field((string) $value);
            if ($clean !== '') {
                return $clean;
            }
        }
        return '';
    }

    /**
     * Return the first non-negative integer-like numeric value.
     *
     * @param array<int,mixed> $values Candidate values.
     */
    private function first_numeric(array $values): ?int {
        foreach ($values as $value) {
            if ($value === null || $value === '' || !is_numeric($value)) {
                continue;
            }
            $amount = (int) $value;
            if ($amount >= 0) {
                return $amount;
            }
        }
        return null;
    }

    /**
     * Log a verification event to WordPress debug.log when WP_DEBUG is enabled.
     */
    private function log(string $message): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[BITCORE x402] ' . $message);
        }
    }
}
