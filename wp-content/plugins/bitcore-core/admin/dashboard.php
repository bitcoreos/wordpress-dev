<?php
declare(strict_types=1);
/**
 * dashboard
 *
 * @package    Bitcore
 * @since      2026.03.01
 * @version    1.3.0
 * @author     hyperion
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * BITCORE Admin Dashboard
 * Internal status checks via server-side REST calls
 */

// Server-side status checks (run on page load)
$eliza_url = get_option('bitcore_eliza_url', 'http://127.0.0.1:3000');
$eliza_status_raw = wp_remote_get($eliza_url . '/api/server/health', ['timeout' => 3]);

$bitcore_health = wp_remote_get(rest_url('bitcore/v1/health'), ['timeout' => 3]);
$bitcore_status = is_wp_error($bitcore_health) ? 'error' : (wp_remote_retrieve_response_code($bitcore_health) === 200 ? 'connected' : 'error');

$n8n_url = get_option('bitcore_n8n_webhook_url');
$n8n_status = 'not_configured';
if ($n8n_url) {
    $n8n_check = wp_remote_head($n8n_url, ['timeout' => 3]);
    $n8n_status = is_wp_error($n8n_check) ? 'error' : 'configured';
}
?>
<div class="wrap bitcore-admin">
    <h1>BITCORE Intelligence Factory</h1>
    
    <div class="bitcore-dashboard-grid">
        <div class="bitcore-card">
            <h2>🔌 System Status</h2>
            <table class="status-table">
                <tr><td>WordPress</td><td><span class="status-ok">Running</span></td></tr>
                <tr><td>BITCORE Plugin</td><td><span class="status-<?php echo $bitcore_status; ?>"><?php echo ucfirst($bitcore_status); ?></span></td></tr>
                <tr><td>ElizaOS Agent</td><td><span class="status-<?php echo (is_wp_error($eliza_status_raw) || wp_remote_retrieve_response_code($eliza_status_raw) !== 200) ? 'error' : 'connected'; ?>"><?php echo (is_wp_error($eliza_status_raw) || wp_remote_retrieve_response_code($eliza_status_raw) !== 200) ? 'Error' : 'Connected'; ?></span></td></tr>
                <tr><td>n8n Webhook</td><td><span class="status-<?php echo $n8n_status; ?>"><?php echo ucfirst(str_replace('_', ' ', $n8n_status)); ?></span></td></tr>
            </table>
        </div>
        
        <div class="bitcore-card">
            <h2>📊 Quick Stats</h2>
            <?php
            global $wpdb;
            $today_calls = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}bitcore_activity WHERE DATE(created_at) = CURDATE()");
            $total_tokens = $wpdb->get_var("SELECT COALESCE(SUM(tokens_used), 0) FROM {$wpdb->prefix}bitcore_activity WHERE DATE(created_at) = CURDATE()");
            ?>
            <table class="stats-table">
                <tr><td class="stat-num"><?php echo number_format($today_calls ?: 0); ?></td><td>API Calls Today</td></tr>
                <tr><td class="stat-num"><?php echo number_format($total_tokens ?: 0); ?></td><td>Tokens Today</td></tr>
                <tr><td class="stat-num"><?php echo count(get_option('bitcore_models', [])); ?></td><td>Models Configured</td></tr>
            </table>
        </div>
        
        <div class="bitcore-card">
            <h2>⚡ Quick Actions</h2>
            <div class="action-grid">
                <a href="<?php echo admin_url('admin.php?page=bitcore-settings'); ?>" class="button">⚙️ Settings</a>
                <a href="<?php echo admin_url('admin.php?page=bitcore-api'); ?>" class="button">🔧 API & Tools</a>
                <a href="<?php echo admin_url('admin.php?page=bitcore-models'); ?>" class="button">🧠 Models</a>
                <a href="<?php echo admin_url('admin.php?page=bitcore-internal'); ?>" class="button">🔒 Internal Agents</a>
            </div>
        </div>
        
        <div class="bitcore-card">
            <h2>📝 Recent Activity</h2>
            <?php
            $recent = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}bitcore_activity ORDER BY created_at DESC LIMIT 5");
            if ($recent): ?>
            <table class="wp-list-table">
                <tr><th>Time</th><th>Action</th><th>Status</th></tr>
                <?php foreach ($recent as $r): ?>
                <tr><td><?php echo $r->created_at; ?></td><td><?php echo esc_html($r->action_type ?? 'API'); ?></td><td><?php echo $r->status ?? 'ok'; ?></td></tr>
                <?php endforeach; ?>
            </table>
            <?php else: ?>
            <p>No recent activity logged.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<style>
.bitcore-dashboard-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
.bitcore-card { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; }
.bitcore-card h2 { margin-top: 0; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; color: #646970; }
.status-table, .stats-table { width: 100%; }
.status-table td, .stats-table td { padding: 8px 0; }
.status-ok { color: #00a32a; font-weight: 600; }
.status-error { color: #d63638; }
.status-not-configured { color: #dba617; }
.stat-num { font-size: 24px; font-weight: 600; color: #2271b1; width: 80px; }
.action-grid { display: flex; gap: 10px; flex-wrap: wrap; }
</style>
