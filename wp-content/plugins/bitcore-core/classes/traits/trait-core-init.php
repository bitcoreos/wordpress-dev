<?php
/**
 * Core boot, REST and module initialisation
 *
 * Extracted verbatim from wp-content/plugins/bitcore-core/classes/core.php (BITCORE 500-LOC refactor); logic unchanged.
 *
 * @package    Bitcore
 * @since      2026.03.01
 * @version    1.3.0
 * @author     coraline
 */

declare(strict_types=1);


if (!defined('ABSPATH')) {
    exit;
}


trait BITCORE_Core_Init_Trait {

    /**
     * Early REST API initialization (runs before plugins_loaded)
     */
    public function init_rest(): void {

        // Sessions API
        require_once BITCORE_PATH . 'rest/class-sessions-api.php';
        new \BITCORE\Rest\Sessions_Api();

        // ElizaOS Bridge — constructor calls register_routes()
        require_once BITCORE_PATH . 'rest/class-eliza-api.php';  // loaded once
        new BITCORE_Rest_Eliza_Api($this);

        // Knowledge API — constructor calls register_routes()
        require_once BITCORE_PATH . 'rest/class-knowledge-api.php';
        new \BITCORE\Rest\Knowledge_Api();

        // Abstract connector for services
        require_once BITCORE_PATH . 'classes/services/abstract-connector.php';
    }

    

    public function init(): void {
        // Initialize Coraline pattern-based services
        $this->encrypted_options = $this->container->get('encrypted_options');
        $this->model_selector = $this->container->get('model_selector');

        // Initialize core services
        $this->init_services();

        // Initialize modules based on options
        $this->init_modules();

        // REST API with auth filter (Coraline) and security middleware (Hyperion)
        if ($this->is_rest) {
            $this->rest_auth_filter = new BITCORE_Rest_Auth_Filter();
            $this->rest = new BITCORE_Rest_API($this);
            $this->rest_tasks = new BITCORE_Rest_Tasks($this);
        }

        // Initialize Wave 4 security middleware
        $this->init_wave4();

        // ElizaOS provisioning hooks (global — registration/login can happen outside admin)
        add_action('user_register', [$this, 'eliza_user_registered']);
        add_action('wp_login', [$this, 'eliza_user_login'], 10, 2);

        // Subscription provisioning hook. Payment integrations must verify purchase first.
        add_action('bitcore_subscription_purchase', [$this, 'provision_subscription_purchase'], 10, 3);

        // Admin
        if (is_admin()) {
            $this->init_admin();
        }

        // Shortcodes
        add_shortcode('bitcore_chat', [$this, 'chatbot_shortcode']);
        add_shortcode('bitcore_agent', [$this, 'agent_shortcode']);
        add_shortcode('bitcore_artifacts', [$this, 'artifacts_shortcode']);
        add_shortcode('bitcore_feed', [$this, 'feed_shortcode']);
    }

    private function init_services(): void {
        $this->response_id_manager = $this->container->get('response_id_manager');
        $this->message_builder = $this->container->get('message_builder');
        $this->session_service = $this->container->get('session_service');
        $this->usage_stats = $this->container->get('usage_stats');
        $this->connector_n8n = $this->container->get('connector_n8n');
        $this->connector_bithub = $this->container->get('connector_bithub');
        $this->connector_bitwiki = $this->container->get('connector_bitwiki');
        // x402 Payment Verification — instantiate to register filter hooks
        $this->container->get('x402.verifier');
        $this->container->get('x402.facilitator');
    }

    /**
     * Initialize Wave 4 security and swarm modules
     */
    public function init_wave4(): void {
        // Security middleware pipeline
        add_action('rest_api_init', [$this, 'register_security_middleware']);

        // Health check endpoint
        add_action('rest_api_init', [$this, 'register_health_endpoint']);
    }


}
