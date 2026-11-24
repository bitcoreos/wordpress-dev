<?php
/**
 * Title: Agent Grid
 * Slug: bitcore-theme/agent-grid
 * Categories: bitcore-theme
 * Description: Grid display for swarm agents.
 *
 * @package BITCORE_Theme
 * @since      2026.11.23
 * @version    1.0.0
 * @author     coraline
 */
?>
<!-- wp:group {"className":"bitcore-dashboard__agents"} -->
<div class="wp-block-group bitcore-dashboard__agents">
    <!-- wp:heading {"level":2,"className":"bitcore-section__title"} -->
    <h2 class="bitcore-section__title">Swarm Agents</h2>
    <!-- /wp:heading -->
    
    <!-- wp:group {"className":"bitcore-grid bitcore-grid--auto","layout":{"type":"grid"}} -->
    <div class="wp-block-group bitcore-grid bitcore-grid--auto" id="agent-grid">
        <!-- Agent 1: Coraline -->
        <!-- wp:group {"className":"bitcore-card bitcore-agent-card"} -->
        <div class="wp-block-group bitcore-card bitcore-agent-card">
            <div class="bitcore-agent-card__avatar">🧬</div>
            <span class="bitcore-agent-card__name">Coraline</span>
            <span class="bitcore-agent-card__role">Orchestration Core</span>
            <span class="bitcore-status bitcore-status--online">Online</span>
        </div>
        <!-- /wp:group -->
        
        <!-- Agent 2: Hyperion -->
        <!-- wp:group {"className":"bitcore-card bitcore-agent-card"} -->
        <div class="wp-block-group bitcore-card bitcore-agent-card">
            <div class="bitcore-agent-card__avatar">⚡</div>
            <span class="bitcore-agent-card__name">Hyperion</span>
            <span class="bitcore-agent-card__role">Execution Core</span>
            <span class="bitcore-status bitcore-status--online">Online</span>
        </div>
        <!-- /wp:group -->
        
        <!-- Agent 3: Researcher -->
        <!-- wp:group {"className":"bitcore-card bitcore-agent-card"} -->
        <div class="wp-block-group bitcore-card bitcore-agent-card">
            <div class="bitcore-agent-card__avatar">📚</div>
            <span class="bitcore-agent-card__name">Researcher</span>
            <span class="bitcore-agent-card__role">Knowledge Worker</span>
            <span class="bitcore-status bitcore-status--busy">Processing</span>
        </div>
        <!-- /wp:group -->
        
        <!-- Agent 4: Developer -->
        <!-- wp:group {"className":"bitcore-card bitcore-agent-card"} -->
        <div class="wp-block-group bitcore-card bitcore-agent-card">
            <div class="bitcore-agent-card__avatar">💻</div>
            <span class="bitcore-agent-card__name">Developer</span>
            <span class="bitcore-agent-card__role">Code Worker</span>
            <span class="bitcore-status bitcore-status--online">Online</span>
        </div>
        <!-- /wp:group -->
        
        <!-- Agent 5: Analyst -->
        <!-- wp:group {"className":"bitcore-card bitcore-agent-card"} -->
        <div class="wp-block-group bitcore-card bitcore-agent-card">
            <div class="bitcore-agent-card__avatar">📊</div>
            <span class="bitcore-agent-card__name">Analyst</span>
            <span class="bitcore-agent-card__role">Data Worker</span>
            <span class="bitcore-status bitcore-status--offline">Offline</span>
        </div>
        <!-- /wp:group -->
    </div>
    <!-- /wp:group -->
</div>
<!-- /wp:group -->
