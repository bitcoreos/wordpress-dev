<?php
/**
 * Plugin Name: BITCORE Core
 * Description: Core BITCORE plugin - unified foundation combining infrastructure, swarm, multitenancy, MCP protocol, SSE streaming, and intelligence services.
 * Version: 1.3.0
 * Author: BITCORE (coraline + hyperion)
 * License: GPLv2+
 * Text Domain: bitcore-core
 * Domain Path: /languages
 *
 * @package Bitcore
 * @since 2026.03.01
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// Define constants.
define('BITCORE_VERSION', '1.3.0');
define('BITCORE_DB_VERSION', '1.3.0');
define('BITCORE_PLUGIN_FILE', __FILE__);
define('BITCORE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BITCORE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BITCORE_PATH', plugin_dir_path(__FILE__));
define('BITCORE_URL', plugin_dir_url(__FILE__));
define('BITCORE_PREFIX', 'bitcore');
define('BITCORE_DOMAIN', 'bitcore');
define('BITCORE_ENTRY', __FILE__);
define('BITCORE_TIMEOUT', 300);
define('BITCORE_FALLBACK_MODEL', 'coraline-v1');
define('BITCORE_FALLBACK_MODEL_FAST', 'hyperion-v1');

/**
 * Load pattern autoloader.
 */
function bitcore_load_patterns(): void {
    $autoloader_file = BITCORE_PLUGIN_DIR . 'classes/patterns-autoloader.php';
    if (file_exists($autoloader_file)) {
        require_once $autoloader_file;
        \BITCORE\Patterns\PatternAutoloader::instance()->load();
    }
}
add_action('plugins_loaded', 'bitcore_load_patterns', 5);

/**
 * Initialize infrastructure services.
 */
function bitcore_init_infrastructure(): void {
    if (!isset($GLOBALS['bitcore'])) {
        require_once BITCORE_PLUGIN_DIR . 'classes/core.php';
        $GLOBALS['bitcore'] = new BITCORE_Core();
    }
}
add_action('plugins_loaded', 'bitcore_init_infrastructure', 15);

/**
 * Register WP-CLI commands.
 */
function bitcore_cli_init(): void {
    if (defined('WP_CLI') && WP_CLI) {
        require_once BITCORE_PLUGIN_DIR . 'infrastructure/cli/commands.php';
        \WP_CLI::add_command('bitcore', \BITCORE\Infrastructure\CLI\BitcoreCommand::class);
    }
}
add_action('init', 'bitcore_cli_init');

/**
 * Register custom cron schedules.
 */
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

/**
 * Schedule queue worker events.
 */
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
    if (!wp_next_scheduled('bitcore_token_rotation_check')) {
        wp_schedule_event(time(), 'daily', 'bitcore_token_rotation_check');
    }
}
add_action('wp', 'bitcore_schedule_cron');

/**
 * Run queue worker on cron.
 */
function bitcore_queue_worker_cron(): void {
    if (isset($GLOBALS['bitcore']) && $GLOBALS['bitcore']->container) {
        $worker = $GLOBALS['bitcore']->container->get('queue.worker');
        if ($worker) {
            $worker->process(10);
        }
    }
}
add_action('bitcore_queue_worker', 'bitcore_queue_worker_cron');

/**
 * Retry failed webhooks.
 */
function bitcore_webhooks_retry_cron(): void {
    if (isset($GLOBALS['bitcore']) && $GLOBALS['bitcore']->container) {
        $manager = $GLOBALS['bitcore']->container->get('webhooks.manager');
        if ($manager) {
            $manager->retryFailed(5);
        }
    }
}
add_action('bitcore_webhooks_retry', 'bitcore_webhooks_retry_cron');

/**
 * System health check.
 */
function bitcore_health_check_cron(): void {
    if (isset($GLOBALS['bitcore']) && $GLOBALS['bitcore']->container) {
        $logger = $GLOBALS['bitcore']->container->get('logger');
        if ($logger) {
            $health = [
                'timestamp' => time(),
                'memory'    => memory_get_usage(true),
                'peak'      => memory_get_peak_usage(true),
                'queue'     => $GLOBALS['wpdb']->get_var("SELECT COUNT(*) FROM {$GLOBALS['wpdb']->prefix}bitcore_jobs WHERE status = 'pending'"),
            ];
            $logger->info('Health check completed', $health);
        }
    }
}
add_action('bitcore_health_check', 'bitcore_health_check_cron');

