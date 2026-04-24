<?php
declare(strict_types=1);
/**
 * Hermes Agent REST API
 * Endpoints for Hermes AI Agent integration
 *
 * @package BITCORE
 */

class BITCORE_Rest_Hermes_Api {
    private $core;
    private $namespace = 'bitcore/v1';

    public function __construct( $core ) {
        $this->core = $core;
        // Call directly since we're already in rest_api_init context
        $this->register_routes();
    }

    /**
     * Register Hermes routes
     */
    public function register_routes(): void {
        // Chat with Hermes
        register_rest_route( $this->namespace, '/hermes/chat', [
            'methods' => 'POST',
            'callback' => [ $this, 'chat' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ]);

        // Submit async task
        register_rest_route( $this->namespace, '/hermes/task', [
            'methods' => 'POST',
            'callback' => [ $this, 'submit_task' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ]);

        // Get task status
        register_rest_route( $this->namespace, '/hermes/task/(?P<id>[a-zA-Z0-9-]+)', [
            'methods' => 'GET',
            'callback' => [ $this, 'get_task_status' ],
            'permission_callback' => '__return_true',
        ]);

        // Cancel task
        register_rest_route( $this->namespace, '/hermes/task/(?P<id>[a-zA-Z0-9-]+)/cancel', [
            'methods' => 'POST',
            'callback' => [ $this, 'cancel_task' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ]);

        // List skills
        register_rest_route( $this->namespace, '/hermes/skills', [
            'methods' => 'GET',
            'callback' => [ $this, 'get_skills' ],
            'permission_callback' => '__return_true',
        ]);

        // Execute skill
        register_rest_route( $this->namespace, '/hermes/skill/(?P<name>[a-zA-Z0-9-]+)/run', [
            'methods' => 'POST',
            'callback' => [ $this, 'run_skill' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ]);

        // Health check
        register_rest_route( $this->namespace, '/hermes/health', [
            'methods' => 'GET',
            'callback' => [ $this, 'health_check' ],
            'permission_callback' => '__return_true',
        ]);

        // Callback webhook for Hermes
        register_rest_route( $this->namespace, '/hermes/callback', [
            'methods' => 'POST',
            'callback' => [ $this, 'handle_callback' ],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Chat with Hermes
     */
    public function chat( $request ) {
        $params = $request->get_json_params();
        $message = sanitize_text_field( $params['message'] ?? '' );
        $session_id = sanitize_text_field( $params['session_id'] ?? '' );

        if ( empty( $message ) ) {
            return new WP_Error( 'no_message', 'Message is required', [ 'status' => 400 ] );
        }

        $toolsets = $params['toolsets'] ?? ['terminal', 'file'];
        $response = $this->core->connector_hermes->chat( $message, $session_id, $toolsets );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return rest_ensure_response( $response );
    }

    /**
     * Submit async task
     */
    public function submit_task( $request ) {
        $params = $request->get_json_params();
        $prompt = sanitize_textarea_field( $params['prompt'] ?? '' );
        $toolsets = $params['toolsets'] ?? ['terminal', 'file'];

        if ( empty( $prompt ) ) {
            return new WP_Error( 'no_prompt', 'Task prompt is required', [ 'status' => 400 ] );
        }

        // Create a task post and get the WP task ID
        $task_data = [
            'prompt' => $prompt,
            'context' => $params['context'] ?? [],
            'type' => sanitize_text_field( $params['type'] ?? 'general' ),
        ];

        // Attempt to find or create a WP task; fall back to 0 for non-task submissions
        $wp_task_id = 0;
        if ( class_exists( 'BITCORE_Modules_Tasks' ) && isset( $this->core->tasks ) ) {
            $result = $this->core->tasks->create_task( $task_data );
            if ( ! is_wp_error( $result ) && is_numeric( $result ) ) {
                $wp_task_id = (int) $result;
            }
        }

        $response = $this->core->connector_hermes->submit_task( $wp_task_id, $prompt, $toolsets );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return rest_ensure_response( $response );
    }

    /**
     * Get task status
     */
    public function get_task_status( $request ) {
        $task_id = sanitize_text_field( $request->get_param( 'id' ) );

        $response = $this->core->connector_hermes->get_task_status( $task_id );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return rest_ensure_response( $response );
    }

    /**
     * Cancel task
     */
    public function cancel_task( $request ) {
        $task_id = sanitize_text_field( $request->get_param( 'id' ) );

        $response = $this->core->connector_hermes->cancel_task( $task_id );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return rest_ensure_response( $response );
    }

    /**
     * Get available skills
     */
    public function get_skills( $request ) {
        $response = $this->core->connector_hermes->list_skills();

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return rest_ensure_response( $response );
    }

    /**
     * Execute a skill
     */
    public function run_skill( $request ) {
        $skill_name = sanitize_text_field( $request->get_param( 'name' ) );
        $params = $request->get_json_params();

        $response = $this->core->connector_hermes->run_skill( $skill_name, $params ?? [] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return rest_ensure_response( $response );
    }

    /**
     * Health check
     */
    public function health_check( $request ) {
        $connected = $this->core->connector_hermes->health_check();
        return rest_ensure_response( [
            'status' => $connected ? 'ok' : 'error',
            'bridge' => $connected ? 'connected' : 'disconnected',
            'timestamp' => current_time( 'iso' )
        ] );
    }

    /**
     * Handle callback from Hermes
     */
    public function handle_callback( $request ) {
        $params = $request->get_json_params();
        $task_id = sanitize_text_field( $params['task_id'] ?? '' );
        $status = sanitize_text_field( $params['status'] ?? '' );
        $result = $params['result'] ?? null;

        if ( empty( $task_id ) ) {
            return new WP_Error( 'no_task_id', 'Task ID is required', [ 'status' => 400 ] );
        }

        // Store callback result - connector doesn't have handle_callback,
        // so we update task post directly
        $updated = false;
        if ( class_exists( 'BITCORE_Modules_Tasks' ) && isset( $this->core->tasks ) ) {
            $updated = $this->core->tasks->update_task_from_callback( $task_id, $status, $result );
        }

        return rest_ensure_response( [
            'received' => true,
            'updated' => $updated,
        ] );
    }

    /**
     * Check permission
     */
    public function check_permission(): bool {
        return is_user_logged_in();
    }
}
