<?php
declare(strict_types=1);
/**
 * BITCORE Models Configuration
 * Simple card-based model management
 */

// Handle actions
if (isset($_POST['bitcore_action']) && check_admin_referer('bitcore_models_nonce')) {
    $action = sanitize_text_field($_POST['bitcore_action']);
    $models = get_option('bitcore_models', []);
    
    if ($action === 'save' && isset($_POST['model_key'])) {
        $key = sanitize_text_field($_POST['model_key']);
        $models[$key] = [
            'name' => sanitize_text_field($_POST['name']),
            'provider' => sanitize_text_field($_POST['provider']),
            'model_id' => sanitize_text_field($_POST['model_id']),
            'endpoint_url' => esc_url_raw($_POST['endpoint_url']),
            'max_tokens' => absint($_POST['max_tokens']),
            'visibility' => sanitize_text_field($_POST['visibility']),
            'features' => implode(',', array_map('sanitize_text_field', $_POST['features'] ?? [])),
        ];
        update_option('bitcore_models', $models);
        echo '<div class="notice notice-success is-dismissible"><p>Model saved.</p></div>';
    }
    
    if ($action === 'delete' && isset($_POST['model_key'])) {
        $key = sanitize_text_field($_POST['model_key']);
        unset($models[$key]);
        update_option('bitcore_models', $models);
        echo '<div class="notice notice-success is-dismissible"><p>Model deleted.</p></div>';
    }
    
    if ($action === 'set_default' && isset($_POST['model_key'])) {
        update_option('bitcore_default_model', sanitize_text_field($_POST['model_key']));
        echo '<div class="notice notice-success is-dismissible"><p>Default model updated.</p></div>';
    }
}