/**
 * Token rotation check.
 */
function bitcore_token_rotation_check(): void {
    if (class_exists('BITCORE_Services_Token_Manager')) {
        BITCORE_Services_Token_Manager::ensure_valid_token();
    }
}
add_action('bitcore_token_rotation_check', 'bitcore_token_rotation_check');

/**
 * Clear scheduled events on plugin deactivation.
 */
function bitcore_clear_cron(): void {
    wp_clear_scheduled_hook('bitcore_queue_worker');
    wp_clear_scheduled_hook('bitcore_webhooks_retry');
    wp_clear_scheduled_hook('bitcore_health_check');
    wp_clear_scheduled_hook('bitcore_token_rotation_check');
}
add_action('bitcore_clear_cron', 'bitcore_clear_cron');

/**
 * Register widgets.
 */
function bitcore_register_widgets(): void {
    if (class_exists('\\BITCORE\\Patterns\\PatternAutoloader')) {
        $autoloader = \BITCORE\Patterns\PatternAutoloader::instance();
        $autoloader->registerWidgets();
    }
}
add_action('widgets_init', 'bitcore_register_widgets');

/**
 * Run database migrations.
 */
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
    
    // Initialize token state
    if (!get_option('bitcore_token_state')) {
        update_option('bitcore_token_state', [
            'token' => wp_generate_password(64, false),
            'created' => time(),
            'previous_token' => null,
            'previous_expires' => null,
        ]);
    }
}

/**
 * Plugin activation hook.
 */
function bitcore_activate(): void {
    $autoloader_file = BITCORE_PLUGIN_DIR . 'classes/patterns-autoloader.php';
    if (file_exists($autoloader_file)) {
        require_once $autoloader_file;
    }

    if (class_exists('\\BITCORE\\Patterns\\PatternAutoloader')) {
        \BITCORE\Patterns\PatternAutoloader::activate();
    }

    bitcore_run_migrations();
    bitcore_schedule_cron();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'bitcore_activate');

/**
 * Plugin deactivation hook.
 */
function bitcore_deactivate(): void {
    bitcore_clear_cron();
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'bitcore_deactivate');

// REST API Routes
add_action('rest_api_init', ['BITCORE_Rest_Tasks', 'register_routes']);
add_action('rest_api_init', ['BITCORE_Rest_Sse_Events', 'register_routes']);
add_action('rest_api_init', ['BITCORE_Rest_Health_Knowledge', 'register_routes']);
add_action('rest_api_init', ['BITCORE_Rest_Research_Intelligence', 'register_routes']);

// Admin Scripts
add_action('admin_enqueue_scripts', ['BITCORE_Integration', 'enqueue_rest_nonce']);

// Enqueue SSE Client Script (frontend)
add_action('wp_enqueue_scripts', function(): void {
    wp_enqueue_script(
        'bitcore-sse',
        BITCORE_URL . 'assets/js/bitcore-sse.js',
        [],
        BITCORE_VERSION,
        true
    );
    wp_localize_script('bitcore-sse', 'bitcoreSSE', [
        'endpoint' => rest_url('bitcore/v1/events/stream'),
        'nonce' => wp_create_nonce('wp_rest'),
    ]);
});

// Enqueue SSE Client Script (admin)
add_action('admin_enqueue_scripts', function(): void {
    wp_enqueue_script(
        'bitcore-sse-admin',
        BITCORE_URL . 'assets/js/bitcore-sse.js',
        [],
        BITCORE_VERSION,
        true
    );
    wp_localize_script('bitcore-sse-admin', 'bitcoreSSE', [
        'endpoint' => rest_url('bitcore/v1/events/stream'),
        'nonce' => wp_create_nonce('wp_rest'),
    ]);
});

/**
 * Helper function to log messages.
 */
function bitcore_log(string $message, array $context = []): void {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log(sprintf(
            '[BITCORE] %s | Context: %s',
            $message,
            json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        ));
    }
}
