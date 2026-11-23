<?php

declare(strict_types=1);

/**
 * BITCORE Timeline Feeds - Watchlist
 *
 * @package BITCORE\Feeds
 * @since 1.0.0
 * @version     1.0.0
 */

namespace BITCORE\Feeds;

require_once __DIR__ . '/traits/trait-watchlist-management.php';
require_once __DIR__ . '/traits/trait-watchlist-alerting.php';
require_once __DIR__ . '/traits/trait-watchlist-persistence.php';
/**
 * Watchlist
 *
 * Keyword and topic monitoring with notifications.
 *
 * @since 1.0.0
 */
class Watchlist
{
    use Watchlist_Management;
    use Watchlist_Alerting;
    use Watchlist_Persistence;
    /** @var int Watchlist ID */
    private int $id;

    /** @var string Watchlist name */
    private string $name;

    /** @var array<int, string> Keywords to monitor */
    private array $keywords;

    /** @var array<string, mixed> Notification settings */
    private array $notification_settings;

    /** @var bool Active status */
    private bool $is_active;

    /** @var string Database table name */
    private string $table_name;

    /** @var string Matches table name */
    private string $matches_table;

    /**
     * Constructor.
     *
     * @param int|null $id Optional watchlist ID to load.
     * @since 1.0.0
     */
    public function __construct(?int $id = null)
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'bitcore_watchlists';
        $this->matches_table = $wpdb->prefix . 'bitcore_watchlist_matches';
        $this->keywords = [];
        $this->notification_settings = [
            'email' => false,
            'webhook' => null,
            'digest' => 'instant',
        ];
        $this->is_active = true;

        if ($id !== null) {
            $this->load($id);
        }
    }

    /**
     * Delete watchlist.
     *
     * @return bool|\WP_Error Success or WP_Error.
     * @since 1.0.0
     */
    public function delete()
    {
        if (!$this->id) {
            return new \WP_Error('no_watchlist', __('No watchlist loaded.', 'bitcore'));
        }

        global $wpdb;

        // Delete matches
        $wpdb->delete($this->matches_table, ['watchlist_id' => $this->id]);

        // Delete watchlist
        $result = $wpdb->delete($this->table_name, ['id' => $this->id]);

        if ($result === false) {
            return new \WP_Error('db_error', __('Failed to delete watchlist.', 'bitcore'));
        }

        $this->id = 0;
        return true;
    }

    /**
     * Get watchlist ID.
     *
     * @return int|null Watchlist ID.
     * @since 1.0.0
     */
    public function get_id(): ?int
    {
        return $this->id ?? null;
    }

    /**
     * Get watchlist name.
     *
     * @return string Name.
     * @since 1.0.0
     */
    public function get_name(): string
    {
        return $this->name;
    }

    /**
     * Get keywords.
     *
     * @return array<int, string> Keywords.
     * @since 1.0.0
     */
    public function get_keywords(): array
    {
        return $this->keywords;
    }

    /**
     * Set active status.
     *
     * @param bool $active Active status.
     * @since 1.0.0
     */
    public function set_active(bool $active): void
    {
        $this->is_active = $active;
        $this->save();
    }
}
