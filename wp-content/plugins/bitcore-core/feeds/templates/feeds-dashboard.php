<?php
/**
 * feeds dashboard
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
 * BITCORE Feeds Dashboard Template
 *
 * @package BITCORE\Feeds
 * @since 1.0.0
 */

?>

<div class="wrap bitcore-feeds-dashboard">
    <h1 class="wp-heading-inline"><?php echo esc_html__('BITCORE Feeds Dashboard', 'bitcore'); ?></h1>
    
    <div class="bitcore-dashboard-grid">
        <!-- Trends Section -->
        <div class="bitcore-card bitcore-card-trends">
            <h2><?php echo esc_html__('Trending Topics', 'bitcore'); ?></h2>
            <div id="bitcore-trends-container">
                <p class="description"><?php echo esc_html__('Loading trends...', 'bitcore'); ?></p>
            </div>
        </div>

        <!-- Sources Section -->
        <div class="bitcore-card bitcore-card-sources">
            <h2><?php echo esc_html__('Content Sources', 'bitcore'); ?></h2>
            <div class="bitcore-sources-list">
                <div class="source-item">
                    <span class="source-name">BITwiki</span>
                    <span class="source-status active"><?php echo esc_html__('Active', 'bitcore'); ?></span>
                </div>
                <div class="source-item">
                    <span class="source-name">BIThub</span>
                    <span class="source-status active"><?php echo esc_html__('Active', 'bitcore'); ?></span>
                </div>
                <div class="source-item">
                    <span class="source-name">RSS Feeds</span>
                    <span class="source-status active"><?php echo esc_html__('Active', 'bitcore'); ?></span>
                </div>
            </div>
        </div>

        <!-- Feed Preview Section -->
        <div class="bitcore-card bitcore-card-preview">
            <h2><?php echo esc_html__('Feed Preview', 'bitcore'); ?></h2>
            <div class="bitcore-feed-actions">
                <button class="button" id="bitcore-refresh-feed">
                    <?php echo esc_html__('Refresh Feed', 'bitcore'); ?>
                </button>
                <select id="bitcore-feed-format">
                    <option value="json">JSON</option>
                    <option value="rss">RSS</option>
                    <option value="atom">Atom</option>
                </select>
            </div>
            <div id="bitcore-feed-preview">
                <p class="description"><?php echo esc_html__('Click refresh to load feed.', 'bitcore'); ?></p>
            </div>
        </div>

        <!-- Statistics Section -->
        <div class="bitcore-card bitcore-card-stats">
            <h2><?php echo esc_html__('Statistics', 'bitcore'); ?></h2>
            <div class="bitcore-stats-grid">
                <div class="stat-item">
                    <span class="stat-value" id="stat-total-items">-</span>
                    <span class="stat-label"><?php echo esc_html__('Total Items', 'bitcore'); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-value" id="stat-watchlists">-</span>
                    <span class="stat-label"><?php echo esc_html__('Watchlists', 'bitcore'); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-value" id="stat-workspaces">-</span>
                    <span class="stat-label"><?php echo esc_html__('RAG Workspaces', 'bitcore'); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-value" id="stat-trends">-</span>
                    <span class="stat-label"><?php echo esc_html__('Trending Topics', 'bitcore'); ?></span>
                </div>
            </div>
        </div>

        <!-- RAG Workspaces Section -->
        <div class="bitcore-card bitcore-card-rag">
            <h2><?php echo esc_html__('RAG Workspaces', 'bitcore'); ?></h2>
            <div class="bitcore-rag-actions">
                <button class="button" id="bitcore-create-workspace">
                    <?php echo esc_html__('New Workspace', 'bitcore'); ?>
                </button>
            </div>
            <div id="bitcore-workspaces-list">
                <p class="description"><?php echo esc_html__('Loading workspaces...', 'bitcore'); ?></p>
            </div>
        </div>

        <!-- Quick Actions Section -->
        <div class="bitcore-card bitcore-card-actions">
            <h2><?php echo esc_html__('Quick Actions', 'bitcore'); ?></h2>
            <div class="bitcore-quick-actions">
                <button class="button" id="bitcore-cluster-content">
                    <?php echo esc_html__('Cluster Content', 'bitcore'); ?>
                </button>
                <button class="button" id="bitcore-deduplicate">
                    <?php echo esc_html__('Deduplicate Items', 'bitcore'); ?>
                </button>
                <button class="button" id="bitcore-clear-cache">
                    <?php echo esc_html__('Clear Cache', 'bitcore'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.bitcore-dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.bitcore-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}

