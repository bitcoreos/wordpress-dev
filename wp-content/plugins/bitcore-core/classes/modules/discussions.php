<?php
declare(strict_types=1);
/**
 * BITCORE_Modules_Discussions class for Bitcore
 *
 * @package    Bitcore
 * @since      2026.03.01
 * @version    1.3.0
 * @author     hyperion
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}
class BITCORE_Modules_Discussions {
    private $core;

    public function __construct( $core ) {
        $this->core = $core;
    }

    /**
     * Get recent discussions
     */
    public function get_recent( $limit = 10 ) {
        return $this->core->connector_bithub->get_latest_topics( $limit );
    }

    /**
     * Get discussions by category
     */
    public function get_by_category( $category_id, $limit = 10 ) {
        // Implementation would fetch from BIThub
        return [];
    }

    /**
     * Create discussion from WordPress
     */
    public function create( $title, $content, $category_id = 1 ) {
        return $this->core->connector_bithub->create_topic( $title, $content, $category_id );
    }
}
