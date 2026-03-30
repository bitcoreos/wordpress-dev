<?php
/**
 * Template Name: Webhooks Dashboard
 * Description: Webhook CRUD management and trigger interface
 * 
 * @package BITCORE_Librarian
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<div class="bitcore-dashboard" id="webhooks-dashboard">
    <header class="dashboard-header">
        <div class="dashboard-title">
            <div class="dashboard-icon">🔗</div>
            <div>
                <h1>Webhooks</h1>
                <p style="color: var(--text-muted); margin: 0; font-size: 0.875rem;">Manage and Monitor Webhook Endpoints</p>
            </div>
        </div>
        <div class="dashboard-actions">
            <button class="btn btn-primary" id="create-webhook-btn">+ New Webhook</button>
        </div>
    </header>

    <!-- Stats Row -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total Webhooks</div>
            <div class="stat-value accent" id="webhooks-count">--</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Active</div>
            <div class="stat-value secondary" id="active-count">--</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Triggers Today</div>
            <div class="stat-value" id="triggers-count">--</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Success Rate</div>
            <div class="stat-value warning" id="success-rate">--</div>
        </div>
    </div>

    <!-- Main Content -->
    <div style="display: grid; grid-template-columns: 1fr 400px; gap: var(--space-xl);">
        <!-- Webhooks List -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Webhook Endpoints</h3>
                <input type="search" class="form-input" id="webhook-search" placeholder="Search..." style="width: 150px; padding: 6px 12px;">
            </div>
            <div class="card-body" style="padding: 0;">
                <table class="data-table" id="webhooks-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>URL</th>
                            <th>Events</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="webhooks-body">
                        <tr>
                            <td colspan="5" style="text-align: center; padding: var(--space-xl);">
                                <div class="spinner"></div>
                                <p>Loading webhooks...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Edit/Create Panel -->
        <div class="card" id="webhook-editor-panel">
            <div class="card-header">
                <h3 class="card-title" id="editor-title">Create Webhook</h3>
            </div>
            <div class="card-body">
                <form id="webhook-form">
                    <input type="hidden" id="webhook-id">
                    
                    <div class="form-group">
                        <label class="form-label">Name *</label>
                        <input type="text" class="form-input" id="webhook-name" required placeholder="My Webhook">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Endpoint URL *</label>
                        <input type="url" class="form-input" id="webhook-url" required placeholder="https://example.com/webhook">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Secret (optional)</label>
                        <input type="text" class="form-input" id="webhook-secret" placeholder="Signing secret">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Events</label>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-sm);">
                            <label style="display: flex; align-items: center; gap: var(--space-sm); font-size: 0.85rem;">
                                <input type="checkbox" name="events" value="task.created"> task.created
                            </label>
                            <label style="display: flex; align-items: center; gap: var(--space-sm); font-size: 0.85rem;">
                                <input type="checkbox" name="events" value="task.completed"> task.completed
                            </label>
                            <label style="display: flex; align-items: center; gap: var(--space-sm); font-size: 0.85rem;">
                                <input type="checkbox" name="events" value="agent.spawned"> agent.spawned
                            </label>
                            <label style="display: flex; align-items: center; gap: var(--space-sm); font-size: 0.85rem;">
                                <input type="checkbox" name="events" value="agent.completed"> agent.completed
                            </label>
                            <label style="display: flex; align-items: center; gap: var(--space-sm); font-size: 0.85rem;">
                                <input type="checkbox" name="events" value="system.alert"> system.alert
                            </label>
                            <label style="display: flex; align-items: center; gap: var(--space-sm); font-size: 0.85rem;">
                                <input type="checkbox" name="events" value="*"> all events
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Headers (JSON)</label>
                        <textarea class="form-input" id="webhook-headers" rows="2" placeholder='{"X-Custom": "value"}'></textarea>
                    </div>
                    
                    <div style="display: flex; gap: var(--space-md);">
                        <button type="submit" class="btn btn-primary" id="save-btn">Save Webhook</button>
                        <button type="button" class="btn btn-secondary" id="cancel-btn">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Trigger Log -->
    <div class="card" style="margin-top: var(--space-xl);">
        <div class="card-header">
            <h3 class="card-title">Recent Deliveries</h3>
            <button class="btn btn-sm btn-secondary" id="clear-log">Clear</button>
        </div>
        <div class="card-body" style="padding: 0; max-height: 400px; overflow-y: auto;">
            <table class="data-table" id="delivery-log">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Webhook</th>
                        <th>Event</th>
                        <th>Status</th>
                        <th>Response</th>
                    </tr>
                </thead>
                <tbody id="delivery-body">
                    <tr>
                        <td colspan="5" style="text-align: center; color: var(--text-muted);">No deliveries yet</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Trigger Modal -->
<div id="trigger-modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center;">
    <div class="card" style="width: 500px; max-width: 90%;">
        <div class="card-header">
            <h3 class="card-title">Trigger Webhook</h3>
            <button class="btn btn-sm btn-secondary" onclick="closeTriggerModal()">×</button>
        </div>
        <div class="card-body">
            <form id="trigger-form">
                <input type="hidden" id="trigger-webhook-id">
                <div class="form-group">
                    <label class="form-label">Event Type</label>
                    <input type="text" class="form-input" id="trigger-event" placeholder="custom.event" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Payload (JSON)</label>
                    <textarea class="form-input" id="trigger-payload" rows="5" placeholder='{"key": "value"}'></textarea>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Send Test</button>
            </form>
            <div id="trigger-result" style="margin-top: var(--space-lg);"></div>
        </div>
    </div>
</div>


<?php get_footer(); ?>
