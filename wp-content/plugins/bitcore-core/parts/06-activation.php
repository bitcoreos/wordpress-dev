<?php
/**
 * Activation, migration, deactivation hooks and log helper
 *
 * Extracted verbatim from bitcore-core.php; require_once order is the original
 * execution order. Bootstrap remains the plugin entry point.
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
register_activation_hook(BITCORE_PLUGIN_FILE, 'bitcore_activate');

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
register_deactivation_hook(BITCORE_PLUGIN_FILE, 'bitcore_deactivate');

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