$models = get_option('bitcore_models', []);
$default_model = get_option('bitcore_default_model', '');
$edit_model = null;
$edit_key = isset($_GET['edit']) ? sanitize_text_field($_GET['edit']) : null;
if ($edit_key && isset($models[$edit_key])) {
    $edit_model = $models[$edit_key];
}
?>
<div class="wrap bitcore-models">
    <h1>🧠 Model Configuration</h1>
    <p class="description">Configure AI models for the BITCORE system. Click a model to edit, or add a new one below.</p>
    
    <?php wp_nonce_field('bitcore_models_nonce', 'bitcore_nonce'); ?>
    
    <div class="models-grid">
        <?php foreach ($models as $key => $m): 
            $is_default = ($key === $default_model);
            $features = isset($m['features']) ? (is_array($m['features']) ? $m['features'] : explode(',', $m['features'])) : [];
        ?>
        <div class="model-card <?php echo $is_default ? 'default' : ''; ?>" data-key="<?php echo esc_attr($key); ?>">
            <div class="model-header">
                <div class="model-name"><?php echo esc_html($m['name'] ?? $key); ?></div>
                <?php if ($is_default): ?><span class="default-badge">DEFAULT</span><?php endif; ?>
            </div>
            <div class="model-details">
                <div class="detail-row">
                    <span class="label">Provider:</span>
                    <span class="value"><?php echo esc_html($m['provider'] ?? 'unknown'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">Model ID:</span>
                    <span class="value code"><?php echo esc_html($m['model_id'] ?? ''); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">Max Tokens:</span>
                    <span class="value"><?php echo number_format($m['max_tokens'] ?? 4096); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">Visibility:</span>
                    <span class="value vis-<?php echo esc_attr($m['visibility'] ?? 'user'); ?>"><?php echo esc_html(ucfirst($m['visibility'] ?? 'user')); ?></span>
                </div>
                <?php if (!empty($features)): ?>
                <div class="detail-row features">
                    <span class="label">Features:</span>
                    <span class="value"><?php foreach ($features as $f): ?><span class="feature-tag"><?php echo esc_html(trim($f)); ?></span><?php endforeach; ?></span>
                </div>
                <?php endif; ?>
            </div>
            <div class="model-actions">
                <a href="<?php echo add_query_arg('edit', $key); ?>" class="button button-small">Edit</a>
                <?php if (!$is_default): ?>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="bitcore_action" value="set_default">
                    <input type="hidden" name="model_key" value="<?php echo esc_attr($key); ?>">
                    <?php wp_nonce_field('bitcore_models_nonce'); ?>
                    <button type="submit" class="button button-small">Set Default</button>
                </form>
                <?php endif; ?>
                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this model?');">
                    <input type="hidden" name="bitcore_action" value="delete">
                    <input type="hidden" name="model_key" value="<?php echo esc_attr($key); ?>">
                    <?php wp_nonce_field('bitcore_models_nonce'); ?>
                    <button type="submit" class="button button-small button-link-delete">Delete</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
        
        <!-- Add New Model Card -->
        <div class="model-card add-new">
            <a href="<?php echo add_query_arg('edit', 'new'); ?>" class="add-link">
                <span class="dashicons dashicons-plus-alt2"></span>
                <span>Add New Model</span>
            </a>
        </div>
    </div>
    
    <!-- Edit/Add Form -->
    <?php if ($edit_model || isset($_GET['edit'])): 
        $is_new = ($edit_key === 'new');
    ?>
    <div class="edit-form-overlay" onclick="if(event.target===this)location.href='<?php echo remove_query_arg('edit'); ?>';"></div>
    <div class="edit-form">
        <h2><?php echo $is_new ? 'Add New Model' : 'Edit Model'; ?></h2>
        <form method="post">
            <?php wp_nonce_field('bitcore_models_nonce'); ?>
            <input type="hidden" name="bitcore_action" value="save">
            
            <table class="form-table">
                <tr>
                    <th><label for="model_key">Key</label></th>
                    <td>
                        <input type="text" id="model_key" name="model_key" value="<?php echo $is_new ? '' : esc_attr($edit_key); ?>" <?php echo $is_new ? '' : 'readonly'; ?> class="regular-text" required>
                        <p class="description">Unique identifier (e.g., venice-qwen). Cannot be changed after creation.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="name">Display Name</label></th>
                    <td><input type="text" id="name" name="name" value="<?php echo esc_attr($edit_model['name'] ?? ''); ?>" class="regular-text" required placeholder="Venice Qwen 3 235B"></td>
                </tr>
                <tr>
                    <th><label for="provider">Provider</label></th>
                    <td>
                        <select id="provider" name="provider">
                            <option value="venice" <?php selected($edit_model['provider'] ?? '', 'venice'); ?>>Venice</option>
                            <option value="openai" <?php selected($edit_model['provider'] ?? '', 'openai'); ?>>OpenAI</option>
                            <option value="anthropic" <?php selected($edit_model['provider'] ?? '', 'anthropic'); ?>>Anthropic</option>
                            <option value="mor" <?php selected($edit_model['provider'] ?? '', 'mor'); ?>>MOR</option>
                            <option value="local" <?php selected($edit_model['provider'] ?? '', 'local'); ?>>Local (Ollama)</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="model_id">Model ID</label></th>
                    <td><input type="text" id="model_id" name="model_id" value="<?php echo esc_attr($edit_model['model_id'] ?? ''); ?>" class="regular-text" required placeholder="qwen-3-235b-a14b"></td>
                </tr>
                <tr>
                    <th><label for="endpoint_url">Endpoint URL</label></th>
                    <td><input type="url" id="endpoint_url" name="endpoint_url" value="<?php echo esc_attr($edit_model['endpoint_url'] ?? ''); ?>" class="regular-text" placeholder="https://api.venice.ai/api/v1"></td>
                </tr>
                <tr>
                    <th><label for="max_tokens">Max Tokens</label></th>
                    <td><input type="number" id="max_tokens" name="max_tokens" value="<?php echo esc_attr($edit_model['max_tokens'] ?? 4096); ?>" class="small-text"></td>
                </tr>
                <tr>
                    <th><label for="visibility">Visibility</label></th>
                    <td>
                        <select id="visibility" name="visibility">
                            <option value="user" <?php selected($edit_model['visibility'] ?? 'user', 'user'); ?>>Users</option>
                            <option value="admin" <?php selected($edit_model['visibility'] ?? '', 'admin'); ?>>Admin Only</option>
                            <option value="internal" <?php selected($edit_model['visibility'] ?? '', 'internal'); ?>>Internal System</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label>Features</label></th>
                    <td>
                        <?php $selected_features = isset($edit_model['features']) ? (is_array($edit_model['features']) ? $edit_model['features'] : explode(',', $edit_model['features'])) : []; ?>
                        <label><input type="checkbox" name="features[]" value="reasoning" <?php checked(in_array('reasoning', $selected_features)); ?>> Reasoning</label><br>
                        <label><input type="checkbox" name="features[]" value="coding" <?php checked(in_array('coding', $selected_features)); ?>> Coding</label><br>
                        <label><input type="checkbox" name="features[]" value="analysis" <?php checked(in_array('analysis', $selected_features)); ?>> Analysis</label><br>
                        <label><input type="checkbox" name="features[]" value="chat" <?php checked(in_array('chat', $selected_features)); ?>> Chat</label>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" class="button button-primary"><?php echo $is_new ? 'Add Model' : 'Save Changes'; ?></button>
                <a href="<?php echo remove_query_arg('edit'); ?>" class="button">Cancel</a>
            </p>
        </form>
    </div>
    <?php endif; ?>
</div>

<style>
.models-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; margin-top: 20px; }
.model-card { background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px; transition: box-shadow 0.2s; }
.model-card:hover { box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.model-card.default { border-color: #2271b1; border-width: 2px; }
.model-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
.model-name { font-size: 16px; font-weight: 600; color: #1d2327; }
.default-badge { background: #2271b1; color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 600; }
.model-details { margin-bottom: 15px; }
.detail-row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #f0f0f1; }
.detail-row:last-child { border-bottom: none; }
.detail-row .label { color: #646970; font-size: 13px; }
.detail-row .value { font-size: 13px; }
.detail-row .value.code { font-family: monospace; background: #f6f7f7; padding: 2px 6px; border-radius: 3px; }
.vis-user { color: #00a32a; }
.vis-admin { color: #dba617; }
.vis-internal { color: #d63638; }
.feature-tag { display: inline-block; background: #e3f2fd; color: #1565c0; padding: 2px 8px; border-radius: 12px; font-size: 11px; margin-right: 4px; }
.model-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.model-card.add-new { display: flex; align-items: center; justify-content: center; min-height: 200px; border-style: dashed; }
.add-link { display: flex; flex-direction: column; align-items: center; text-decoration: none; color: #2271b1; }
.add-link .dashicons { font-size: 32px; width: 32px; height: 32px; margin-bottom: 8px; }
.edit-form-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 100000; }
.edit-form { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #fff; border-radius: 8px; padding: 30px; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto; z-index: 100001; box-shadow: 0 5px 30px rgba(0,0,0,0.3); }
.edit-form h2 { margin-top: 0; }
</style>
