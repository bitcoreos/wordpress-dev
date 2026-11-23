<?php
declare(strict_types=1);
/**
 * BITCORE_Services_Connector_BIThub implementation
 *
 * @package    Bitcore
 * @since      2026.03.01
 * @version    1.3.0
 * @author     hyperion
 */


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

        if ( ! $this->is_success_response( $response ) ) {
            return [];
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['topic_list']['topics'] ) ) {
            return array_slice( $body['topic_list']['topics'], 0, $limit );
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

        if ( ! $this->is_success_response( $response ) ) {
            return [];
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        return $body['category_list']['categories'] ?? [];
    }

    /**
     * Get artifacts from Artifacts category
     */
    public function get_artifacts(): array {
        $response = $this->request('c/artifacts/6.json');
        
        if (is_wp_error($response)) {
            return [];
        }

        if ( ! $this->is_success_response( $response ) ) {
            return [];
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        return $body['topic_list']['topics'] ?? [];
    }

    /**
     * Get a single BIThub topic by Discourse topic ID
     */
    public function get_topic( $topic_id ) {
        $topic_id = is_numeric( $topic_id ) ? absint( $topic_id ) : 0;
        if ( $topic_id <= 0 ) {
            return [];
        }

        $url = $this->api_url . '/t/' . $topic_id . '.json';

        $args = [
            'headers' => $this->get_default_headers(),
            'timeout' => 30,
        ];

        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) ) {
            return [];
        }

        if ( ! $this->is_success_response( $response ) ) {
            return [];
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        return is_array( $body ) ? $body : [];
    }

    /**
     * Get a single artifact topic by ID, slug, or title match
     */

    /**
     * Search BIThub topics via Discourse search API.
     *
     * @param string $query Search term.
     * @param int    $limit Maximum number of results.
     * @return array|WP_Error
     */
    public function search( $query, $limit = 10 ) {
        $url = add_query_arg(
            [
                'q' => sanitize_text_field( $query ),
            ],
            $this->api_url . '/search.json'
        );

        $response = wp_remote_get(
            $url,
            [
                'headers' => $this->get_default_headers(),
                'timeout' => 30,
            ]
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        if ( ! $this->is_success_response( $response ) ) {
            return [];
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $topics = $body['topics'] ?? [];

        return array_slice( is_array( $topics ) ? $topics : [], 0, max( 1, (int) $limit ) );
    }

    public function get_artifact( $artifact_id ) {
        if ( is_numeric( $artifact_id ) ) {
            return $this->get_topic( $artifact_id );
        }

        $needle = sanitize_title( (string) $artifact_id );
        if ( $needle === '' ) {
            return [];
        }

        foreach ( $this->get_artifacts() as $artifact ) {
            $candidate_values = [
                isset( $artifact['slug'] ) ? sanitize_title( (string) $artifact['slug'] ) : '',
                isset( $artifact['title'] ) ? sanitize_title( (string) $artifact['title'] ) : '',
                isset( $artifact['fancy_title'] ) ? sanitize_title( (string) $artifact['fancy_title'] ) : '',
            ];

            if ( in_array( $needle, $candidate_values, true ) ) {
                return $artifact;
            }
        }

        return [];
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

        $args = [
            'method' => 'POST',
            'body' => [
                'title' => $title,
                'raw' => $content,
                'category' => $category_id,
            ],
            'headers' => $this->get_default_headers(),
            'timeout' => 30,
        ];

        $response = wp_remote_post( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        if ( ! $this->is_success_response( $response ) ) {
            $code = wp_remote_retrieve_response_code( $response );
            return new WP_Error( 'bithub_http_error', 'HTTP ' . $code . ': ' . wp_remote_retrieve_body( $response ) );
        }

        return json_decode( wp_remote_retrieve_body( $response ), true );
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

    /**
     * Check whether an HTTP response status is successful.
     */
    private function is_success_response( array $response ): bool {
        $code = wp_remote_retrieve_response_code( $response );
        return $code >= 200 && $code < 300;
    }
}
