<?php
/**
 * BITCORE Search Module
 * Unified search across BITCORE systems
 */

class BITCORE_Modules_Search {
    private $core;

    public function __construct( $core ) {
        $this->core = $core;

        add_action( 'wp_ajax_bitcore_search', [ $this, 'ajax_search' ] );
        add_action( 'wp_ajax_nopriv_bitcore_search', [ $this, 'ajax_search' ] );
    }

    /**
     * AJAX search handler
     */
    public function ajax_search() {
        $query = sanitize_text_field( $_POST['query'] ?? '' );

        if ( empty( $query ) ) {
            wp_send_json_error( 'Query is required' );
        }

        // Search BITwiki
        $bitwiki_results = $this->core->connector_bitwiki->search( $query, 5 );

        // Search BIThub
        $bithub_results = []; // Would implement

        wp_send_json_success( [
            'bitwiki' => $bitwiki_results,
            'bithub' => $bithub_results,
        ] );
    }
}
