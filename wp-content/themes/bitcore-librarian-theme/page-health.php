<?php
/**
 * Template Name: Health
 * Description: System health monitoring and status dashboard
 *
 * @package BitCore
 * @since 1.2.0
 */
defined('ABSPATH') || exit;
get_header();
?>
<main id="health-page" class="bitcore-page">
    <header class="page-header">
        <h1>System Health</h1>
        <p class="subtitle">Health Monitoring & Status</p>
    </header>
    <section class="content-grid">
        <div class="card status-card">
            <h2>System Status</h2>
            <div id="health-status" class="status-indicator">Checking...</div>
            <div id="health-details" class="health-grid">Loading...</div>
        </div>
        <div class="card">
            <h2>Actions</h2>
            <div class="action-buttons">
                <button id="refresh-health" class="btn btn-primary">Refresh Status</button>
                <button id="health-refresh-api" class="btn">Refresh from API</button>
            </div>
            <div id="last-refresh" class="meta-text">Last checked: Never</div>
        </div>
    </section>
    <section class="content-grid">
        <div class="card full-width">
            <h2>Health Log</h2>
            <div id="health-log" class="log-container">No health checks recorded</div>
        </div>
    </section>
</main>
<script>
const API = '/wp-json/bitcore/v1/health';
let healthLog = [];
function updateTimestamp() {
    const now = new Date().toLocaleString();
    document.getElementById('last-refresh').textContent = `Last checked: ${now}`;
}
function addToLog(status, details) {
    const entry = { time: new Date().toLocaleString(), status, details };
    healthLog.unshift(entry);
    if (healthLog.length > 20) healthLog.pop();
    const html = healthLog.map(e => `
        <div class="log-entry ${e.status}">
            <span class="log-time">${e.time}</span>
            <span class="log-status">${e.status}</span>
            <span class="log-details">${JSON.stringify(e.details).substring(0, 100)}</span>
        </div>
    `).join('');
    document.getElementById('health-log').innerHTML = html;
}
async function checkHealth() {
    try {
        const res = await fetch(API);
        const data = await res.json();
        const status = data.status || 'unknown';
        const statusEl = document.getElementById('health-status');
        statusEl.textContent = status.toUpperCase();
        statusEl.className = `status-indicator status-${status}`;
        const detailsHtml = Object.entries(data).filter(([k]) => k !== 'status').map(([key, val]) => `
            <div class="health-item">
                <span class="health-label">${key}</span>
                <span class="health-value">${typeof val === 'object' ? JSON.stringify(val) : val}</span>
            </div>
        `).join('');
        document.getElementById('health-details').innerHTML = detailsHtml;
        updateTimestamp();
        addToLog(status, data);
    } catch (err) {
        document.getElementById('health-status').textContent = 'ERROR';
        document.getElementById('health-status').className = 'status-indicator status-error';
        document.getElementById('health-details').innerHTML = `<p class="error">${err.message}</p>`;
        addToLog('error', { error: err.message });
    }
}
async function refreshFromApi() {
    try {
        await fetch(`${API}/refresh`, { method: 'POST' });
        checkHealth();
    } catch (err) {
        alert('Failed to refresh: ' + err.message);
    }
}
document.getElementById('refresh-health').addEventListener('click', checkHealth);
document.getElementById('health-refresh-api').addEventListener('click', refreshFromApi);
document.addEventListener('DOMContentLoaded', checkHealth);
</script>
<?php get_footer(); ?>
