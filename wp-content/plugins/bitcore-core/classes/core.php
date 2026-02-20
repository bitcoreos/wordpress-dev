<?php
/**
 * BITCORE Core Class
 * Service-oriented architecture with modular modules
 */

class BITCORE_Core {
    public $version = BITCORE_VERSION;
    public $is_rest = false;
    public $is_cli = false;

    // Service instances
    public $response_id_manager = null;
    public $message_builder = null;
    public $session_service = null;
    public $usage_stats = null;
    public $connector_n8n = null;
    public $connector_bithub = null;
    public $connector_bitwiki = null;

    // Module instances
    public $chatbot = null;
    public $discussions = null;
    public $mcp = null;
    public $rest = null;
    public $search = null;
    public $forms = null;

    private $options = null;

    public function __construct() {
        $this->is_rest = $this->is_rest_request();
        $this->is_cli = defined( 'WP_CLI' );

        add_action( 'plugins_loaded', [ $this, 'init' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
        add_action( 'admin_menu', [ $this, 'admin_menu' ] );
    }

    public function init() {
        // Initialize services
        $this->init_services();

        // Initialize modules based on options
        $this->init_modules();

        // REST API
        if ( $this->is_rest ) {
            $this->rest = new BITCORE_Rest_API( $this );
        }

        // Admin
        if ( is_admin() ) {
            $this->init_admin();
        }

        // Shortcodes
        add_shortcode( 'bitcore_chat', [ $this, 'chatbot_shortcode' ] );
        add_shortcode( 'bitcore_agent', [ $this, 'agent_shortcode' ] );
        add_shortcode( 'bitcore_artifacts', [ $this, 'artifacts_shortcode' ] );
        add_shortcode( 'bitcore_feed', [ $this, 'feed_shortcode' ] );
    }

    private function init_services() {
        $this->response_id_manager = new BITCORE_Services_Response_ID_Manager( $this );
        $this->message_builder = new BITCORE_Services_Message_Builder( $this );
        $this->session_service = new BITCORE_Services_Session( $this );
        $this->usage_stats = new BITCORE_Services_Usage_Stats( $this );
        $this->connector_n8n = new BITCORE_Services_Connector_N8N( $this );
        $this->connector_bithub = new BITCORE_Services_Connector_BIThub( $this );
        $this->connector_bitwiki = new BITCORE_Services_Connector_BITwiki( $this );
    }

    private function init_modules() {
        // Chatbot module
        if ( $this->get_option( 'module_chatbot', true ) ) {
            $this->chatbot = new BITCORE_Modules_Chatbot( $this );
        }

        // Discussions module
        if ( $this->get_option( 'module_discussions', true ) ) {
            $this->discussions = new BITCORE_Modules_Discussions( $this );
        }

        // MCP module
        if ( $this->get_option( 'module_mcp', true ) ) {
            $this->mcp = new BITCORE_MCP_Core( $this );
        }

        // Search module
        if ( $this->get_option( 'module_search', true ) ) {
            $this->search = new BITCORE_Modules_Search( $this );
        }

        // Forms module
        if ( $this->get_option( 'module_forms', true ) ) {
            $this->forms = new BITCORE_Modules_Forms( $this );
        }
    }

    private function init_admin() {
        add_action( 'admin_init', [ $this, 'admin_init' ] );
    }

    public function admin_init() {
        // Admin initialization actions
    }

    public function admin_menu() {
        add_menu_page(
            'BITCORE',
            'BITCORE',
            'manage_options',
            'bitcore',
            [ $this, 'admin_page' ],
            'dashicons-art',
            30
        );

        add_submenu_page(
            'bitcore',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'bitcore',
            [ $this, 'admin_page' ]
        );

        add_submenu_page(
            'bitcore',
            'Agents',
            'Agents',
            'manage_options',
            'bitcore-agents',
            [ $this, 'agents_page' ]
        );

        add_submenu_page(
            'bitcore',
            'MCP Tools',
            'MCP Tools',
            'manage_options',
            'bitcore-mcp',
            [ $this, 'mcp_page' ]
        );

        add_submenu_page(
            'bitcore',
            'Settings',
            'Settings',
            'manage_options',
            'bitcore-settings',
            [ $this, 'settings_page' ]
        );
    }

    public function admin_page() {
        include BITCORE_PATH . '/admin/dashboard.php';
    }

    public function agents_page() {
        include BITCORE_PATH . '/admin/agents.php';
    }

    public function mcp_page() {
        include BITCORE_PATH . '/admin/mcp.php';
    }

    public function settings_page() {
        include BITCORE_PATH . '/admin/settings.php';
    }

    public function enqueue_scripts() {
        wp_enqueue_style( 'bitcore-base', BITCORE_URL . 'assets/css/bitcore-base.css', [], BITCORE_VERSION );
        wp_enqueue_script( 'bitcore-chat', BITCORE_URL . 'assets/js/bitcore-chat.js', [ 'jquery' ], BITCORE_VERSION, true );

        wp_localize_script( 'bitcore-chat', 'bitcoreConfig', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'restUrl' => rest_url( 'bitcore/v1/' ),
            'nonce' => wp_create_nonce( 'bitcore_nonce' ),
            'userId' => get_current_user_id(),
            'userName' => wp_get_current_user()->display_name,
        ] );
    }

    public function enqueue_admin_scripts( $hook ) {
        if ( strpos( $hook, 'bitcore' ) !== false ) {
            wp_enqueue_style( 'bitcore-admin', BITCORE_URL . 'assets/css/bitcore-admin.css', [], BITCORE_VERSION );
            wp_enqueue_script( 'bitcore-admin', BITCORE_URL . 'assets/js/bitcore-admin.js', [ 'jquery' ], BITCORE_VERSION, true );
        }
    }

    // Shortcode handlers
    public function chatbot_shortcode( $atts ) {
        $atts = shortcode_atts( [
            'agent' => 'coraline',
            'theme' => 'dark',
        ], $atts );

        ob_start();
        include BITCORE_PATH . '/templates/chatbot.php';
        return ob_get_clean();
    }

    public function agent_shortcode( $atts ) {
        $atts = shortcode_atts( [
            'name' => 'coraline',
            'show_avatar' => true,
        ], $atts );

        return '<div class="bitcore-agent" data-agent="' . esc_attr( $atts['name'] ) . '"></div>';
    }

    public function artifacts_shortcode( $atts ) {
        return '<div class="bitcore-artifacts"></div>';
    }

    public function feed_shortcode( $atts ) {
        return '<div class="bitcore-feed"></div>';
    }

    // Utility functions
    public function get_option( $key, $default = false ) {
        $options = get_option( 'bitcore_options', [] );
        return isset( $options[ $key ] ) ? $options[ $key ] : $default;
    }

    public function update_option( $key, $value ) {
        $options = get_option( 'bitcore_options', [] );
        $options[ $key ] = $value;
        update_option( 'bitcore_options', $options );
    }

    private function is_rest_request() {
        return defined( 'REST_REQUEST' ) && REST_REQUEST;
    }
}
