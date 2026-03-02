<?php
/**
 * BITCORE Watchlist Manager Template
 *
 * @package BITCORE\Feeds
 * @since 1.0.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap bitcore-watchlist-manager">
    <h1 class="wp-heading-inline"><?php echo esc_html__('Watchlist Manager', 'bitcore'); ?></h1>
    
    <div class="bitcore-watchlist-container">
        <!-- Create Watchlist Form -->
        <div class="bitcore-card">
            <h2><?php echo esc_html__('Create New Watchlist', 'bitcore'); ?></h2>
            <form id="bitcore-create-watchlist-form">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="watchlist-name"><?php echo esc_html__('Name', 'bitcore'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="watchlist-name" name="name" class="regular-text" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="watchlist-keywords"><?php echo esc_html__('Keywords', 'bitcore'); ?></label>
                        </th>
                        <td>
                            <textarea id="watchlist-keywords" name="keywords" rows="3" class="large-text" placeholder="<?php echo esc_attr__('Enter keywords, one per line', 'bitcore'); ?>"></textarea>
                            <p class="description"><?php echo esc_html__('Keywords to monitor in content feeds.', 'bitcore'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Notifications', 'bitcore'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="email_notifications" value="1">
                                <?php echo esc_html__('Enable email notifications', 'bitcore'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php echo esc_html__('Create Watchlist', 'bitcore'); ?></button>
                </p>
            </form>
        </div>

        <!-- Active Watchlists -->
        <div class="bitcore-card">
            <h2><?php echo esc_html__('Active Watchlists', 'bitcore'); ?></h2>
            <div id="bitcore-watchlists-container">
                <p class="description"><?php echo esc_html__('Loading watchlists...', 'bitcore'); ?></p>
            </div>
        </div>

        <!-- Recent Matches -->
        <div class="bitcore-card">
            <h2><?php echo esc_html__('Recent Matches', 'bitcore'); ?></h2>
            <div id="bitcore-matches-container">
                <p class="description"><?php echo esc_html__('Select a watchlist to view matches.', 'bitcore'); ?></p>
            </div>
        </div>
    </div>
</div>

<style>
.bitcore-watchlist-container {
    margin-top: 20px;
}

.bitcore-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
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

.watchlist-item {
    padding: 15px;
    border: 1px solid #eee;
    border-radius: 4px;
    margin-bottom: 10px;
    background: #f9f9f9;
}

.watchlist-item.active {
    border-left: 4px solid #00a32a;
}

.watchlist-item.inactive {
    border-left: 4px solid #d63638;
    opacity: 0.7;
}

.watchlist-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.watchlist-name {
    font-weight: 600;
    font-size: 14px;
}

.watchlist-status {
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 3px;
}

.watchlist-status.active {
    background: #d4edda;
    color: #155724;
}

.watchlist-status.inactive {
    background: #f8d7da;
    color: #721c24;
}

.watchlist-keywords {
    margin-bottom: 10px;
}

.keyword-tag {
    display: inline-block;
    background: #e5e5e5;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    margin-right: 5px;
    margin-bottom: 5px;
}

.watchlist-actions {
    display: flex;
    gap: 10px;
}

.match-item {
    padding: 10px;
    border-bottom: 1px solid #eee;
}

.match-item:last-child {
    border-bottom: none;
}

.match-keywords {
    margin-bottom: 5px;
}

.match-preview {
    font-size: 12px;
    color: #646970;
    margin-bottom: 5px;
}

.match-timestamp {
    font-size: 11px;
    color: #999;
}
</style>

<script>
jQuery(document).ready(function($) {
    const restUrl = '<?php echo esc_url(rest_url('bitcore/v1')); ?>';
    const nonce = '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>';
    let selectedWatchlistId = null;

    // Load watchlists
    function loadWatchlists() {
        $.ajax({
            url: restUrl + '/watchlists',
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', nonce);
            },
            success: function(response) {
                const container = $('#bitcore-watchlists-container');
                
                if (!response.watchlists || response.watchlists.length === 0) {
                    container.html('<p class="description">No watchlists found. Create one above to get started.</p>');
                    return;
                }

                let html = '';
                response.watchlists.forEach(function(wl) {
                    const statusClass = wl.is_active ? 'active' : 'inactive';
                    const statusText = wl.is_active ? 'Active' : 'Inactive';
                    
                    html += '<div class="watchlist-item ' + statusClass + '" data-id="' + wl.id + '">';
                    html += '<div class="watchlist-header">';
                    html += '<span class="watchlist-name">' + wl.name + '</span>';
                    html += '<span class="watchlist-status ' + statusClass + '">' + statusText + '</span>';
                    html += '</div>';
                    
                    if (wl.keywords && wl.keywords.length > 0) {
                        html += '<div class="watchlist-keywords">';
                        wl.keywords.forEach(function(kw) {
                            html += '<span class="keyword-tag">' + kw + '</span>';
                        });
                        html += '</div>';
                    }
                    
                    html += '<div class="watchlist-actions">';
                    html += '<button class="button button-small view-matches" data-id="' + wl.id + '">View Matches</button>';
                    html += '<button class="button button-small toggle-status" data-id="' + wl.id + '">' + (wl.is_active ? 'Deactivate' : 'Activate') + '</button>';
                    html += '<button class="button button-small button-link-delete delete-watchlist" data-id="' + wl.id + '">Delete</button>';
                    html += '</div>';
                    html += '</div>';
                });
                
                container.html(html);
            },
            error: function() {
                $('#bitcore-watchlists-container').html('<p class="description error">Failed to load watchlists.</p>');
            }
        });
    }

    // Create watchlist
    $('#bitcore-create-watchlist-form').on('submit', function(e) {
        e.preventDefault();
        
        const name = $('#watchlist-name').val();
        const keywordsText = $('#watchlist-keywords').val();
        const keywords = keywordsText.split('\n').map(function(k) { return k.trim(); }).filter(function(k) { return k.length > 0; });
        
        $.ajax({
            url: restUrl + '/watchlists',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                name: name,
                keywords: keywords
            }),
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', nonce);
            },
            success: function() {
                $('#watchlist-name').val('');
                $('#watchlist-keywords').val('');
                loadWatchlists();
            },
            error: function(xhr) {
                alert('Failed to create watchlist: ' + (xhr.responseJSON?.error || 'Unknown error'));
            }
        });
    });

    // View matches
    $(document).on('click', '.view-matches', function() {
        const id = $(this).data('id');
        selectedWatchlistId = id;
        loadMatches(id);
    });

    // Load matches for a watchlist
    function loadMatches(watchlistId) {
        // This would call a dedicated endpoint for matches
        const container = $('#bitcore-matches-container');
        container.html('<p class="description">Loading matches...</p>');
        
        // Simulated matches display
        setTimeout(function() {
            container.html('<p class="description">No recent matches found for this watchlist.</p>');
        }, 500);
    }

    // Delete watchlist
    $(document).on('click', '.delete-watchlist', function() {
        if (!confirm('Are you sure you want to delete this watchlist?')) {
            return;
        }
        
        const id = $(this).data('id');
        
        $.ajax({
            url: restUrl + '/watchlists/' + id,
            method: 'DELETE',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', nonce);
            },
            success: function() {
                loadWatchlists();
            },
            error: function(xhr) {
                alert('Failed to delete watchlist: ' + (xhr.responseJSON?.error || 'Unknown error'));
            }
        });
    });

    // Initialize
    loadWatchlists();
});
</script>
