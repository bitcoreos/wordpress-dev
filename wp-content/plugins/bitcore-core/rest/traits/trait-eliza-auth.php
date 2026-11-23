<?php
/**
 * ElizaOS Bridge REST API — Authentication, permission, anonymous access and credits-manager helpers.
 *
 * @package    Bitcore
 * @since      2026.03.01
 * @version    2026.03.01
 * @author     coraline
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

trait BITCORE_Rest_Eliza_Auth_Trait {

    /**
     * Check basic permission: logged-in user with read or bitcore_access capability.
     */
    public function check_permission(): bool {
        $is_logged_in = is_user_logged_in();
        $this->rate_limit_user_id = $is_logged_in ? get_current_user_id() : null;

        if ($is_logged_in) {
            return true;
        }

        return $this->has_valid_inbound_api_key();
    }

    /**
     * Validate inbound API access for service/API clients.
     *
     * This intentionally reuses the configured ElizaOS auth token because the
     * bridge already exposes it as the administrative API credential. An empty
     * configured token never authorizes anonymous access.
     */
    private function has_valid_inbound_api_key(): bool {
        $configured = trim((string) $this->eliza_api_key);
        if ($configured === '') {
            return false;
        }

        $provided = '';
        if (isset($_SERVER['HTTP_X_API_KEY'])) {
            $provided = trim((string) wp_unslash($_SERVER['HTTP_X_API_KEY']));
        } elseif (isset($_SERVER['REDIRECT_HTTP_X_API_KEY'])) {
            $provided = trim((string) wp_unslash($_SERVER['REDIRECT_HTTP_X_API_KEY']));
        }

        return $provided !== '' && hash_equals($configured, $provided);
    }

    /**
     * Check auth with rate limiting for expensive endpoints.
     * Falls back to check_permission for logged-in users without rate limit.
     *
     * @return bool|WP_Error
     */
    public function check_auth_and_rate_limit() {
        $is_logged_in = is_user_logged_in();
        $this->rate_limit_user_id = $is_logged_in ? get_current_user_id() : null;

        if (!$is_logged_in) {
            return new WP_Error(
                'rest_not_logged_in',
                __('You must be logged in to use this endpoint.'),
                ['status' => 401]
            );
        }

        // Admins bypass rate limiting
        if (current_user_can('manage_options')) {
            return true;
        }

        return $this->check_rate_limit();
    }

    /**
     * Check anonymous access: supports both logged-in users and x402 anonymous access.
     *
     * - Logged in: normal auth + rate limiting (admins bypass)
     * - Not logged in + X-Payment header: x402 access (bypasses rate limit)
     * - Not logged in + no X-Payment: 401 unauthorized
     *
     * @return bool|WP_Error
     */
    public function check_anonymous_access() {
        $is_logged_in = is_user_logged_in();
        $this->rate_limit_user_id = $is_logged_in ? get_current_user_id() : null;

        // Service/API clients: allow configured inbound API key for chat/session routes.
        // This does not fake a WordPress user and does not weaken x402 anonymous access.
        if (!$is_logged_in && $this->has_valid_inbound_api_key()) {
            return true;
        }

        // Case 1: Anonymous user
        if (!$is_logged_in) {
            // Check if anonymous access is enabled
            if (!$this->anonymous_access) {
                return new WP_Error(
                    'rest_not_logged_in',
                    __('Authentication required. Include X-Payment header for x402 access.'),
                    ['status' => 401]
                );
            }

            // Check for x402 payment header
            $payment = $this->get_payment_header();
            if (empty($payment)) {
                return new WP_Error(
                    'rest_not_logged_in',
                    __('Authentication required. Include X-Payment header for x402 access.'),
                    ['status' => 401]
                );
            }

            // Verify x402 payment via registered verifier/facilitator filters
            $verified = apply_filters('bitcore_eliza_x402_verify', false, $payment);
            if (!$verified) {
                return new WP_Error(
                    'x402_payment_invalid',
                    __('x402 payment verification failed.'),
                    ['status' => 402]
                );
            }

            // x402 payment accepted — mark bypass for rate limit headers
            $this->x402_bypass = true;
            return true;
        }

        // Case 2: Logged-in user — admins bypass rate limiting
        if (current_user_can('manage_options')) {
            return true;
        }

        // Non-admin logged-in user — check rate limit with x402 bypass option
        return $this->check_rate_limit_with_x402();
    }

    // =========================================================================
    // Rate Limiting
    // =========================================================================

    /**
     * Create unified credits manager with a local require guard for standalone tests.
     *
     * @return \BITCORE\Infrastructure\Credits_Manager
     */
    private function create_credits_manager() {
        if (!class_exists('BITCORE\\Infrastructure\\Credits_Manager')) {
            $path = dirname(__DIR__) . '/infrastructure/class-credits.php';
            if (is_file($path)) {
                require_once $path;
            }
        }

        return new \BITCORE\Infrastructure\Credits_Manager();
    }

    /**
     * Convert a credits decision into a REST permission error.
     *
     * @param array<string,mixed> $decision Credits manager decision payload.
     */
    private function credits_error(array $decision): WP_Error {
        $status = (int) ($decision['status'] ?? 402);
        $code = sanitize_text_field((string) ($decision['code'] ?? 'x402_payment_required'));
        $message = $code === 'x402_payment_required'
            ? __('Daily call limit reached. Include X-Payment header to continue or wait for reset.')
            : __('Credit authorization failed.');

        return new WP_Error($code, $message, ['status' => $status]);
    }

}
