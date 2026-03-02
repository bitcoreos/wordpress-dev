<?php
/**
 * BITCORE Timeline Feeds Module
 *
 * @package BITCORE\Feeds
 * @since 1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Feeds;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Feeds Module Loader
 *
 * @since 1.0.0
 */
class Feeds_Loader
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
        add_action('rest_api_init', [self::class, 'register_rest_routes']);

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
            'class-rag-workspace.php',
        ];

        foreach ($files as $file) {
            $filepath = self::$path . '/' . $file;
            if (file_exists($filepath)) {
                require_once $filepath;
            }
        }
    }

    /**
     * Register REST API routes.
     *
     * @since 1.0.0
     */
    public static function register_rest_routes(): void
    {
        $namespace = 'bitcore/v1';

        // Feed endpoints
        register_rest_route($namespace, '/feeds', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [self::class, 'get_feeds'],
            'permission_callback' => [self::class, 'check_read_permission'],
        ]);

        register_rest_route($namespace, '/feeds/(?P<id>\\d+)', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [self::class, 'get_feed'],
            'permission_callback' => [self::class, 'check_read_permission'],
        ]);

        // Text extraction endpoint
        register_rest_route($namespace, '/feeds/extract', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'extract_text'],
            'permission_callback' => [self::class, 'check_write_permission'],
            'args' => [
                'url' => ['type' => 'string', 'required' => true],
            ],
        ]);

        // Summarization endpoint
        register_rest_route($namespace, '/feeds/summarize', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'summarize_content'],
            'permission_callback' => [self::class, 'check_write_permission'],
            'args' => [
                'content' => ['type' => 'string', 'required' => true],
            ],
        ]);

        // Watchlist endpoints
        register_rest_route($namespace, '/watchlists', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [self::class, 'get_watchlists'],
            'permission_callback' => [self::class, 'check_read_permission'],
        ]);

        register_rest_route($namespace, '/watchlists', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'create_watchlist'],
            'permission_callback' => [self::class, 'check_write_permission'],
        ]);

        register_rest_route($namespace, '/watchlists/(?P<id>\\d+)', [
            'methods' => \WP_REST_Server::DELETABLE,
            'callback' => [self::class, 'delete_watchlist'],
            'permission_callback' => [self::class, 'check_write_permission'],
        ]);

        register_rest_route($namespace, '/watchlists/(?P<id>\\d+)/keywords', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'add_watchlist_keyword'],
            'permission_callback' => [self::class, 'check_write_permission'],
        ]);

        // Trend detection endpoint
        register_rest_route($namespace, '/feeds/trends', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [self::class, 'get_trends'],
            'permission_callback' => [self::class, 'check_read_permission'],
        ]);

        // Topic clustering endpoint
        register_rest_route($namespace, '/feeds/cluster', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'cluster_content'],
            'permission_callback' => [self::class, 'check_write_permission'],
        ]);

        // RAG Workspace endpoints
        register_rest_route($namespace, '/rag/workspaces', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [self::class, 'get_rag_workspaces'],
            'permission_callback' => [self::class, 'check_read_permission'],
        ]);

        register_rest_route($namespace, '/rag/workspaces', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'create_rag_workspace'],
            'permission_callback' => [self::class, 'check_write_permission'],
        ]);

        register_rest_route($namespace, '/rag/workspaces/(?P<id>\\d+)/documents', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'store_rag_document'],
            'permission_callback' => [self::class, 'check_write_permission'],
        ]);

        register_rest_route($namespace, '/rag/workspaces/(?P<id>\\d+)/query', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'query_rag_workspace'],
            'permission_callback' => [self::class, 'check_read_permission'],
        ]);
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

    // ==================== REST API Callbacks ====================

    /**
     * Get feeds.
     *
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response Response.
     */
    public static function get_feeds(\WP_REST_Request $request): \WP_REST_Response
    {
        $format = $request->get_param('format') ?? 'json';
        $aggregator = new Source_Aggregator();
        $items = $aggregator->aggregate();

        $generator = new Feed_Generator();
        $content = match ($format) {
            'rss' => $generator->generate_rss($items),
            'atom' => $generator->generate_atom($items),
            default => $generator->generate_json($items),
        };

        return new \WP_REST_Response(['content' => $content, 'count' => count($items)]);
    }

    /**
     * Get single feed.
     */
    public static function get_feed(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response(['message' => 'Feed endpoint']);
    }

    /**
     * Extract text from URL.
     */
    public static function extract_text(\WP_REST_Request $request): \WP_REST_Response
    {
        $url = $request->get_param('url');
        $extractor = new Text_Extractor();
        $result = $extractor->extract_from_url($url);

        if (is_wp_error($result)) {
            return new \WP_REST_Response(['error' => $result->get_error_message()], 400);
        }

        return new \WP_REST_Response(['content' => $result]);
    }

    /**
     * Summarize content.
     */
    public static function summarize_content(\WP_REST_Request $request): \WP_REST_Response
    {
        $content = $request->get_param('content');
        $summarizer = new Summarizer();
        $result = $summarizer->summarize($content);

        if (is_wp_error($result)) {
            return new \WP_REST_Response(['error' => $result->get_error_message()], 400);
        }

        return new \WP_REST_Response(['summary' => $result]);
    }

    /**
     * Get watchlists.
     */
    public static function get_watchlists(\WP_REST_Request $request): \WP_REST_Response
    {
        $watchlists = Watchlist::get_user_watchlists();
        return new \WP_REST_Response(['watchlists' => $watchlists]);
    }

    /**
     * Create watchlist.
     */
    public static function create_watchlist(\WP_REST_Request $request): \WP_REST_Response
    {
        $watchlist = new Watchlist();
        $result = $watchlist->create_watchlist(
            $request->get_param('name'),
            ['keywords' => $request->get_param('keywords') ?? []]
        );

        if (is_wp_error($result)) {
            return new \WP_REST_Response(['error' => $result->get_error_message()], 400);
        }

        return new \WP_REST_Response(['id' => $result, 'message' => 'Watchlist created']);
    }

    /**
     * Delete watchlist.
     */
    public static function delete_watchlist(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $watchlist = new Watchlist($id);
        $result = $watchlist->delete();

        if (is_wp_error($result)) {
            return new \WP_REST_Response(['error' => $result->get_error_message()], 400);
        }

        return new \WP_REST_Response(['message' => 'Watchlist deleted']);
    }

    /**
     * Add watchlist keyword.
     */
    public static function add_watchlist_keyword(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $keyword = $request->get_param('keyword');
        $watchlist = new Watchlist($id);
        $result = $watchlist->add_keyword($keyword);

        if (is_wp_error($result)) {
            return new \WP_REST_Response(['error' => $result->get_error_message()], 400);
        }

        return new \WP_REST_Response(['message' => 'Keyword added']);
    }

    /**
     * Get trends.
     */
    public static function get_trends(\WP_REST_Request $request): \WP_REST_Response
    {
        $aggregator = new Source_Aggregator();
        $items = $aggregator->aggregate();
        $detector = new Trend_Detector();
        $trends = $detector->detect_trends($items);

        return new \WP_REST_Response($trends);
    }

    /**
     * Cluster content.
     */
    public static function cluster_content(\WP_REST_Request $request): \WP_REST_Response
    {
        $items = $request->get_param('items') ?? [];
        $clusterer = new Topic_Clusterer();
        $result = $clusterer->cluster($items);

        return new \WP_REST_Response($result);
    }

    /**
     * Get RAG workspaces.
     */
    public static function get_rag_workspaces(\WP_REST_Request $request): \WP_REST_Response
    {
        $workspaces = RAG_Workspace::get_user_workspaces();
        return new \WP_REST_Response(['workspaces' => $workspaces]);
    }

    /**
     * Create RAG workspace.
     */
    public static function create_rag_workspace(\WP_REST_Request $request): \WP_REST_Response
    {
        $workspace = new RAG_Workspace();
        $result = $workspace->create_workspace($request->get_param('name'));

        if (is_wp_error($result)) {
            return new \WP_REST_Response(['error' => $result->get_error_message()], 400);
        }

        return new \WP_REST_Response(['id' => $result, 'message' => 'Workspace created']);
    }

    /**
     * Store RAG document.
     */
    public static function store_rag_document(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $workspace = new RAG_Workspace($id);
        $result = $workspace->store_document(
            $request->get_param('content'),
            $request->get_param('metadata') ?? []
        );

        if (is_wp_error($result)) {
            return new \WP_REST_Response(['error' => $result->get_error_message()], 400);
        }

        return new \WP_REST_Response(['document_id' => $result, 'message' => 'Document stored']);
    }

    /**
     * Query RAG workspace.
     */
    public static function query_rag_workspace(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $workspace = new RAG_Workspace($id);
        $result = $workspace->query($request->get_param('query'));

        if (is_wp_error($result)) {
            return new \WP_REST_Response(['error' => $result->get_error_message()], 400);
        }

        return new \WP_REST_Response($result);
    }

    // ==================== Permission Callbacks ====================

    /**
     * Check read permission.
     */
    public static function check_read_permission(): bool
    {
        return current_user_can('read');
    }

    /**
     * Check write permission.
     */
    public static function check_write_permission(): bool
    {
        return current_user_can('edit_posts');
    }

    // ==================== Admin Renderers ====================

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
add_action('plugins_loaded', [Feeds_Loader::class, 'init']);
