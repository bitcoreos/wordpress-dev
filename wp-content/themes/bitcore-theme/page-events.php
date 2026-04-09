<?php
/**
 * Template Name: SSE Events Dashboard
 * Description: Server-Sent Events stream viewer and broadcaster
 * 
 * @package BITCORE_Librarian
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>
<?php include(get_template_directory() . "/nav-header.php"); ?>

<div class="bitcore-dashboard" id="sse-events-dashboard">
    <header class="dashboard-header">
        <div class="dashboard-title">
            <div class="dashboard-icon">📡</div>
            <div>
                <h1>Event Stream</h1>
                <p style="color: var(--text-muted); margin: 0; font-size: 0.875rem;">Real-time Server-Sent Events Monitor</p>
            </div>
        </div>
        <div class="dashboard-actions">
            <span class="badge" id="connection-status" class="badge-warning">
                <span class="badge-dot"></span> Disconnected
            </span>
            <button class="btn btn-primary" id="connect-btn">Connect</button>
        </div>
    </header>

    <!-- Stats Row -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Events Received</div>
            <div class="stat-value accent" id="events-count">0</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Events/Second</div>
            <div class="stat-value secondary" id="events-rate">0</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Last Event</div>
            <div class="stat-value" id="last-event-time" style="font-size: 1rem;">--</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Uptime</div>
            <div class="stat-value warning" id="uptime">00:00</div>
        </div>
    </div>

    <!-- Main Content -->
    <div style="display: grid; grid-template-columns: 1fr 350px; gap: var(--space-xl);">
        <!-- Event Stream -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Live Event Stream</h3>
                <div style="display: flex; gap: var(--space-sm);">
                    <select class="form-input" id="event-filter" style="width: 120px; padding: 6px 12px;">
                        <option value="all">All Events</option>
                        <option value="task">Tasks</option>
                        <option value="agent">Agents</option>
                        <option value="system">System</option>
                        <option value="error">Errors</option>
                    </select>
                    <button class="btn btn-sm btn-secondary" id="clear-events">Clear</button>
                </div>
            </div>
            <div class="event-stream" id="event-stream">
                <div class="empty-state">
                    <div class="empty-state-icon">📡</div>
                    <p>Click "Connect" to start receiving events</p>
                </div>
            </div>
        </div>

        <!-- Broadcast Panel -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Broadcast Event</h3>
            </div>
            <div class="card-body">
                <form id="broadcast-form">
                    <div class="form-group">
                        <label class="form-label">Event Type</label>
                        <select class="form-input" id="broadcast-type" required>
                            <option value="notification">Notification</option>
                            <option value="task">Task Update</option>
                            <option value="agent">Agent Event</option>
                            <option value="system">System Alert</option>
                            <option value="custom">Custom</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Channel</label>
                        <input type="text" class="form-input" id="broadcast-channel" placeholder="default" value="default">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Payload (JSON)</label>
                        <textarea class="form-input" id="broadcast-payload" rows="4" placeholder='{"message": "Hello World"}'>{"message": "Hello World"}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        📡 Broadcast
                    </button>
                </form>
                <div id="broadcast-result" style="margin-top: var(--space-lg);"></div>
            </div>
        </div>
    </div>

    <!-- Event Type Distribution -->
    <div class="card" style="margin-top: var(--space-xl);">
        <div class="card-header">
            <h3 class="card-title">Event Type Distribution</h3>
        </div>
        <div class="card-body">
            <div id="event-distribution" style="display: grid; grid-template-columns: repeat(5, 1fr); gap: var(--space-md);">
                <div style="text-align: center; padding: var(--space-md); background: var(--bg-tertiary); border-radius: var(--radius-md);">
                    <div style="font-size: 1.5rem; font-weight: 700; color: var(--accent-primary);" id="count-task">0</div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">Task</div>
                </div>
                <div style="text-align: center; padding: var(--space-md); background: var(--bg-tertiary); border-radius: var(--radius-md);">
                    <div style="font-size: 1.5rem; font-weight: 700; color: var(--accent-secondary);" id="count-agent">0</div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">Agent</div>
                </div>
                <div style="text-align: center; padding: var(--space-md); background: var(--bg-tertiary); border-radius: var(--radius-md);">
                    <div style="font-size: 1.5rem; font-weight: 700; color: var(--accent-info);" id="count-system">0</div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">System</div>
                </div>
                <div style="text-align: center; padding: var(--space-md); background: var(--bg-tertiary); border-radius: var(--radius-md);">
                    <div style="font-size: 1.5rem; font-weight: 700; color: var(--accent-warning);" id="count-notification">0</div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">Notification</div>
                </div>
                <div style="text-align: center; padding: var(--space-md); background: var(--bg-tertiary); border-radius: var(--radius-md);">
                    <div style="font-size: 1.5rem; font-weight: 700; color: var(--accent-danger);" id="count-error">0</div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">Error</div>
                </div>
            </div>
        </div>
    </div>
</div>


<style>
#sse-events-dashboard .badge-dot {
    animation: none;
}
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}
</style>

<?php get_footer(); ?>
