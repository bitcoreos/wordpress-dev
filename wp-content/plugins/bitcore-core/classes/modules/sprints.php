<?php
/**
 * BITCORE Sprints Module
 * Custom post type for sprint management
 *
 * @package BITCORE_Core
 * @since 1.3.0
 */

declare(strict_types=1);

/**
 * Class BITCORE_Modules_Sprints
 * Handles sprint CPT, meta fields, and sprint lifecycle
 */
class BITCORE_Modules_Sprints {

    const POST_TYPE = 'sprint';
    const TAX_STATUS = 'sprint_status';

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
                'name' => _x('Sprints', 'Post type general name', 'bitcore'),
                'singular_name' => _x('Sprint', 'Post type singular name', 'bitcore'),
                'menu_name' => _x('Sprints', 'Admin Menu text', 'bitcore'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'bitcore',
            'show_in_rest' => true,
            'rest_base' => 'sprints',
            'supports' => ['title', 'editor', 'author', 'revisions'],
            'capability_type' => 'post',
        ]);
    }

    public function register_taxonomy(): void {
        register_taxonomy(self::TAX_STATUS, self::POST_TYPE, [
            'labels' => ['name' => _x('Sprint Statuses', 'taxonomy name', 'bitcore')],
            'public' => false,
            'show_ui' => true,
            'show_in_rest' => true,
        ]);
    }

    public function register_default_statuses(): void {
        $statuses = ['planning' => 'Planning', 'active' => 'Active', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
        foreach ($statuses as $slug => $name) {
            if (!term_exists($slug, self::TAX_STATUS)) {
                wp_insert_term($name, self::TAX_STATUS, ['slug' => $slug]);
            }
        }
    }
}
