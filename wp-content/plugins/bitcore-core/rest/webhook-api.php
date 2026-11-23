<?php
declare(strict_types=1);
/**
 * BITCORE Webhooks REST API
 * Extends bitcore-core with webhook triggers for external systems
 * 
 * New endpoints:
 * - POST /bitcore/v1/webhooks - Register webhook
 * - GET  /bitcore/v1/webhooks - List webhooks
 * - DELETE /bitcore/v1/webhooks/:id - Delete webhook
 * - POST /bitcore/v1/webhooks/:id/trigger - Manual trigger
 * 
 * Triggers on WordPress events:
 * - post_publish, post_update, post_delete
 * - user_register, user_login
 * - comment_post
 * @package     Bitcore
 * @since       1.0.0
 * @version     1.0.0
 */

class BITCORE_Rest_Webhooks_API {
    private $namespace = 'bitcore/v1';
    private $option_name = 'bitcore_webhooks';

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
        add_action( 'transition_post_status', array( $this, 'trigger_post_webhooks' ), 10, 3 );
        add_action( 'user_register', array( $this, 'trigger_user_webhooks' ), 10, 1 );
    }

    /**
     * Register REST routes for webhooks
     */
    public function register_routes() {
        // List webhooks
        register_rest_route( $this->namespace, '/webhooks', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_webhooks' ),
                'permission_callback' => '__return_true',
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'create_webhook' ),
                'permission_callback' => array( $this, 'check_admin_permission' ),
            ),
        ) );

        // Delete webhook
        register_rest_route( $this->namespace, '/webhooks/(?P<id>[a-z0-9_-]+)', array(
            'methods'             => 'DELETE',
            'callback'            => array( $this, 'delete_webhook' ),
            'permission_callback' => array( $this, 'check_admin_permission' ),
        ) );

        // Manual trigger
        register_rest_route( $this->namespace, '/webhooks/(?P<id>[a-z0-9_-]+)/trigger', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'trigger_webhook' ),
            'permission_callback' => array( $this, 'check_admin_permission' ),
        ) );
    }

    /**
     * Get all webhooks
     */
    public function get_webhooks() {
        $webhooks = get_option( $this->option_name, array() );
        return rest_ensure_response( $webhooks );
    }

    /**
     * Create webhook
     */
    public function create_webhook( $request ) {
        $input = $request->get_json_params();
        
        $webhook = array(
            'id'        => uniqid( 'wh_' ),
            'url'       => isset( $input['url'] ) ? esc_url_raw( $input['url'] ) : '',
            'events'    => isset( $input['events'] ) ? $input['events'] : array(),
            'active'    => true,
            'created'   => current_time( 'mysql' ),
        );

        if ( empty( $webhook['url'] ) || empty( $webhook['events'] ) ) {
            return new WP_Error(
                'invalid_webhook',
                __( 'Webhook requires URL and events.', 'bitcore' ),
                array( 'status' => 400 )
            );
        }

        $webhooks = get_option( $this->option_name, array() );
        $webhooks[] = $webhook;
        update_option( $this->option_name, $webhooks );

        return rest_ensure_response( $webhook );
    }

    /**
     * Delete webhook
     */
    public function delete_webhook( $request ) {
        $id = $request->get_param( 'id' );
        $webhooks = get_option( $this->option_name, array() );
        
        $found = false;
        foreach ( $webhooks as $key => $webhook ) {
            if ( $webhook['id'] === $id ) {
                unset( $webhooks[ $key ] );
                $found = true;
                break;
            }
        }

        if ( ! $found ) {
            return new WP_Error(
                'webhook_not_found',
                __( 'Webhook not found.', 'bitcore' ),
                array( 'status' => 404 )
            );
        }

        update_option( $this->option_name, array_values( $webhooks ) );
        return rest_ensure_response( array( 'deleted' => true ) );
    }

    /**
     * Manual trigger
     */
    public function trigger_webhook( $request ) {
        $id = $request->get_param( 'id' );
        $webhooks = get_option( $this->option_name, array() );
        
        $webhook = null;
        foreach ( $webhooks as $wh ) {
            if ( $wh['id'] === $id ) {
                $webhook = $wh;
                break;
            }
        }

        if ( ! $webhook ) {
            return new WP_Error(
                'webhook_not_found',
                __( 'Webhook not found.', 'bitcore' ),
                array( 'status' => 404 )
            );
        }

        $result = $this->send_webhook( $webhook, array(
            'event'     => 'manual_trigger',
            'timestamp' => current_time( 'mysql' ),
            'test'      => true,
        ) );

        return rest_ensure_response( array(
            'sent'    => true,
            'response' => $result,
        ) );
    }

    /**
     * Trigger post webhooks
     */
    public function trigger_post_webhooks( $new_status, $old_status, $post ) {
        if ( $new_status === 'publish' && $old_status !== 'publish' ) {
            $event = 'post_publish';
        } elseif ( $new_status === 'publish' && $old_status === 'publish' ) {
            $event = 'post_update';
        } elseif ( $old_status === 'publish' && $new_status !== 'publish' ) {
            $event = 'post_unpublish';
        } else {
            return;
        }

        $payload = array(
            'event'   => $event,
            'post_id' => $post->ID,
            'title'   => $post->post_title,
            'status'  => $new_status,
            'url'     => get_permalink( $post->ID ),
            'date'    => current_time( 'mysql' ),
        );

        $this->trigger_event( $event, $payload );
    }

    /**
     * Trigger user webhooks
     */
    public function trigger_user_webhooks( $user_id ) {
        $user = get_userdata( $user_id );
        $payload = array(
            'event'    => 'user_register',
            'user_id'  => $user_id,
            'username' => $user->user_login,
            'email'    => $user->user_email,
            'date'     => current_time( 'mysql' ),
        );

        $this->trigger_event( 'user_register', $payload );
    }

    /**
     * Trigger event on matching webhooks
     */
    private function trigger_event( $event, $payload ) {
        $webhooks = get_option( $this->option_name, array() );
        
        foreach ( $webhooks as $webhook ) {
            if ( ! $webhook['active'] ) {
                continue;
            }
            if ( ! in_array( $event, $webhook['events'] ) && ! in_array( '*', $webhook['events'] ) ) {
                continue;
            }
            
            $this->send_webhook( $webhook, $payload );
        }
    }

    /**
     * Send webhook payload
     */
    private function send_webhook( $webhook, $payload ) {
        $response = wp_remote_post( $webhook['url'], array(
            'body'    => wp_json_encode( $payload ),
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-Bitcore-Signature' => $this->generate_signature( $payload ),
            ),
            'timeout' => 5,
        ) );

        return is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_response_code( $response );
    }

    /**
     * Generate webhook signature
     */
    private function generate_signature( $payload ) {
        $secret = get_option( 'bitcore_webhook_secret', wp_generate_password( 32, false ) );
        return hash_hmac( 'sha256', wp_json_encode( $payload ), $secret );
    }

    /**
     * Check admin permission
     */
    public function check_admin_permission() {
        return current_user_can( 'manage_options' );
    }
}

// Initialize
new BITCORE_Rest_Webhooks_API();
