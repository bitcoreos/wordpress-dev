<?php
declare(strict_types=1);
/**
 * BITCORE Settings Page
 * Complete configuration for all BITCORE components
 */

// Handle form submission
if (isset($_POST['bitcore_settings_save']) && check_admin_referer('bitcore_settings_nonce')) {
    $settings_fields = [
        'bitcore_eliza_url', 'bitcore_callback_secret',
        'bitcore_n8n_webhook_url', 'bitcore_n8n_api_key',
        'bitcore_bithub_api_url', 'bitcore_bithub_api_key',
        'bitcore_bitwiki_api_url', 'bitcore_bitwiki_api_key',
        'bitcore_default_model', 'bitcore_max_tokens',
        'bitcore_enable_chat', 'bitcore_enable_knowledge_graph',
        'bitcore_enable_context', 'bitcore_enable_tasks'
    ];
    foreach ($settings_fields as $field) {
        if (isset($_POST[$field])) {
            update_option($field, sanitize_text_field($_POST[$field]));
        }
    }
    echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
}

$eliza_url = get_option('bitcore_eliza_url', 'http://127.0.0.1:3000');
?>
<div class="wrap bitcore-admin">
    <h1>⚙️ BITCORE Settings</h1>
    
    <form method="post">
        <?php wp_nonce_field('bitcore_settings_nonce'); ?>
        
        <div class="settings-grid">
            <!-- ElizaOS Agent -->
            <div class="settings-card">
                <h2>🤖 ElizaOS Agent</h2>
                <p class="description">ElizaOS runs as the agent runtime. Configure the server URL.</p>
                <table class="form-table">
                    <tr>
                        <th>Server URL</th>
                        <td>
                            <input type="url" name="bitcore_eliza_url" value="<?php echo esc_attr($eliza_url); ?>" class="regular-text">
                            <p class="description">Default: <code>http://127.0.0.1:3000</code></p>
                        </td>
                    </tr>
                    <tr>
                        <th>Callback Secret</th>
                        <td>
                            <input type="text" name="bitcore_callback_secret" value="<?php echo esc_attr(get_option('bitcore_callback_secret')); ?>" class="regular-text">
                            <p class="description">Shared secret for ElizaOS callback authentication</p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- n8n Integration -->
            <div class="settings-card">
                <h2>🔄 n8n Webhook</h2>
                <p class="description">Connect to n8n automation workflows.</p>
                <table class="form-table">
                    <tr>
                        <th>Webhook URL</th>
                        <td><input type="url" name="bitcore_n8n_webhook_url" value="<?php echo esc_attr(get_option('bitcore_n8n_webhook_url')); ?>" class="regular-text" placeholder="https://n8n.example.com/webhook/..."></td>
                    </tr>
                    <tr>
                        <th>API Key</th>
                        <td><input type="text" name="bitcore_n8n_api_key" value="<?php echo esc_attr(get_option('bitcore_n8n_api_key')); ?>" class="regular-text"></td>
                    </tr>
                </table>
            </div>
            
            <!-- BIThub -->
            <div class="settings-card">
                <h2>🌐 BIThub API</h2>
                <p class="description">Connect to BIThub community platform.</p>
                <table class="form-table">
                    <tr>
                        <th>API URL</th>
                        <td><input type="url" name="bitcore_bithub_api_url" value="<?php echo esc_attr(get_option('bitcore_bithub_api_url')); ?>" class="regular-text" placeholder="https://bithub.bitwiki.org/api"></td>
                    </tr>
                    <tr>
                        <th>API Key</th>
                        <td><input type="text" name="bitcore_bithub_api_key" value="<?php echo esc_attr(get_option('bitcore_bithub_api_key')); ?>" class="regular-text"></td>
                    </tr>
                </table>
            </div>
            
            <!-- BITwiki -->
            <div class="settings-card">
                <h2>📚 BITwiki API</h2>
                <p class="description">Connect to BITwiki knowledge base.</p>
                <table class="form-table">
                    <tr>
                        <th>API URL</th>
                        <td><input type="url" name="bitcore_bitwiki_api_url" value="<?php echo esc_attr(get_option('bitcore_bitwiki_api_url')); ?>" class="regular-text" placeholder="https://bitwiki.org/api.php"></td>
                    </tr>
                    <tr>
                        <th>API Key</th>
                        <td><input type="text" name="bitcore_bitwiki_api_key" value="<?php echo esc_attr(get_option('bitcore_bitwiki_api_key')); ?>" class="regular-text"></td>
                    </tr>
                </table>
            </div>
            
            <!-- Default Model -->
            <div class="settings-card">
                <h2>🧠 Default Model</h2>
                <table class="form-table">
                    <tr>
                        <th>Default Model</th>
                        <td>
                            <select name="bitcore_default_model">
                                <?php 
                                $models = get_option('bitcore_models', []);
                                $current = get_option('bitcore_default_model');
                                foreach ($models as $key => $m): ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($current, $key); ?>><?php echo esc_html($m['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <a href="<?php echo admin_url('admin.php?page=bitcore-models'); ?>">Manage models</a>
                        </td>
                    </tr>
                    <tr>
                        <th>Max Tokens</th>
                        <td><input type="number" name="bitcore_max_tokens" value="<?php echo esc_attr(get_option('bitcore_max_tokens', 4096)); ?>" class="small-text"></td>
                    </tr>
                </table>
            </div>
            
            <!-- Module Toggles -->
            <div class="settings-card">
                <h2>📦 Modules</h2>
                <table class="form-table">
                    <tr>
                        <th>Chat API</th>
                        <td><label><input type="checkbox" name="bitcore_enable_chat" value="1" <?php checked(get_option('bitcore_enable_chat', 1)); ?>> Enable /eliza/chat endpoint</label></td>
                    </tr>
                    <tr>
                        <th>Knowledge Graph</th>
                        <td><label><input type="checkbox" name="bitcore_enable_knowledge_graph" value="1" <?php checked(get_option('bitcore_enable_knowledge_graph', 1)); ?>> Enable knowledge graph storage</label></td>
                    </tr>
                    <tr>
                        <th>Context API</th>
                        <td><label><input type="checkbox" name="bitcore_enable_context" value="1" <?php checked(get_option('bitcore_enable_context', 1)); ?>> Enable session context management</label></td>
                    </tr>
                    <tr>
                        <th>Tasks API</th>
                        <td><label><input type="checkbox" name="bitcore_enable_tasks" value="1" <?php checked(get_option('bitcore_enable_tasks', 1)); ?>> Enable async task processing</label></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <p class="submit">
            <input type="submit" name="bitcore_settings_save" class="button button-primary" value="Save Settings">
        </p>
    </form>
</div>

<style>
.settings-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-top: 20px; }
.settings-card { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; }
.settings-card h2 { margin-top: 0; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; color: #646970; }
.form-table th { width: 120px; }
.form-table input[type="text"], .form-table input[type="url"] { width: 100%; max-width: 350px; }
</style>
