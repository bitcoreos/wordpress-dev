<?php
/**
 * Title: Dashboard Stat Cards
 * Slug: bitcore-theme/stat-cards
 * Categories: bitcore-theme
 * Description: Four stat cards with icons for dashboard metrics.
 *
 * @package BITCORE_Theme
 * @since      2026.11.23
 * @version    1.0.0
 * @author     coraline
 */
?>
<!-- wp:group {"className":"bitcore-dashboard__stats","layout":{"type":"flex","flexWrap":"wrap"}} -->
<div class="wp-block-group bitcore-dashboard__stats">
    <!-- wp:group {"className":"bitcore-card bitcore-stat-card","layout":{"type":"flex","flexWrap":"nowrap"}} -->
    <div class="wp-block-group bitcore-card bitcore-stat-card">
        <div class="bitcore-stat-card__icon bitcore-stat-card__icon--primary">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
        </div>
        <div class="bitcore-stat-card__content">
            <span class="bitcore-card__title">Active Tasks</span>
            <span class="bitcore-card__value" id="stat-active-tasks">12</span>
        </div>
    </div>
    <!-- /wp:group -->
    
    <!-- wp:group {"className":"bitcore-card bitcore-stat-card","layout":{"type":"flex","flexWrap":"nowrap"}} -->
    <div class="wp-block-group bitcore-card bitcore-stat-card">
        <div class="bitcore-stat-card__icon bitcore-stat-card__icon--success">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <div class="bitcore-stat-card__content">
            <span class="bitcore-card__title">Completed</span>
            <span class="bitcore-card__value" id="stat-completed">248</span>
        </div>
    </div>
    <!-- /wp:group -->
    
    <!-- wp:group {"className":"bitcore-card bitcore-stat-card","layout":{"type":"flex","flexWrap":"nowrap"}} -->
    <div class="wp-block-group bitcore-card bitcore-stat-card">
        <div class="bitcore-stat-card__icon bitcore-stat-card__icon--warning">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        </div>
        <div class="bitcore-stat-card__content">
            <span class="bitcore-card__title">Pending</span>
            <span class="bitcore-card__value" id="stat-pending">7</span>
        </div>
    </div>
    <!-- /wp:group -->
    
    <!-- wp:group {"className":"bitcore-card bitcore-stat-card","layout":{"type":"flex","flexWrap":"nowrap"}} -->
    <div class="wp-block-group bitcore-card bitcore-stat-card">
        <div class="bitcore-stat-card__icon bitcore-stat-card__icon--accent">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div class="bitcore-stat-card__content">
            <span class="bitcore-card__title">Agents Online</span>
            <span class="bitcore-card__value" id="stat-agents">5</span>
        </div>
    </div>
    <!-- /wp:group -->
</div>
<!-- /wp:group -->
