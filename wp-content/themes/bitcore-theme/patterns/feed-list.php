<?php
/**
 * Pattern: Feed List
 *
 * @package BITCORE_Theme
 */

defined( 'ABSPATH' ) || exit;

register_block_pattern(
    'bitcore-theme/feed-list',
    array(
        'title'       => __( 'Feed List', 'bitcore-theme' ),
        'description' => __( 'Activity feed list for dashboard.', 'bitcore-theme' ),
        'categories'  => array( 'bitcore-theme' ),
        'content'     => '<!-- wp:group {"className":"bitcore-feed-container"} -->
<div class="wp-block-group bitcore-feed-container">
    <!-- wp:group {"className":"bitcore-feed__header","layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between"}} -->
    <div class="wp-block-group bitcore-feed__header">
        <!-- wp:heading {"level":2,"className":"bitcore-section__title"} -->
        <h2 class="bitcore-section__title">Activity Feed</h2>
        <!-- /wp:heading -->
        
        <!-- wp:group {"className":"bitcore-feed__filters","layout":{"type":"flex","flexWrap":"nowrap"}} -->
        <div class="wp-block-group bitcore-feed__filters">
            <button class="bitcore-btn bitcore-btn--ghost bitcore-btn--sm" data-filter="all">All</button>
            <button class="bitcore-btn bitcore-btn--ghost bitcore-btn--sm" data-filter="task">Tasks</button>
            <button class="bitcore-btn bitcore-btn--ghost bitcore-btn--sm" data-filter="agent">Agents</button>
            <button class="bitcore-btn bitcore-btn--ghost bitcore-btn--sm" data-filter="system">System</button>
        </div>
        <!-- /wp:group -->
    </div>
    <!-- /wp:group -->
    
    <!-- wp:list {"className":"bitcore-feed"} -->
    <ul class="bitcore-feed" id="activity-feed" role="feed">
        <li class="bitcore-feed__item">
            <span class="bitcore-feed__icon">✅</span>
            <span class="bitcore-feed__content">
                <strong>Task #1247</strong> completed by Developer agent
            </span>
            <span class="bitcore-feed__timestamp">2 min ago</span>
        </li>
        <li class="bitcore-feed__item">
            <span class="bitcore-feed__icon">🧬</span>
            <span class="bitcore-feed__content">
                <strong>Coraline</strong> spawned Researcher worker
            </span>
            <span class="bitcore-feed__timestamp">5 min ago</span>
        </li>
        <li class="bitcore-feed__item">
            <span class="bitcore-feed__icon">📊</span>
            <span class="bitcore-feed__content">
                <strong>Report generated</strong> for Q4 analysis
            </span>
            <span class="bitcore-feed__timestamp">12 min ago</span>
        </li>
        <li class="bitcore-feed__item">
            <span class="bitcore-feed__icon">⚡</span>
            <span class="bitcore-feed__content">
                <strong>Hyperion</strong> processing intent queue
            </span>
            <span class="bitcore-feed__timestamp">18 min ago</span>
        </li>
        <li class="bitcore-feed__item">
            <span class="bitcore-feed__icon">🔒</span>
            <span class="bitcore-feed__content">
                <strong>System</strong> security scan completed
            </span>
            <span class="bitcore-feed__timestamp">1 hour ago</span>
        </li>
    </ul>
    <!-- /wp:list -->
</div>
<!-- /wp:group -->',
    )
);
