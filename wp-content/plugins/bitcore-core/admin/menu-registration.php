<?php
declare(strict_types=1);
/**
 * BITCORE Admin Menu Registration
 * Registers all admin pages with proper permissions
 */

// Register admin menu
add_action('admin_menu', function() {
    // Main menu
    add_menu_page(
        'BITCORE',
        'BITCORE',
        'manage_options',
        'bitcore',
        function() { require_once BITCORE_PATH . 'admin/dashboard.php'; },
        'dashicons-superhero',
        25
    );
    
    // Submenu pages - Core
    add_submenu_page(
        'bitcore',
        'Dashboard',
        'Dashboard',
        'manage_options',
        'bitcore',
        function() { require_once BITCORE_PATH . 'admin/dashboard.php'; }
    );
    
    add_submenu_page(
        'bitcore',
        'Settings',
        'Settings',
        'manage_options',
        'bitcore-settings',
        function() { require_once BITCORE_PATH . 'admin/settings.php'; }
    );
    
    add_submenu_page(
        'bitcore',
        'API & Tools',
        'API & Tools',
        'manage_options',
        'bitcore-api',
        function() { require_once BITCORE_PATH . 'admin/api-tools.php'; }
    );
    
    add_submenu_page(
        'bitcore',
        'Models',
        'Models',
        'manage_options',
        'bitcore-models',
        function() { require_once BITCORE_PATH . 'admin/models.php'; }
    );
    
    add_submenu_page(
        'bitcore',
        'Agents',
        'Agents',
        'manage_options',
        'bitcore-agents',
        function() { require_once BITCORE_PATH . 'admin/agents.php'; }
    );
    
    add_submenu_page(
        'bitcore',
        'Internal Agents',
        'Internal Agents',
        'manage_options',
        'bitcore-internal',
        function() { require_once BITCORE_PATH . 'admin/internal-agents.php'; }
    );
    
    add_submenu_page(
        'bitcore',
        'MCP',
        'MCP',
        'manage_options',
        'bitcore-mcp',
        function() { require_once BITCORE_PATH . 'admin/mcp.php'; }
    );
    
    // Paperclip Integration - Org Chart
    add_submenu_page(
        'bitcore',
        'Org Chart',
        'Org Chart',
        'manage_options',
        'bitcore-org-chart',
        function() { require_once BITCORE_PATH . 'admin/org-chart.php'; }
    );
    
    // Paperclip Integration - Budget
    add_submenu_page(
        'bitcore',
        'Budget',
        'Budget',
        'manage_options',
        'bitcore-budget',
        function() { require_once BITCORE_PATH . 'admin/budget.php'; }
    );
    
    // Paperclip Integration - Goals
    add_submenu_page(
        'bitcore',
        'Goals',
        'Goals',
        'manage_options',
        'bitcore-goals',
        function() { require_once BITCORE_PATH . 'admin/goals.php'; }
    );
    
    // Paperclip Integration - Approvals
    add_submenu_page(
        'bitcore',
        'Approvals',
        'Approvals',
        'manage_options',
        'bitcore-approvals',
        function() { require_once BITCORE_PATH . 'admin/approvals.php'; }
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
