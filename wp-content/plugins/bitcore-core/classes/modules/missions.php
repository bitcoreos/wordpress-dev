<?php
/**
 * BITCORE Missions Module
 * Custom post type for mission/epic management
 *
 * @package BITCORE_Core
 * @since 1.3.0
 */

declare(strict_types=1);

class BITCORE_Modules_Missions {

    const POST_TYPE = 'mission';
    const TAX_STATUS = 'mission_status';

    private $core;

    public function __construct($core) {
        $this->core = $core;
        add_action('init', [$this, 'register_post_type']);
        add_action('init', [$this, 'register_taxonomy']);
        add_action('init', [$this, 'register_default_statuses'], 20);
    }

    public function register_post_type(): void {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => _x('Missions', 'Post type general name', 'bitcore'),
                'singular_name' => _x('Mission', 'Post type singular name', 'bitcore'),
                'menu_name' => _x('Missions', 'Admin Menu text', 'bitcore'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'bitcore',
            'show_in_rest' => true,
            'rest_base' => 'missions',
            'supports' => ['title', 'editor', 'author', 'revisions'],
            'capability_type' => 'post',
        ]);
    }

    public function register_taxonomy(): void {
        register_taxonomy(self::TAX_STATUS, self::POST_TYPE, [
            'labels' => ['name' => _x('Mission Statuses', 'taxonomy name', 'bitcore')],
            'public' => false,
            'show_ui' => true,
            'show_in_rest' => true,
        ]);
    }

    public function register_default_statuses(): void {
        $statuses = ['proposed' => 'Proposed', 'approved' => 'Approved', 'in-progress' => 'In Progress', 'completed' => 'Completed', 'archived' => 'Archived'];
        foreach ($statuses as $slug => $name) {
            if (!term_exists($slug, self::TAX_STATUS)) {
                wp_insert_term($name, self::TAX_STATUS, ['slug' => $slug]);
            }
        }
    }
}