.bitcore-card h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
    font-size: 14px;
    text-transform: uppercase;
    color: #646970;
}

.bitcore-stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

.stat-item {
    text-align: center;
    padding: 10px;
    background: #f6f7f7;
    border-radius: 4px;
}

.stat-value {
    display: block;
    font-size: 24px;
    font-weight: 600;
    color: #2271b1;
}

.stat-label {
    font-size: 11px;
    color: #646970;
}

.source-item {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #eee;
}

.source-item:last-child {
    border-bottom: none;
}

.source-status.active {
    color: #00a32a;
    font-weight: 500;
}

.bitcore-feed-actions {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
}

.bitcore-quick-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.bitcore-quick-actions .button {
    text-align: left;
}
</style>

<script>
jQuery(document).ready(function($) {
    const restUrl = '<?php echo esc_url(rest_url('bitcore/v1')); ?>';
    const nonce = '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>';

    // Load trends
    function loadTrends() {
        $.ajax({
            url: restUrl + '/feeds/trends',
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', nonce);
            },
            success: function(response) {
                const container = $('#bitcore-trends-container');
                if (response.trending && response.trending.length > 0) {
                    let html = '<ul class="trends-list">';
                    response.trending.slice(0, 10).forEach(function(item) {
                        html += '<li><span class="trend-term">' + item.term + '</span>';
                        html += '<span class="trend-velocity">' + item.velocity.toFixed(1) + 'x</span></li>';
                    });
                    html += '</ul>';
                    container.html(html);
                    $('#stat-trends').text(response.trending.length);
                } else {
                    container.html('<p class="description">No trending topics found.</p>');
                }
            },
            error: function() {
                $('#bitcore-trends-container').html('<p class="description error">Failed to load trends.</p>');
            }
        });
    }

    // Load workspaces
    function loadWorkspaces() {
        $.ajax({
            url: restUrl + '/rag/workspaces',
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', nonce);
            },
            success: function(response) {
                const container = $('#bitcore-workspaces-list');
                if (response.workspaces && response.workspaces.length > 0) {
                    let html = '<ul class="workspaces-list">';
                    response.workspaces.forEach(function(ws) {
                        html += '<li><strong>' + ws.name + '</strong>';
                        html += ' <span class="workspace-count">(' + ws.document_count + ' docs)</span></li>';
                    });
                    html += '</ul>';
                    container.html(html);
                    $('#stat-workspaces').text(response.workspaces.length);
                } else {
                    container.html('<p class="description">No workspaces found. Create one to get started.</p>');
                }
            }
        });
    }

    // Load watchlist count
    function loadWatchlistCount() {
        $.ajax({
            url: restUrl + '/watchlists',
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', nonce);
            },
            success: function(response) {
                $('#stat-watchlists').text(response.watchlists ? response.watchlists.length : 0);
            }
        });
    }

    // Load feed preview
    $('#bitcore-refresh-feed').on('click', function() {
        const format = $('#bitcore-feed-format').val();
        const btn = $(this);
        btn.prop('disabled', true).text('Loading...');
        
        $.ajax({
            url: restUrl + '/feeds',
            method: 'GET',
            data: { format: format },
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', nonce);
            },
            success: function(response) {
                const preview = $('#bitcore-feed-preview');
                if (format === 'json') {
                    preview.html('<pre>' + JSON.stringify(JSON.parse(response.content), null, 2).substring(0, 2000) + '...</pre>');
                } else {
                    preview.html('<pre>' + response.content.substring(0, 2000) + '...</pre>');
                }
                $('#stat-total-items').text(response.count);
            },
            complete: function() {
                btn.prop('disabled', false).text('Refresh Feed');
            }
        });
    });

    // Initialize
    loadTrends();
    loadWorkspaces();
    loadWatchlistCount();
});
</script>
