<?php
/**
 * Template Name: Dashboard
 * Personal SaaS dashboard — my tasks, posts, agents, and activity.
 */
get_header();
?>
<main class="bitcore-dashboard">
    <div class="dashboard-header">
        <h1>Welcome, <?php echo esc_html(wp_get_current_user()->display_name); ?></h1>
        <div class="credits-badge" id="credits-badge">
            <span class="credits-icon">⚡</span>
            <span class="credits-text" id="credits-text">Loading...</span>
        </div>
    </div>
    
    <div class="dashboard-grid">
        <!-- My Tasks -->
        <section class="dashboard-card">
            <div class="card-header">
                <span class="card-icon">📋</span>
                <h2>My Tasks</h2>
                <a href="/work/" class="card-link">View all</a>
            </div>
            <ul class="task-list" id="my-tasks">
                <li>Loading...</li>
            </ul>
            <a href="/work/" class="button card-cta">Go to Work</a>
        </section>
        
        <!-- My Posts -->
        <section class="dashboard-card">
            <div class="card-header">
                <span class="card-icon">📝</span>
                <h2>My Posts</h2>
                <a href="/my-posts/" class="card-link">View all</a>
            </div>
            <ul class="post-list" id="my-posts-list">
                <li>Loading...</li>
            </ul>
            <a href="/my-posts/" class="button card-cta">New Post</a>
        </section>
        
        <!-- My Agents -->
        <section class="dashboard-card">
            <div class="card-header">
                <span class="card-icon">🤖</span>
                <h2>My Agents</h2>
                <a href="/agents/" class="card-link">Manage</a>
            </div>
            <div class="agent-list" id="my-agents">
                <div class="loading-state">Loading...</div>
            </div>
            <a href="/agents/" class="button card-cta">Go to Agents</a>
        </section>
        
        <!-- Quick Actions -->
        <section class="dashboard-card quick-actions">
            <div class="card-header">
                <span class="card-icon">⚡</span>
                <h2>Quick Actions</h2>
            </div>
            <div class="action-grid">
                <a href="/chat/" class="action-btn">
                    <span class="action-icon">💬</span>
                    <span>Start Chat</span>
                </a>
                <a href="/knowledge/" class="action-btn">
                    <span class="action-icon">📚</span>
                    <span>New Prompt</span>
                </a>
                <a href="/work/" class="action-btn">
                    <span class="action-icon">📋</span>
                    <span>New Task</span>
                </a>
                <a href="/my-posts/" class="action-btn">
                    <span class="action-icon">📝</span>
                    <span>New Post</span>
                </a>
            </div>
        </section>
        
        <!-- Recent Activity -->
        <section class="dashboard-card wide">
            <div class="card-header">
                <span class="card-icon">📊</span>
                <h2>Recent Activity</h2>
            </div>
            <ul class="activity-list" id="activity-list">
                <li>Loading...</li>
            </ul>
        </section>
    </div>
</main>

