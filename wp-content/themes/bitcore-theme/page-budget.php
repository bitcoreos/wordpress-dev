<?php
/**
 * Template Name: Budget Dashboard
 * Paperclip Integration - Per-Agent Spending & Limits
 * @package    Bitcore\Theme
 * @since      2026.11.23
 * @version    1.0.0
 * @author     coraline
 */
get_header();
?>
<div class="budget-container">
    <header class="page-header">
        <h1>💰 Budget Dashboard</h1>
        <p class="subtitle">Per-agent spending and limits</p>
    </header>
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-value accent" id="total-budget">--</div>
            <div class="stat-label">Total Budget</div>
        </div>
        <div class="stat-card">
            <div class="stat-value warning" id="total-spent">--</div>
            <div class="stat-label">Spent This Month</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" id="remaining-budget">--</div>
            <div class="stat-label">Remaining</div>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <span class="card-title">Agent Budgets</span>
            <button class="btn btn-sm" id="refresh-budget">⟳ Refresh</button>
        </div>
        <div class="card-body">
            <div id="budget-list" class="budget-list">
                <div class="empty-state">Loading budgets...</div>
            </div>
        </div>
    </div>
</div>
<?php get_footer(); ?>
