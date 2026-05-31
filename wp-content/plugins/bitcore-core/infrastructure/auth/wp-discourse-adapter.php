<?php
/**
 * BITCORE adapter for the official WP Discourse plugin.
 *
 * This file does not implement DiscourseConnect signing. The official
 * WP Discourse plugin remains the connector and SSO authority. BITCORE only
 * generates safe handoff URLs and exposes sanitized integration status.
 *
 * @package BITCORE\Infrastructure\Auth
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Normalize an ASHI post-login return URL to the local WordPress host.
 *
 * @param string $return_url Requested return URL.
 * @return string Safe local return URL.
 */
function bitcore_normalize_login_return_url(string $return_url = ''): string {
    $fallback = home_url('/dashboard/');
    $return_url = trim($return_url);

    if ($return_url === '') {
        return $fallback;
    }

    if (str_starts_with($return_url, '/')) {
        return esc_url_raw(home_url($return_url));
    }

    $home_host = wp_parse_url(home_url('/'), PHP_URL_HOST);
    $return_host = wp_parse_url($return_url, PHP_URL_HOST);

    if ($home_host && $return_host && strtolower((string) $home_host) === strtolower((string) $return_host)) {
        return esc_url_raw($return_url);
    }

    return $fallback;
}

/**
 * Return the canonical ASHI login URL through WP Discourse client flow.
 *
 * The URL intentionally uses the official plugin query entrypoint and does not
 * include DiscourseConnect secrets, signatures, or custom SSO payloads.
 *
 * @param string $return_url Requested return URL after login.
 * @return string Login handoff URL.
 */
function bitcore_get_login_url(string $return_url = ''): string {
    $safe_return_url = bitcore_normalize_login_return_url($return_url);
    return home_url('/?discourse_sso=1&redirect_to=' . rawurlencode($safe_return_url));
}

/**
 * Return sanitized WP Discourse integration status for diagnostics.
 *
 * @return array<string,mixed>
 */
function bitcore_wp_discourse_status(): array {
    $client_options = get_option('discourse_sso_client', []);
    $client_options = is_array($client_options) ? $client_options : [];

    $client_enabled = false;
    foreach (['sso-client-enabled', 'enable-sso-client', 'enabled'] as $key) {
        if (!empty($client_options[$key])) {
            $client_enabled = true;
            break;
        }
    }

    $login_redirect = '';
    if (!empty($client_options['sso-client-login-form-redirect']) && is_scalar($client_options['sso-client-login-form-redirect'])) {
        $login_redirect = esc_url_raw((string) $client_options['sso-client-login-form-redirect']);
    }

    return [
        'adapter' => 'bitcore-wp-discourse',
        'official_plugin_detected' => defined('WP_DISCOURSE_VERSION') || class_exists('WPDiscourse\Discourse\Discourse') || function_exists('discourse_publish_format_html'),
        'client_configured' => $client_enabled || $login_redirect !== '',
        'login_entrypoint' => home_url('/?discourse_sso=1'),
        'login_redirect_configured' => $login_redirect !== '',
        'secrets_redacted' => true,
    ];
}
