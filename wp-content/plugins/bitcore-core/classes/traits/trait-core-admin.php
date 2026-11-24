<?php
/**
 * Core admin pages, assets and shortcodes
 *
 * Extracted verbatim from wp-content/plugins/bitcore-core/classes/core.php (BITCORE 500-LOC refactor); logic unchanged.
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


trait BITCORE_Core_Admin_Trait {

    public function admin_init(): void {}

    public function admin_menu(): void {
        add_menu_page('BITCORE', 'BITCORE', 'manage_options', 'bitcore', [$this, 'admin_page'], 'dashicons-art', 30);
        add_submenu_page('bitcore', 'Dashboard', 'Dashboard', 'manage_options', 'bitcore', [$this, 'admin_page']);
        add_submenu_page('bitcore', 'Agents', 'Agents', 'manage_options', 'bitcore-agents', [$this, 'agents_page']);
        add_submenu_page('bitcore', 'MCP Tools', 'MCP Tools', 'manage_options', 'bitcore-mcp', [$this, 'mcp_page']);
        add_submenu_page('bitcore', 'Settings', 'Settings', 'manage_options', 'bitcore-settings', [$this, 'settings_page']);
    }

    public function admin_page(): void { include BITCORE_PATH . '/admin/dashboard.php'; }
    public function agents_page(): void { include BITCORE_PATH . '/admin/agents-models.php'; }
    public function mcp_page(): void { include BITCORE_PATH . '/admin/mcp.php'; }
    public function settings_page(): void { include BITCORE_PATH . '/admin/settings.php'; }

    public function enqueue_scripts(): void {
        wp_enqueue_style('bitcore-base', BITCORE_URL . 'assets/css/bitcore-base.css', [], BITCORE_VERSION);
        wp_enqueue_script('bitcore-chat', BITCORE_URL . 'assets/js/bitcore-chat.js', ['jquery'], BITCORE_VERSION, true);
        wp_localize_script('bitcore-chat', 'bitcoreConfig', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('bitcore/v1/'),
            'nonce' => wp_create_nonce('bitcore_nonce'),
            'userId' => get_current_user_id(),
            'userName' => wp_get_current_user()->display_name,
        ]);
    }

    public function enqueue_admin_scripts(string $hook): void {
        if (strpos($hook, 'bitcore') !== false) {
            wp_enqueue_style('bitcore-admin', BITCORE_URL . 'assets/css/bitcore-admin.css', [], BITCORE_VERSION);
            wp_enqueue_script('bitcore-admin', BITCORE_URL . 'assets/js/bitcore-admin.js', ['jquery'], BITCORE_VERSION, true);
        }
    }

    // Shortcode handlers
    public function chatbot_shortcode(array $atts): string {
        $atts = shortcode_atts(['agent' => 'coraline', 'theme' => 'dark'], $atts);
        ob_start();
        include BITCORE_PATH . '/templates/chatbot.php';
        return ob_get_clean();
    }

    public function agent_shortcode(array $atts): string {
        $atts = shortcode_atts(['name' => 'coraline', 'show_avatar' => true], $atts);
        return '<div class="bitcore-agent" data-agent="' . esc_attr($atts['name']) . '"></div>';
    }

    public function artifacts_shortcode(): string { return '<div class="bitcore-artifacts"></div>'; }
    public function feed_shortcode(): string { return '<div class="bitcore-feed"></div>'; }

    // === Coraline: Encrypted options handling ===

}
