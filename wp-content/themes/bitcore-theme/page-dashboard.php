<?php
/**
 * Template Name: Dashboard
 * The main BITCORE dashboard - unified view of work, agents, and activity.
 */
get_header();
?>
<main class="bitcore-dashboard">
    <div class="dashboard-header">
        <h1>BITCORE Dashboard</h1>
        <div class="server-health" id="server-health">
            <span class="health-dot" id="health-dot"></span>
            <span class="health-text" id="health-text">Checking...</span>
        </div>
    </div>
    
    <div class="dashboard-grid">
        <section class="dashboard-card">
            <div class="card-header">
                <span class="card-icon">📋</span>
                <h2>Work</h2>
            </div>
            <p class="card-desc">Tasks, goals, and missions</p>
            <div class="card-meta" id="work-summary">Loading...</div>
            <a href="/work/" class="button card-cta">Go to Work</a>
        </section>
        
        <section class="dashboard-card">
            <div class="card-header">
                <span class="card-icon">💬</span>
                <h2>Chat</h2>
            </div>
            <p class="card-desc">Agent conversations</p>
            <div class="card-meta" id="chat-summary">Loading...</div>
            <a href="/chat/" class="button card-cta">Go to Chat</a>
        </section>
        
        <section class="dashboard-card">
            <div class="card-header">
                <span class="card-icon">🤖</span>
                <h2>Agents</h2>
            </div>
            <p class="card-desc">Manage your agent swarm</p>
            <div class="card-meta" id="agents-summary">Loading...</div>
            <a href="/agents/" class="button card-cta">Go to Agents</a>
        </section>
        
        <section class="dashboard-card">
            <div class="card-header">
                <span class="card-icon">📚</span>
                <h2>Knowledge</h2>
            </div>
            <p class="card-desc">Prompts, skills, artifacts</p>
            <div class="card-meta" id="knowledge-summary">Loading...</div>
            <a href="/knowledge/" class="button card-cta">Go to Knowledge</a>
        </section>
        
        <section class="dashboard-card">
            <div class="card-header">
                <span class="card-icon">📝</span>
                <h2>My Posts</h2>
            </div>
            <p class="card-desc">Your content and tasks</p>
            <div class="card-meta" id="posts-summary">Loading...</div>
            <a href="/my-posts/" class="button card-cta">Go to My Posts</a>
        </section>
        
        <section class="dashboard-card recent-activity">
            <div class="card-header">
                <span class="card-icon">⚡</span>
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
.server-health {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background: var(--bg-tertiary, #1a1a25);
    border-radius: 8px;
    border: 1px solid var(--border, #2a2a3a);
}
.health-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #6a6a7a;
    transition: background 0.3s ease;
}
.health-dot.online { background: #00d4aa; box-shadow: 0 0 8px rgba(0,212,170,0.4); }
.health-dot.offline { background: #ef4444; }
.health-dot.warning { background: #f59e0b; }
.health-text {
    font-size: 14px;
    color: var(--text-secondary, #a0a0b0);
}

.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
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
    border-color: rgba(99,102,241,0.3);
}
.card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}
.card-icon {
    font-size: 24px;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg-elevated, #22222e);
    border-radius: 10px;
}
.card-header h2 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
}
.card-desc {
    margin: 0 0 12px 0;
    color: var(--text-secondary, #a0a0b0);
    font-size: 14px;
}
.card-meta {
    font-size: 13px;
    color: var(--text-muted, #6a6a7a);
    margin-bottom: 16px;
    min-height: 20px;
}
.card-cta {
    display: inline-block;
    padding: 10px 20px;
    background: linear-gradient(135deg, #00d4aa, #6366f1);
    color: #fff;
    text-decoration: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    transition: opacity 0.2s ease;
}
.card-cta:hover { opacity: 0.9; }

.recent-activity { grid-column: 1 / -1; }
.activity-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.activity-list li {
    padding: 10px 12px;
    background: var(--bg-elevated, #22222e);
    border-radius: 8px;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.activity-list li .time {
    color: var(--text-muted, #6a6a7a);
    font-size: 12px;
    min-width: 60px;
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
    
    async function loadHealth() {
        try {
            const data = await api('/eliza/health');
            const dot = document.getElementById('health-dot');
            const text = document.getElementById('health-text');
            if (data && data.status === 'ok') {
                dot.className = 'health-dot online';
                text.textContent = 'ElizaOS Online';
            } else {
                dot.className = 'health-dot offline';
                text.textContent = 'ElizaOS Offline';
            }
        } catch (e) {
            document.getElementById('health-dot').className = 'health-dot offline';
            document.getElementById('health-text').textContent = 'ElizaOS Unreachable';
        }
    }
    
    async function loadAgents() {
        try {
            const data = await api('/eliza/agents');
            const active = (data || []).filter(a => a.status === 'active').length;
            document.getElementById('agents-summary').textContent = active + ' active / ' + (data || []).length + ' total';
        } catch (e) {
            document.getElementById('agents-summary').textContent = 'Unable to load';
        }
    }
    
    async function loadWork() {
        try {
            const data = await api('/kanban');
            const total = (data || []).length;
            document.getElementById('work-summary').textContent = total + ' tasks in board';
        } catch (e) {
            document.getElementById('work-summary').textContent = 'Unable to load';
        }
    }
    
    async function loadActivity() {
        try {
            const data = await api('/kanban?limit=5');
            const list = document.getElementById('activity-list');
            if (!data || !data.length) {
                list.innerHTML = '<li>No recent activity</li>';
                return;
            }
            list.innerHTML = data.slice(0, 5).map(item => {
                const time = item.updated_at ? new Date(item.updated_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : '';
                return '<li><span class="time">' + time + '</span><span>' + (item.title || 'Task updated') + '</span></li>';
            }).join('');
        } catch (e) {
            document.getElementById('activity-list').innerHTML = '<li>Unable to load activity</li>';
        }
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        loadHealth();
        loadAgents();
        loadWork();
        loadActivity();
        document.getElementById('chat-summary').textContent = 'Ready';
        document.getElementById('knowledge-summary').textContent = 'Ready';
        document.getElementById('posts-summary').textContent = 'Ready';
    });
})();
</script>
<?php get_footer(); ?>
