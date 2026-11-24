<?php
/**
 * Template Name: SSE Events Dashboard
 * Description: Server-Sent Events stream viewer and broadcaster
 * 
 * @package BITCORE_Librarian
 * @version 2.0.0
 * @since      2026.11.23
 * @author     coraline
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

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

<script>
(function() {
    const restUrl = '<?php echo rest_url("bitcore/v1"); ?>';
    let eventSource = null;
    let events = [];
    let eventCounts = { task: 0, agent: 0, system: 0, notification: 0, error: 0 };
    let startTime = null;
    let eventsInLastSecond = [];

    // Connect to SSE stream
    function connect() {
        if (eventSource) {
            eventSource.close();
        }

        const statusEl = document.getElementById('connection-status');
        statusEl.innerHTML = '<span class="badge-dot" style="animation: pulse 1s infinite;"></span> Connecting...';
        statusEl.className = 'badge badge-warning';

        eventSource = new EventSource(restUrl + '/events/stream');
        startTime = Date.now();

        eventSource.onopen = function() {
            statusEl.innerHTML = '<span class="badge-dot"></span> Connected';
            statusEl.className = 'badge badge-success';
            document.getElementById('connect-btn').textContent = 'Disconnect';
        };

        eventSource.onmessage = function(event) {
            try {
                const data = JSON.parse(event.data);
                addEvent(data);
            } catch (e) {
                addEvent({ type: 'raw', data: event.data });
            }
        };

        eventSource.onerror = function() {
            statusEl.innerHTML = '<span class="badge-dot"></span> Error';
            statusEl.className = 'badge badge-danger';
            setTimeout(() => {
                if (eventSource) connect();
            }, 5000);
        };
    }

    // Disconnect
    function disconnect() {
        if (eventSource) {
            eventSource.close();
            eventSource = null;
        }
        const statusEl = document.getElementById('connection-status');
        statusEl.innerHTML = '<span class="badge-dot"></span> Disconnected';
        statusEl.className = 'badge badge-warning';
        document.getElementById('connect-btn').textContent = 'Connect';
    }

    // Add event to stream
    function addEvent(data) {
        const type = data.type || 'unknown';
        const timestamp = new Date();
        
        events.unshift({ ...data, timestamp });
        if (events.length > 100) events.pop();
        
        eventCounts[type] = (eventCounts[type] || 0) + 1;
        eventsInLastSecond.push(timestamp);
        
        updateStream();
        updateStats();
    }

    // Update stream display
    function updateStream() {
        const filter = document.getElementById('event-filter').value;
        const filtered = filter === 'all' ? events : events.filter(e => e.type === filter);
        
        const streamEl = document.getElementById('event-stream');
        if (filtered.length === 0) {
            streamEl.innerHTML = '<div class="empty-state"><p>No events to display</p></div>';
            return;
        }

        const typeColors = {
            task: 'var(--accent-primary)',
            agent: 'var(--accent-secondary)',
            system: 'var(--accent-info)',
            notification: 'var(--accent-warning)',
            error: 'var(--accent-danger)',
            default: 'var(--text-muted)'
        };

        streamEl.innerHTML = filtered.slice(0, 50).map(event => {
            const color = typeColors[event.type] || typeColors.default;
            const time = event.timestamp.toLocaleTimeString();
            return `
                <div class="event-item">
                    <span class="event-time">${time}</span>
                    <span class="event-type" style="background: ${color}20; color: ${color};">${event.type}</span>
                    <span style="color: var(--text-secondary);">${escapeHtml(JSON.stringify(event.data || event))}</span>
                </div>
            `;
        }).join('');

        streamEl.scrollTop = 0;
    }

    // Update stats
    function updateStats() {
        document.getElementById('events-count').textContent = events.length;
        
        // Events per second
        const now = Date.now();
        eventsInLastSecond = eventsInLastSecond.filter(t => now - t.getTime() < 1000);
        document.getElementById('events-rate').textContent = eventsInLastSecond.length;
        
        // Last event time
        if (events.length > 0) {
            document.getElementById('last-event-time').textContent = events[0].timestamp.toLocaleTimeString();
        }
        
        // Uptime
        if (startTime) {
            const elapsed = Math.floor((Date.now() - startTime) / 1000);
            const mins = Math.floor(elapsed / 60).toString().padStart(2, '0');
            const secs = (elapsed % 60).toString().padStart(2, '0');
            document.getElementById('uptime').textContent = `${mins}:${secs}`;
        }

        // Update counts
        for (const [type, count] of Object.entries(eventCounts)) {
            const el = document.getElementById(`count-${type}`);
            if (el) el.textContent = count;
        }
    }

    // Broadcast event
    async function broadcast(e) {
        e.preventDefault();
        
        const type = document.getElementById('broadcast-type').value;
        const channel = document.getElementById('broadcast-channel').value || 'default';
        let payload;
        
        try {
            payload = JSON.parse(document.getElementById('broadcast-payload').value);
        } catch (err) {
            document.getElementById('broadcast-result').innerHTML = 
                '<p style="color: var(--accent-danger);">Invalid JSON payload</p>';
            return;
        }

        try {
            const response = await fetch(restUrl + '/events/broadcast', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type, channel, data: payload })
            });
            const result = await response.json();
            
            document.getElementById('broadcast-result').innerHTML = `
                <div class="badge badge-success" style="margin-bottom: var(--space-sm);">✓ Broadcast sent</div>
                <div class="code-block"><pre>${escapeHtml(JSON.stringify(result, null, 2))}</pre></div>
            `;
        } catch (err) {
            document.getElementById('broadcast-result').innerHTML = 
                `<p style="color: var(--accent-danger);">Failed: ${escapeHtml(err.message)}</p>`;
        }
    }

    // Helpers
    function escapeHtml(str) {
        if (typeof str !== 'string') return JSON.stringify(str);
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // Event listeners
    document.getElementById('connect-btn').addEventListener('click', () => {
        if (eventSource) {
            disconnect();
        } else {
            connect();
        }
    });

    document.getElementById('event-filter').addEventListener('change', updateStream);
    document.getElementById('clear-events').addEventListener('click', () => {
        events = [];
        eventCounts = { task: 0, agent: 0, system: 0, notification: 0, error: 0 };
        updateStream();
        updateStats();
    });
    document.getElementById('broadcast-form').addEventListener('submit', broadcast);

    // Update stats every second
    setInterval(updateStats, 1000);
})();
</script>

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
