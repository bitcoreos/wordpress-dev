<?php
/**
 * Template Name: RAG Workspaces
 * Description: RAG workspace management and document querying interface
 * @package bitcore-librarian-theme
 * @since 1.0.0
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_title('|', true, 'right'); ?> - RAG Workspaces</title>
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
        .ws-container { max-width: 1400px; margin: 0 auto; padding: var(--space-lg); }
        .ws-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-lg);
            padding: var(--space-lg);
            background: linear-gradient(135deg, var(--bg-secondary), var(--bg-tertiary));
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-default);
        }
        .ws-title { display: flex; align-items: center; gap: var(--space-md); }
        .ws-icon {
            width: 48px; height: 48px;
            background: linear-gradient(135deg, #f59e0b, var(--accent-secondary));
            border-radius: var(--radius-md);
            display: flex; align-items: center; justify-content: center;
            font-size: 24px;
        }
        .main-grid { display: grid; grid-template-columns: 300px 1fr; gap: var(--space-lg); }
        .card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-default);
            border-radius: var(--radius-lg);
            overflow: hidden;
        }
        .card-header {
            padding: var(--space-md) var(--space-lg);
            background: var(--bg-tertiary);
            border-bottom: 1px solid var(--border-default);
            display: flex; justify-content: space-between; align-items: center;
        }
        .card-title { font-weight: 600; }
        .card-body { padding: var(--space-md); }
        .workspace-list { max-height: 400px; overflow-y: auto; }
        .workspace-item {
            padding: var(--space-md);
            border-bottom: 1px solid var(--border-default);
            cursor: pointer;
            transition: background 0.2s;
        }
        .workspace-item:hover { background: var(--bg-tertiary); }
        .workspace-item.active { background: var(--bg-elevated); border-left: 3px solid var(--accent-primary); }
        .workspace-name { font-weight: 600; margin-bottom: 4px; }
        .workspace-meta { font-size: 0.75rem; color: var(--text-muted); }
        .btn {
            padding: var(--space-sm) var(--space-md);
            border-radius: 6px;
            border: 1px solid var(--border-default);
            background: var(--bg-tertiary);
            color: var(--text-primary);
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn:hover { background: var(--bg-elevated); border-color: var(--accent-primary); }
        .btn-primary { background: var(--accent-primary); border-color: var(--accent-primary); color: #000; }
        .btn-sm { padding: 4px 8px; font-size: 0.75rem; }
        .doc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: var(--space-md); }
        .doc-card {
            background: var(--bg-tertiary);
            border: 1px solid var(--border-default);
            border-radius: 8px;
            padding: var(--space-md);
            transition: all 0.2s;
        }
        .doc-card:hover { border-color: var(--accent-secondary); transform: translateY(-2px); }
        .doc-icon { font-size: 2rem; margin-bottom: var(--space-sm); }
        .doc-name { font-weight: 600; font-size: 0.9rem; margin-bottom: 4px; }
        .doc-size { font-size: 0.75rem; color: var(--text-muted); }
        .query-section { margin-top: var(--space-lg); }
        .query-input-wrapper { display: flex; gap: var(--space-sm); }
        .query-input {
            flex: 1;
            padding: var(--space-md);
            background: var(--bg-tertiary);
            border: 1px solid var(--border-default);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 1rem;
        }
        .query-input:focus { outline: none; border-color: var(--accent-primary); }
        .results-container {
            margin-top: var(--space-lg);
            background: var(--bg-tertiary);
            border-radius: 8px;
            padding: var(--space-md);
            max-height: 400px;
            overflow-y: auto;
        }
        .result-item {
            padding: var(--space-md);
            border-bottom: 1px solid var(--border-default);
        }
        .result-score {
            display: inline-block;
            padding: 2px 8px;
            background: var(--accent-primary);
            color: #000;
            font-size: 0.7rem;
            font-weight: 600;
            border-radius: 12px;
            margin-bottom: var(--space-sm);
        }
        .result-text { font-size: 0.9rem; line-height: 1.5; color: var(--text-secondary); }
        .result-source { font-size: 0.75rem; color: var(--text-muted); margin-top: var(--space-sm); }
        .empty-state { text-align: center; padding: 40px; color: var(--text-muted); }
        .form-input {
            width: 100%;
            padding: var(--space-sm) var(--space-md);
            background: var(--bg-tertiary);
            border: 1px solid var(--border-default);
            border-radius: 6px;
            color: var(--text-primary);
            margin-bottom: var(--space-sm);
        }
        @media (max-width: 900px) {
            .main-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="ws-container">
        <header class="ws-header">
            <div class="ws-title">
                <div class="ws-icon">📚</div>
                <div>
                    <h1>RAG Workspaces</h1>
                    <p style="color: var(--text-muted);">Document Management & Retrieval</p>
                </div>
            </div>
            <div style="display: flex; gap: var(--space-sm);">
                <button class="btn" id="refresh-btn">⟳ Refresh</button>
                <button class="btn btn-primary" id="new-workspace-btn">+ New Workspace</button>
            </div>
        </header>

        <div class="main-grid">
            <div class="card">
                <div class="card-header">
                    <span class="card-title">Workspaces</span>
                </div>
                <div class="card-body workspace-list" id="workspace-list">
                    <div class="empty-state">Loading...</div>
                </div>
            </div>

            <div>
                <div class="card">
                    <div class="card-header">
                        <span class="card-title">Documents</span>
                        <span id="current-workspace" style="color: var(--text-muted); font-size: 0.85rem;">Select a workspace</span>
                    </div>
                    <div class="card-body">
                        <div class="doc-grid" id="doc-grid">
                            <div class="empty-state">Select a workspace to view documents</div>
                        </div>
                    </div>
                </div>

                <div class="card query-section">
                    <div class="card-header">
                        <span class="card-title">Query</span>
                    </div>
                    <div class="card-body">
                        <div class="query-input-wrapper">
                            <input type="text" class="query-input" id="query-input" placeholder="Ask a question about your documents...">
                            <button class="btn btn-primary" id="query-btn">Search</button>
                        </div>
                        <div class="results-container" id="results-container" style="display: none;">
                            <div id="results-list"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php wp_footer(); ?>
</body>
</html>
