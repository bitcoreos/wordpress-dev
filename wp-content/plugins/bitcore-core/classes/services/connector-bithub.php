<?php
/**
 * BITCORE_Services_Connector_BIThub implementation
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
 * BITCORE BIThub Connector Service
 * Handles communication with hub.bitwiki.org (Discourse)
 *
 * @package BITCORE_Core
 * @since 1.0.0
 */

/**
 * Class BITCORE_Services_Connector_BIThub
 * BIThub connector extending abstract base
 */
class BITCORE_Services_Connector_BIThub extends BITCORE_Abstract_Connector {

    /**
     * Get API key from options
     */
    protected function get_api_key(): ?string {
        return get_option('bitcore_bithub_api_key') ?: null;
    }

    /**
     * Get API URL from options
     */
    protected function get_api_url(): string {
        return get_option('bitcore_bithub_api_url', 'https://hub.bitwiki.org');
    }

    /**
     * Get connector name
     */
    public function get_name(): string {
        return 'bithub';
    }

    /**
     * Get latest topics from BIThub
     */
    public function get_latest_topics(int $limit = 10): array {
        $response = $this->request('latest.json');
        
        if (is_wp_error($response)) {
            return [];
        }

        if (isset($response['topic_list']['topics'])) {
            return array_slice($response['topic_list']['topics'], 0, $limit);
        }

        return [];
    }

    /**
     * Get categories from BIThub
     */
    public function get_categories(): array {
        $response = $this->request('categories.json');
        
        if (is_wp_error($response)) {
            return [];
        }

        return $response['category_list']['categories'] ?? [];
    }

    /**
     * Get artifacts from Artifacts category
     */
    public function get_artifacts(): array {
        $response = $this->request('c/artifacts/6.json');
        
        if (is_wp_error($response)) {
            return [];
        }

        return $response['topic_list']['topics'] ?? [];
    }

    /**
     * Create topic on BIThub
     */
    public function create_topic(string $title, string $content, int $category_id = 1): array|\WP_Error {
        $response = $this->request('posts.json', 'POST', [
            'title' => $title,
            'raw' => $content,
            'category' => $category_id,
        ]);

        return $response;
    }

    /**
     * Override default headers for Discourse API
     */
    protected function get_default_headers(): array {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if ($this->api_key) {
            $headers['Api-Key'] = $this->api_key;
        }

        return $headers;
    }
}
