<?php
/**
 * Template Name: Approvals Queue
 * Paperclip Integration - Pending Approval Requests
 */
get_header();
?>
<div class="approvals-container">
    <header class="page-header">
        <h1>✅ Approvals Queue</h1>
        <p class="subtitle">Pending governance requests</p>
    </header>
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-value warning" id="pending-approvals">--</div>
            <div class="stat-label">Pending</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" id="approved-count">--</div>
            <div class="stat-label">Approved Today</div>
        </div>
        <div class="stat-card">
            <div class="stat-value secondary" id="rejected-count">--</div>
            <div class="stat-label">Rejected Today</div>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <span class="card-title">Approval Requests</span>
            <button class="btn btn-sm" id="refresh-approvals">⟳ Refresh</button>
        </div>
        <div class="card-body">
            <div id="approval-list" class="approval-list">
                <div class="empty-state">Loading approvals...</div>
            </div>
        </div>
    </div>
</div>
<?php get_footer(); ?>
