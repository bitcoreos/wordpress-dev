<?php
declare(strict_types=1);
/**
 * BITCORE Admin Menu Registration
 * Backend Configuration Pages ONLY
 * 
 * User-facing features (Org Chart, Budget, Goals, Approvals) are handled by
 * Coraline's frontend pages in the theme.
 */

add_action('admin_menu', function() {
    // Main BITCORE menu
    add_menu_page(
        'BITCORE',
        'BITCORE', 
        'manage_options',
        'bitcore',
        function() { require_once BITCORE_PATH . 'admin/dashboard.php'; },
        'dashicons-superhero',
        25
    );
    
    // Dashboard
    add_submenu_page(
        'bitcore',
        'Dashboard',
        'Dashboard',
        'manage_options',
        'bitcore',
        function() { require_once BITCORE_PATH . 'admin/dashboard.php'; }
    );
    
    // Settings - API keys, model configs, system settings
    add_submenu_page(
        'bitcore',
        'Settings',
        'Settings',
        'manage_options',
        'bitcore-settings',
        function() { require_once BITCORE_PATH . 'admin/settings.php'; }
    );
    
    // API & Tools
    add_submenu_page(
        'bitcore',
        'API & Tools',
        'API & Tools',
        'manage_options',
        'bitcore-api',
        function() { require_once BITCORE_PATH . 'admin/api-tools.php'; }
    );
    
    // Models - AI model management
    add_submenu_page(
        'bitcore',
        'Models',
        'Models',
        'manage_options',
        'bitcore-models',
        function() { require_once BITCORE_PATH . 'admin/models.php'; }
    );
    
    // Agents - Agent registration/configuration
    add_submenu_page(
        'bitcore',
        'Agents',
        'Agents',
        'manage_options',
        'bitcore-agents',
        function() { require_once BITCORE_PATH . 'admin/agents.php'; }
    );
    
    // Internal Agents
    add_submenu_page(
        'bitcore',
        'Internal Agents',
        'Internal Agents',
        'manage_options',
        'bitcore-internal',
        function() { require_once BITCORE_PATH . 'admin/internal-agents.php'; }
    );
    
    // MCP - MCP server configuration
    add_submenu_page(
        'bitcore',
        'MCP',
        'MCP',
        'manage_options',
        'bitcore-mcp',
        function() { require_once BITCORE_PATH . 'admin/mcp.php'; }
    );
});

// Enqueue admin scripts
add_action('admin_enqueue_scripts', function($hook) {
    if (strpos($hook, 'bitcore') === false) return;
    
    wp_enqueue_script('jquery');
    wp_add_inline_script('jquery', '
        var bitcoreConfig = {
            restUrl: "' . rest_url('bitcore/v1/') . '",
            nonce: "' . wp_create_nonce('wp_rest') . '"
        };
    ', 'before');
});
