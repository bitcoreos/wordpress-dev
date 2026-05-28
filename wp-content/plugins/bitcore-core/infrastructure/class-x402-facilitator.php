<?php
/**
 * x402 Facilitator Client
 *
 * Fails closed unless a configured facilitator verifies and, by default,
 * settles the payment payload against the server payment requirements.
 *
 * Protocol evidence: x402 resource servers POST the Base64 payment payload
 * plus payment requirements to facilitator /verify and /settle endpoints.
 *
 * @package BITCORE
 * @subpackage Infrastructure
 */

declare(strict_types=1);

namespace BITCORE\Infrastructure;

if (!defined('ABSPATH')) {
    exit;
}

class X402_Facilitator_Client {

    private string $base_url = '';
    private string $token = '';
    private bool $settle = true;
    private string $resource = '';
    private int $timeout = 15;

    public function __construct() {
        $this->base_url = rtrim(esc_url_raw((string) get_option('bitcore_eliza_x402_facilitator_url', '')), '/');
        $this->token    = sanitize_text_field((string) get_option('bitcore_eliza_x402_facilitator_token', ''));
        $this->settle   = $this->truthy(get_option('bitcore_eliza_x402_facilitator_settle', '1'));
        $this->resource = sanitize_text_field((string) get_option('bitcore_eliza_x402_resource', '/wp-json/bitcore/v1/eliza/chat'));
        $this->timeout  = max(3, (int) get_option('bitcore_eliza_x402_facilitator_timeout', 15));

        add_filter('bitcore_x402_facilitator_verify', [$this, 'verify_and_settle'], 10, 5);
    }

    /**
     * Verify and optionally settle via configured x402 facilitator.
     *
     * @param bool                $default Default false.
     * @param array<string,mixed> $payload Decoded x402 payment payload.
     * @param array<string,mixed> $requirements Local payment requirements.
     * @param array<string,mixed> $normalized Locally normalized fields.
     * @param string              $payment_header Original Base64 payment header.
     */
    public function verify_and_settle(
        bool $default,
        array $payload,
        array $requirements,
        array $normalized,
        string $payment_header = ''
    ): bool {
        if ($this->base_url === '' || $payment_header === '') {
            $this->log('rejected: facilitator URL or payment header missing');
            return false;
        }

        $body = $this->request_body($payload, $requirements, $normalized, $payment_header);

        $verify = $this->post_json('/verify', $body);
        if (!$this->response_flag($verify, ['isValid', 'valid', 'verified', 'success'])) {
            $this->log('rejected: facilitator verify failed');
            return false;
        }

        if (!$this->settle) {
            $this->log('accepted: facilitator verify succeeded; settlement disabled by configuration');
            return true;
        }

        $settle = $this->post_json('/settle', $body);
        if (!$this->response_flag($settle, ['success', 'settled', 'isSettled', 'valid'])) {
            $this->log('rejected: facilitator settle failed');
            return false;
        }

        $this->log('accepted: facilitator verify and settle succeeded');
        return true;
    }

    /**
     * Build facilitator request body.
     *
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $requirements
     * @param array<string,mixed> $normalized
     * @return array<string,mixed>
     */
    private function request_body(array $payload, array $requirements, array $normalized, string $payment_header): array {
        $payment_requirements = [
            'scheme'            => (string) ($normalized['scheme'] ?? $payload['scheme'] ?? 'exact'),
            'network'           => (string) ($requirements['network'] ?? $normalized['network'] ?? ''),
            'payTo'             => (string) ($requirements['destination'] ?? $normalized['destination'] ?? ''),
            'asset'             => (string) ($requirements['asset'] ?? $normalized['asset'] ?? ''),
            'maxAmountRequired' => (string) ($requirements['min_amount'] ?? $normalized['amount'] ?? ''),
            'resource'          => $this->resource,
        ];

        $payment_requirements = array_filter(
            $payment_requirements,
            static fn($value): bool => $value !== ''
        );

        return [
            'x402Version'         => $payload['x402Version'] ?? $payload['version'] ?? 2,
            'paymentHeader'       => $payment_header,
            'paymentRequirements' => $payment_requirements,
        ];
    }

    /**
     * POST JSON to facilitator endpoint and return decoded JSON, or null on any failure.
     *
     * @param array<string,mixed> $body
     * @return array<string,mixed>|null
     */
    private function post_json(string $path, array $body): ?array {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];

        if ($this->token !== '') {
            $headers['Authorization'] = 'Bearer ' . $this->token;
        }

        $response = wp_remote_post($this->base_url . $path, [
            'headers' => $headers,
            'timeout' => $this->timeout,
            'body'    => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            return null;
        }

        try {
            $decoded = json_decode(wp_remote_retrieve_body($response), true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Require an explicit boolean true success flag. Strings are not accepted.
     *
     * @param array<string,mixed>|null $response
     * @param array<int,string>        $fields
     */
    private function response_flag(?array $response, array $fields): bool {
        if ($response === null) {
            return false;
        }

        foreach ($fields as $field) {
            if (array_key_exists($field, $response)) {
                return $response[$field] === true;
            }
        }

        return false;
    }

    private function truthy(mixed $value): bool {
        if (is_bool($value)) {
            return $value;
        }
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    private function log(string $message): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[BITCORE x402 facilitator] ' . $message);
        }
    }
}