<style>
.bitcore-dashboard {
    max-width: 1400px;
    margin: 0 auto;
    padding: 24px;
    color: var(--text-primary, #f0f0f5);
}
.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 32px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--border, #2a2a3a);
}
.dashboard-header h1 {
    margin: 0;
    font-size: 28px;
    font-weight: 700;
}
.credits-badge {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background: linear-gradient(135deg, #1a1a25, #22222e);
    border: 1px solid rgba(99,102,241,0.3);
    border-radius: 8px;
    font-size: 14px;
    color: var(--text-secondary, #a0a0b0);
}
.credits-icon { font-size: 16px; }

.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
    gap: 24px;
}
.dashboard-card {
    background: linear-gradient(135deg, var(--bg-secondary, #12121a), var(--bg-tertiary, #1a1a25));
    border: 1px solid var(--border, #2a2a3a);
    border-radius: 16px;
    padding: 24px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.dashboard-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 32px rgba(0,0,0,0.3);
}
.dashboard-card.wide {
    grid-column: 1 / -1;
}
.card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}
.card-icon {
    font-size: 20px;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg-elevated, #22222e);
    border-radius: 8px;
}
.card-header h2 {
    margin: 0;
    font-size: 17px;
    font-weight: 600;
    flex: 1;
}
.card-link {
    font-size: 13px;
    color: #00d4aa;
    text-decoration: none;
}
.card-link:hover { text-decoration: underline; }

.task-list, .post-list, .activity-list {
    list-style: none;
    margin: 0 0 16px 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-height: 120px;
}
.task-list li, .post-list li, .activity-list li {
    padding: 10px 12px;
    background: var(--bg-elevated, #22222e);
    border-radius: 8px;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.task-list li .status {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}
.task-list li .status.inbox { background: #6a6a7a; }
.task-list li .status.doing { background: #3b82f6; }
.task-list li .status.done { background: #22c55e; }
.task-list li .title { flex: 1; }
.task-list li .meta {
    font-size: 11px;
    color: var(--text-muted, #6a6a7a);
}

.agent-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 16px;
    min-height: 80px;
}
.agent-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    background: var(--bg-elevated, #22222e);
    border-radius: 8px;
}
.agent-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, #00d4aa, #6366f1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
}
.agent-name { font-size: 13px; font-weight: 500; }
.agent-status {
    margin-left: auto;
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 4px;
}
.agent-status.active {
    background: rgba(34,197,94,0.2);
    color: #22c55e;
}
.agent-status.inactive {
    background: rgba(107,114,128,0.2);
    color: #6b7280;
}

.action-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}
.action-btn {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px;
    background: var(--bg-elevated, #22222e);
    border: 1px solid var(--border, #2a2a3a);
    border-radius: 10px;
    color: var(--text-primary, #f0f0f5);
    text-decoration: none;
    font-size: 14px;
    transition: border-color 0.2s ease;
}
.action-btn:hover {
    border-color: rgba(99,102,241,0.4);
}
.action-icon { font-size: 18px; }

.card-cta {
    display: inline-block;
    padding: 10px 20px;
    background: linear-gradient(135deg, #00d4aa, #6366f1);
    color: #fff;
    text-decoration: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
}
.loading-state {
    text-align: center;
    padding: 20px;
    color: var(--text-muted, #6a6a7a);
    font-size: 13px;
}
</style>

<script>
(function() {
    const config = {
        restUrl: '/wp-json/bitcore/v1/',
        nonce: '<?php echo wp_create_nonce("wp_rest"); ?>'
    };
    
    async function api(endpoint, opts = {}) {
        const url = config.restUrl + endpoint.replace(/^\//, '');
        const res = await fetch(url, {
            ...opts,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-WP-Nonce': config.nonce,
                ...(opts.headers || {})
            }
        });
        if (!res.ok) throw new Error(res.statusText);
        return res.json().catch(() => null);
    }
    
    async function loadTasks() {
        try {
            const data = await api('/kanban?limit=5');
            const list = document.getElementById('my-tasks');
            const items = Array.isArray(data) ? data : [];
            if (!items.length) { list.innerHTML = '<li>No tasks yet</li>'; return; }
            list.innerHTML = items.slice(0, 5).map(t => {
                const status = t.status || 'inbox';
                return '<li><span class="status ' + status + '"></span><span class="title">' + (t.title || 'Untitled') + '</span><span class="meta">' + status + '</span></li>';
            }).join('');
        } catch (e) {
            document.getElementById('my-tasks').innerHTML = '<li>Unable to load tasks</li>';
        }
    }
    
    async function loadPosts() {
        try {
            const data = await api('/my-posts?limit=5');
            const list = document.getElementById('my-posts-list');
            const items = (data && data.posts) ? data.posts : (Array.isArray(data) ? data : []);
            if (!items.length) { list.innerHTML = '<li>No posts yet</li>'; return; }
            list.innerHTML = items.slice(0, 5).map(p => {
                return '<li><span>' + (p.title || 'Untitled') + '</span><span class="meta">' + (p.type || 'post') + '</span></li>';
            }).join('');
        } catch (e) {
            document.getElementById('my-posts-list').innerHTML = '<li>Unable to load posts</li>';
        }
    }
    
    async function loadAgents() {
        try {
            const data = await api('/eliza/agents');
            const container = document.getElementById('my-agents');
            const items = Array.isArray(data) ? data : [];
            if (!items.length) { container.innerHTML = '<div class="loading-state">No agents configured</div>'; return; }
            container.innerHTML = items.map(a => {
                const active = a.status === 'active';
                return '<div class="agent-item"><span class="agent-avatar">' + (a.emoji || '🤖') + '</span><span class="agent-name">' + (a.name || 'Agent') + '</span><span class="agent-status ' + (active ? 'active' : 'inactive') + '">' + (active ? 'Active' : 'Idle') + '</span></div>';
            }).join('');
        } catch (e) {
            document.getElementById('my-agents').innerHTML = '<div class="loading-state">Unable to load agents</div>';
        }
    }
    
    async function loadCredits() {
        try {
            const data = await api('/budget');
            const credits = data && data.credits ? data.credits : '—';
            document.getElementById('credits-text').textContent = credits + ' credits';
        } catch (e) {
            document.getElementById('credits-text').textContent = 'Credits unavailable';
        }
    }
    
    async function loadActivity() {
        try {
            const tasks = await api('/kanban?limit=3').catch(() => []);
            const posts = await api('/my-posts?limit=3').catch(() => []);
            const list = document.getElementById('activity-list');
            const items = [];
            (Array.isArray(tasks) ? tasks : []).slice(0, 3).forEach(t => items.push('<li><span>📋</span><span>Task: ' + (t.title || 'Untitled') + ' (' + (t.status || 'inbox') + ')</span></li>'));
            (posts.posts || []).slice(0, 3).forEach(p => items.push('<li><span>📝</span><span>Post: ' + (p.title || 'Untitled') + '</span></li>'));
            list.innerHTML = items.length ? items.join('') : '<li>No recent activity</li>';
        } catch (e) {
            document.getElementById('activity-list').innerHTML = '<li>Unable to load activity</li>';
        }
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        loadTasks();
        loadPosts();
        loadAgents();
        loadCredits();
        loadActivity();
    });
})();
</script>
<?php get_footer(); ?>
