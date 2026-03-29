<?php
declare(strict_types=1);
/**
 * BITCORE Org Chart Admin Page
 * Paperclip Integration - Agent Hierarchy Management
 */

if (!defined('ABSPATH')) exit;

?>
<div class="wrap">
    <h1><span class="dashicons dashicons-groups"></span> Org Chart - Agent Hierarchy</h1>
    
    <div class="bitcore-admin-content">
        <p class="description">Manage agent reporting relationships and organizational structure.</p>
        
        <div id="bitcore-org-chart-container">
            <h2>Current Hierarchy</h2>
            <div id="org-tree" data-endpoint="<?php echo rest_url('bitcore/v1/org-chart'); ?>" data-nonce="<?php echo wp_create_nonce('wp_rest'); ?>">
                <p><em>Loading org chart...</em></p>
            </div>
            
            <h3>Add Agent to Org Chart</h3>
            <form id="org-chart-form">
                <table class="form-table">
                    <tr>
                        <th><label for="agent_name">Agent Name</label></th>
                        <td><input type="text" id="agent_name" name="agent_name" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="agent_role">Role</label></th>
                        <td><input type="text" id="agent_role" name="agent_role" class="regular-text" placeholder="e.g., Nexus Node, Inference Engine"></td>
                    </tr>
                    <tr>
                        <th><label for="reports_to">Reports To</label></th>
                        <td><input type="text" id="reports_to" name="reports_to" class="regular-text" placeholder="Parent agent name"></td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary">Add Agent</button>
                </p>
            </form>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var restUrl = '<?php echo rest_url("bitcore/v1/org-chart"); ?>';
    var nonce = '<?php echo wp_create_nonce("wp_rest"); ?>';
    
    // Load org chart
    $.get(restUrl, function(data) {
        if (data && data.hierarchy) {
            var html = '<ul class="org-tree">';
            data.hierarchy.forEach(function(agent) {
                html += '<li><strong>' + agent.name + '</strong> - ' + agent.role;
                if (agent.reports_to) {
                    html += ' <em>(reports to ' + agent.reports_to + ')</em>';
                }
                html += '</li>';
            });
            html += '</ul>';
            $('#org-tree').html(html);
        } else {
            $('#org-tree').html('<p>No agents in org chart yet.</p>');
        }
    }).fail(function() {
        $('#org-tree').html('<p class="error">Failed to load org chart. Ensure REST API is accessible.</p>');
    });
});
</script>
