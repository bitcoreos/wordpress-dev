<?php
/**
 * BITCORE Admin Settings Page
 */
?>
<div class="wrap bitcore-admin">
    <h1>BITCORE Settings</h1>

    <form method="post" action="options.php">
        <?php settings_fields('bitcore_options'); ?>

        <div class="bitcore-settings-grid">
            <!-- n8n Settings -->
            <div class="bitcore-card">
                <h2>n8n Configuration</h2>
                <table class="form-table">
                    <tr>
                        <th><label for="bitcore_n8n_webhook_url">Webhook URL</label></th>
                        <td>
                            <input type="url" id="bitcore_n8n_webhook_url" name="bitcore_options[n8n_webhook_url]" 
                                   value="<?php echo esc_attr(get_option('bitcore_n8n_webhook_url')); ?>" class="regular-text">
                            <p class="description">n8n webhook endpoint for intent submission</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="bitcore_n8n_api_key">API Key</label></th>
                        <td>
                            <input type="password" id="bitcore_n8n_api_key" name="bitcore_options[n8n_api_key]" 
                                   value="<?php echo esc_attr(get_option('bitcore_n8n_api_key')); ?>" class="regular-text">
                        </td>
                    </tr>
                </table>
            </div>

            <!-- BIThub Settings -->
            <div class="bitcore-card">
                <h2>BIThub Configuration</h2>
                <table class="form-table">
                    <tr>
                        <th><label for="bitcore_bithub_api_url">API URL</label></th>
                        <td>
                            <input type="url" id="bitcore_bithub_api_url" name="bitcore_options[bithub_api_url]" 
                                   value="<?php echo esc_attr(get_option('bitcore_bithub_api_url', 'https://hub.bitwiki.org')); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="bitcore_bithub_api_key">API Key</label></th>
                        <td>
                            <input type="password" id="bitcore_bithub_api_key" name="bitcore_options[bithub_api_key]" 
                                   value="<?php echo esc_attr(get_option('bitcore_bithub_api_key')); ?>" class="regular-text">
                        </td>
                    </tr>
                </table>
            </div>

            <!-- BITwiki Settings -->
            <div class="bitcore-card">
                <h2>BITwiki Configuration</h2>
                <table class="form-table">
                    <tr>
                        <th><label for="bitcore_bitwiki_api_url">API URL</label></th>
                        <td>
                            <input type="url" id="bitcore_bitwiki_api_url" name="bitcore_options[bitwiki_api_url]" 
                                   value="<?php echo esc_attr(get_option('bitcore_bitwiki_api_url', 'https://bitwiki.org/api.php')); ?>" class="regular-text">
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Module Settings -->
            <div class="bitcore-card">
                <h2>Module Settings</h2>
                <table class="form-table">
                    <tr>
                        <th>Active Modules</th>
                        <td>
                            <label><input type="checkbox" name="bitcore_options[module_chatbot]" checked disabled> Chatbot</label><br>
                            <label><input type="checkbox" name="bitcore_options[module_discussions]" checked> Discussions</label><br>
                            <label><input type="checkbox" name="bitcore_options[module_mcp]" checked> MCP</label><br>
                            <label><input type="checkbox" name="bitcore_options[module_search]" checked> Search</label><br>
                            <label><input type="checkbox" name="bitcore_options[module_forms]" checked> Forms</label>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <?php submit_button('Save Settings'); ?>
    </form>
</div>

<style>
.bitcore-settings-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 20px;
}
</style>
