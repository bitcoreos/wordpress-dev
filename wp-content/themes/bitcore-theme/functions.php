<?php
/**
 * BITCORE Theme Functions
 *
 * @package BITCORE_Theme
 * @version 2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Theme Setup
 */
function bitcore_theme_setup(): void {
    add_theme_support( 'wp-block-styles' );
    add_theme_support( 'responsive-embeds' );
    add_theme_support( 'editor-styles' );
    add_theme_support( 'dark-editor-style' );
    add_theme_support( 'align-wide' );
    add_theme_support( 'custom-spacing' );
    add_theme_support( 'custom-line-height' );
    add_theme_support( 'appearance-tools' );
    add_theme_support( 'block-template-parts' );
    
    add_editor_style( array(
        'style.css',
        'assets/css/design-system.css',
    ) );
}
add_action( 'after_setup_theme', 'bitcore_theme_setup' );

/**
 * Register Scripts
 */
function bitcore_register_scripts(): void {
    $version = wp_get_theme()->get( 'Version' );
    
    wp_register_script(
        'bitcore-chat',
        get_template_directory_uri() . '/assets/js/chat.js',
        array( 'bitcore-theme-js' ),
        $version,
        true
    );
}
add_action( 'wp_enqueue_scripts', 'bitcore_register_scripts', 5 );

/**
 * Enqueue Scripts and Styles
 */
function bitcore_enqueue_assets(): void {
    $version = wp_get_theme()->get( 'Version' );
    
    wp_enqueue_style(
        'bitcore-theme-style',
        get_template_directory_uri() . '/style.css',
        array(),
        $version
    );
    
    wp_enqueue_style(
        'bitcore-design-system',
        get_template_directory_uri() . '/assets/css/design-system.css',
        array( 'bitcore-theme-style' ),
        $version
    );
    
    wp_enqueue_script(
        'bitcore-theme-js',
        get_template_directory_uri() . '/assets/js/theme.js',
        array(),
        $version,
        true
    );
}
add_action( 'wp_enqueue_scripts', 'bitcore_enqueue_assets' );

/**
 * Register Block Patterns
 */
function bitcore_register_block_patterns(): void {
    register_block_pattern_category(
        'bitcore-theme',
        array( 'label' => __( 'BITCORE Theme', 'bitcore-theme' ) )
    );
    
    $patterns = array( 'stat-cards', 'agent-grid', 'chat-widget' );
    
    foreach ( $patterns as $pattern ) {
        $file = get_template_directory() . "/patterns/{$pattern}.php";
        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }
}
add_action( 'init', 'bitcore_register_block_patterns' );

/**
 * Register Widget Areas
 */
function bitcore_register_widget_areas(): void {
    register_sidebar( array(
        'name'          => __( 'Dashboard Sidebar', 'bitcore-theme' ),
        'id'            => 'dashboard-sidebar',
        'description'   => __( 'Widgets for the dashboard sidebar area.', 'bitcore-theme' ),
        'before_widget' => '<div id="%1$s" class="bitcore-widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="bitcore-widget__title">',
        'after_title'   => '</h3>',
    ) );
    
    register_sidebar( array(
        'name'          => __( 'Footer Widgets', 'bitcore-theme' ),
        'id'            => 'footer-widgets',
        'description'   => __( 'Widgets for the footer area.', 'bitcore-theme' ),
        'before_widget' => '<div id="%1$s" class="bitcore-footer-widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h4 class="bitcore-footer-widget__title">',
        'after_title'   => '</h4>',
    ) );
}
add_action( 'widgets_init', 'bitcore_register_widget_areas' );

/**
 * Shortcode: BITCORE Chat
 */
