<?php
/**
 * BITCORE Sessions Module
 * Custom post type for agent work sessions
 *
 * @package BITCORE_Core
 * @since 1.3.0
 */

declare(strict_types=1);

class BITCORE_Modules_Sessions {

    const POST_TYPE = 'agent_session';
    const TAX_STATUS = 'session_status';

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
                'name' => _x('Agent Sessions', 'Post type general name', 'bitcore'),
                'singular_name' => _x('Agent Session', 'Post type singular name', 'bitcore'),
                'menu_name' => _x('Sessions', 'Admin Menu text', 'bitcore'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'bitcore',
            'show_in_rest' => true,
            'rest_base' => 'agent-sessions',
            'supports' => ['title', 'author', 'revisions'],
            'capability_type' => 'post',
        ]);
    }

    public function register_taxonomy(): void {
        register_taxonomy(self::TAX_STATUS, self::POST_TYPE, [
            'labels' => ['name' => _x('Session Statuses', 'taxonomy name', 'bitcore')],
            'public' => false,
            'show_ui' => true,
            'show_in_rest' => true,
        ]);
    }

    public function register_default_statuses(): void {
        $statuses = ['active' => 'Active', 'idle' => 'Idle', 'completed' => 'Completed', 'error' => 'Error'];
        foreach ($statuses as $slug => $name) {
            if (!term_exists($slug, self::TAX_STATUS)) {
                wp_insert_term($name, self::TAX_STATUS, ['slug' => $slug]);
            }
        }
    }
}
