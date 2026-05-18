<?php
declare(strict_types=1);
/**
 * Plugin Name: BITCORE Core
 * Plugin URI: https://bitcore.org
 * Description: Core plugin for BITCORE Intelligence Factory. Modular AI system with MCP, REST API, and agent orchestration.
 * Version: 1.2.0
 * Author: BITCORE
 * Author URI: https://bitcore.org
 * Text Domain: bitcore-core
 * Domain Path: /languages
 * License: GPLv2+
 *
 * @package BITCORE
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

// Define constants.
define('BITCORE_VERSION', '1.2.0');
define('BITCORE_DB_VERSION', '1.2.0');
define('BITCORE_PLUGIN_FILE', __FILE__);
define('BITCORE_ENTRY', __FILE__);
define('BITCORE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BITCORE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BITCORE_PATH', plugin_dir_path(__FILE__));
define('BITCORE_URL', plugin_dir_url(__FILE__));
define('BITCORE_PREFIX', 'bitcore');
define('BITCORE_DOMAIN', 'bitcore');
define('BITCORE_TIMEOUT', 300);
define('BITCORE_FALLBACK_MODEL', 'coraline-v1');
define('BITCORE_FALLBACK_MODEL_FAST', 'hyperion-v1');

// === FROM CORALINE: Class Autoloader ===
spl_autoload_register(function ($class) {
    $file = null;

    if (strpos($class, 'BITCORE_Modules') !== false) {
        $filename = str_replace('BITCORE_Modules_', '', $class);
        $filename = strtolower(str_replace('_', '-', $filename));
        $file = BITCORE_PATH . '/classes/modules/' . $filename . '.php';
    } elseif (strpos($class, 'BITCORE_Services') !== false) {
        $filename = str_replace('BITCORE_Services_', '', $class);
        $filename = strtolower(str_replace('_', '-', $filename));
        // Try without class- prefix first, then with prefix
        $file = BITCORE_PATH . '/classes/services/' . $filename . '.php';
        if (!file_exists($file)) {
            $file = BITCORE_PATH . '/classes/services/class-' . $filename . '.php';
        }
    } elseif (strpos($class, 'BITCORE_Engines') !== false) {
        $filename = str_replace('BITCORE_Engines_', '', $class);
        $filename = strtolower(str_replace('_', '-', $filename));
        $file = BITCORE_PATH . '/classes/engines/' . $filename . '.php';
    } elseif (strpos($class, 'BITCORE_Rest') !== false) {
        $filename = str_replace('BITCORE_Rest_', '', $class);
        $filename = strtolower(str_replace('_', '-', $filename));
        $file = BITCORE_PATH . '/rest/' . $filename . '.php';
        if (!file_exists($file)) {
            $file = BITCORE_PATH . '/rest/class-' . $filename . '.php';
        }
    } elseif (strpos($class, 'BITCORE_MCP') !== false) {
        $filename = str_replace('BITCORE_MCP_', '', $class);
        $filename = strtolower(str_replace('_', '-', $filename));
        $file = BITCORE_PATH . '/mcp/' . $filename . '.php';
    } elseif (strpos($class, 'BITCORE') !== false) {
        $filename = str_replace('BITCORE_', '', $class);
        $filename = strtolower(str_replace('_', '-', $filename));
        // Try class- prefixed path first (WordPress coding standard), then fallback
        $file = BITCORE_PATH . '/classes/class-' . $filename . '.php';
        if (!file_exists($file)) {
            $file = BITCORE_PATH . '/classes/' . $filename . '.php';
        }
    }

    if ($file && file_exists($file)) {
        require_once $file;
    }
});

// === FROM HYPERION: Pattern Autoloader ===
function bitcore_load_patterns(): void {
    $autoloader_file = BITCORE_PLUGIN_DIR . 'classes/patterns-autoloader.php';
    if (file_exists($autoloader_file)) {
        require_once $autoloader_file;
        \BITCORE\Patterns\PatternAutoloader::instance()->load();
    }
}
add_action('plugins_loaded', 'bitcore_load_patterns', 5);

// === FROM HYPERION: Initialize Infrastructure ===
    // PSR-4 Autoloader for Paperclip Integration Modules
    require_once BITCORE_PLUGIN_DIR . 'classes/psr4-autoloader.php';
function bitcore_init_infrastructure(): void {
    if (!isset($GLOBALS['bitcore'])) {
        require_once BITCORE_PLUGIN_DIR . 'classes/core.php';
        $GLOBALS['bitcore'] = new BITCORE_Core();
    }
}
add_action('plugins_loaded', 'bitcore_init_infrastructure', 15);

// === FROM HYPERION: WP-CLI Commands ===
function bitcore_cli_init(): void {
    if (defined('WP_CLI') && WP_CLI) {
        require_once BITCORE_PLUGIN_DIR . 'infrastructure/cli/commands.php';
        \WP_CLI::add_command('bitcore', \BITCORE\Infrastructure\CLI\BitcoreCommand::class);
    }
}
add_action('init', 'bitcore_cli_init');

// === FROM HYPERION: Discourse SSO Client Redirect Fix ===
// Validates redirect_to parameter during SSO flow to ensure
// return_sso_url is always a WordPress domain URL, not Discourse.
// Two-layer defense: init priority 3 (before WP-Discourse) + wpdc_sso_client_redirect_url filter.
require_once BITCORE_PLUGIN_DIR . 'infrastructure/auth/discourse-sso-fix.php';

// === FROM HYPERION: Admin Menu Registration ===
require_once BITCORE_PLUGIN_DIR . 'admin/menu-registration.php';

// === FROM HYPERION: Custom Cron Schedules ===
function bitcore_cron_schedules(array $schedules): array {
    $schedules['bitcore_queue_1min'] = [
        'interval' => 60,
        'display'  => __('BITCORE Queue: Every Minute'),
    ];
    $schedules['bitcore_queue_5min'] = [
        'interval' => 300,
        'display'  => __('BITCORE Queue: Every 5 Minutes'),
    ];
    $schedules['bitcore_webhooks_retry'] = [
        'interval' => 900,
        'display'  => __('BITCORE Webhooks: Retry Failed (15 min)'),
    ];
    $schedules['bitcore_health_check'] = [
        'interval' => 3600,
        'display'  => __('BITCORE System: Health Check (Hourly)'),
    ];
    return $schedules;
}
add_filter('cron_schedules', 'bitcore_cron_schedules', 10, 1);

// === FROM HYPERION: Schedule Cron Events ===
function bitcore_schedule_cron(): void {
    if (!wp_next_scheduled('bitcore_queue_worker')) {
        wp_schedule_event(time(), 'bitcore_queue_1min', 'bitcore_queue_worker');
    }
    if (!wp_next_scheduled('bitcore_webhooks_retry')) {
        wp_schedule_event(time(), 'bitcore_webhooks_retry', 'bitcore_webhooks_retry');
    }
    if (!wp_next_scheduled('bitcore_health_check')) {
        wp_schedule_event(time(), 'bitcore_health_check', 'bitcore_health_check');
    }
}
add_action('wp', 'bitcore_schedule_cron');

// === FROM HYPERION: Queue Worker Cron ===
// DISABLED: queue.worker depends on job.repository and logger — both missing.
// function bitcore_queue_worker_cron(): void {
//     if (isset($GLOBALS['bitcore']) && $GLOBALS['bitcore']->container) {
//         $worker = $GLOBALS['bitcore']->container->get('queue.worker');
//         if ($worker) {
//             $worker->process(10);
//         }
//     }
// }
// add_action('bitcore_queue_worker', 'bitcore_queue_worker_cron');

// === FROM HYPERION: Webhooks Retry Cron ===
function bitcore_webhooks_retry_cron(): void {
    if (isset($GLOBALS['bitcore']) && $GLOBALS['bitcore']->container) {
        try {
            $manager = $GLOBALS['bitcore']->container->get('webhooks.manager');
            if ($manager) {
                $manager->retryFailed(5);
            }
        } catch (Exception $e) {
            // Service not registered; silently skip
        }
    }
}
add_action('bitcore_webhooks_retry', 'bitcore_webhooks_retry_cron');

// === FROM HYPERION: Health Check Cron ===
// DISABLED: logger service references missing infrastructure/Logging/Logger.php
// function bitcore_health_check_cron(): void {
//     if (isset($GLOBALS['bitcore']) && $GLOBALS['bitcore']->container) {
//         $logger = $GLOBALS['bitcore']->container->get('logger');
//         if ($logger) {
//             $health = [
//                 'timestamp' => time(),
//                 'memory'    => memory_get_usage(true),
//                 'peak'      => memory_get_peak_usage(true),
//                 'queue'     => $GLOBALS['wpdb']->get_var("SELECT COUNT(*) FROM {$GLOBALS['wpdb']->prefix}bitcore_jobs WHERE status = 'pending'"),
//             ];
//             $logger->info('Health check completed', $health);
//         }
//     }
// }
// add_action('bitcore_health_check', 'bitcore_health_check_cron');

// === FROM CORALINE: Token Rotation Schedule ===
function bitcore_schedule_token_check(): void {
    if (!wp_next_scheduled('bitcore_token_rotation_check')) {
        wp_schedule_event(time(), 'daily', 'bitcore_token_rotation_check');
    }
}
add_action('wp', 'bitcore_schedule_token_check');

add_action('bitcore_token_rotation_check', function() {
    if (class_exists('BITCORE_Services_Token_Manager')) {
        BITCORE_Services_Token_Manager::ensure_valid_token();
    }
});

// === FROM HYPERION: Clear Cron on Deactivation ===
function bitcore_clear_cron(): void {
    wp_clear_scheduled_hook('bitcore_queue_worker');
    wp_clear_scheduled_hook('bitcore_webhooks_retry');
    wp_clear_scheduled_hook('bitcore_health_check');
    wp_clear_scheduled_hook('bitcore_token_rotation_check');
}
add_action('bitcore_clear_cron', 'bitcore_clear_cron');

// === FROM HYPERION: Register Widgets ===
function bitcore_register_widgets(): void {
    if (class_exists('\BITCORE\Patterns\PatternAutoloader')) {
        $autoloader = \BITCORE\Patterns\PatternAutoloader::instance();
        $autoloader->registerWidgets();
    }
}
add_action('widgets_init', 'bitcore_register_widgets');

// === FROM CORALINE: REST API Routes Registration ===
add_action('rest_api_init', ['BITCORE_Rest_Tasks', 'register_routes']);
add_action('rest_api_init', ['BITCORE_Rest_Health_Knowledge', 'register_routes']);
add_action('rest_api_init', ['BITCORE_Rest_Research_Intelligence', 'register_routes']);
add_action('rest_api_init', ['BITCORE_Rest_Sse_Events', 'register_routes']);

// === RESTORED (April 29): Route registrations from April 23 repair (verified class names) ===
add_action('rest_api_init', function() {
    if (class_exists('BITCORE_Rest_MCP_Tools_API')) {
        (new BITCORE_Rest_MCP_Tools_API())->register_routes();
    }
    if (class_exists('BITCORE_Coordination_API')) {
        (new BITCORE_Coordination_API())->register_routes();
    }
});
// === FROM CORALINE: Admin Scripts ===
add_action('admin_enqueue_scripts', ['BITCORE_Integration', 'enqueue_rest_nonce']);

// === FROM CORALINE: SSE Client Scripts ===
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_script(
        'bitcore-sse',
        BITCORE_URL . 'assets/js/bitcore-sse.js',
        [],
        BITCORE_VERSION,
        true
    );
    wp_localize_script('bitcore-sse', 'bitcoreSSE', [
        'endpoint' => rest_url('bitcore/v1/events/stream'),
        'nonce'    => wp_create_nonce('wp_rest'),
    ]);
});

add_action('admin_enqueue_scripts', function() {
    wp_enqueue_script(
        'bitcore-sse-admin',
        BITCORE_URL . 'assets/js/bitcore-sse.js',
        [],
        BITCORE_VERSION,
        true
    );
    wp_localize_script('bitcore-sse-admin', 'bitcoreSSE', [
        'endpoint' => rest_url('bitcore/v1/events/stream'),
        'nonce'    => wp_create_nonce('wp_rest'),
    ]);
});

// === MERGED: Activation Hook ===
function bitcore_activate(): void {
    // Load autoloader
    $autoloader_file = BITCORE_PLUGIN_DIR . 'classes/patterns-autoloader.php';
    if (file_exists($autoloader_file)) {
        require_once $autoloader_file;
    }

    // Initialize database schema (Hyperion)
    if (class_exists('\BITCORE\Patterns\PatternAutoloader')) {
        \BITCORE\Patterns\PatternAutoloader::activate();
    }

    // Run migrations (Coraline)
    bitcore_run_migrations();

    // Initialize token state (Coraline)
    if (!get_option('bitcore_token_state')) {
        update_option('bitcore_token_state', [
            'token'          => wp_generate_password(64, false),
            'created'        => time(),
            'previous_token' => null,
            'previous_expires' => null,
        ]);
    }
    // Initialize start time for dashboard uptime calculation
    if (!get_option('bitcore_start_time')) {
        update_option('bitcore_start_time', time());
    }

    // Schedule initial cron events
    bitcore_schedule_cron();

    // Flush rewrite rules
    flush_rewrite_rules();

    // Register custom capabilities (from origin/main fix dc0776b/c3c0f5c)
    require_once BITCORE_PLUGIN_DIR . 'classes/class-capabilities.php';
    \Bitcore\Tasks\Capabilities::register();
}
register_activation_hook(__FILE__, 'bitcore_activate');

// === FROM CORALINE: Run Database Migrations ===
function bitcore_run_migrations(): void {
    global $wpdb;
    
    $migration_file = BITCORE_PATH . '/migrations/agent-identities.sql';
    
    if (file_exists($migration_file)) {
        $sql = file_get_contents($migration_file);
        $sql = str_replace('{wpdb_prefix}', $wpdb->prefix, $sql);
        
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                $wpdb->query($statement);
            }
        }
    }
}

// === MERGED: Deactivation Hook ===
function bitcore_deactivate(): void {
    // Clear scheduled events
    bitcore_clear_cron();
    
    // Deactivate via core class (Coraline)
    if (class_exists('BITCORE_Core')) {
        BITCORE_Core::deactivate();
    }

    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'bitcore_deactivate');

// === FROM HYPERION: Helper Log Function ===
function bitcore_log(string $message, array $context = []): void {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log(sprintf(
            '[BITCORE] %s | Context: %s',
            $message,
            json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        ));
    }
}

// Paperclip Integration REST API Routes (Instance-based registration)
add_action('rest_api_init', function() {
    // Explicit requires for classes not matching BITCORE_Rest_ autoloader prefix
    require_once BITCORE_PATH . '/rest/class-checkout-api.php';
    require_once BITCORE_PATH . '/rest/class-org-chart-api.php';
    require_once BITCORE_PATH . '/rest/class-budget-api.php';
    require_once BITCORE_PATH . '/rest/class-goals-api.php';
    require_once BITCORE_PATH . '/rest/class-approvals-api.php';
    require_once BITCORE_PATH . '/rest/class-sessions-api.php';
    require_once BITCORE_PATH . '/rest/class-kanban-api.php';
    require_once BITCORE_PATH . '/rest/class-my-posts-api.php';
    $apis = [
        'BITCORE\\Rest\\Checkout_Api',
        'BITCORE\\Rest\\Org_Chart_Api',
        'BITCORE\\Rest\\Budget_Api',
        'BITCORE\\Rest\\Goals_Api',
        'BITCORE\\Rest\\Approvals_Api',
        'BITCORE\\Rest\\Sessions_Api',
        'BITCORE_Rest_Kanban_API',
        'BITCORE_Rest_My_Posts_API',
    ];
    foreach ($apis as $api_class) {
        if (class_exists($api_class)) {
            $instance = new $api_class();
            $instance->register_routes();
        }
    }
});

// === ASHI: Post Types and Taxonomies ===
function bitcore_register_post_types(): void {
    register_post_type('bitcore_prompt', [
        'labels'             => [
            'name'          => __('Prompts', 'bitcore-core'),
            'singular_name' => __('Prompt', 'bitcore-core'),
        ],
        'public'             => false,
        'publicly_queryable' => false,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'capability_type'    => 'post',
        'map_meta_cap'       => true,
        'supports'           => ['title', 'editor', 'author'],
        'menu_icon'          => 'dashicons-format-chat',
        'rewrite'            => false,
    ]);
    register_post_type('bitcore_artifact', [
        'labels'             => [
            'name'          => __('Artifacts', 'bitcore-core'),
            'singular_name' => __('Artifact', 'bitcore-core'),
        ],
        'public'             => false,
        'publicly_queryable' => false,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'capability_type'    => 'post',
        'map_meta_cap'       => true,
        'supports'           => ['title', 'editor', 'author', 'revisions'],
        'taxonomies'         => ['bitcore_channel', 'artifact_status'],
        'menu_icon'          => 'dashicons-archive',
        'rewrite'            => false,
    ]);
}
add_action('init', 'bitcore_register_post_types', 5);

function bitcore_register_taxonomies(): void {
    register_taxonomy('bitcore_channel', ['agent_task', 'bitcore_artifact', 'post'], [
        'labels'       => [
            'name'          => __('Channels', 'bitcore-core'),
            'singular_name' => __('Channel', 'bitcore-core'),
        ],
        'public'       => false,
        'hierarchical' => true,
        'show_ui'      => true,
    ]);
    register_taxonomy('task_type', ['bitcore_task'], [
        'labels'       => [
            'name'          => __('Task Types', 'bitcore-core'),
            'singular_name' => __('Task Type', 'bitcore-core'),
        ],
        'public'       => false,
        'hierarchical' => true,
        'show_ui'      => true,
    ]);
    register_taxonomy('artifact_status', ['bitcore_artifact'], [
        'labels'       => [
            'name'          => __('Artifact Statuses', 'bitcore-core'),
            'singular_name' => __('Artifact Status', 'bitcore-core'),
        ],
        'public'       => false,
        'hierarchical' => false,
        'show_ui'      => true,
    ]);
}
add_action('init', 'bitcore_register_taxonomies', 6);


// === ASHI: Post Router and Privacy ===
require_once BITCORE_PLUGIN_DIR . 'infrastructure/class-post-router.php';
require_once BITCORE_PLUGIN_DIR . 'infrastructure/class-privacy.php';
