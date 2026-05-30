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
 * Require authentication for canonical ASHI app routes before templates render.
 *
 * WP-Discourse remains the SSO authority. This guard only routes unauthenticated
 * WordPress visitors to the normal WordPress login URL, where WP-Discourse adds
 * the Discourse SSO flow.
 */
function bitcore_require_auth_for_app_routes(): void {
    if ( is_user_logged_in() || is_admin() || wp_doing_ajax() || wp_is_json_request() ) {
        return;
    }

    $protected_slugs = array(
        'dashboard',
        'work',
        'agents',
        'chat',
        'knowledge',
        'my-posts',
    );

    foreach ( $protected_slugs as $slug ) {
        if ( is_page( $slug ) ) {
            wp_safe_redirect( wp_login_url( home_url( '/' . $slug . '/' ) ) );
            exit;
        }
    }
}
add_action( 'template_redirect', 'bitcore_require_auth_for_app_routes', 1 );

/**
 * Register Block Patterns
 */
function bitcore_register_block_patterns(): void {
    register_block_pattern_category(
        'bitcore-theme',
        array( 'label' => __( 'BITCORE Theme', 'bitcore-theme' ) )
    );
    // Pattern files are auto-discovered by WordPress from /patterns.
    // Keep this function limited to category registration to avoid duplicate registration notices.
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
 * Shortcode: BITCORE Dashboard
 */
function bitcore_shortcode_dashboard( $atts = array() ): string {
    $atts = shortcode_atts( array(
        'show_stats' => 'true',
        'show_agents' => 'true',
        'layout' => 'grid',
    ), $atts, 'bitcore-dashboard' );
    
    wp_enqueue_script( 'bitcore-dashboard-js' );
    wp_localize_script( 'bitcore-dashboard-js', 'bitcoreData', array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'wp_rest' ),
        'restUrl' => rest_url( 'bitcore/v1/' ),
    ) );
    
    ob_start();
    ?>
    <div class="bitcore-dashboard" data-layout="<?php echo esc_attr( $atts['layout'] ); ?>">
        <section class="bitcore-dashboard__stats bitcore-grid bitcore-grid--4">
            <div class="bitcore-card bitcore-stat-card">
                <div class="bitcore-stat-card__icon bitcore-stat-card__icon--primary">📡</div>
                <div class="bitcore-stat-card__content">
                    <span class="bitcore-card__title"><?php esc_html_e( 'BIThub Topics', 'bitcore-theme' ); ?></span>
                    <span class="bitcore-card__value" id="stat-topics">--</span>
                </div>
            </div>
            <div class="bitcore-card bitcore-stat-card">
                <div class="bitcore-stat-card__icon bitcore-stat-card__icon--success">🗂️</div>
                <div class="bitcore-stat-card__content">
                    <span class="bitcore-card__title"><?php esc_html_e( 'Artifacts', 'bitcore-theme' ); ?></span>
                    <span class="bitcore-card__value" id="stat-artifacts">--</span>
                </div>
            </div>
            <div class="bitcore-card bitcore-stat-card">
                <div class="bitcore-stat-card__icon bitcore-stat-card__icon--warning">⚡</div>
                <div class="bitcore-stat-card__content">
                    <span class="bitcore-card__title"><?php esc_html_e( 'Active Tasks', 'bitcore-theme' ); ?></span>
                    <span class="bitcore-card__value" id="stat-active-tasks">--</span>
                </div>
            </div>
            <div class="bitcore-card bitcore-stat-card">
                <div class="bitcore-stat-card__icon bitcore-stat-card__icon--accent">🤖</div>
                <div class="bitcore-stat-card__content">
                    <span class="bitcore-card__title"><?php esc_html_e( 'Agents Online', 'bitcore-theme' ); ?></span>
                    <span class="bitcore-card__value" id="stat-agents">--</span>
                </div>
            </div>
        </section>
        <p class="bitcore-dashboard__timestamp" style="font-size:0.75rem;color:var(--text-secondary);margin-top:var(--space-2);">Loading...</p>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'bitcore-dashboard', 'bitcore_shortcode_dashboard' );

/**
 * Shortcode: BITCORE Feed
 */
