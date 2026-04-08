<?php
/**
 * BITCORE Librarian Theme Functions
 * Enterprise-grade WordPress theme for AI-Human Convergence Platform
 * @version 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Theme Setup
 */
function bitcore_theme_setup() {
    // Add theme support
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', array('search-form', 'comment-form', 'comment-list', 'gallery', 'caption'));
    add_theme_support('customize-selective-refresh-widgets');

    // Register menus
    register_nav_menus(array(
        'primary' => __('Primary Menu', 'bitcore'),
        'footer' => __('Footer Menu', 'bitcore'),
    ));
}
add_action('after_setup_theme', 'bitcore_theme_setup');

/**
 * Enqueue Scripts and Styles
 */
function bitcore_enqueue_assets() {
    $theme_version = '1.1.0';
    $assets_url = get_template_directory_uri() . '/assets';

    // Base styles
    wp_enqueue_style(
        'bitcore-base',
        $assets_url . '/css/base.css',
        array(),
        $theme_version
    );

    // BitFlux avatar styles
    wp_enqueue_style(
        'bitcore-bitflux',
        $assets_url . '/css/bitflux.css',
        array('bitcore-base'),
        $theme_version
    );

    // Widget styles
    wp_enqueue_style(
        'bitcore-widgets',
        $assets_url . '/css/widgets.css',
        array('bitcore-base'),
        $theme_version
    );

    // Layout styles
    wp_enqueue_style(
        'bitcore-layout',
        $assets_url . '/css/layout.css',
        array('bitcore-base', 'bitcore-widgets'),
        $theme_version
    );

    // Theme styles (last to override)
    wp_enqueue_style(
        'bitcore-theme',
        get_stylesheet_uri(),
        array('bitcore-base', 'bitcore-layout'),
        $theme_version
    );

    // BitFlux JavaScript
    wp_enqueue_script(
        'bitcore-bitflux-js',
        $assets_url . '/js/bitflux-avatar.js',
        array(),
        $theme_version,
        true
    );

    // Widget manager JavaScript
    wp_enqueue_script(
        'bitcore-widgets-js',
        $assets_url . '/js/widget-manager.js',
        array('bitcore-bitflux-js'),
        $theme_version,
        true
    );

    // Localize script for AJAX
    wp_localize_script('bitcore-widgets-js', 'bitcoreData', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'assetsUrl' => $assets_url,
        'restUrl' => rest_url('bitcore/v1/'),
        'nonce' => wp_create_nonce('bitcore_nonce'),
    ));
}
add_action('wp_enqueue_scripts', 'bitcore_enqueue_assets');

/**
 * Theme Switcher (for user preferences)
 */
function bitcore_theme_switcher() {
    $theme = isset($_COOKIE['bitcore_theme']) ? sanitize_text_field($_COOKIE['bitcore_theme']) : 'default';
    $allowed_themes = array('default', 'terminal', 'creative');

    if (in_array($theme, $allowed_themes) && $theme !== 'default') {
        wp_enqueue_style(
            'bitcore-theme-' . $theme,
            get_template_directory_uri() . '/assets/css/themes/' . $theme . '.css',
            array('bitcore-theme'),
            '1.1.0'
        );
    }

    echo '<script>document.documentElement.setAttribute("data-theme", "' . esc_attr($theme) . '");</script>';
}
add_action('wp_head', 'bitcore_theme_switcher', 1);

/**
 * Custom Template for Office Suite
 */
function bitcore_page_templates($templates) {
    $templates['page-office-suite.php'] = 'BITCORE Office Suite';
    $templates['page-dashboard.php'] = 'BITCORE Dashboard';
    return $templates;
}
add_filter('theme_page_templates', 'bitcore_page_templates');

/**
 * Template Loader
 */
function bitcore_template_loader($template) {
    if (is_page_template('page-office-suite.php')) {
        $new_template = locate_template('page-office-suite.php');
        if ($new_template) {
            return $new_template;
        }
    }
    return $template;
}
add_filter('template_include', 'bitcore_template_loader');

/**
 * AJAX Handlers for Widget Data
 */
