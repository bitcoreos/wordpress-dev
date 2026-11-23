<?php
/**
 * ElizaOS Bridge REST API — Rate limiting, x402 payment gating and rate-limit response headers.
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

trait BITCORE_Rest_Eliza_Rate_Limit_Trait {

    /**
     * Check daily rate limit using wp_usermeta.
     *
     * @return bool|WP_Error
     */
    private function check_rate_limit() {
        $user_id = (int) ($this->rate_limit_user_id ?? get_current_user_id());
        $this->rate_limit_user_id = $user_id > 0 ? $user_id : null;

        $decision = $this->credits_manager->can_consume(
            $user_id,
            $this->credits_agent_id,
            1,
            $this->credits_resource_type,
            false
        );

        if (!($decision['allowed'] ?? false)) {
            return $this->credits_error($decision);
        }

        $this->credits_manager->consume(
            $user_id,
            $this->credits_agent_id,
            1,
            $this->credits_resource_type,
            'eliza_proxy',
            ['endpoint' => 'eliza']
        );

        return true;
    }

    /**
     * Check rate limit with x402 payment bypass.
     * If rate limit exceeded and X-Payment header present, bypass rate limit.
     * If rate limit exceeded and no X-Payment, return 402 Payment Required.
     *
     * @return bool|WP_Error
     */
    private function check_rate_limit_with_x402() {
        $user_id = (int) ($this->rate_limit_user_id ?? get_current_user_id());
        $this->rate_limit_user_id = $user_id > 0 ? $user_id : null;

        $payment = $this->get_payment_header();
        $verified = $payment !== '' && $this->credits_manager->verify_x402_payment($payment);
        $decision = $this->credits_manager->can_consume(
            $user_id,
            $this->credits_agent_id,
            1,
            $this->credits_resource_type,
            $verified
        );

        if (!($decision['allowed'] ?? false)) {
            return $this->credits_error($decision);
        }

        if (!empty($decision['paid'])) {
            $this->x402_bypass = true;
            return true;
        }

        $this->credits_manager->consume(
            $user_id,
            $this->credits_agent_id,
            1,
            $this->credits_resource_type,
            'eliza_proxy',
            ['endpoint' => 'eliza']
        );

        return true;
    }

    /**
     * Get rate limit info for the current user.
     *
     * @param int $user_id WordPress user ID
     * @return array{limit: int, remaining: int, reset: int, plan: string}
     */
    private function get_rate_limit_info(int $user_id): array {
        $status = $this->credits_manager->get_status(
            $user_id,
            $this->credits_agent_id,
            $this->credits_resource_type
        );

        // Calculate midnight UTC timestamp for reset
        $tomorrow = strtotime('tomorrow 00:00:00 UTC');

        return [
            'limit'      => (int) ($status['daily_limit'] ?? 0),
            'remaining'  => (int) ($status['remaining_today'] ?? 0),
            'reset'      => $tomorrow,
            'plan'       => current_user_can('manage_options') ? 'admin' : (string) ($status['tier'] ?? 'standard'),
        ];
    }

    // =========================================================================
    // x402 Payment Middleware
    // =========================================================================

    /**
     * Get X-Payment header from the current request.
     *
     * @return string Payment token/value or empty string if not present
     */
    private function get_payment_header(): string {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        $payment = $_SERVER['HTTP_X_PAYMENT'] ?? '';
        return sanitize_text_field($payment);
    }

    /**
     * Check x402 payment validity.
     *
     * Checks for X-Payment header in request and delegates verification to
     * registered x402 verifier/facilitator filters.
     *
     * @return bool Whether payment is valid
     */
    public function check_x402_payment(): bool {
        $payment = $this->get_payment_header();

        if (empty($payment)) {
            return false;
        }

        /**
         * Filter to verify x402 payment.
         *
         * @param bool   $verified Whether payment is verified (default: false)
         * @param string $payment  The payment token/value from X-Payment header
         */
        return (bool) apply_filters('bitcore_eliza_x402_verify', false, $payment);
    }

    // =========================================================================
    // Response Headers
    // =========================================================================

    /**
     * Add rate limit headers to all Eliza proxy responses.
     *
     * Hooks into rest_post_dispatch to inject X-RateLimit-* headers
     * and X-Wallet-Address header when appropriate.
     *
     * @param WP_REST_Response|WP_Error $response Response object
     * @param WP_REST_Server             $server   Server object
     * @param WP_REST_Request           $request  Request object
     * @return WP_REST_Response|WP_Error Modified response
     */
    public function add_rate_limit_headers($response, $server, $request) {
        // Only process Eliza endpoints
        $route = $request->get_route();
        if (strpos($route, '/bitcore/v1/eliza') === false) {
            return $response;
        }

        // Only add headers on WP_REST_Response objects
        if (!is_object($response) || !method_exists($response, 'header')) {
            return $response;
        }

        // Add rate limit headers for authenticated non-admin users without
        // repeating is_user_logged_in(); permission callbacks cache this context.
        $user_id = (int) ($this->rate_limit_user_id ?? get_current_user_id());
        if ($user_id > 0 && !current_user_can('manage_options')) {
            $this->rate_limit_user_id = $user_id;
            $info = $this->get_rate_limit_info($user_id);

            $response->header('X-RateLimit-Limit', (string) $info['limit']);
            $response->header('X-RateLimit-Remaining', (string) $info['remaining']);
            $response->header('X-RateLimit-Reset', (string) $info['reset']);
        }

        // Add X-Wallet-Address header for 402 responses
        if (is_object($response) && method_exists($response, 'get_status')) {
            if ($response->get_status() === 402) {
                $wallet = get_option('bitcore_eliza_x402_wallet', '');
                if (!empty($wallet)) {
                    $response->header('X-Wallet-Address', $wallet);
                }
            }
        }

        return $response;
    }

    // =========================================================================
    // Proxy Helper
    // =========================================================================

    /**
     * GET /eliza/rate-limit — Get rate limit status for the current user.
     */
    public function get_rate_limit(): WP_REST_Response {
        $user_id = get_current_user_id();
        $info    = $this->get_rate_limit_info($user_id);

        return new WP_REST_Response([
            'limit'      => $info['limit'],
            'remaining'  => $info['remaining'],
            'reset'      => gmdate('Y-m-d\TH:i:s\Z', $info['reset']),
            'plan'       => $info['plan'],
        ], 200);
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

}
