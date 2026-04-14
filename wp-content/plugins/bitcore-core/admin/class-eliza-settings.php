<?php
/**
 * ElizaOS Admin Settings Page
 *
 * Configuration page for ElizaOS integration under Settings > ElizaOS.
 * Uses WordPress Settings API for secure option management.
 *
 * Options managed:
 *  - bitcore_eliza_server_url       (default: http://172.17.0.1:3000/api)
 *  - bitcore_eliza_auth_token       (default: bitcore-eliza-os-auth-token-2026)
 *  - bitcore_eliza_daily_limit      (default: 100)
 *  - bitcore_eliza_default_agent    (default: aria)
 *  - bitcore_eliza_x402_wallet      (default: '')
 *  - bitcore_eliza_anonymous_access (default: false)
 *  - bitcore_eliza_agent_aria       (default: eac677dd-94c4-02ab-bfea-bc554e52b2a3)
 *  - bitcore_eliza_agent_forge      (default: 3ab11b75-ca36-0cee-a3d7-7bfcab3c27cd)
 *  - bitcore_eliza_agent_muse       (default: cf0dee2b-9fc6-041b-83a0-90a644d26362)
 *
 * @package BITCORE
 * @since 1.2.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class BITCORE_Eliza_Settings {

    /** @var string Settings page slug */
    private string $page_slug = 'bitcore-eliza-settings';

    /** @var string Settings option group */
    private string $option_group = 'bitcore_eliza_settings';

    /**
     * Constructor — hooks into WordPress admin.
     */
    public function __construct() {
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_menu', [$this, 'add_settings_page']);
    }

    /**
     * Register all ElizaOS settings using WordPress Settings API.
     */
    public function register_settings(): void {
        // Server URL
        register_setting($this->option_group, 'bitcore_eliza_server_url', [
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => 'http://172.17.0.1:3000/api',
        ]);

        // Auth Token
        register_setting($this->option_group, 'bitcore_eliza_auth_token', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'bitcore-eliza-os-auth-token-2026',
        ]);

        // Daily Call Limit
        register_setting($this->option_group, 'bitcore_eliza_daily_limit', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 100,
        ]);

        // Default Agent
        register_setting($this->option_group, 'bitcore_eliza_default_agent', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'aria',
        ]);

        // x402 Wallet Address
        register_setting($this->option_group, 'bitcore_eliza_x402_wallet', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]);

        // Anonymous Access
        register_setting($this->option_group, 'bitcore_eliza_anonymous_access', [
            'type'              => 'boolean',
            'sanitize_callback' => [$this, 'sanitize_checkbox'],
            'default'           => false,
        ]);

        // Agent IDs
        register_setting($this->option_group, 'bitcore_eliza_agent_aria', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'eac677dd-94c4-02ab-bfea-bc554e52b2a3',
        ]);

        register_setting($this->option_group, 'bitcore_eliza_agent_forge', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '3ab11b75-ca36-0cee-a3d7-7bfcab3c27cd',
        ]);

        register_setting($this->option_group, 'bitcore_eliza_agent_muse', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'cf0dee2b-9fc6-041b-83a0-90a644d26362',
        ]);

        // === Section: Connection ===
        add_settings_section(
            'bitcore_eliza_section_connection',
            __('ElizaOS Server Connection', 'bitcore-core'),
            '__return_null',
            $this->page_slug
        );

        add_settings_field(
            'bitcore_eliza_server_url',
            __('Server URL', 'bitcore-core'),
            [$this, 'render_text_field'],
            $this->page_slug,
            'bitcore_eliza_section_connection',
            ['field' => 'bitcore_eliza_server_url', 'description' => 'ElizaOS API base URL (e.g., http://172.17.0.1:3000/api)']
        );

        add_settings_field(
            'bitcore_eliza_auth_token',
            __('Auth Token', 'bitcore-core'),
            [$this, 'render_text_field'],
            $this->page_slug,
            'bitcore_eliza_section_connection',
            ['field' => 'bitcore_eliza_auth_token', 'type' => 'password', 'description' => 'X-API-KEY token for ElizaOS authentication']
        );

        // === Section: Rate Limiting ===
        add_settings_section(
            'bitcore_eliza_section_ratelimit',
            __('Rate Limiting & Billing', 'bitcore-core'),
            '__return_null',
            $this->page_slug
        );

        add_settings_field(
            'bitcore_eliza_daily_limit',
            __('Daily Call Limit', 'bitcore-core'),
            [$this, 'render_number_field'],
            $this->page_slug,
            'bitcore_eliza_section_ratelimit',
            ['field' => 'bitcore_eliza_daily_limit', 'min' => 1, 'max' => 10000, 'description' => 'Maximum API calls per user per day (admins bypass this limit)']
        );

        add_settings_field(
            'bitcore_eliza_x402_wallet',
            __('x402 Wallet Address', 'bitcore-core'),
            [$this, 'render_text_field'],
            $this->page_slug,
            'bitcore_eliza_section_ratelimit',
            ['field' => 'bitcore_eliza_x402_wallet', 'description' => 'Crypto wallet address for receiving x402 payments. Leave empty to disable payment header.']
        );

        add_settings_field(
            'bitcore_eliza_anonymous_access',
            __('Enable Anonymous x402 Access', 'bitcore-core'),
            [$this, 'render_checkbox_field'],
            $this->page_slug,
            'bitcore_eliza_section_ratelimit',
            ['field' => 'bitcore_eliza_anonymous_access', 'description' => 'Allow unauthenticated users to access chat/sessions endpoints with a valid X-Payment header']
        );

        // === Section: Agent Configuration ===
        add_settings_section(
            'bitcore_eliza_section_agents',
            __('Agent Configuration', 'bitcore-core'),
            '__return_null',
            $this->page_slug
        );

        add_settings_field(
            'bitcore_eliza_default_agent',
            __('Default Agent', 'bitcore-core'),
            [$this, 'render_agent_dropdown'],
            $this->page_slug,
            'bitcore_eliza_section_agents',
            ['field' => 'bitcore_eliza_default_agent', 'description' => 'Agent assigned to new users on registration']
        );

        add_settings_field(
            'bitcore_eliza_agent_aria',
            __('Aria Agent ID', 'bitcore-core'),
            [$this, 'render_text_field'],
            $this->page_slug,
            'bitcore_eliza_section_agents',
            ['field' => 'bitcore_eliza_agent_aria', 'description' => 'ElizaOS UUID for Aria (analytical, research-focused)']
        );

        add_settings_field(
            'bitcore_eliza_agent_forge',
            __('Forge Agent ID', 'bitcore-core'),
            [$this, 'render_text_field'],
            $this->page_slug,
            'bitcore_eliza_section_agents',
            ['field' => 'bitcore_eliza_agent_forge', 'description' => 'ElizaOS UUID for Forge (practical, action-oriented)']
        );

        add_settings_field(
            'bitcore_eliza_agent_muse',
            __('Muse Agent ID', 'bitcore-core'),
            [$this, 'render_text_field'],
            $this->page_slug,
            'bitcore_eliza_section_agents',
            ['field' => 'bitcore_eliza_agent_muse', 'description' => 'ElizaOS UUID for Muse (creative, exploratory)']
        );
    }

    /**
     * Add settings page as submenu under Settings.
     */
    public function add_settings_page(): void {
        add_submenu_page(
            'options-general.php',
            __('ElizaOS Settings', 'bitcore-core'),
            __('ElizaOS', 'bitcore-core'),
            'manage_options',
            $this->page_slug,
            [$this, 'render_settings_page']
        );
    }

    /**
     * Render the settings page.
     */
    public function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('Access denied.', 'bitcore-core'));
        }
        ?>
        <div class="wrap">
            <h1>🤖 ElizaOS Settings</h1>
            <p>Configure the ElizaOS integration: server connection, rate limiting, x402 billing, and agent configuration.</p>

            <form method="post" action="options.php">
                <?php
                settings_fields($this->option_group);
                do_settings_sections($this->page_slug);
                submit_button();
                ?>
            </form>

            <hr>

            <h2>📊 Connection Status</h2>
            <?php
            $server_url = get_option('bitcore_eliza_server_url', 'http://172.17.0.1:3000/api');
            $auth_token = get_option('bitcore_eliza_auth_token', 'bitcore-eliza-os-auth-token-2026');

            // Quick health check
            $response = wp_remote_get(
                rtrim($server_url, '/') . '/server/health',
                [
                    'headers' => ['X-API-KEY' => $auth_token],
                    'timeout' => 5,
                ]
            );

            if (is_wp_error($response)) {
                echo '<div class="notice notice-error"><p><strong>Connection Failed:</strong> ' . esc_html($response->get_error_message()) . '</p></div>';
            } else {
                $code = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);

                if ($code >= 200 && $code < 300) {
                    $status = $data['status'] ?? 'unknown';
                    echo '<div class="notice notice-success"><p><strong>Connected:</strong> ElizaOS server is reachable. Status: <code>' . esc_html($status) . '</code></p></div>';
                } else {
                    echo '<div class="notice notice-warning"><p><strong>Unexpected Response:</strong> HTTP ' . esc_html((string) $code) . '</p></div>';
                }
            }
            ?>

            <h2>📋 Agent IDs</h2>
            <table class="widefat striped">
                <thead>
                    <tr><th>Agent</th><th>Name</th><th>UUID</th><th>Style</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td>🧠</td>
                        <td><strong>Aria</strong></td>
                        <td><code><?php echo esc_html(get_option('bitcore_eliza_agent_aria', 'eac677dd-94c4-02ab-bfea-bc554e52b2a3')); ?></code></td>
                        <td>Analytical, precise, research-focused</td>
                    </tr>
                    <tr>
                        <td>🔨</td>
                        <td><strong>Forge</strong></td>
                        <td><code><?php echo esc_html(get_option('bitcore_eliza_agent_forge', '3ab11b75-ca36-0cee-a3d7-7bfcab3c27cd')); ?></code></td>
                        <td>Practical, action-oriented, builder</td>
                    </tr>
                    <tr>
                        <td>🎨</td>
                        <td><strong>Muse</strong></td>
                        <td><code><?php echo esc_html(get_option('bitcore_eliza_agent_muse', 'cf0dee2b-9fc6-041b-83a0-90a644d26362')); ?></code></td>
                        <td>Creative, exploratory, strategist</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    // =========================================================================
    // Field Renderers
    // =========================================================================

    /**
     * Render a text input field.
     */
    public function render_text_field(array $args): void {
        $field = $args['field'] ?? '';
        $type  = $args['type'] ?? 'text';
        $desc  = $args['description'] ?? '';
        $value = get_option($field, '');

        printf(
            '<input type="%s" name="%s" value="%s" class="regular-text" />',
            esc_attr($type),
            esc_attr($field),
            esc_attr($value)
        );

        if ($desc) {
            printf('<p class="description">%s</p>', esc_html($desc));
        }
    }

    /**
     * Render a number input field.
     */
    public function render_number_field(array $args): void {
        $field = $args['field'] ?? '';
        $min   = $args['min'] ?? 0;
        $max   = $args['max'] ?? 99999;
        $desc  = $args['description'] ?? '';
        $value = get_option($field, 100);

        printf(
            '<input type="number" name="%s" value="%s" min="%d" max="%d" class="small-text" />',
            esc_attr($field),
            esc_attr((string) $value),
            $min,
            $max
        );

        if ($desc) {
            printf('<p class="description">%s</p>', esc_html($desc));
        }
    }

    /**
     * Render a checkbox field.
     */
    public function render_checkbox_field(array $args): void {
        $field = $args['field'] ?? '';
        $desc  = $args['description'] ?? '';
        $value = get_option($field, false);

        printf(
            '<label><input type="checkbox" name="%s" value="1" %s /> %s</label>',
            esc_attr($field),
            checked($value, true, false),
            esc_html($desc)
        );
    }

    /**
     * Render agent dropdown field.
     */
    public function render_agent_dropdown(array $args): void {
        $field = $args['field'] ?? '';
        $desc  = $args['description'] ?? '';
        $value = get_option($field, 'aria');

        $agents = [
            'aria'  => '🧠 Aria — Analytical, precise, research-focused',
            'forge' => '🔨 Forge — Practical, action-oriented, builder',
            'muse'  => '🎨 Muse — Creative, exploratory, strategist',
        ];

        printf('<select name="%s">', esc_attr($field));
        foreach ($agents as $slug => $label) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($slug),
                selected($value, $slug, false),
                esc_html($label)
            );
        }
        echo '</select>';

        if ($desc) {
            printf('<p class="description">%s</p>', esc_html($desc));
        }
    }

    /**
     * Sanitize checkbox value.
     */
    public function sanitize_checkbox($value): bool {
        return !empty($value);
    }
}
