<?php
/**
 * Template Name: Dashboard
 * Personal SaaS dashboard — my tasks, posts, agents, and activity.
 * @package bitcore-theme
 * @since 1.2.1
 */
if (!is_user_logged_in()) {
    wp_redirect(wp_login_url($_SERVER['REQUEST_URI']));
    exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_title('|', true, 'right'); ?> - Dashboard</title>
    <?php wp_head(); ?>
    <style>
        :root {
            --bg-primary: #0a0a0f;
            --bg-secondary: #12121a;
            --bg-tertiary: #1a1a25;
            --bg-elevated: #22222e;
            --text-primary: #f0f0f5;
            --text-secondary: #a0a0b0;
            --text-muted: #6a6a7a;
            --accent-primary: #00d4aa;
            --accent-secondary: #6366f1;
            --accent-warning: #f59e0b;
            --accent-danger: #ef4444;
            --border-default: #2a2a3a;
            --radius-md: 10px;
            --radius-lg: 16px;
            --space-sm: 8px;
            --space-md: 16px;
            --space-lg: 24px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
        }
        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: var(--space-lg);
        }
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border-default);
        }
        .dashboard-header h1 {
            font-size: 28px;
            font-weight: 700;
        }
        .credits-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: var(--bg-tertiary);
            border: 1px solid rgba(99,102,241,0.3);
            border-radius: 8px;
            font-size: 14px;
            color: var(--text-secondary);
        }
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
            gap: 24px;
        }
        .dashboard-card {
            background: linear-gradient(135deg, var(--bg-secondary), var(--bg-tertiary));
            border: 1px solid var(--border-default);
            border-radius: var(--radius-lg);
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
            width: 36px; height: 36px;
            display: flex; align-items: center; justify-content: center;
            background: var(--bg-elevated);
            border-radius: 8px;
        }
        .card-header h2 { margin: 0; font-size: 17px; font-weight: 600; flex: 1; }
        .card-link { font-size: 13px; color: var(--accent-primary); text-decoration: none; }
        .card-link:hover { text-decoration: underline; }
        .item-list { list-style: none; padding: 0; display: flex; flex-direction: column; gap: 8px; min-height: 120px; margin-bottom: 16px; }
        .item-list li {
            padding: 10px 12px;
            background: var(--bg-elevated);
            border-radius: 8px;
            font-size: 13px;
            display: flex; align-items: center; gap: 8px;
        }
        .status-dot {
            width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0;
        }
        .status-dot.inbox { background: var(--text-muted); }
        .status-dot.doing { background: #3b82f6; }
        .status-dot.done { background: #22c55e; }
        .item-title { flex: 1; }
        .item-meta { font-size: 11px; color: var(--text-muted); }
        .agent-item {
            display: flex; align-items: center; gap: 12px;
            padding: 10px 12px;
            background: var(--bg-elevated);
            border-radius: 8px;
            margin-bottom: 10px;
        }
        .agent-avatar {
            width: 32px; height: 32px; border-radius: 50%;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            display: flex; align-items: center; justify-content: center;
            font-size: 14px;
        }
        .agent-name { font-size: 13px; font-weight: 500; }
        .agent-status {
            margin-left: auto; font-size: 11px; padding: 2px 8px; border-radius: 4px;
        }
        .agent-status.active { background: rgba(34,197,94,0.2); color: #22c55e; }
        .agent-status.inactive { background: rgba(107,114,128,0.2); color: #6b7280; }
        .action-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
        .action-btn {
            display: flex; align-items: center; gap: 10px;
            padding: 14px;
            background: var(--bg-elevated);
            border: 1px solid var(--border-default);
            border-radius: var(--radius-md);
            color: var(--text-primary);
            text-decoration: none;
            font-size: 14px;
            transition: border-color 0.2s ease;
        }
        .action-btn:hover { border-color: rgba(99,102,241,0.4); }
        .action-icon { font-size: 18px; }
        .card-cta {
            display: inline-block;
            padding: 10px 20px;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            color: #fff; text-decoration: none; border-radius: 8px;
            font-size: 14px; font-weight: 500;
        }
        .loading-state {
            text-align: center; padding: 20px;
            color: var(--text-muted); font-size: 13px;
        }
    </style>
</head>
<body <?php body_class(); ?>>
<div class="dashboard-container">
    <div class="dashboard-header">
        <h1>Welcome, <?php echo esc_html(wp_get_current_user()->display_name); ?></h1>
        <div class="credits-badge">
            <span>⚡</span>
            <span id="credits-text">Loading...</span>
        </div>
    </div>
    
    <div class="dashboard-grid">
        <div class="dashboard-card">
            <div class="card-header">
                <span class="card-icon">📋</span>
                <h2>My Tasks</h2>
                <a href="/work/" class="card-link">View all</a>
            </div>
            <ul class="item-list" id="my-tasks"><li class="loading-state">Loading...</li></ul>
            <a href="/work/" class="card-cta">Go to Work</a>
        </div>
        
        <div class="dashboard-card">
            <div class="card-header">
                <span class="card-icon">📝</span>
                <h2>My Posts</h2>
                <a href="/my-posts/" class="card-link">View all</a>
            </div>
            <ul class="item-list" id="my-posts-list"><li class="loading-state">Loading...</li></ul>
            <a href="/my-posts/" class="card-cta">New Post</a>
        </div>
        
        <div class="dashboard-card">
            <div class="card-header">
                <span class="card-icon">🤖</span>
                <h2>My Agents</h2>
                <a href="/agents/" class="card-link">Manage</a>
            </div>
            <div class="agent-list" id="my-agents"><div class="loading-state">Loading...</div></div>
            <a href="/agents/" class="card-cta">Go to Agents</a>
        </div>
        
        <div class="dashboard-card">
            <div class="card-header">
                <span class="card-icon">⚡</span>
                <h2>Quick Actions</h2>
            </div>
            <div class="action-grid">
                <a href="/chat/" class="action-btn"><span class="action-icon">💬</span><span>Start Chat</span></a>
                <a href="/knowledge/" class="action-btn"><span class="action-icon">📚</span><span>New Prompt</span></a>
                <a href="/work/" class="action-btn"><span class="action-icon">📋</span><span>New Task</span></a>
                <a href="/my-posts/" class="action-btn"><span class="action-icon">📝</span><span>New Post</span></a>
            </div>
        </div>
        
        <div class="dashboard-card wide">
            <div class="card-header">
                <span class="card-icon">📊</span>
                <h2>Recent Activity</h2>
            </div>
            <ul class="item-list" id="activity-list"><li class="loading-state">Loading...</li></ul>
        </div>
    </div>
</div>

<script>
(function() {
    const restUrl = '/wp-json/bitcore/v1/';
    const nonce = '<?php echo wp_create_nonce("wp_rest"); ?>';
    
    async function api(endpoint) {
        try {
            const res = await fetch(restUrl + endpoint.replace(/^\//, ''), {
                headers: { 'Accept': 'application/json', 'X-WP-Nonce': nonce }
            });
            if (!res.ok) throw new Error(res.statusText);
            return await res.json().catch(() => null);
        } catch (e) { return null; }
    }
    
    async function loadTasks() {
        const data = await api('kanban?limit=5');
        const list = document.getElementById('my-tasks');
        const items = Array.isArray(data) ? data : [];
        if (!items.length) { list.innerHTML = '<li class="loading-state">No tasks yet</li>'; return; }
        list.innerHTML = items.slice(0, 5).map(t => {
            const s = t.status || 'inbox';
            return '<li><span class="status-dot ' + s + '"></span><span class="item-title">' + (t.title || 'Untitled') + '</span><span class="item-meta">' + s + '</span></li>';
        }).join('');
    }
    
    async function loadPosts() {
        const data = await api('my-posts?limit=5');
        const list = document.getElementById('my-posts-list');
        const items = (data && data.posts) ? data.posts : (Array.isArray(data) ? data : []);
        if (!items.length) { list.innerHTML = '<li class="loading-state">No posts yet</li>'; return; }
        list.innerHTML = items.slice(0, 5).map(p => {
            return '<li><span class="item-title">' + (p.title || 'Untitled') + '</span><span class="item-meta">' + (p.type || 'post') + '</span></li>';
        }).join('');
    }
    
    async function loadAgents() {
        const data = await api('eliza/agents');
        const container = document.getElementById('my-agents');
        const items = Array.isArray(data) ? data : (data && data.agents ? data.agents : []);
        if (!items.length) { container.innerHTML = '<div class="loading-state">No agents configured</div>'; return; }
        container.innerHTML = items.map(a => {
            const active = (a.status || '').toLowerCase() === 'active';
            return '<div class="agent-item"><span class="agent-avatar">' + (a.emoji || '🤖') + '</span><span class="agent-name">' + (a.name || 'Agent') + '</span><span class="agent-status ' + (active ? 'active' : 'inactive') + '">' + (active ? 'Active' : 'Idle') + '</span></div>';
        }).join('');
    }
    
    async function loadCredits() {
        const data = await api('budget');
        document.getElementById('credits-text').textContent = (data && data.credits) ? data.credits + ' credits' : 'Credits unavailable';
    }
    
    async function loadActivity() {
        const [tasks, posts] = await Promise.all([api('kanban?limit=3'), api('my-posts?limit=3')]);
        const list = document.getElementById('activity-list');
        const items = [];
        (Array.isArray(tasks) ? tasks : []).slice(0, 3).forEach(t => items.push('<li><span>📋</span><span class="item-title">Task: ' + (t.title || 'Untitled') + '</span><span class="item-meta">' + (t.status || 'inbox') + '</span></li>'));
        const pItems = (posts && posts.posts) ? posts.posts : [];
        pItems.slice(0, 3).forEach(p => items.push('<li><span>📝</span><span class="item-title">Post: ' + (p.title || 'Untitled') + '</span></li>'));
        list.innerHTML = items.length ? items.join('') : '<li class="loading-state">No recent activity</li>';
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        loadTasks(); loadPosts(); loadAgents(); loadCredits(); loadActivity();
    });
})();
</script>
<?php wp_footer(); ?>
</body>
</html>
