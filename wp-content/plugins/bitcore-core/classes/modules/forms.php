<?php
/**
 * BITCORE Forms Module
 * AI-powered forms with intent detection
 */

class BITCORE_Modules_Forms {
    private $core;

    public function __construct( $core ) {
        $this->core = $core;

        add_action( 'wp_ajax_bitcore_form_submit', [ $this, 'ajax_submit' ] );
        add_action( 'wp_ajax_nopriv_bitcore_form_submit', [ $this, 'ajax_submit' ] );
    }

    /**
     * AJAX form submission
     */
    public function ajax_submit() {
        check_ajax_referer( 'bitcore_nonce', 'nonce' );

        $intent = sanitize_text_field( $_POST['intent'] ?? '' );
        $scope = sanitize_text_field( $_POST['scope'] ?? 'quick' );

        if ( empty( $intent ) ) {
            wp_send_json_error( 'Intent is required' );
        }

        // Send to n8n
        $response = $this->core->connector_n8n->send_intent( $intent, $scope, get_current_user_id() );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( $response->get_error_message() );
        }

        wp_send_json_success( [
            'message' => 'Research intent submitted successfully',
            'correlation_id' => $response['correlation_id'] ?? '',
            'queue_position' => $response['queue_position'] ?? 0,
        ] );
    }
}
