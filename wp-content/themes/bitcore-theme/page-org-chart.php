<?php
/**
 * Template Name: Org Chart
 * Paperclip Integration - Agent Hierarchy Visualization
 * @package    Bitcore\Theme
 * @since      2026.11.23
 * @version    1.0.0
 * @author     coraline
 */
get_header();
?>
<div class="org-chart-container">
    <header class="page-header">
        <h1>🏛️ Agent Org Chart</h1>
        <p class="subtitle">Visual hierarchy of the swarm</p>
    </header>
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-value" id="total-agents">--</div>
            <div class="stat-label">Total Agents</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" id="active-agents">--</div>
            <div class="stat-label">Active</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" id="teams-count">--</div>
            <div class="stat-label">Teams</div>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <span class="card-title">Organization Structure</span>
            <button class="btn btn-sm" id="refresh-org">⟳ Refresh</button>
        </div>
        <div class="card-body">
            <div id="org-tree" class="org-tree">
                <div class="empty-state">Loading organization...</div>
            </div>
        </div>
    </div>
</div>
<?php get_footer(); ?>