function bitcore_shortcode_chat( $atts = array() ): string {
    $atts = shortcode_atts( array(
        'height' => '500',
        'agent' => 'default',
    ), $atts, 'bitcore-chat' );
    
    wp_enqueue_script( 'bitcore-chat' );
    
    ob_start();
    ?>
    <div id="bitcore-chat" class="bitcore-chat" data-agent="<?php echo esc_attr( $atts['agent'] ); ?>" style="height:<?php echo esc_attr( $atts['height'] ); ?>px;">
        <div class="bitcore-chat__header">
            <div class="bitcore-chat__agent-info">
                <span class="bitcore-chat__agent-avatar">🤖</span>
                <span class="bitcore-chat__agent-name"><?php esc_html_e( 'BITCORE Assistant', 'bitcore-theme' ); ?></span>
                <span class="bitcore-status bitcore-status--online"><?php esc_html_e( 'Online', 'bitcore-theme' ); ?></span>
            </div>
        </div>
        <div id="chat-messages" class="bitcore-chat__messages" role="log" aria-live="polite">
            <div class="bitcore-chat__message">
                <div class="bitcore-chat__avatar">🤖</div>
                <div class="bitcore-chat__bubble"><?php esc_html_e( 'Hello! I am your BITCORE assistant. How can I help you today?', 'bitcore-theme' ); ?></div>
            </div>
        </div>
        <div class="bitcore-chat__input">
            <input type="text" id="chat-input" class="bitcore-input" placeholder="<?php esc_attr_e( 'Type your message...', 'bitcore-theme' ); ?>" aria-label="<?php esc_attr_e( 'Chat message input', 'bitcore-theme' ); ?>">
            <button id="chat-send" class="bitcore-btn bitcore-btn--primary" aria-label="<?php esc_attr_e( 'Send message', 'bitcore-theme' ); ?>">Send</button>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'bitcore-chat', 'bitcore_shortcode_chat' );

/**
 * REST API Endpoint: Get Dashboard Stats
 */
function bitcore_rest_get_stats(): WP_REST_Response {
    $stats = array(
        'active_tasks'  => rand( 5, 25 ),
        'completed'     => rand( 100, 500 ),
        'pending'       => rand( 10, 50 ),
        'agents_online' => rand( 2, 8 ),
    );
    return new WP_REST_Response( $stats, 200 );
}

function bitcore_register_rest_routes(): void {
    register_rest_route( 'bitcore/v1', '/stats', array(
        'methods'  => 'GET',
        'callback' => 'bitcore_rest_get_stats',
        'permission_callback' => '__return_true',
    ) );
}
add_action( 'rest_api_init', 'bitcore_register_rest_routes' );

/**
 * Enqueue page-specific scripts for BITCORE SaaS pages
 */
function bitcore_enqueue_page_scripts() {
    $nonce = wp_create_nonce('wp_rest');
    $bitcore_data = array(
        'restUrl' => rest_url('bitcore/v1/'),
        'nonce'   => $nonce,
        'userId'  => get_current_user_id(),
    );

    // Core SaaS page scripts
    $page_scripts = array(
        'work'       => 'work.js',
        'tasks'      => 'tasks.js',
        'agents'     => 'chat.js',
        'chat'       => 'chat.js',
        'sessions'   => 'sessions.js',
        'approvals'  => 'approvals.js',
        'goals'      => 'goals.js',
        'budget'     => 'budget.js',
        'org-chart'  => 'org-chart.js',
    );
    
    // Paperclip integration pages
    $paperclip_pages = array(
        'org-chart'  => 'org-chart.js',
        'budget'     => 'budget.js',
        'goals'      => 'goals.js',
        'approvals'  => 'approvals.js',
        'sessions'   => 'sessions.js',
    );

    // Merge: paperclip pages take precedence for enqueue style
    $all_scripts = array_merge( $page_scripts, $paperclip_pages );

    $slug = get_post_field('post_name', get_queried_object_id());
    
    if (isset($all_scripts[$slug])) {
        $js_file = $all_scripts[$slug];
        $handle = 'bitcore-' . str_replace('-', '', $slug) . '-js';
        $js_path = get_template_directory() . '/assets/js/' . $js_file;
        
        wp_enqueue_script(
            $handle,
            get_template_directory_uri() . '/assets/js/' . $js_file,
            array('bitcore-theme-js'),
            file_exists($js_path) ? filemtime($js_path) : '1.0.0',
            true
        );
        wp_localize_script($handle, 'bitcoreData', $bitcore_data);
    }
}
add_action('wp_enqueue_scripts', 'bitcore_enqueue_page_scripts', 15);

/**
 * Enqueue Core CSS
 */
function bitcore_enqueue_core_css() {
    wp_enqueue_style('bitcore-base-css', get_template_directory_uri() . '/assets/css/base.css', array(), '1.0.0');
    wp_enqueue_style('bitcore-design-system-css', get_template_directory_uri() . '/assets/css/design-system.css', array('bitcore-base-css'), '1.0.0');
    wp_enqueue_style('bitcore-template-bridge-css', get_template_directory_uri() . '/assets/css/template-bridge.css', array('bitcore-design-system-css'), '1.0.0');
}
add_action('wp_enqueue_scripts', 'bitcore_enqueue_core_css');
