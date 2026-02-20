<?php
/**
 * BITCORE Chatbot Module
 * Advanced chatbot with BitFlux avatar and agent integration
 */

class BITCORE_Modules_Chatbot {
    private $core;

    public function __construct( $core ) {
        $this->core = $core;

        add_action( 'wp_ajax_bitcore_chat', [ $this, 'ajax_chat' ] );
        add_action( 'wp_ajax_nopriv_bitcore_chat', [ $this, 'ajax_chat' ] );
    }

    /**
     * AJAX chat handler
     */
    public function ajax_chat() {
        check_ajax_referer( 'bitcore_nonce', 'nonce' );

        $message = sanitize_text_field( $_POST['message'] ?? '' );
        $agent = sanitize_text_field( $_POST['agent'] ?? 'coraline' );
        $conversation_id = sanitize_text_field( $_POST['conversation_id'] ?? '' );

        if ( empty( $message ) ) {
            wp_send_json_error( 'Message is required' );
        }

        // Build context
        $context = [
            'conversation_id' => $conversation_id,
            'user_id' => get_current_user_id(),
            'timestamp' => time(),
        ];

        // Send to n8n
        $response = $this->core->connector_n8n->send_intent( $message, 'chat', get_current_user_id() );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( $response->get_error_message() );
        }

        wp_send_json_success( [
            'message' => $response['response'] ?? 'Processing...',
            'agent' => $agent,
            'conversation_id' => $conversation_id,
            'correlation_id' => $response['correlation_id'] ?? '',
        ] );
    }

    /**
     * Get chatbot HTML
     */
    public function get_chatbot_html( $agent = 'coraline', $theme = 'dark' ) {
        $conversation_id = $this->core->session_service->get_conversation_id();

        ob_start();
        ?>
        <div class="bitcore-chatbot" data-agent="<?php echo esc_attr( $agent ); ?>" data-theme="<?php echo esc_attr( $theme ); ?>">
            <div class="chatbot-header">
                <div class="chatbot-avatar bitflux-avatar" data-state="welcome"></div>
                <div class="chatbot-info">
                    <h3><?php echo esc_html( ucfirst( $agent ) ); ?></h3>
                    <span class="chatbot-status">Online</span>
                </div>
            </div>
            <div class="chatbot-messages" id="chat-messages"></div>
            <div class="chatbot-input">
                <input type="text" id="chat-input" placeholder="Type your message..." />
                <button id="chat-send">Send</button>
            </div>
            <input type="hidden" id="chat-conversation-id" value="<?php echo esc_attr( $conversation_id ); ?>" />
        </div>
        <?php
        return ob_get_clean();
    }
}
