<?php
/**
 * BITCORE Discussions Module
 * Integration with BIThub discussions
 */

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
