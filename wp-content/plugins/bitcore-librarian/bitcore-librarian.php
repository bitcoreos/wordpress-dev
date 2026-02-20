<?php
/**
 * Plugin Name: BITCORE Librarian
 * Description: Research queue interface for BITCORE. UI only - no credentials.
 * Version: 0.2.1
 * Author: BITCORE
 * Text Domain: bitcore-librarian
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Define constants - NO API KEYS, NO SECRETS
define('BITCORE_LIBRARIAN_VERSION', '0.2.1');
define('BITCORE_LIBRARIAN_URL', plugin_dir_url(__FILE__));
define('BITCORE_LIBRARIAN_PATH', plugin_dir_path(__FILE__));

// n8n webhook URL - public endpoint, no auth required
define('BITCORE_N8N_WEBHOOK', 'http://n8n:5678/webhook/bitcore-librarian');

class BITCORE_Librarian {

    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_shortcode('bitcore_chat', [$this, 'render_chat_interface']);
        add_shortcode('bitcore_queue', [$this, 'render_queue_status']);
        add_shortcode('bitcore_dashboard', [$this, 'render_dashboard']);
        add_action('wp_ajax_bitcore_submit_intent', [$this, 'submit_intent']);
        add_action('wp_ajax_nopriv_bitcore_submit_intent', [$this, 'submit_intent']);
    }

    public function enqueue_assets() {
        // Plugin base styles
        wp_enqueue_style(
            'bitcore-librarian-css',
            BITCORE_LIBRARIAN_URL . 'assets/css/librarian.css',
            [],
            BITCORE_LIBRARIAN_VERSION
        );

        // BitFlux styles from theme
        $theme_css_url = get_template_directory_uri() . '/assets/css/';

        wp_enqueue_style(
            'bitcore-base',
            $theme_css_url . 'base.css',
            [],
            BITCORE_LIBRARIAN_VERSION
        );

        wp_enqueue_style(
            'bitcore-bitflux',
            $theme_css_url . 'bitflux.css',
            ['bitcore-base'],
            BITCORE_LIBRARIAN_VERSION
        );

        wp_enqueue_style(
            'bitcore-widgets',
            $theme_css_url . 'widgets.css',
            ['bitcore-base'],
            BITCORE_LIBRARIAN_VERSION
        );

        // Plugin JavaScript
        wp_enqueue_script(
            'bitcore-librarian-js',
            BITCORE_LIBRARIAN_URL . 'assets/js/librarian.js',
            ['jquery'],
            BITCORE_LIBRARIAN_VERSION,
            true
        );

        // BitFlux JavaScript from theme
        wp_enqueue_script(
            'bitcore-bitflux-js',
            $theme_css_url . '../js/bitflux-avatar.js',
            [],
            BITCORE_LIBRARIAN_VERSION,
            true
        );

        wp_localize_script('bitcore-librarian-js', 'bitcoreAjax', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bitcore_librarian_nonce'),
            'assetsUrl' => $theme_css_url . '../'
        ]);

        // Also localize for BitFlux
        wp_localize_script('bitcore-bitflux-js', 'bitcoreData', [
            'assetsUrl' => $theme_css_url . '../'
        ]);
    }

    public function submit_intent() {
        check_ajax_referer('bitcore_librarian_nonce', 'nonce');

        $intent = sanitize_text_field($_POST['intent'] ?? '');
        $scope = sanitize_text_field($_POST['scope'] ?? 'quick');

        if (empty($intent)) {
            wp_send_json_error('Intent required');
        }

        $payload = [
            'intent' => $intent,
            'scope' => $scope,
            'user_id' => get_current_user_id(),
            'session_id' => session_id(),
            'timestamp' => current_time('mysql'),
            'source' => 'wordpress_ui',
            'agent' => 'coraline'
        ];

        $response = wp_remote_post(BITCORE_N8N_WEBHOOK, [
            'body' => json_encode($payload),
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 5
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error('Unable to reach librarian. Please try again.');
        }

        wp_send_json_success([
            'message' => 'Intent received. The librarians are reviewing.',
            'queue_position' => rand(1, 5),
            'estimated_time' => $scope === 'quick' ? '5-10 min' : ($scope === 'deep' ? '30-60 min' : 'Open-ended'),
            'correlation_id' => wp_generate_uuid4()
        ]);
    }

    public function render_chat_interface($atts) {
        ob_start();
        include BITCORE_LIBRARIAN_PATH . 'templates/chat-interface.php';
        return ob_get_clean();
    }

    public function render_queue_status($atts) {
        ob_start();
        include BITCORE_LIBRARIAN_PATH . 'templates/queue-status.php';
        return ob_get_clean();
    }

    public function render_dashboard($atts) {
        ob_start();
        include BITCORE_LIBRARIAN_PATH . 'templates/dashboard.php';
        return ob_get_clean();
    }
}

new BITCORE_Librarian();
