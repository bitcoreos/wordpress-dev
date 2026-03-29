<?php
declare(strict_types=1);
/**
 * BITCORE Goals Admin Page
 * Paperclip Integration - Goal Hierarchy Management
 */

if (!defined('ABSPATH')) exit;

?>
<div class="wrap">
    <h1><span class="dashicons dashicons-flag"></span> Goals Hierarchy</h1>
    
    <div class="bitcore-admin-content">
        <p class="description">Manage Mission → Objective → Task hierarchy for goal alignment.</p>
        
        <div id="bitcore-goals-container">
            <h2>Goal Tree</h2>
            <div id="goals-tree" data-endpoint="<?php echo rest_url('bitcore/v1/goals'); ?>" data-nonce="<?php echo wp_create_nonce('wp_rest'); ?>">
                <p><em>Loading goals...</em></p>
            </div>
            
            <h3>Create Goal</h3>
            <form id="goal-form">
                <table class="form-table">
                    <tr>
                        <th><label for="goal_title">Title</label></th>
                        <td><input type="text" id="goal_title" name="goal_title" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="goal_level">Level</label></th>
                        <td>
                            <select id="goal_level" name="goal_level">
                                <option value="mission">Mission (Top Level)</option>
                                <option value="objective">Objective (Mid Level)</option>
                                <option value="task">Task (Bottom Level)</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="parent_goal">Parent Goal</label></th>
                        <td><input type="text" id="parent_goal" name="parent_goal" class="regular-text" placeholder="Parent goal ID (for objectives/tasks)"></td>
                    </tr>
                    <tr>
                        <th><label for="goal_description">Description</label></th>
                        <td><textarea id="goal_description" name="goal_description" class="large-text" rows="3"></textarea></td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary">Create Goal</button>
                </p>
            </form>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var restUrl = '<?php echo rest_url("bitcore/v1/goals"); ?>';
    
    $.get(restUrl, function(data) {
        if (data && data.goals) {
            var html = '<ul class="goal-tree">';
            data.goals.forEach(function(g) {
                var levelIcon = g.level === 'mission' ? '🎯' : g.level === 'objective' ? '📌' : '✅';
                html += '<li>' + levelIcon + ' <strong>' + g.title + '</strong> <em>(' + g.level + ')</em>';
                if (g.description) html += '<br><small>' + g.description + '</small>';
                html += '</li>';
            });
            html += '</ul>';
            $('#goals-tree').html(html);
        } else {
            $('#goals-tree').html('<p>No goals defined yet.</p>');
        }
    }).fail(function() {
        $('#goals-tree').html('<p class="error">Failed to load goals.</p>');
    });
});
</script>
