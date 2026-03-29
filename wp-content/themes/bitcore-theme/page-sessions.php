<?php
/**
 * Template Name: Agent Sessions
 * Paperclip Integration - Heartbeat Session History
 */
get_header();
?>
<div class="sessions-container">
    <header class="page-header">
        <h1>💓 Agent Sessions</h1>
        <p class="subtitle">Heartbeat session history</p>
    </header>
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-value accent" id="active-sessions">--</div>
            <div class="stat-label">Active Sessions</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" id="total-heartbeats">--</div>
            <div class="stat-label">Heartbeats Today</div>
        </div>
        <div class="stat-card">
            <div class="stat-value secondary" id="avg-response">--</div>
            <div class="stat-label">Avg Response (ms)</div>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <span class="card-title">Session Timeline</span>
            <button class="btn btn-sm" id="refresh-sessions">⟳ Refresh</button>
        </div>
        <div class="card-body">
            <div id="session-list" class="session-list">
                <div class="empty-state">Loading sessions...</div>
            </div>
        </div>
    </div>
</div>
<?php get_footer(); ?>
