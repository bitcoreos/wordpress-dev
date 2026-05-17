<?php
/**
 * Template Name: My Posts
 * Description: Unified editor and feed for tasks, prompts, artifacts, and social posts
 * @package bitcore-theme
 * @since 1.2.0
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_title('|', true, 'right'); ?> - My Posts</title>
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
            --accent-info: #3b82f6;
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
        .posts-container { max-width: 1200px; margin: 0 auto; padding: var(--space-lg); }

        /* Header */
        .posts-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: var(--space-lg); padding: var(--space-lg);
            background: linear-gradient(135deg, var(--bg-secondary), var(--bg-tertiary));
            border-radius: var(--radius-lg); border: 1px solid var(--border-default);
        }
        .posts-title { display: flex; align-items: center; gap: var(--space-md); }
        .posts-icon {
            width: 48px; height: 48px;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            border-radius: var(--radius-md);
            display: flex; align-items: center; justify-content: center;
            font-size: 24px;
        }

        /* Editor */
        .editor-card {
            background: var(--bg-secondary); border: 1px solid var(--border-default);
            border-radius: var(--radius-lg); margin-bottom: var(--space-lg);
            overflow: hidden;
        }
        .editor-header {
            padding: var(--space-md) var(--space-lg); background: var(--bg-tertiary);
            border-bottom: 1px solid var(--border-default);
            display: flex; justify-content: space-between; align-items: center;
            cursor: pointer;
        }
        .editor-body { padding: var(--space-lg); }
        .editor-body.collapsed { display: none; }
        .form-group { margin-bottom: var(--space-md); }
        .form-label { display: block; margin-bottom: 4px; font-size: 0.85rem; color: var(--text-secondary); }
        .form-input, .form-select, .form-textarea {
            width: 100%; padding: var(--space-sm) var(--space-md);
            background: var(--bg-tertiary); border: 1px solid var(--border-default);
            border-radius: 6px; color: var(--text-primary); font-size: 0.9rem;
        }
        .form-input:focus, .form-select:focus, .form-textarea:focus { outline: none; border-color: var(--accent-primary); }
        .form-textarea { min-height: 120px; resize: vertical; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-md); }

        /* Filters */
        .filter-bar {
            display: flex; gap: var(--space-sm); margin-bottom: var(--space-lg);
            flex-wrap: wrap; align-items: center;
        }
        .filter-select {
            padding: 6px 14px; border-radius: 20px; border: 1px solid var(--border-default);
            background: var(--bg-tertiary); color: var(--text-primary);
            font-size: 0.85rem; cursor: pointer;
        }
        .filter-btn {
            padding: 6px 14px; border-radius: 20px; border: 1px solid var(--border-default);
            background: transparent; color: var(--text-secondary);
            cursor: pointer; font-size: 0.8rem; transition: all 0.2s;
        }
        .filter-btn:hover { background: var(--bg-tertiary); }
        .filter-btn.active { background: var(--accent-primary); border-color: var(--accent-primary); color: #000; }

        /* Feed */
        .feed-list { display: flex; flex-direction: column; gap: var(--space-md); }
        .feed-item {
            background: var(--bg-secondary); border: 1px solid var(--border-default);
            border-radius: var(--radius-md); padding: var(--space-md);
            transition: border-color 0.2s;
        }
        .feed-item:hover { border-color: var(--accent-primary); }
        .feed-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-sm); }
        .feed-type {
            padding: 3px 10px; border-radius: 12px; font-size: 0.7rem;
            font-weight: 600; text-transform: uppercase;
        }
        .feed-type.task { background: var(--accent-primary); color: #000; }
        .feed-type.prompt { background: var(--accent-secondary); color: #fff; }
        .feed-type.artifact { background: var(--accent-warning); color: #000; }
        .feed-type.post { background: var(--accent-info); color: #fff; }
        .feed-channel {
            padding: 2px 8px; border-radius: 8px; font-size: 0.7rem;
            background: var(--bg-elevated); color: var(--text-muted);
        }
        .feed-title { font-weight: 600; margin-bottom: var(--space-sm); }
        .feed-excerpt { font-size: 0.85rem; color: var(--text-secondary); margin-bottom: var(--space-sm); }
        .feed-meta { display: flex; gap: var(--space-lg); font-size: 0.75rem; color: var(--text-muted); }

        /* Actions */
        .actions-bar {
            display: flex; gap: var(--space-sm); margin-bottom: var(--space-lg);
            justify-content: flex-end;
        }
        .btn {
            padding: var(--space-sm) var(--space-md); border-radius: 6px;
            border: 1px solid var(--border-default); background: var(--bg-tertiary);
            color: var(--text-primary); cursor: pointer; transition: all 0.2s; font-size: 0.85rem;
        }
        .btn:hover { background: var(--bg-elevated); border-color: var(--accent-primary); }
        .btn-primary { background: var(--accent-primary); border-color: var(--accent-primary); color: #000; }
        .btn-danger { background: var(--accent-danger); border-color: var(--accent-danger); color: #fff; }

        /* Empty state */
        .empty-state { text-align: center; padding: 60px; color: var(--text-muted); }

        /* Loading */
        .loading { text-align: center; padding: 40px; color: var(--text-muted); }

        /* Responsive */
        @media (max-width: 900px) {
            .form-row { grid-template-columns: 1fr; }
            .posts-header { flex-direction: column; gap: var(--space-md); align-items: flex-start; }
        }
    </style>
</head>
<body>
<?php include(get_template_directory() . "/nav-header.php"); ?>

<div class="posts-container">
    <header class="posts-header">
        <div class="posts-title">
            <div class="posts-icon">📝</div>
            <div>
                <h1>My Posts</h1>
                <p style="color: var(--text-muted);">Tasks · Prompts · Artifacts · Posts</p>
            </div>
        </div>
        <div class="actions-bar">
            <button class="btn" onclick="exportData()">⬇️ Export</button>
            <button class="btn btn-danger" onclick="deleteAll()">🗑️ Delete All</button>
        </div>
    </header>

    <!-- Inline Editor -->
    <div class="editor-card">
        <div class="editor-header" onclick="toggleEditor()">
            <span>✏️ New Post</span>
            <span id="editor-toggle">▼</span>
        </div>
        <div class="editor-body" id="editor-body">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Type</label>
                    <select class="form-select" id="post-type">
                        <option value="agent_task">Task</option>
                        <option value="bitcore_prompt">Prompt</option>
                        <option value="bitcore_artifact">Artifact</option>
                        <option value="post">Social Post</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Channel</label>
                    <select class="form-select" id="post-channel">
                        <option value="personal">Personal (no federation)</option>
                        <option value="bithub">Bithub</option>
                        <option value="fediverse">Fediverse (disabled)</option>
                        <option value="bitwiki">Bitwiki (deferred)</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Title</label>
                <input type="text" class="form-input" id="post-title" placeholder="Enter title...">
            </div>
            <div class="form-group">
                <label class="form-label">Content</label>
                <textarea class="form-textarea" id="post-content" placeholder="Enter content..."></textarea>
            </div>
            <div class="form-row" id="task-meta" style="display: none;">
                <div class="form-group">
                    <label class="form-label">Task Type</label>
                    <select class="form-select" id="task-type">
                        <option value="task">Task</option>
                        <option value="research">Research</option>
                        <option value="scheduled">Scheduled</option>
                        <option value="approval">Approval</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Priority</label>
                    <select class="form-select" id="task-priority">
                        <option value="low">Low</option>
                        <option value="normal">Normal</option>
                        <option value="high">High</option>
                    </select>
                </div>
            </div>
            <button class="btn btn-primary" onclick="createPost()">Create</button>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-bar">
        <select class="filter-select" id="filter-type" onchange="loadFeed()">
            <option value="">All Types</option>
            <option value="agent_task">Tasks</option>
            <option value="bitcore_prompt">Prompts</option>
            <option value="bitcore_artifact">Artifacts</option>
            <option value="post">Posts</option>
        </select>
        <select class="filter-select" id="filter-channel" onchange="loadFeed()">
            <option value="">All Channels</option>
            <option value="personal">Personal</option>
            <option value="bithub">Bithub</option>
            <option value="fediverse">Fediverse</option>
        </select>
        <select class="filter-select" id="filter-status" onchange="loadFeed()">
            <option value="">All Statuses</option>
            <option value="inbox">Inbox</option>
            <option value="backlog">Backlog</option>
            <option value="doing">Doing</option>
            <option value="review">Review</option>
            <option value="done">Done</option>
            <option value="archived">Archived</option>
        </select>
        <button class="filter-btn" onclick="loadFeed()">🔄 Refresh</button>
    </div>

    <!-- Feed -->
    <div id="feed-container">
        <div class="loading">Loading...</div>
    </div>
</div>

<script>
const restUrl = '<?php echo esc_url(rest_url('bitcore/v1')); ?>';
const nonce = '<?php echo wp_create_nonce('wp_rest'); ?>';

// Toggle editor
document.getElementById('post-type').addEventListener('change', function() {
    document.getElementById('task-meta').style.display = this.value === 'agent_task' ? 'grid' : 'none';
});

function toggleEditor() {
    const body = document.getElementById('editor-body');
    const toggle = document.getElementById('editor-toggle');
    body.classList.toggle('collapsed');
    toggle.textContent = body.classList.contains('collapsed') ? '▶' : '▼';
}

// Load feed
async function loadFeed() {
    const container = document.getElementById('feed-container');
    container.innerHTML = '<div class="loading">Loading...</div>';

    const params = new URLSearchParams();
    const type = document.getElementById('filter-type').value;
    const channel = document.getElementById('filter-channel').value;
    const status = document.getElementById('filter-status').value;

    if (type) params.append('type', type);
    if (channel) params.append('channel', channel);
    if (status) params.append('status', status);

    try {
        const res = await fetch(`${restUrl}/my-posts?${params}`, {
            headers: { 'X-WP-Nonce': nonce }
        });
        const data = await res.json();

        if (!data.items || data.items.length === 0) {
            container.innerHTML = '<div class="empty-state">No posts found. Create your first post above.</div>';
            return;
        }

        container.innerHTML = '<div class="feed-list">' + data.items.map(item => `
            <div class="feed-item">
                <div class="feed-header">
                    <span class="feed-type ${item.type.replace('bitcore_', '').replace('agent_', '')}">${item.type.replace('bitcore_', '').replace('agent_', '').replace('_', ' ')}</span>
                    <span class="feed-channel">${item.channel}</span>
                </div>
                <div class="feed-title">${escapeHtml(item.title)}</div>
                <div class="feed-excerpt">${escapeHtml(item.excerpt)}</div>
                <div class="feed-meta">
                    ${item.task_status ? `<span>Status: ${item.task_status}</span>` : ''}
                    ${item.priority ? `<span>Priority: ${item.priority}</span>` : ''}
                    ${item.assigned_to ? `<span>Assigned: ${item.assigned_to}</span>` : ''}
                    <span>${new Date(item.modified).toLocaleString()}</span>
                </div>
            </div>
        `).join('') + '</div>';
    } catch (err) {
        container.innerHTML = '<div class="empty-state">Error loading posts: ' + err.message + '</div>';
    }
}

// Create post
async function createPost() {
    const type = document.getElementById('post-type').value;
    const title = document.getElementById('post-title').value;
    const content = document.getElementById('post-content').value;
    const channel = document.getElementById('post-channel').value;

    if (!title) { alert('Title required'); return; }

    let endpoint, body;
    if (type === 'agent_task') {
        endpoint = '/kanban';
        body = JSON.stringify({
            title, content,
            assigned_to: '',
            priority: document.getElementById('task-priority').value,
            task_type: document.getElementById('task-type').value
        });
    } else {
        endpoint = '/my-posts';
        body = JSON.stringify({ title, content, type, channel });
    }

    try {
        const res = await fetch(restUrl + endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce
            },
            body
        });
        if (res.ok) {
            document.getElementById('post-title').value = '';
            document.getElementById('post-content').value = '';
            loadFeed();
        } else {
            const err = await res.json();
            alert('Error: ' + (err.message || 'Failed to create'));
        }
    } catch (err) {
        alert('Error: ' + err.message);
    }
}

// Export data
async function exportData() {
    try {
        const res = await fetch(restUrl + '/my-posts/export', {
            headers: { 'X-WP-Nonce': nonce }
        });
        const data = await res.json();
        const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `bitcore-export-${new Date().toISOString().slice(0,10)}.json`;
        a.click();
        URL.revokeObjectURL(url);
    } catch (err) {
        alert('Export failed: ' + err.message);
    }
}

// Delete all
async function deleteAll() {
    if (!confirm('Permanently delete all your tasks, prompts, and artifacts?')) return;
    try {
        const res = await fetch(restUrl + '/my-posts/delete', {
            method: 'POST',
            headers: { 'X-WP-Nonce': nonce }
        });
        if (res.ok) {
            loadFeed();
        } else {
            alert('Delete failed');
        }
    } catch (err) {
        alert('Error: ' + err.message);
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

// Init
loadFeed();
</script>

<?php wp_footer(); ?>
</body>
</html>
