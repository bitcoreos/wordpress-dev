<?php
/**
 * Discourse SSO Client Redirect Fix.
 *
 * Validates that the `redirect_to` parameter during Discourse SSO Client flow
 * always points to a WordPress domain URL, never a Discourse URL.
 *
 * Bug: When a user arrives at WordPress from Discourse (hub.bitwiki.org),
 * the `redirect_to` query parameter contains `https://hub.bitwiki.org/welcome`.
 * WP-Discourse's query-redirect.php uses this as `return_sso_url` in the SSO payload.
 * Discourse then checks the host against `discourse_connect_provider_secrets` and
 * finds no match (only ashi.bitwiki.org is mapped), raising BlankSecret.
 *
 * Fix: Two-layer defense:
 *   1. `init` priority 3 — sanitize $_GET['redirect_to'] before WP-Discourse reads it
 *   2. `wpdc_sso_client_redirect_url` filter — validate at the SSO URL construction level
 *
 * @package BITCORE\Infrastructure\Auth
 * @since 1.2.0
 * @version     1.0.0
 */

declare(strict_types=1);

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Validates that a redirect URL host matches the WordPress home URL host.
 *
 * @param string $url The URL to validate.
 * @return string The validated URL, or the fallback if invalid.
 */
function bitcore_validate_sso_redirect_host(string $url): string {
    $home_host = wp_parse_url(home_url('/'), PHP_URL_HOST);
    $redirect_host = wp_parse_url($url, PHP_URL_HOST);

    // If redirect host matches WordPress home host, it's valid.
    if ($redirect_host && strtolower($redirect_host) === strtolower($home_host)) {
        return $url;
    }

    // Invalid host — use configured SSO login redirect or home URL.
    $sso_client_options = get_option('discourse_sso_client', []);
    $sso_login_redirect = is_array($sso_client_options) && !empty($sso_client_options['sso-client-login-form-redirect'])
        ? $sso_client_options['sso-client-login-form-redirect']
        : '';
    if (!empty($sso_login_redirect)) {
        return esc_url_raw($sso_login_redirect);
    }

    return home_url('/dashboard');
}

/**
 * Layer 1: Sanitize redirect_to in $_GET before WP-Discourse processes it.
 *
 * Hooks at `init` priority 3, which runs before WP-Discourse's
 * QueryRedirect constructor (which adds parse_query at default priority)
 * and before its discourse_sso_url_redirect callback.
 *
 * Only acts when discourse_sso parameter is present (SSO Client flow).
 */
function bitcore_sso_sanitize_redirect_early(): void {
    // Only act during SSO Client flow.
    if (!isset($_GET['discourse_sso'])) {
        return;
    }

    // Only act if redirect_to is present.
    if (!isset($_GET['redirect_to']) || empty($_GET['redirect_to'])) {
        return;
    }

    $redirect_to = esc_url_raw(wp_unslash($_GET['redirect_to']));
    $validated = bitcore_validate_sso_redirect_host($redirect_to);

    if ($validated !== $redirect_to) {
        // Override the $_GET parameter so WP-Discourse picks up the corrected URL.
        $_GET['redirect_to'] = $validated;

        // Log the redirect correction for debugging.
        if (function_exists('bitcore_log')) {
            bitcore_log('SSO redirect sanitized', [
                'original' => $redirect_to,
                'corrected' => $validated,
                'reason' => 'Host mismatch with WordPress home URL',
            ]);
        }
    }
}
add_action('init', 'bitcore_sso_sanitize_redirect_early', 3);

/**
 * Layer 2: Filter wpdc_sso_client_redirect_url as secondary defense.
 *
 * This filter runs in sso-client-base.php -> get_discourse_sso_url()
 * where redirect_to is passed through apply_filters().
 * It validates the URL even if $_GET was already sanitized.
 *
 * @param string $redirect_encoded The URL-encoded, escaped redirect URL.
 * @param string $redirect_raw    The raw redirect URL before encoding.
 * @return string Filtered redirect URL (URL-encoded).
 */
function bitcore_sso_filter_redirect_url(string $redirect_encoded, string $redirect_raw): string {
    // Decode the encoded redirect to get the actual URL.
    $decoded = esc_url_raw(urldecode($redirect_encoded));
    $validated = bitcore_validate_sso_redirect_host($decoded);

    // Re-encode to match the expected format.
    return urlencode(esc_url_raw($validated));
}
add_filter('wpdc_sso_client_redirect_url', 'bitcore_sso_filter_redirect_url', 10, 2);
