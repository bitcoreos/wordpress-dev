<?php
/**
 * Template: Queue Status
 * Read-only view of research queue
 * NO API credentials - UI only
 * @package     Bitcore
 * @since       1.0.0
 * @version     1.0.0
 */
?>
<div class="bitcore-queue-container">
    
    <div class="bitcore-queue-header">
        <h2>Research Queue</h2>
        <span class="bitcore-queue-badge">3 active</span>
    </div>
    
    <div class="bitcore-queue-list">
        
        <!-- Item: In Progress -->
        <div class="bitcore-queue-item bitcore-status-active">
            <div class="bitcore-queue-icon">🔄</div>
            <div class="bitcore-queue-content">
                <h4>Agent workflow patterns</h4>
                <p class="bitcore-queue-meta">
                    <span class="bitcore-scope">Deep</span>
                    <span class="bitcore-agent">Coraline + 3 subordinates</span>
                </p>
                <div class="bitcore-progress">
                    <div class="bitcore-progress-bar" style="width: 45%"></div>
                </div>
                <p class="bitcore-status-text">Synthesizing findings...</p>
            </div>
            <div class="bitcore-queue-time">12 min</div>
        </div>
        
        <!-- Item: Queued -->
        <div class="bitcore-queue-item bitcore-status-queued">
            <div class="bitcore-queue-icon">⏳</div>
            <div class="bitcore-queue-content">
                <h4>Lightning Network governance</h4>
                <p class="bitcore-queue-meta">
                    <span class="bitcore-scope">Quick</span>
                    <span class="bitcore-position">Position 2</span>
                </p>
            </div>
            <div class="bitcore-queue-time">—</div>
        </div>
        
        <!-- Item: Complete -->
        <div class="bitcore-queue-item bitcore-status-complete">
            <div class="bitcore-queue-icon">✓</div>
            <div class="bitcore-queue-content">
                <h4>Bitcoin mining centralization</h4>
                <p class="bitcore-queue-meta">
                    <span class="bitcore-scope">Deep</span>
                    <span class="bitcore-confidence">Confidence: 0.87</span>
                </p>
            </div>
            <div class="bitcore-queue-actions">
                <a href="https://bitwiki.org" target="_blank" class="bitcore-view-link">View →</a>
            </div>
        </div>
        
    </div>
    
    <div class="bitcore-queue-footer">
        <p>Completed research is published to <a href="https://bitwiki.org" target="_blank">bitwiki.org</a></p>
    </div>
    
</div>
