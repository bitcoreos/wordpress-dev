<?php
declare(strict_types=1);
/**
 * Feeds Module Loader
 *
 * @package BITCORE\Feeds
 * @since 1.0.0
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/trait-feeds-endpoints.php';
require_once __DIR__ . '/trait-feeds-admin.php';
use BITCORE\Feeds\FeedsEndpoints;
use BITCORE\Feeds\FeedsAdmin;


class Feeds_Loader
{
    use FeedsEndpoints;
    use FeedsAdmin;
    
    private static ?Feeds_Loader $instance = null;
    
    public static function init(): void
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        self::load_dependencies();
        self::register_rest_routes();
        self::register_admin_menu();
        self::schedule_cron_jobs();
        
        add_action('admin_enqueue_scripts', [__CLASS__, 'register_admin_assets']);
        add_action('wp_loaded', [__CLASS__, 'create_tables']);
    }
    
    private static function load_dependencies(): void
    {
        $files = [
            'class-text-extractor.php',
            'class-chunker.php',
            'class-summarizer.php',
            'class-deduplicator.php',
            'class-topic-clusterer.php',
            'class-trend-detector.php',
            'class-watchlist.php',
            'class-rag-workspace.php',
        ];
        
        foreach ($files as $file) {
            $path = __DIR__ . '/' . $file;
            if (file_exists($path)) {
                require_once $path;
            }
        }
    }
    
    public static function register_rest_routes(): void
    {
        register_rest_route('bitcore/v1', '/feeds', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_feeds'],
            'permission_callback' => [__CLASS__, 'check_read_permission'],
        ]);
        
        register_rest_route('bitcore/v1', '/feeds/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_feed'],
            'permission_callback' => [__CLASS__, 'check_read_permission'],
        ]);
        
        register_rest_route('bitcore/v1', '/feeds/extract', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'extract_text'],
            'permission_callback' => [__CLASS__, 'check_write_permission'],
        ]);
        
        register_rest_route('bitcore/v1', '/feeds/summarize', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'summarize_content'],
            'permission_callback' => [__CLASS__, 'check_write_permission'],
        ]);
        
        register_rest_route('bitcore/v1', '/watchlists', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_watchlists'],
            'permission_callback' => [__CLASS__, 'check_read_permission'],
        ]);
        
        register_rest_route('bitcore/v1', '/watchlists', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'create_watchlist'],
            'permission_callback' => [__CLASS__, 'check_write_permission'],
        ]);
        
        register_rest_route('bitcore/v1', '/watchlists/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [__CLASS__, 'delete_watchlist'],
            'permission_callback' => [__CLASS__, 'check_write_permission'],
        ]);
        
        register_rest_route('bitcore/v1', '/watchlists/(?P<id>\d+)/keywords', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'add_watchlist_keyword'],
            'permission_callback' => [__CLASS__, 'check_write_permission'],
        ]);
        
        register_rest_route('bitcore/v1', '/trends', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_trends'],
            'permission_callback' => [__CLASS__, 'check_read_permission'],
        ]);
        
        register_rest_route('bitcore/v1', '/cluster', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'cluster_content'],
            'permission_callback' => [__CLASS__, 'check_write_permission'],
        ]);
        
    }
    
    public static function register_admin_menu(): void
    {
        add_menu_page(
            'Feeds Dashboard',
            'Feeds',
            'read',
            'bitcore-feeds',
            [__CLASS__, 'render_feeds_dashboard'],
            'dashicons-rss',
            30
        );
        
        add_submenu_page(
            'bitcore-feeds',
            'Watchlists',
            'Watchlists',
            'read',
            'bitcore-watchlists',
            [__CLASS__, 'render_watchlist_manager']
        );
    }
    
    public static function register_admin_assets(string $hook): void
    {
        if (strpos($hook, 'bitcore-feeds') === false && strpos($hook, 'bitcore-watchlists') === false) {
            return;
        }
        
        wp_enqueue_style('bitcore-feeds-css', plugins_url('assets/css/feeds.css', __FILE__), [], '1.0.0');
        wp_enqueue_script('bitcore-feeds-js', plugins_url('assets/js/feeds.js', __FILE__), ['jquery'], '1.0.0', true);
    }
    
    public static function create_tables(): void
    {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $tables = [
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bitcore_watchlists (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NOT NULL,
                name varchar(255) NOT NULL,
                keywords text,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY user_id (user_id)
            ) $charset_collate;",
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bitcore_watchlist_matches (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                watchlist_id bigint(20) unsigned NOT NULL,
                content_id varchar(255) NOT NULL,
                content_type varchar(50) NOT NULL,
                matched_keyword varchar(255) NOT NULL,
                matched_at datetime DEFAULT CURRENT_TIMESTAMP,
                notified tinyint(1) DEFAULT 0,
                PRIMARY KEY (id),
                KEY watchlist_id (watchlist_id)
            ) $charset_collate;",
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bitcore_rag_workspaces (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NOT NULL,
                name varchar(255) NOT NULL,
                options text,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY user_id (user_id)
            ) $charset_collate;",
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bitcore_rag_documents (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                workspace_id bigint(20) unsigned NOT NULL,
                content longtext,
                metadata text,
                chunk_count int DEFAULT 0,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY workspace_id (workspace_id)
            ) $charset_collate;",
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bitcore_rag_embeddings (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                document_id bigint(20) unsigned NOT NULL,
                workspace_id bigint(20) unsigned NOT NULL,
                chunk_index int DEFAULT 0,
                content text,
                embedding longtext,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY document_id (document_id),
                KEY workspace_id (workspace_id)
            ) $charset_collate;",
        ];
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        foreach ($tables as $sql) {
            dbDelta($sql);
        }
    }
    
    public static function schedule_cron_jobs(): void
    {
        if (!wp_next_scheduled('bitcore_feeds_aggregate')) {
            wp_schedule_event(time(), 'hourly', 'bitcore_feeds_aggregate');
        }
        
        if (!wp_next_scheduled('bitcore_trends_calculate')) {
            wp_schedule_event(time(), 'daily', 'bitcore_trends_calculate');
        }
    }
    
    public static function clear_cron_jobs(): void
    {
        wp_clear_scheduled_hook('bitcore_feeds_aggregate');
        wp_clear_scheduled_hook('bitcore_trends_calculate');
    }
}
