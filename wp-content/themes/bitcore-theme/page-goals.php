<?php
/**
 * Template Name: Goals Hierarchy
 * Paperclip Integration - Mission → Objective → Task Tree
 * @package    Bitcore\Theme
 * @since      2026.11.23
 * @version    1.0.0
 * @author     coraline
 */
get_header();
?>
<div class="goals-container">
    <header class="page-header">
        <h1>🎯 Goals Hierarchy</h1>
        <p class="subtitle">Mission ancestry chain</p>
    </header>
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-value accent" id="total-missions">--</div>
            <div class="stat-label">Missions</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" id="total-objectives">--</div>
            <div class="stat-label">Objectives</div>
        </div>
        <div class="stat-card">
            <div class="stat-value secondary" id="total-goal-tasks">--</div>
            <div class="stat-label">Linked Tasks</div>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <span class="card-title">Goal Tree</span>
            <button class="btn btn-sm" id="refresh-goals">⟳ Refresh</button>
        </div>
        <div class="card-body">
            <div id="goal-tree" class="goal-tree">
                <div class="empty-state">Loading goals...</div>
            </div>
        </div>
    </div>
</div>
<?php get_footer(); ?>