function bitcore_shortcode_feed( $atts = array() ): string {
    $atts = shortcode_atts( array(
        'count' => 10,
        'type' => 'all',
    ), $atts, 'bitcore-feed' );
    
    wp_enqueue_script( 'bitcore-feed' );
    
    ob_start();
    ?>
    <div class="bitcore-feed-container">
        <div class="bitcore-feed__header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--space-4);flex-wrap:wrap;gap:var(--space-2);">
            <h2 class="bitcore-section__title" style="margin:0;"><?php esc_html_e( 'BIThub Feed', 'bitcore-theme' ); ?></h2>
            <input type="search" id="feed-search" class="bitcore-input" placeholder="<?php esc_attr_e( 'Search feed...', 'bitcore-theme' ); ?>" style="max-width:240px;">
        </div>
        <div id="bitcore-feed" class="bitcore-container"></div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'bitcore-feed', 'bitcore_shortcode_feed' );

/**
 * Shortcode: BITCORE Artifacts
 */
function bitcore_shortcode_artifacts( $atts = array() ): string {
    $atts = shortcode_atts( array(
        'count' => 20,
        'filter' => 'all',
    ), $atts, 'bitcore-artifacts' );
    
    wp_enqueue_script( 'bitcore-artifacts' );
    
    ob_start();
    ?>
    <div class="bitcore-artifacts-container">
        <div class="bitcore-artifacts__header" style="margin-bottom:var(--space-4);">
            <h2 class="bitcore-section__title"><?php esc_html_e( 'Artifacts Gallery', 'bitcore-theme' ); ?></h2>
            <div class="bitcore-artifacts__filters" style="display:flex;gap:var(--space-2);flex-wrap:wrap;margin-top:var(--space-2);">
                <button class="bitcore-btn bitcore-btn--ghost bitcore-btn--sm active" data-artifact-filter="all">All</button>
                <button class="bitcore-btn bitcore-btn--ghost bitcore-btn--sm" data-artifact-filter="bithub">BIThub</button>
                <button class="bitcore-btn bitcore-btn--ghost bitcore-btn--sm" data-artifact-filter="directory">Directory</button>
                <button class="bitcore-btn bitcore-btn--ghost bitcore-btn--sm" data-artifact-filter="reasoning">Reasoning</button>
            </div>
        </div>
        <div id="bitcore-artifacts" class="bitcore-container"></div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'bitcore-artifacts', 'bitcore_shortcode_artifacts' );

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
 * Count agent_task items assigned to one or more task_status terms.
 *
 * @param array<int, string> $slugs Task status slugs.
 * @return int Real term count, or 0 when taxonomy data is unavailable.
 */
function bitcore_theme_count_task_status_terms( array $slugs ): int {
    if ( ! function_exists( 'taxonomy_exists' ) || ! taxonomy_exists( 'task_status' ) ) {
        return 0;
    }

    $slugs = array_values( array_filter( array_map( 'sanitize_key', $slugs ) ) );
    if ( empty( $slugs ) || ! function_exists( 'get_terms' ) ) {
        return 0;
    }

    $terms = get_terms( array(
        'taxonomy'   => 'task_status',
        'slug'       => $slugs,
        'hide_empty' => false,
    ) );

    if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
        return 0;
    }

    $count = 0;
    foreach ( $terms as $term ) {
        $count += isset( $term->count ) ? max( 0, (int) $term->count ) : 0;
    }

    return $count;
}

/**
 * Get active agent count from the BITCORE health snapshot when available.
 */
function bitcore_theme_get_agents_online_count(): int {
    if ( ! class_exists( 'BITCORE_Health_Snapshot' ) || ! method_exists( 'BITCORE_Health_Snapshot', 'get_latest' ) ) {
        return 0;
    }

    $snapshot = BITCORE_Health_Snapshot::get_latest( false );
    if ( ! is_array( $snapshot ) ) {
        return 0;
    }

    return max( 0, (int) ( $snapshot['active_agents'] ?? 0 ) );
}

/**
 * REST API Endpoint: Get Dashboard Stats
 */
function bitcore_rest_get_stats(): WP_REST_Response {
    $stats = array(
        'active_tasks'  => bitcore_theme_count_task_status_terms( array( 'doing', 'review', 'running', 'active' ) ),
        'completed'     => bitcore_theme_count_task_status_terms( array( 'done', 'completed' ) ),
        'pending'       => bitcore_theme_count_task_status_terms( array( 'inbox', 'pending', 'backlog' ) ),
        'agents_online' => bitcore_theme_get_agents_online_count(),
        'source'        => 'real_counts',
        'generated_at'  => current_time( 'mysql' ),
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

    // Canonical ASHI page scripts. Legacy task/goal/session pages are consolidated into /work/.
    $all_scripts = array(
        'work' => 'work.js',
    );

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
