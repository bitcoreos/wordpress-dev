<?php
/**
 * Custom post types and taxonomies
 *
 * Extracted verbatim from bitcore-core.php; require_once order is the original
 * execution order. Bootstrap remains the plugin entry point.
 *
 * @package    Bitcore
 * @since      2026.03.01
 * @version    1.3.0
 * @author     coraline
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// === ASHI: Post Types and Taxonomies ===
function bitcore_register_post_types(): void {
    register_post_type('bitcore_prompt', [
        'labels'             => [
            'name'          => __('Prompts', 'bitcore-core'),
            'singular_name' => __('Prompt', 'bitcore-core'),
        ],
        'public'             => false,
        'publicly_queryable' => false,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'capability_type'    => 'post',
        'map_meta_cap'       => true,
        'supports'           => ['title', 'editor', 'author'],
        'menu_icon'          => 'dashicons-format-chat',
        'rewrite'            => false,
    ]);

    $bitmon_common = [
        'public'             => false,
        'publicly_queryable' => false,
        'show_ui'            => true,
        'show_in_rest'       => true,
        'capability_type'    => 'post',
        'map_meta_cap'       => true,
        'supports'           => ['title', 'editor', 'author', 'custom-fields', 'revisions'],
        'rewrite'            => false,
    ];
    register_post_type('bitmon_battle', array_merge($bitmon_common, [
        'labels'    => ['name' => __('BITmon Battles', 'bitcore-core'), 'singular_name' => __('BITmon Battle', 'bitcore-core')],
        'menu_icon' => 'dashicons-shield-alt',
        'taxonomies' => ['bitmon_format', 'bitmon_event_type', 'bitmon_visibility'],
    ]));
    register_post_type('bitmon_tournament', array_merge($bitmon_common, [
        'labels'    => ['name' => __('BITmon Tournaments', 'bitcore-core'), 'singular_name' => __('BITmon Tournament', 'bitcore-core')],
        'menu_icon' => 'dashicons-awards',
        'taxonomies' => ['bitmon_format', 'bitmon_event_type', 'bitmon_visibility'],
    ]));
    register_post_type('bitmon_agent_profile', array_merge($bitmon_common, [
        'labels'    => ['name' => __('BITmon Agents', 'bitcore-core'), 'singular_name' => __('BITmon Agent', 'bitcore-core')],
        'menu_icon' => 'dashicons-games',
        'taxonomies' => ['bitmon_event_type', 'bitmon_visibility'],
    ]));
    register_post_type('bitmon_result', array_merge($bitmon_common, [
        'labels'    => ['name' => __('BITmon Benchmark Results', 'bitcore-core'), 'singular_name' => __('BITmon Benchmark Result', 'bitcore-core')],
        'menu_icon' => 'dashicons-chart-line',
        'taxonomies' => ['bitmon_format', 'bitmon_event_type', 'bitmon_visibility'],
    ]));
    register_post_type('bitcore_artifact', [
        'labels'             => [
            'name'          => __('Artifacts', 'bitcore-core'),
            'singular_name' => __('Artifact', 'bitcore-core'),
        ],
        'public'             => false,
        'publicly_queryable' => false,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'capability_type'    => 'post',
        'map_meta_cap'       => true,
        'supports'           => ['title', 'editor', 'author', 'revisions'],
        'taxonomies'         => ['bitcore_channel', 'artifact_status'],
        'menu_icon'          => 'dashicons-archive',
        'rewrite'            => false,
    ]);
}
add_action('init', 'bitcore_register_post_types', 5);

function bitcore_register_taxonomies(): void {

    foreach (['bitmon_format' => 'BITmon Formats', 'bitmon_event_type' => 'BITmon Event Types', 'bitmon_visibility' => 'BITmon Visibility'] as $taxonomy => $label) {
        register_taxonomy($taxonomy, ['bitmon_battle', 'bitmon_tournament', 'bitmon_agent_profile', 'bitmon_result'], [
            'labels'       => ['name' => __($label, 'bitcore-core'), 'singular_name' => __($label, 'bitcore-core')],
            'public'       => false,
            'hierarchical' => true,
            'show_ui'      => true,
            'show_in_rest' => true,
        ]);
    }
    register_taxonomy('bitcore_channel', ['agent_task', 'bitcore_artifact', 'post'], [
        'labels'       => [
            'name'          => __('Channels', 'bitcore-core'),
            'singular_name' => __('Channel', 'bitcore-core'),
        ],
        'public'       => false,
        'hierarchical' => true,
        'show_ui'      => true,
    ]);
    register_taxonomy('task_type', ['agent_task'], [
        'labels'       => [
            'name'          => __('Task Types', 'bitcore-core'),
            'singular_name' => __('Task Type', 'bitcore-core'),
        ],
        'public'       => false,
        'hierarchical' => true,
        'show_ui'      => true,
    ]);
    register_taxonomy('artifact_status', ['bitcore_artifact'], [
        'labels'       => [
            'name'          => __('Artifact Statuses', 'bitcore-core'),
            'singular_name' => __('Artifact Status', 'bitcore-core'),
        ],
        'public'       => false,
        'hierarchical' => false,
        'show_ui'      => true,
    ]);
}
add_action('init', 'bitcore_register_taxonomies', 6);
