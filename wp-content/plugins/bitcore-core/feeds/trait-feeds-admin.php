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
 */

trait FeedsAdmin {
    public static function check_read_permission(): bool
    {
        return is_user_logged_in();
    }

    /**
     * Check write permission.
     */
    public static function check_write_permission(): bool
    {
        return is_user_logged_in();
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

