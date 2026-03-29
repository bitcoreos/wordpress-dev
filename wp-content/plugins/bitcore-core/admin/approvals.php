<?php
declare(strict_types=1);
/**
 * BITCORE Approvals Admin Page
 * Paperclip Integration - Approval Queue Management
 */

if (!defined('ABSPATH')) exit;

?>
<div class="wrap">
    <h1><span class="dashicons dashicons-yes-alt"></span> Approvals Queue</h1>
    
    <div class="bitcore-admin-content">
        <p class="description">Manage pending approval requests and governance workflows.</p>
        
        <div id="bitcore-approvals-container">
            <h2>Pending Approvals</h2>
            <div id="approvals-queue" data-endpoint="<?php echo rest_url('bitcore/v1/approvals'); ?>" data-nonce="<?php echo wp_create_nonce('wp_rest'); ?>">
                <p><em>Loading approvals queue...</em></p>
            </div>
            
            <h3>Request Approval</h3>
            <form id="approval-form">
                <table class="form-table">
                    <tr>
                        <th><label for="request_type">Request Type</label></th>
                        <td>
                            <select id="request_type" name="request_type">
                                <option value="config_change">Config Change</option>
                                <option value="budget_override">Budget Override</option>
                                <option value="agent_action">Agent Action</option>
                                <option value="deployment">Deployment</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="requester">Requester</label></th>
                        <td><input type="text" id="requester" name="requester" class="regular-text" placeholder="Agent or user name"></td>
                    </tr>
                    <tr>
                        <th><label for="description">Description</label></th>
                        <td><textarea id="description" name="description" class="large-text" rows="3" placeholder="What needs approval?"></textarea></td>
                    </tr>
                    <tr>
                        <th><label for="impact">Impact Level</label></th>
                        <td>
                            <select id="impact" name="impact">
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary">Submit Request</button>
                </p>
            </form>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var restUrl = '<?php echo rest_url("bitcore/v1/approvals"); ?>';
    
    $.get(restUrl, function(data) {
        if (data && data.approvals && data.approvals.length > 0) {
            var html = '<table class="widefat"><thead><tr><th>ID</th><th>Type</th><th>Requester</th><th>Impact</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
            data.approvals.forEach(function(a) {
                html += '<tr>';
                html += '<td>' + a.id + '</td>';
                html += '<td>' + a.request_type + '</td>';
                html += '<td>' + a.requester + '</td>';
                html += '<td>' + a.impact + '</td>';
                html += '<td>' + a.status + '</td>';
                html += '<td>';
                if (a.status === 'pending') {
                    html += '<button class="button approve-btn" data-id="' + a.id + '">Approve</button> ';
                    html += '<button class="button reject-btn" data-id="' + a.id + '">Reject</button>';
                }
                html += '</td>';
                html += '</tr>';
            });
            html += '</tbody></table>';
            $('#approvals-queue').html(html);
        } else {
            $('#approvals-queue').html('<p>No pending approvals.</p>');
        }
    }).fail(function() {
        $('#approvals-queue').html('<p class="error">Failed to load approvals queue.</p>');
    });
});
</script>
