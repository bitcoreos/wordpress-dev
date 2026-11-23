<?php
/**
 * BITCORE Core Class
 * Pattern: Service Container with Modular Architecture
 * 
 * Service-oriented architecture with dependency injection container,
 * encrypted options, capability-based model selection, security middleware,
 * and A2A swarm coordination.
 *
 * @package BITCORE
 * @since 1.0.0
 * @version     1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/traits/trait-core-container.php';
require_once __DIR__ . '/traits/trait-core-init.php';
require_once __DIR__ . '/traits/trait-core-security.php';
require_once __DIR__ . '/traits/trait-core-eliza.php';
require_once __DIR__ . '/traits/trait-core-admin.php';
require_once __DIR__ . '/traits/trait-core-options.php';

class BITCORE_Core {
    use BITCORE_Core_Container_Trait;
    use BITCORE_Core_Init_Trait;
    use BITCORE_Core_Security_Trait;
    use BITCORE_Core_Eliza_Trait;
    use BITCORE_Core_Admin_Trait;
    use BITCORE_Core_Options_Trait;
    public string $version = BITCORE_VERSION;
    public bool $is_rest = false;
    public bool $is_cli = false;

    /** @var BITCORE_Container Service container */
    public ?BITCORE_Container $container = null;

    // Coraline: Pattern-based services
    public $encrypted_options = null;
    public $model_selector = null;
    public $rest_auth_filter = null;

    // Hyperion: Infrastructure service instances
    public ?BITCORE_Services_Response_ID_Manager $response_id_manager = null;
    public ?BITCORE_Services_Message_Builder $message_builder = null;
    public ?BITCORE_Services_Session $session_service = null;
    public ?BITCORE_Services_Usage_Stats $usage_stats = null;
    public ?BITCORE_Services_Connector_N8N $connector_n8n = null;
    public ?BITCORE_Services_Connector_BIThub $connector_bithub = null;
    public ?BITCORE_Services_Connector_BITwiki $connector_bitwiki = null;

    // Module instances
    public ?BITCORE_Modules_Chatbot $chatbot = null;
    public ?BITCORE_Modules_Discussions $discussions = null;
    public ?BITCORE_MCP_Core $mcp = null;
    public ?BITCORE_Rest_API $rest = null;
    public ?BITCORE_Rest_Tasks $rest_tasks = null;
    public $eliza = null;
    public $eliza_settings = null;
    public ?BITCORE_Modules_Search $search = null;
    public ?BITCORE_Modules_Forms $forms = null;
    public ?BITCORE_Modules_Tasks $tasks = null;

    private ?array $options = null;

    public function __construct() {
        $this->is_rest = $this->is_rest_request();
        $this->is_cli = defined('WP_CLI') && WP_CLI;
        
        // Initialize container BEFORE plugins_loaded (Hyperion pattern)
        $this->init_container();

        add_action('rest_api_init', [$this, 'init_rest'], 5);
        add_action('plugins_loaded', [$this, 'init']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        // admin_menu handled by admin/menu-registration.php
    }

}
