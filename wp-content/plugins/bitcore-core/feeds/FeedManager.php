<?php

declare(strict_types=1);

/**
 * BITCORE Timeline Feeds - Feed Manager
 *
 * @package BITCORE\Feeds
 * @author hyperion
 * @since 1.0.0
 */

namespace BITCORE\Feeds;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Feed Manager
 *
 * Module initialization, admin UI, and database management.
 *
 * @since 1.0.0
 */
class Feed_Manager
{
    /** @var string Module version */
    private const VERSION = '1.0.0';

    /** @var string Module path */
    private static string $path;

    /** @var string Module URL */
    private static string $url;

    /** @var bool Initialized flag */
    private static bool $initialized = false;

    /**
     * Initialize the feeds module.
     *
     * @since 1.0.0
     */
    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$path = dirname(__FILE__);
        self::$url = plugin_dir_url(__FILE__);

        // Load dependencies
        self::load_dependencies();

        // Register REST API routes
        add_action('rest_api_init', [Feed_Processor::class, 'register_rest_routes']);

        // Register admin menu
        add_action('admin_menu', [self::class, 'register_admin_menu']);

        // Register admin scripts and styles
        add_action('admin_enqueue_scripts', [self::class, 'register_admin_assets']);

        // Create database tables on activation
        add_action('bitcore_feeds_activate', [self::class, 'create_tables']);

        // Schedule cron jobs
        add_action('bitcore_feeds_activate', [self::class, 'schedule_cron_jobs']);

        // Clear scheduled jobs on deactivation
        add_action('bitcore_feeds_deactivate', [self::class, 'clear_cron_jobs']);

        self::$initialized = true;
    }

    /**
     * Load module dependencies.
     *
     * @since 1.0.0
     */
    private static function load_dependencies(): void
    {
        $files = [
            'class-feed-generator.php',
            'class-intent-matcher.php',
            'class-source-aggregator.php',
            'class-text-extractor.php',
            'class-chunker.php',
            'class-summarizer.php',
            'class-deduplicator.php',
            'class-topic-clusterer.php',
            'class-trend-detector.php',
            'class-watchlist.php',
            'RAGWorkspace.php',
            'RAGProcessor.php',
            'FeedProcessor.php',
        ];

        foreach ($files as $file) {
            $filepath = self::$path . '/' . $file;
            if (file_exists($filepath)) {
                require_once $filepath;
            }
        }
    }

    /**
     * Register admin menu.
     *
     * @since 1.0.0
     */
    public static function register_admin_menu(): void
    {
        add_submenu_page(
            'bitcore',
            __('Feeds Dashboard', 'bitcore'),
            __('Feeds', 'bitcore'),
            'manage_options',
            'bitcore-feeds',
            [self::class, 'render_feeds_dashboard']
        );

        add_submenu_page(
            'bitcore',
            __('Watchlists', 'bitcore'),
            __('Watchlists', 'bitcore'),
            'manage_options',
            'bitcore-watchlists',
            [self::class, 'render_watchlist_manager']
        );
    }

    /**
     * Register admin assets.
     *
     * @param string $hook Admin page hook.
     * @since 1.0.0
     */
    public static function register_admin_assets(string $hook): void
    {
        if (strpos($hook, 'bitcore-feeds') !== false || strpos($hook, 'bitcore-watchlists') !== false) {
            wp_enqueue_style(
                'bitcore-feeds-admin',
                self::$url . 'assets/css/admin.css',
                [],
                self::VERSION
            );

            wp_enqueue_script(
                'bitcore-feeds-admin',
                self::$url . 'assets/js/admin.js',
                ['jquery', 'wp-api'],
                self::VERSION,
                true
            );

            wp_localize_script('bitcore-feeds-admin', 'bitcoreFeeds', [
                'restUrl' => rest_url('bitcore/v1'),
                'nonce' => wp_create_nonce('wp_rest'),
            ]);
        }
    }

    /**
     * Create database tables.
     *
     * @since 1.0.0
     */
    public static function create_tables(): void
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Watchlists table
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bitcore_watchlists (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            keywords longtext,
            notification_settings longtext,
            is_active tinyint(1) DEFAULT 1,
            user_id bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_is_active (is_active)
        ) {$charset_collate}");

        // Watchlist matches table
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bitcore_watchlist_matches (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            watchlist_id bigint(20) unsigned NOT NULL,
            keywords_matched longtext,
            content_preview text,
            source_url varchar(500),
            source_title varchar(255),
            source_type varchar(50),
            matched_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_watchlist_id (watchlist_id),
            KEY idx_matched_at (matched_at)
        ) {$charset_collate}");

        // RAG Workspaces table
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bitcore_rag_workspaces (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            description text,
            settings longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id)
        ) {$charset_collate}");

        // RAG Documents table
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bitcore_rag_documents (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            workspace_id bigint(20) unsigned NOT NULL,
            title varchar(255),
            content longtext,
            source_url varchar(500),
            source_type varchar(50),
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_workspace_id (workspace_id)
        ) {$charset_collate}");

        // RAG Embeddings table
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bitcore_rag_embeddings (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            document_id bigint(20) unsigned NOT NULL,
            workspace_id bigint(20) unsigned NOT NULL,
            chunk_index int,
            chunk_content text,
            embedding longtext,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_document_id (document_id),
            KEY idx_workspace_id (workspace_id)
        ) {$charset_collate}");
    }

    /**
     * Schedule cron jobs.
     *
     * @since 1.0.0
     */
    public static function schedule_cron_jobs(): void
    {
        if (!wp_next_scheduled('bitcore_feeds_aggregate')) {
            wp_schedule_event(time(), 'hourly', 'bitcore_feeds_aggregate');
        }

        if (!wp_next_scheduled('bitcore_feeds_digest')) {
            wp_schedule_event(time(), 'daily', 'bitcore_feeds_digest');
        }
    }

    /**
     * Clear cron jobs.
     *
     * @since 1.0.0
     */
    public static function clear_cron_jobs(): void
    {
        wp_clear_scheduled_hook('bitcore_feeds_aggregate');
        wp_clear_scheduled_hook('bitcore_feeds_digest');
    }

    /**
     * Render feeds dashboard.
     *
     * @since 1.0.0
     */
    public static function render_feeds_dashboard(): void
    {
        include self::$path . '/templates/feeds-dashboard.php';
    }

    /**
     * Render watchlist manager.
     *
     * @since 1.0.0
     */
    public static function render_watchlist_manager(): void
    {
        include self::$path . '/templates/watchlist-manager.php';
    }
}

// Initialize module
add_action('plugins_loaded', [Feed_Manager::class, 'init']);
