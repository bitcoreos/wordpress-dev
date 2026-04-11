<?php
declare(strict_types=1);
/**
 * BITCORE Agents & Models - Unified Management
 */

// Handle model actions
if (isset($_POST['bitcore_action']) && check_admin_referer('bitcore_agents_models_nonce')) {
    $action = sanitize_text_field($_POST['bitcore_action']);
    $models = get_option('bitcore_models', []);
    
    if ($action === 'save_model' && isset($_POST['model_key'])) {
        $key = sanitize_text_field($_POST['model_key']);
        $models[$key] = [
            'name' => sanitize_text_field($_POST['name']),
            'provider' => sanitize_text_field($_POST['provider']),
            'model_id' => sanitize_text_field($_POST['model_id']),
            'endpoint_url' => esc_url_raw($_POST['endpoint_url']),
            'max_tokens' => absint($_POST['max_tokens']),
            'visibility' => sanitize_text_field($_POST['visibility']),
        ];
        update_option('bitcore_models', $models);
        echo '<div class="notice notice-success is-dismissible"><p>Model saved.</p></div>';
    }
    
    if ($action === 'delete_model' && isset($_POST['model_key'])) {
        $key = sanitize_text_field($_POST['model_key']);
        unset($models[$key]);
        update_option('bitcore_models', $models);
        echo '<div class="notice notice-success is-dismissible"><p>Model deleted.</p></div>';
    }
}

