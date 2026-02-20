<?php
/**
 * BITCORE BIThub Connector Service
 * Handles communication with hub.bitwiki.org (Discourse)
 */

class BITCORE_Services_Connector_BIThub {
    private $core;
    private $api_url;
    private $api_key;

    public function __construct( $core ) {
        $this->core = $core;
        $this->api_url = get_option( 'bitcore_bithub_api_url', 'https://hub.bitwiki.org' );
        $this->api_key = get_option( 'bitcore_bithub_api_key', '' );
    }

    /**
     * Get latest topics from BIThub
     */
    public function get_latest_topics( $limit = 10 ) {
        $url = $this->api_url . '/latest.json';

        $args = [
            'headers' => $this->get_headers(),
            'timeout' => 30,
        ];

        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) ) {
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
    public function get_categories() {
        $url = $this->api_url . '/categories.json';

        $args = [
            'headers' => $this->get_headers(),
            'timeout' => 30,
        ];

        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) ) {
            return [];
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        return $body['category_list']['categories'] ?? [];
    }

    /**
     * Get artifacts from Artifacts category
     */
    public function get_artifacts() {
        $url = $this->api_url . '/c/artifacts/6.json';

        $args = [
            'headers' => $this->get_headers(),
            'timeout' => 30,
        ];

        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) ) {
            return [];
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        return $body['topic_list']['topics'] ?? [];
    }

    /**
     * Create topic on BIThub
     */
    public function create_topic( $title, $content, $category_id = 1 ) {
        $url = $this->api_url . '/posts.json';

        $args = [
            'method' => 'POST',
            'body' => [
                'title' => $title,
                'raw' => $content,
                'category' => $category_id,
            ],
            'headers' => $this->get_headers(),
            'timeout' => 30,
        ];

        $response = wp_remote_post( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return json_decode( wp_remote_retrieve_body( $response ), true );
    }

    /**
     * Get headers for API requests
     */
    private function get_headers() {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if ( ! empty( $this->api_key ) ) {
            $headers['Api-Key'] = $this->api_key;
        }

        return $headers;
    }
}
