<?php
/**
 * Template Name: MCP Tools Dashboard
 * Description: MCP (Model Context Protocol) tool browser and executor interface
 * 
 * @package BITCORE_Librarian
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<div class="bitcore-dashboard" id="mcp-tools-dashboard">
    <!-- Dashboard Header -->
    <header class="dashboard-header">
        <div class="dashboard-title">
            <div class="dashboard-icon">🔧</div>
            <div>
                <h1>MCP Tools</h1>
                <p style="color: var(--text-muted); margin: 0; font-size: 0.875rem;">Model Context Protocol Tool Browser</p>
            </div>
        </div>
        <div class="dashboard-actions">
            <button class="btn btn-secondary" id="refresh-tools">
                <span>↻</span> Refresh
            </button>
        </div>
    </header>

    <!-- Stats Row -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Available Tools</div>
            <div class="stat-value accent" id="tools-count">--</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Executions Today</div>
            <div class="stat-value secondary" id="executions-count">--</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Success Rate</div>
            <div class="stat-value" id="success-rate">--</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Avg Response</div>
            <div class="stat-value warning" id="avg-response">--</div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div style="display: grid; grid-template-columns: 350px 1fr; gap: var(--space-xl);">
        <!-- Tools List Panel -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Tool Library</h3>
                <input type="search" class="form-input" id="tool-search" placeholder="Search tools..." style="width: 150px; padding: 6px 12px;">
            </div>
            <div class="card-body" style="max-height: 600px; overflow-y: auto; padding: var(--space-md);">
                <div id="tools-list">
                    <!-- Tools populated via JS -->
                    <div class="empty-state">
                        <div class="spinner" style="margin: 0 auto;"></div>
                        <p style="margin-top: var(--space-md);">Loading tools...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tool Details Panel -->
        <div class="card" id="tool-details-panel">
            <div class="card-header">
                <h3 class="card-title" id="selected-tool-name">Select a Tool</h3>
                <span class="badge badge-info" id="selected-tool-category">--</span>
            </div>
            <div class="card-body">
                <div id="tool-details-content">
                    <div class="empty-state">
                        <div class="empty-state-icon">🔧</div>
                        <p>Select a tool from the library to view details and execute</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Execution History -->
    <div class="card" style="margin-top: var(--space-xl);">
        <div class="card-header">
            <h3 class="card-title">Recent Executions</h3>
            <button class="btn btn-sm btn-secondary" id="clear-history">Clear</button>
        </div>
        <div class="card-body" style="padding: 0;">
            <table class="data-table" id="execution-history">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Tool</th>
                        <th>Status</th>
                        <th>Duration</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="history-body">
                    <tr>
                        <td colspan="5" style="text-align: center; color: var(--text-muted);">No executions yet</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>


<style>
/* MCP Tools specific overrides */
#mcp-tools-dashboard .tool-card {
    margin-bottom: var(--space-sm);
}

#mcp-tools-dashboard .tool-card.selected {
    border-color: var(--accent-primary);
    background: rgba(74,222,128,0.08);
}

#mcp-tools-dashboard .code-block pre {
    margin: 0;
    white-space: pre-wrap;
    word-break: break-word;
}
</style>

<?php get_footer(); ?>