$hermes_url = get_option('bitcore_hermes_url', 'http://172.17.0.1:8787');
$hermes_health = wp_remote_get($hermes_url . '/health', ['timeout' => 3]);
$hermes_data = !is_wp_error($hermes_health) ? json_decode(wp_remote_retrieve_body($hermes_health), true) : null;
$coraline_url = 'http://64.23.131.221:50081/';
$coraline_health = wp_remote_get($coraline_url, ['timeout' => 3]);
$hyperion_url = 'http://64.227.106.173:8081/';
$local_health = wp_remote_get(rest_url('bitcore/v1/health'), ['timeout' => 3]);
$models = get_option('bitcore_models', []);
?>
<style>
.bitcore-tabs { margin: 20px 0 10px 0; border-bottom: 1px solid #ccc; }
.bitcore-tabs .tab { 
    display: inline-block; padding: 8px 16px; margin-right: 4px;
    background: #f0f0f1; border: 1px solid #ccc; border-bottom: none;
    text-decoration: none; color: #555; border-radius: 4px 4px 0 0;
}
.bitcore-tabs .tab:hover { background: #e0e0e0; }
.bitcore-tabs .tab.active { background: #fff; color: #0073aa; font-weight: 600; border-bottom: 1px solid #fff; margin-bottom: -1px; }
.tab-content { display: none; padding: 20px 0; }
.tab-content.active { display: block; }
.agent-card { transition: box-shadow 0.2s; }
.agent-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
</style>
<div class="wrap bitcore-admin">
    <h1>Agents & Models</h1>
    <p class="description">Unified management for all task executors.</p>
    
    <div class="bitcore-tabs">
        <a href="#agents" class="tab active">Agents</a>
        <a href="#models" class="tab">Models</a>
    </div>
    
    <div id="agents" class="tab-content active">
        <h2>System Agents</h2>
        <div class="agents-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
            <div class="agent-card" style="background: #fff; border: 1px solid #ccc; border-radius: 8px; padding: 20px;">
                <div style="display: flex; align-items: center; margin-bottom: 15px;">
                    <div style="width: 50px; height: 50px; background: #9b59b6; border-radius: 50%; margin-right: 15px;"></div>
                    <div><h3 style="margin: 0;">Hermes</h3><span style="color: #666;">Guardian / Coordinator</span></div>
                </div>
                <table style="width: 100%; font-size: 13px;">
                    <tr><td style="padding: 4px 0; color: #666;">Status:</td><td><?php echo $hermes_data ? '<span style="color: green;">Active</span>' : '<span style="color: red;">Offline</span>'; ?></td></tr>
                    <tr><td style="padding: 4px 0; color: #666;">Endpoint:</td><td><code style="font-size: 11px;"><?php echo esc_html($hermes_url); ?></code></td></tr>
                </table>
            </div>
            <div class="agent-card" style="background: #fff; border: 1px solid #ccc; border-radius: 8px; padding: 20px;">
                <div style="display: flex; align-items: center; margin-bottom: 15px;">
                    <div style="width: 50px; height: 50px; background: #e74c3c; border-radius: 50%; margin-right: 15px;"></div>
                    <div><h3 style="margin: 0;">Coraline</h3><span style="color: #666;">Inference Node</span></div>
                </div>
                <table style="width: 100%; font-size: 13px;">
                    <tr><td style="padding: 4px 0; color: #666;">Status:</td><td><?php echo !is_wp_error($coraline_health) ? '<span style="color: green;">Active</span>' : '<span style="color: red;">Offline</span>'; ?></td></tr>
                    <tr><td style="padding: 4px 0; color: #666;">Endpoint:</td><td><code style="font-size: 11px;"><?php echo esc_html($coraline_url); ?></code></td></tr>
                </table>
            </div>
            <div class="agent-card" style="background: #fff; border: 1px solid #ccc; border-radius: 8px; padding: 20px;">
                <div style="display: flex; align-items: center; margin-bottom: 15px;">
                    <div style="width: 50px; height: 50px; background: #f39c12; border-radius: 50%; margin-right: 15px;"></div>
                    <div><h3 style="margin: 0;">Hyperion</h3><span style="color: #666;">Nexus Node</span></div>
                </div>
                <table style="width: 100%; font-size: 13px;">
                    <tr><td style="padding: 4px 0; color: #666;">Status:</td><td><?php echo !is_wp_error($local_health) ? '<span style="color: green;">Active</span>' : '<span style="color: red;">Offline</span>'; ?></td></tr>
                    <tr><td style="padding: 4px 0; color: #666;">Endpoint:</td><td><code style="font-size: 11px;"><?php echo esc_html($hyperion_url); ?></code></td></tr>
                </table>
            </div>
        </div>
    </div>
    
    <div id="models" class="tab-content">
        <h2>AI Models</h2>
        <div style="margin-top: 20px;">
            <h3>Add/Edit Model</h3>
            <form method="post" action="">
                <?php wp_nonce_field('bitcore_agents_models_nonce'); ?>
                <input type="hidden" name="bitcore_action" value="save_model">
                <table class="form-table">
                    <tr><th><label for="model_key">Key</label></th><td><input type="text" id="model_key" name="model_key" class="regular-text" required></td></tr>
                    <tr><th><label for="name">Display Name</label></th><td><input type="text" id="name" name="name" class="regular-text"></td></tr>
                    <tr><th><label for="provider">Provider</label></th><td>
                        <select id="provider" name="provider">
                            <option value="openai">OpenAI</option>
                            <option value="anthropic">Anthropic</option>
                            <option value="google">Google</option>
                            <option value="venice">Venice</option>
                            <option value="custom">Custom</option>
                        </select>
                    </td></tr>
                    <tr><th><label for="model_id">Model ID</label></th><td><input type="text" id="model_id" name="model_id" class="regular-text"></td></tr>
                    <tr><th><label for="endpoint_url">Endpoint URL</label></th><td><input type="url" id="endpoint_url" name="endpoint_url" class="regular-text"></td></tr>
                    <tr><th><label for="max_tokens">Max Tokens</label></th><td><input type="number" id="max_tokens" name="max_tokens" class="small-text" value="4096"></td></tr>
                </table>
                <p class="submit"><button type="submit" class="button button-primary">Save Model</button></p>
            </form>
        </div>
        <div style="margin-top: 30px;">
            <h3>Configured Models</h3>
            <?php if (empty($models)): ?>
                <p>No models configured yet.</p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr><th>Key</th><th>Name</th><th>Provider</th><th>Model ID</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($models as $key => $m): ?>
                        <tr>
                            <td><code><?php echo esc_html($key); ?></code></td>
                            <td><?php echo esc_html($m['name'] ?? ''); ?></td>
                            <td><?php echo esc_html($m['provider'] ?? ''); ?></td>
                            <td><?php echo esc_html($m['model_id'] ?? ''); ?></td>
                            <td>
                                <form method="post" action="" style="display:inline;">
                                    <?php wp_nonce_field('bitcore_agents_models_nonce'); ?>
                                    <input type="hidden" name="bitcore_action" value="delete_model">
                                    <input type="hidden" name="model_key" value="<?php echo esc_attr($key); ?>">
                                    <button type="submit" class="button button-small" onclick="return confirm('Delete this model?');">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
jQuery(document).ready(function($) {
    $('.bitcore-tabs .tab').on('click', function(e) {
        e.preventDefault();
        var target = $(this).attr('href');
        $('.bitcore-tabs .tab').removeClass('active');
        $(this).addClass('active');
        $('.tab-content').removeClass('active').hide();
        $(target).addClass('active').show();
    });
});
</script>
