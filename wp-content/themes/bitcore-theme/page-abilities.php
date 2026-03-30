<?php
/**
 * Template Name: Abilities Browser
 * Description: WordPress Abilities API browser and capability explorer
 * @package bitcore-librarian-theme
 * @since 1.0.0
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_title('|', true, 'right'); ?> - Abilities Browser</title>
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
        .ab-container { max-width: 1400px; margin: 0 auto; padding: var(--space-lg); }
        .ab-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-lg);
            padding: var(--space-lg);
            background: linear-gradient(135deg, var(--bg-secondary), var(--bg-tertiary));
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-default);
        }
        .ab-title { display: flex; align-items: center; gap: var(--space-md); }
        .ab-icon {
            width: 48px; height: 48px;
            background: linear-gradient(135deg, #ec4899, var(--accent-secondary));
            border-radius: var(--radius-md);
            display: flex; align-items: center; justify-content: center;
            font-size: 24px;
        }
        .main-grid { display: grid; grid-template-columns: 280px 1fr; gap: var(--space-lg); }
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
        }
        .card-title { font-weight: 600; }
        .card-body { padding: var(--space-md); }
        .category-tree { list-style: none; }
        .category-item {
            padding: var(--space-sm) var(--space-md);
            cursor: pointer;
            border-radius: 6px;
            margin-bottom: 2px;
            transition: background 0.2s;
        }
        .category-item:hover { background: var(--bg-tertiary); }
        .category-item.active { background: var(--accent-primary); color: #000; }
        .category-count {
            float: right;
            font-size: 0.75rem;
            background: var(--bg-elevated);
            padding: 2px 8px;
            border-radius: 12px;
            color: var(--text-muted);
        }
        .category-item.active .category-count { background: rgba(0,0,0,0.2); color: #000; }
        .ability-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: var(--space-md); }
        .ability-card {
            background: var(--bg-tertiary);
            border: 1px solid var(--border-default);
            border-radius: var(--radius-md);
            padding: var(--space-md);
            transition: all 0.2s;
        }
        .ability-card:hover { border-color: var(--accent-secondary); }
        .ability-header { display: flex; align-items: center; gap: var(--space-sm); margin-bottom: var(--space-sm); }
        .ability-icon { font-size: 1.25rem; }
        .ability-name { font-weight: 600; flex: 1; }
        .ability-permission {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .ability-permission.read { background: #22c55e; color: #000; }
        .ability-permission.write { background: #f59e0b; color: #000; }
        .ability-permission.admin { background: #ef4444; color: #fff; }
        .ability-desc { font-size: 0.85rem; color: var(--text-secondary); margin-bottom: var(--space-sm); }
        .ability-meta {
            display: flex;
            gap: var(--space-sm);
            font-size: 0.75rem;
            color: var(--text-muted);
        }
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
        .search-box {
            display: flex;
            gap: var(--space-sm);
            margin-bottom: var(--space-md);
        }
        .search-input {
            flex: 1;
            padding: var(--space-sm) var(--space-md);
            background: var(--bg-tertiary);
            border: 1px solid var(--border-default);
            border-radius: 6px;
            color: var(--text-primary);
        }
        .search-input:focus { outline: none; border-color: var(--accent-primary); }
        .empty-state { text-align: center; padding: 40px; color: var(--text-muted); }
        .api-explorer {
            margin-top: var(--space-lg);
            background: var(--bg-tertiary);
            border-radius: 8px;
            padding: var(--space-md);
        }
        .api-endpoint {
            font-family: 'SF Mono', monospace;
            font-size: 0.85rem;
            background: var(--bg-primary);
            padding: var(--space-sm) var(--space-md);
            border-radius: 6px;
            margin-top: var(--space-sm);
        }
        .http-method {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 700;
            margin-right: var(--space-sm);
        }
        .http-method.get { background: #22c55e; color: #000; }
        .http-method.post { background: #3b82f6; color: #fff; }
        @media (max-width: 900px) {
            .main-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="ab-container">
        <header class="ab-header">
            <div class="ab-title">
                <div class="ab-icon">⚡</div>
                <div>
                    <h1>Abilities Browser</h1>
                    <p style="color: var(--text-muted);">WordPress Abilities API Explorer</p>
                </div>
            </div>
            <div style="display: flex; gap: var(--space-sm);">
                <button class="btn" id="refresh-btn">⟳ Refresh</button>
                <button class="btn btn-primary" id="check-permission-btn">🔍 Check Permission</button>
            </div>
        </header>

        <div class="main-grid">
            <div class="card">
                <div class="card-header">
                    <span class="card-title">Categories</span>
                </div>
                <div class="card-body">
                    <ul class="category-tree" id="category-tree">
                        <li class="category-item active" data-category="all">All Abilities <span class="category-count" id="all-count">--</span></li>
                    </ul>
                </div>
            </div>

            <div>
                <div class="search-box">
                    <input type="text" class="search-input" id="search-input" placeholder="Search abilities...">
                    <button class="btn" id="search-btn">Search</button>
                </div>

                <div class="card">
                    <div class="card-header">
                        <span class="card-title">Abilities</span>
                        <span id="ability-count" style="color: var(--text-muted); font-size: 0.85rem;">Loading...</span>
                    </div>
                    <div class="card-body">
                        <div class="ability-grid" id="ability-grid">
                            <div class="empty-state">Loading abilities...</div>
                        </div>
                    </div>
                </div>

                <div class="api-explorer">
                    <h3 style="margin-bottom: var(--space-sm); font-size: 0.9rem;">API Endpoints</h3>
                    <div class="api-endpoint">
                        <span class="http-method get">GET</span>
                        <span id="api-list">/wp-json/wp-abilities/v1/abilities</span>
                    </div>
                    <div class="api-endpoint">
                        <span class="http-method get">GET</span>
                        <span>/wp-json/wp-abilities/v1/categories</span>
                    </div>
                    <div class="api-endpoint">
                        <span class="http-method post">POST</span>
                        <span>/wp-json/wp-abilities/v1/check</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php wp_footer(); ?>
</body>
</html>
