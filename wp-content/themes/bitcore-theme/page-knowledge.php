<?php
/**
 * Template Name: Knowledge
 * Knowledge hub - prompts, skills, artifacts from Bithub and BitWiki.
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
    <title><?php wp_title('|', true, 'right'); ?> - Knowledge</title>
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
            --border-default: #2a2a3a;
            --radius-md: 10px;
            --radius-lg: 16px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
        }
        .knowledge-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 24px;
        }
        .knowledge-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }
        .knowledge-header h1 { font-size: 28px; font-weight: 700; }
        .knowledge-search {
            display: flex;
            gap: 8px;
        }
        .knowledge-search input {
            padding: 10px 16px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border-default);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 14px;
            width: 280px;
        }
        .knowledge-search input:focus { outline: none; border-color: var(--accent-secondary); }
        .knowledge-search button {
            padding: 10px 20px;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            border: none;
            border-radius: 8px;
            color: #fff;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
        }
        .knowledge-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            border-bottom: 1px solid var(--border-default);
            padding-bottom: 12px;
        }
        .tab-btn {
            padding: 10px 20px;
            background: transparent;
            border: 1px solid var(--border-default);
            border-radius: 8px;
            color: var(--text-secondary);
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .tab-btn.active, .tab-btn:hover {
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            color: #fff;
            border-color: transparent;
        }
        .knowledge-panel { display: none; }
        .knowledge-panel.active { display: block; }
        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        .panel-header h2 { margin: 0; font-size: 20px; }
        .panel-count { font-size: 13px; color: var(--text-muted); }
        .knowledge-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 16px;
        }
        .knowledge-card {
            background: linear-gradient(135deg, var(--bg-secondary), var(--bg-tertiary));
            border: 1px solid var(--border-default);
            border-radius: 12px;
            padding: 16px;
            transition: transform 0.2s ease, border-color 0.2s ease;
        }
        .knowledge-card:hover {
            transform: translateY(-2px);
            border-color: rgba(99,102,241,0.3);
        }
        .knowledge-card h3 { margin: 0 0 8px 0; font-size: 15px; font-weight: 600; }
        .knowledge-card p { margin: 0; font-size: 13px; color: var(--text-secondary); line-height: 1.5; }
        .knowledge-card .card-meta {
            margin-top: 12px;
            display: flex;
            gap: 12px;
            font-size: 12px;
            color: var(--text-muted);
        }
        .knowledge-card .card-tags {
            margin-top: 10px;
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        .knowledge-card .tag {
            padding: 4px 10px;
            background: var(--bg-elevated);
            border-radius: 6px;
            font-size: 11px;
            color: var(--text-secondary);
        }
        .loading-state, .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 40px;
            color: var(--text-muted);
            font-size: 14px;
        }
        @media (max-width: 900px) {
            .knowledge-header { align-items: stretch; }
            .knowledge-search { width: 100%; }
            .knowledge-search input { width: 100%; }
            .knowledge-tabs { overflow-x: auto; }
            .knowledge-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body <?php body_class(); ?>>
<div class="knowledge-container">
    <div class="knowledge-header">
        <h1>Knowledge</h1>
        <div class="knowledge-search">
            <input type="text" id="knowledge-search" placeholder="Search prompts, skills, artifacts...">
            <button id="search-btn">Search</button>
        </div>
    </div>
    
    <nav class="knowledge-tabs">
        <button class="tab-btn active" data-tab="prompts">Prompts</button>
        <button class="tab-btn" data-tab="skills">Skills</button>
        <button class="tab-btn" data-tab="artifacts">Artifacts</button>
    </nav>
    
    <section class="knowledge-panel active" id="prompts-panel">
        <div class="panel-header">
            <h2>Prompts</h2>
            <span class="panel-count" id="prompts-count">Loading...</span>
        </div>
        <div class="knowledge-grid" id="prompts-grid">
            <div class="loading-state">Loading prompts from Bithub...</div>
        </div>
    </section>
    
    <section class="knowledge-panel" id="skills-panel">
        <div class="panel-header">
            <h2>Skills</h2>
            <span class="panel-count" id="skills-count">Loading...</span>
        </div>
        <div class="knowledge-grid" id="skills-grid">
            <div class="loading-state">Loading skills from BitWiki...</div>
        </div>
    </section>
    
    <section class="knowledge-panel" id="artifacts-panel">
        <div class="panel-header">
            <h2>Artifacts</h2>
            <span class="panel-count" id="artifacts-count">Loading...</span>
        </div>
        <div class="knowledge-grid" id="artifacts-grid">
            <div class="loading-state">Loading artifacts from library...</div>
        </div>
    </section>
</div>

<script>
(function() {
    const restUrl = '/wp-json/bitcore/v1/';
    const nonce = '<?php echo wp_create_nonce("wp_rest"); ?>';
    
    async function api(endpoint, options = {}) {
        try {
            const res = await fetch(restUrl + endpoint.replace(/^\//, ''), {
                method: options.method || 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce
                },
                body: options.body ? JSON.stringify(options.body) : undefined
            });
            if (!res.ok) throw new Error(res.statusText);
            return await res.json().catch(() => null);
        } catch (e) { return null; }
    }
    
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.knowledge-panel').forEach(p => p.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById(btn.dataset.tab + '-panel').classList.add('active');
        });
    });
    
    let promptsItems = [];
    let skillsItems = [];
    let artifactItems = [];

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, function(char) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char];
        });
    }

    function renderCard(item) {
        const tags = (item.tags || []).map(t => '<span class="tag">' + escapeHtml(t) + '</span>').join('');
        return '<div class="knowledge-card">' +
            '<h3>' + escapeHtml(item.title || 'Untitled') + '</h3>' +
            '<p>' + escapeHtml(item.description || item.excerpt || item.content || 'No description') + '</p>' +
            '<div class="card-meta">' +
                '<span>' + escapeHtml(item.source || 'BITCORE') + '</span>' +
                (item.updated_at ? '<span>' + escapeHtml(new Date(item.updated_at).toLocaleDateString()) + '</span>' : '') +
            '</div>' +
            (tags ? '<div class="card-tags">' + tags + '</div>' : '') +
        '</div>';
    }

    function filterItems(items, query) {
        const needle = query.trim().toLowerCase();
        if (!needle) return items;
        return items.filter(item => [item.title, item.description, item.excerpt, item.content, item.source, (item.tags || []).join(' ')]
            .filter(Boolean)
            .join(' ')
            .toLowerCase()
            .includes(needle));
    }

    function renderGrid(gridId, countId, items, emptyMessage) {
        const grid = document.getElementById(gridId);
        document.getElementById(countId).textContent = items.length + ' items';
        if (!items.length) { grid.innerHTML = '<div class="empty-state">' + escapeHtml(emptyMessage) + '</div>'; return; }
        grid.innerHTML = items.map(renderCard).join('');
    }


    function mergeRemoteSearchResults(remoteItems) {
        const byKey = new Map();
        [promptsItems, skillsItems, artifactItems, remoteItems || []].flat().forEach(item => {
            const key = [item.source || 'local', item.id || item.title || JSON.stringify(item)].join(':');
            if (!byKey.has(key)) byKey.set(key, item);
        });
        return Array.from(byKey.values());
    }

    async function runSearch() {
        const query = document.getElementById('knowledge-search').value || '';
        const data = query.trim() ? await api('context/search', { method: 'POST', body: { query } }) : null;
        const remoteItems = (data && data.items) ? data.items : [];
        const mergedItems = mergeRemoteSearchResults(remoteItems);
        renderGrid('prompts-grid', 'prompts-count', filterItems(mergedItems.filter(item => ['prompt', 'topic'].includes(item.type || 'topic')), query), 'No prompts found. Create one in Bithub.');
        renderGrid('skills-grid', 'skills-count', filterItems(mergedItems.filter(item => (item.type || '') === 'skill'), query), 'No skills found. Create one in BitWiki.');
        renderGrid('artifacts-grid', 'artifacts-count', filterItems(mergedItems.filter(item => (item.type || '') === 'artifact'), query), 'No artifacts found. Create one in My Posts.');
    }
    
    async function loadPrompts() {
        const data = await api('knowledge/nodes');
        promptsItems = (data && data.nodes) ? data.nodes : (Array.isArray(data) ? data : []);
        renderGrid('prompts-grid', 'prompts-count', filterItems(promptsItems, document.getElementById('knowledge-search').value || ''), 'No prompts found. Create one in Bithub.');
    }
    
    async function loadSkills() {
        const data = await api('knowledge/edges');
        skillsItems = (data && data.edges) ? data.edges : (Array.isArray(data) ? data : []);
        renderGrid('skills-grid', 'skills-count', filterItems(skillsItems, document.getElementById('knowledge-search').value || ''), 'No skills found. Create one in BitWiki.');
    }
    
    async function loadArtifacts() {
        const data = await api('my-posts?type=bitcore_artifact');
        artifactItems = (data && data.items) ? data.items : (Array.isArray(data) ? data : []);
        renderGrid('artifacts-grid', 'artifacts-count', filterItems(artifactItems, document.getElementById('knowledge-search').value || ''), 'No artifacts found. Create one in My Posts.');
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        loadPrompts(); loadSkills(); loadArtifacts();
        document.getElementById('search-btn').addEventListener('click', runSearch);
        document.getElementById('knowledge-search').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') runSearch();
        });
    });
})();
</script>
<?php wp_footer(); ?>
</body>
</html>
