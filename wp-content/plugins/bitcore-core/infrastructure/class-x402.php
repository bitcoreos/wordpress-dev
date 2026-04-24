<?php
/**
 * x402 Payment Verification
 *
 * Validates X-Payment headers for x402 protocol access.
 * Registers the `bitcore_eliza_x402_verify` filter callback
 * to enable payment-gated API access.
 *
 * Header format: x402,amount=<cents>,wallet=<address>
 *
 * @package BITCORE
 * @subpackage Infrastructure
 * @since 1.2.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) exit;

namespace BITCORE\Infrastructure;

class X402_Verifier {

    /**
     * Configured wallet address for payment verification.
     *
     * @var string
     */
    private string $wallet = '';

    /**
     * Register the x402 verification filter on construction.
     */
    public function __construct() {
        $this->wallet = get_option('bitcore_eliza_x402_wallet', '');
        add_filter('bitcore_eliza_x402_verify', [$this, 'verify_payment'], 10, 2);
    }

    /**
     * Verify an x402 payment header.
     *
     * Expected format: x402,amount=<cents>,wallet=<address>
     *
     * @param bool   $default  Default verification result (always false from filter).
     * @param string $payment  Raw X-Payment header value.
     * @return bool  True if payment is valid, false otherwise.
     */
    public function verify_payment(bool $default, string $payment): bool {
        if (empty($payment)) {
            $this->log('rejected: empty payment header');
            return false;
        }

        // Parse the x402 header format: x402,amount=<cents>,wallet=<address>
        $parsed = $this->parse_payment($payment);

        if ($parsed === null) {
            $this->log('rejected: invalid format — ' . $payment);
            return false;
        }

        // Verify wallet matches configured wallet
        if (!empty($this->wallet) && strtolower($parsed['wallet']) !== strtolower($this->wallet)) {
            $this->log('rejected: wallet mismatch — expected ' . $this->wallet . ', got ' . $parsed['wallet']);
            return false;
        }

        // Verify amount is positive
        if ($parsed['amount'] <= 0) {
            $this->log('rejected: invalid amount — ' . $parsed['amount']);
            return false;
        }

        $this->log('accepted: amount=' . $parsed['amount'] . ', wallet=' . $parsed['wallet']);
        return true;
    }

    /**
     * Parse an x402 payment header string.
     *
     * @param string $payment  Raw header value.
     * @return array|null  Associative array with 'amount' (int) and 'wallet' (string), or null on failure.
     */
    private function parse_payment(string $payment): ?array {
        // Format: x402,amount=<cents>,wallet=<address>
        $parts = explode(',', $payment);

        // Must start with 'x402' prefix
        if (count($parts) < 3 || strtolower(trim($parts[0])) !== 'x402') {
            return null;
        }

        $amount = 0;
        $wallet = '';

        foreach ($parts as $part) {
            $kv = explode('=', $part, 2);
            if (count($kv) !== 2) {
                continue;
            }
            $key   = strtolower(trim($kv[0]));
            $value = trim($kv[1]);

            if ($key === 'amount') {
                $amount = (int) $value;
            } elseif ($key === 'wallet') {
                $wallet = $value;
            }
        }

        if ($wallet === '') {
            return null;
        }

        return ['amount' => $amount, 'wallet' => $wallet];
    }

    /**
     * Log a verification event to WordPress debug.log.
     *
     * @param string $message  Log message.
     */
    private function log(string $message): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[BITCORE x402] ' . $message);
        }
    }
}
