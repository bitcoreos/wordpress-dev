<?php
declare(strict_types=1);
/**
 * BITCORE Admin Menu Registration
 * Backend Configuration Pages ONLY
 */

add_action('admin_menu', function() {
    add_menu_page(
        'BITCORE',
        'BITCORE',
        'manage_options',
        'bitcore',
        function() { require_once BITCORE_PATH . 'admin/dashboard.php'; },
        'dashicons-superhero',
        25
    );
    
    add_submenu_page('bitcore', 'Dashboard', 'Dashboard', 'manage_options', 'bitcore',
        function() { require_once BITCORE_PATH . 'admin/dashboard.php'; });
    
    add_submenu_page('bitcore', 'Settings', 'Settings', 'manage_options', 'bitcore-settings',
        function() { require_once BITCORE_PATH . 'admin/settings.php'; });
    
    add_submenu_page('bitcore', 'API & Tools', 'API & Tools', 'manage_options', 'bitcore-api',
        function() { require_once BITCORE_PATH . 'admin/api-tools.php'; });
    
    add_submenu_page('bitcore', 'Agents & Models', 'Agents & Models', 'manage_options', 'bitcore-agents-models',
        function() { require_once BITCORE_PATH . 'admin/agents-models.php'; });
    
    add_submenu_page('bitcore', 'MCP', 'MCP', 'manage_options', 'bitcore-mcp',
        function() { require_once BITCORE_PATH . 'admin/mcp.php'; });

    add_submenu_page('bitcore', 'ElizaOS', 'ElizaOS', 'manage_options', 'bitcore-eliza',
        function() {
            require_once BITCORE_PATH . 'admin/class-eliza-settings.php';
            $settings = new BITCORE_Eliza_Settings();
            $settings->render_settings_page();
        });
});

add_action('admin_enqueue_scripts', function($hook) {
    if (strpos($hook, 'bitcore') === false) return;
    wp_enqueue_script('jquery');
});