function bitcore_ajax_get_stats() {
    check_ajax_referer('bitcore_nonce', 'nonce');

    // Mock data - replace with actual API calls
    $stats = array(
        'articles' => 75,
        'activity' => 58,
        'gold' => 1247,
        'credits' => 89,
    );

    wp_send_json_success($stats);
}
add_action('wp_ajax_bitcore_get_stats', 'bitcore_ajax_get_stats');
add_action('wp_ajax_nopriv_bitcore_get_stats', 'bitcore_ajax_get_stats');

function bitcore_ajax_get_queue() {
    check_ajax_referer('bitcore_nonce', 'nonce');

    // Mock queue data
    $queue = array(
        array('id' => 1, 'title' => 'Research: OpenClaw patterns', 'status' => 'active', 'time' => '2m ago'),
        array('id' => 2, 'title' => 'Analysis: BitFlux states', 'status' => 'pending', 'time' => '5m ago'),
        array('id' => 3, 'title' => 'Draft: Widget architecture', 'status' => 'completed', 'time' => '1h ago'),
    );

    wp_send_json_success($queue);
}
add_action('wp_ajax_bitcore_get_queue', 'bitcore_ajax_get_queue');
add_action('wp_ajax_nopriv_bitcore_get_queue', 'bitcore_ajax_get_queue');

/**
 * REST API Endpoints
 */
function bitcore_register_rest_routes() {
    register_rest_route('bitcore/v1', '/status', array(
        'methods' => 'GET',
        'callback' => 'bitcore_rest_get_status',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('bitcore/v1', '/bitflux/state', array(
        'methods' => 'POST',
        'callback' => 'bitcore_rest_set_bitflux_state',
        'permission_callback' => function() {
            return current_user_can('read');
        },
    ));
}
add_action('rest_api_init', 'bitcore_register_rest_routes');

function bitcore_rest_get_status() {
    return array(
        'status' => 'operational',
        'version' => '1.1.0',
        'timestamp' => current_time('mysql'),
    );
}

function bitcore_rest_set_bitflux_state($request) {
    $state = $request->get_param('state');
    $allowed_states = array('welcome', 'listen', 'chat-ready', 'focused-shield', 
                           'agent-task', 'process', 'swarm-guard', 'crown-mode', 
                           'portal-enter', 'error-404');

    if (!in_array($state, $allowed_states)) {
        return new WP_Error('invalid_state', 'Invalid BitFlux state', array('status' => 400));
    }

    // Store in transient or user meta
    set_transient('bitcore_bitflux_state_' . get_current_user_id(), $state, HOUR_IN_SECONDS);

    return array('success' => true, 'state' => $state);
}

/**
 * Admin Bar Customization
 */
function bitcore_admin_bar($wp_admin_bar) {
    if (!is_admin()) {
        $wp_admin_bar->add_node(array(
            'id' => 'bitcore-status',
            'title' => '<span style="color: #4ade80;">●</span> BITCORE Online',
            'href' => '#',
            'meta' => array('class' => 'bitcore-status-indicator'),
        ));
    }
}
add_action('admin_bar_menu', 'bitcore_admin_bar', 100);

/**
 * Body Class for Office Suite
 */
function bitcore_body_class($classes) {
    if (is_page_template('page-office-suite.php')) {
        $classes[] = 'bitcore-office-suite';
        $classes[] = 'bf-theme-dark';
    }
    return $classes;
}
add_filter('body_class', 'bitcore_body_class');

/**
 * Customizer Settings
 */
function bitcore_customize_register($wp_customize) {
    // BitFlux Settings Section
    $wp_customize->add_section('bitcore_bitflux', array(
        'title' => __('BitFlux Avatar', 'bitcore'),
        'priority' => 30,
    ));

    // Default State
    $wp_customize->add_setting('bitcore_bitflux_default_state', array(
        'default' => 'welcome',
        'sanitize_callback' => 'sanitize_text_field',
    ));

    $wp_customize->add_control('bitcore_bitflux_default_state', array(
        'label' => __('Default State', 'bitcore'),
        'section' => 'bitcore_bitflux',
        'type' => 'select',
        'choices' => array(
            'welcome' => 'Welcome',
            'listen' => 'Listening',
            'chat-ready' => 'Chat Ready',
            'focused-shield' => 'Focused Shield',
        ),
    ));
}
add_action('customize_register', 'bitcore_customize_register');
