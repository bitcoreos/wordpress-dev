<?php
/**
 * Template Name: Activity
 * Description: Activity feed, digest, stats, and aggregate tracking
 *
 * @package BitCore
 * @since 1.2.0
 */
defined('ABSPATH') || exit;
get_header();
?>
<main id="activity-page" class="bitcore-page">
    <header class="page-header">
        <h1>Activity</h1>
        <p class="subtitle">Activity Feed & Analytics</p>
    </header>
    <section class="content-grid three-col">
        <div class="card">
            <h2>Live Feed</h2>
            <div id="activity-feed" class="feed-container">Loading...</div>
            <button id="refresh-feed" class="btn btn-sm">Refresh</button>
        </div>
        <div class="card">
            <h2>Digest</h2>
            <div id="activity-digest" class="digest-container">Loading...</div>
        </div>
        <div class="card">
            <h2>Statistics</h2>
            <div id="activity-stats" class="stats-grid">Loading...</div>
        </div>
    </section>
    <section class="content-grid">
        <div class="card full-width">
            <h2>Aggregate Activity</h2>
            <form id="aggregate-form" class="inline-form">
                <input type="date" id="start-date" placeholder="Start Date">
                <input type="date" id="end-date" placeholder="End Date">
                <button type="submit" class="btn btn-primary">Generate</button>
            </form>
            <div id="aggregate-results" class="aggregate-container"></div>
        </div>
    </section>
</main>
<script>
const API = '/wp-json/bitcore/v1/activity';
async function loadFeed() {
    try {
        const res = await fetch(`${API}/feed`);
        const data = await res.json();
        const html = (data.feed || data || []).slice(0, 20).map(item => `
            <div class="feed-item">
                <span class="timestamp">${item.timestamp || item.created_at || ''}</span>
                <span class="action">${item.action || item.type || ''}</span>
                <span class="details">${item.details || item.message || ''}</span>
            </div>
        `).join('');
        document.getElementById('activity-feed').innerHTML = html || '<p class="empty-state">No activity yet</p>';
    } catch (err) {
        document.getElementById('activity-feed').innerHTML = '<p class="error">Failed to load feed</p>';
    }
}
async function loadDigest() {
    try {
        const res = await fetch(`${API}/digest`);
        const data = await res.json();
        document.getElementById('activity-digest').innerHTML = `<pre>${JSON.stringify(data, null, 2)}</pre>`;
    } catch (err) {
        document.getElementById('activity-digest').innerHTML = '<p class="error">Failed to load digest</p>';
    }
}
async function loadStats() {
    try {
        const res = await fetch(`${API}/stats`);
        const data = await res.json();
        const html = Object.entries(data.stats || data).map(([key, val]) => `
            <div class="stat-item">
                <span class="stat-label">${key}</span>
                <span class="stat-value">${val}</span>
            </div>
        `).join('');
        document.getElementById('activity-stats').innerHTML = html;
    } catch (err) {
        document.getElementById('activity-stats').innerHTML = '<p class="error">Failed to load stats</p>';
    }
}
document.getElementById('refresh-feed').addEventListener('click', loadFeed);
document.getElementById('aggregate-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const start = document.getElementById('start-date').value;
    const end = document.getElementById('end-date').value;
    const res = await fetch(`${API}/aggregate?start=${start}&end=${end}`);
    const data = await res.json();
    document.getElementById('aggregate-results').innerHTML = `<pre>${JSON.stringify(data, null, 2)}</pre>`;
});
document.addEventListener('DOMContentLoaded', () => {
    loadFeed();
    loadDigest();
    loadStats();
});
</script>
<?php get_footer(); ?>
