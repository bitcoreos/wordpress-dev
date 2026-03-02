<?php
/**
 * BITCORE_Services_Connector_BITwiki implementation
 *
 * @package    Bitcore
 * @since      2026.03.01
 * @version    1.3.0
 * @author     hyperion
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * BITCORE BITwiki Connector Service
 * Handles communication with bitwiki.org (MediaWiki)
 *
 * @package BITCORE_Core
 * @since 1.0.0
 */

/**
 * Class BITCORE_Services_Connector_BITwiki
 * BITwiki connector extending abstract base
 */
class BITCORE_Services_Connector_BITwiki extends BITCORE_Abstract_Connector {

    /**
     * BITwiki uses action API, not REST API key
     */
    protected function get_api_key(): ?string {
        return null;
    }

    /**
     * Get MediaWiki API URL
     */
    protected function get_api_url(): string {
        return get_option('bitcore_bitwiki_api_url', 'https://bitwiki.org/api.php');
    }

    /**
     * Get connector name
     */
    public function get_name(): string {
        return 'bitwiki';
    }

    /**
     * Search BITwiki
     */
    public function search(string $query, int $limit = 10): array {
        $url = add_query_arg([
            'action' => 'query',
            'list' => 'search',
            'srsearch' => $query,
            'srlimit' => $limit,
            'format' => 'json',
        ], $this->api_url);

        $response = wp_remote_get($url, ['timeout' => $this->timeout]);

        if (is_wp_error($response)) {
            $this->last_error = $response->get_error_message();
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        return $data['query']['search'] ?? [];
    }

    /**
     * Get page content
     */
    public function get_page(string $title): ?array {
        $url = add_query_arg([
            'action' => 'parse',
            'page' => $title,
            'prop' => 'text',
            'format' => 'json',
        ], $this->api_url);

        $response = wp_remote_get($url, ['timeout' => $this->timeout]);

        if (is_wp_error($response)) {
            $this->last_error = $response->get_error_message();
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        return $data['parse'] ?? null;
    }

    /**
     * Get recent changes
     */
    public function get_recent_changes(int $limit = 10): array {
        $url = add_query_arg([
            'action' => 'query',
            'list' => 'recentchanges',
            'rclimit' => $limit,
            'format' => 'json',
        ], $this->api_url);

        $response = wp_remote_get($url, ['timeout' => $this->timeout]);

        if (is_wp_error($response)) {
            $this->last_error = $response->get_error_message();
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        return $data['query']['recentchanges'] ?? [];
    }

    /**
     * Override default headers - BITwiki doesn't use Bearer auth
     */
    protected function get_default_headers(): array {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }
}
