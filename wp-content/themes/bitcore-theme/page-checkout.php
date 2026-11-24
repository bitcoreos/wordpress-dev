<?php
/**
 * Template Name: Task Checkout
 * Paperclip Integration - Exclusive Execution Status
 * @package    Bitcore\Theme
 * @since      2026.11.23
 * @version    1.0.0
 * @author     coraline
 */
get_header();
?>
<div class="checkout-container">
    <header class="page-header">
        <h1>🔒 Task Checkout</h1>
        <p class="subtitle">Exclusive execution locks</p>
    </header>
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-value accent" id="locked-tasks">--</div>
            <div class="stat-label">Locked</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" id="available-tasks">--</div>
            <div class="stat-label">Available</div>
        </div>
        <div class="stat-card">
            <div class="stat-value warning" id="stale-locks">--</div>
            <div class="stat-label">Stale Locks</div>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <span class="card-title">Checkout Status</span>
            <button class="btn btn-sm" id="refresh-checkout">⟳ Refresh</button>
        </div>
        <div class="card-body">
            <div id="checkout-list" class="checkout-list">
                <div class="empty-state">Loading checkout status...</div>
            </div>
        </div>
    </div>
</div>
<?php get_footer(); ?>
