<?php
declare(strict_types=1);
/**
 * BITCORE Budget Admin Page
 * Paperclip Integration - Per-Agent Budget Enforcement
 */

if (!defined('ABSPATH')) exit;

?>
<div class="wrap">
    <h1><span class="dashicons dashicons-money-alt"></span> Budget Management</h1>
    
    <div class="bitcore-admin-content">
        <p class="description">Manage per-agent budget limits and track spending.</p>
        
        <div id="bitcore-budget-container">
            <h2>Budget Policies</h2>
            <div id="budget-policies" data-endpoint="<?php echo rest_url('bitcore/v1/budget'); ?>" data-nonce="<?php echo wp_create_nonce('wp_rest'); ?>">
                <p><em>Loading budget policies...</em></p>
            </div>
            
            <h3>Create Budget Policy</h3>
            <form id="budget-form">
                <table class="form-table">
                    <tr>
                        <th><label for="agent_id">Agent ID</label></th>
                        <td><input type="text" id="agent_id" name="agent_id" class="regular-text" required placeholder="e.g., hyperion"></td>
                    </tr>
                    <tr>
                        <th><label for="monthly_limit">Monthly Limit ($)</label></th>
                        <td><input type="number" id="monthly_limit" name="monthly_limit" class="regular-text" min="0" step="0.01" required></td>
                    </tr>
                    <tr>
                        <th><label for="enforcement_level">Enforcement Level</label></th>
                        <td>
                            <select id="enforcement_level" name="enforcement_level">
                                <option value="soft">Soft (Warning Only)</option>
                                <option value="hard">Hard (Block When Exceeded)</option>
                                <option value="throttle">Throttle (Slow Down)</option>
                            </select>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary">Create Policy</button>
                </p>
            </form>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var restUrl = '<?php echo rest_url("bitcore/v1/budget"); ?>';
    
    $.get(restUrl, function(data) {
        if (data && data.policies) {
            var html = '<table class="widefat"><thead><tr><th>Agent</th><th>Monthly Limit</th><th>Current Spend</th><th>Status</th></tr></thead><tbody>';
            data.policies.forEach(function(p) {
                var pct = (p.current_spend / p.monthly_limit * 100).toFixed(1);
                var status = pct < 80 ? '<span style="color:green">OK</span>' : pct < 95 ? '<span style="color:orange">Warning</span>' : '<span style="color:red">Critical</span>';
                html += '<tr><td>' + p.agent_id + '</td><td>$' + p.monthly_limit + '</td><td>$' + p.current_spend + ' (' + pct + '%)</td><td>' + status + '</td></tr>';
            });
            html += '</tbody></table>';
            $('#budget-policies').html(html);
        } else {
            $('#budget-policies').html('<p>No budget policies configured.</p>');
        }
    }).fail(function() {
        $('#budget-policies').html('<p class="error">Failed to load budget policies.</p>');
    });
});
</script>
