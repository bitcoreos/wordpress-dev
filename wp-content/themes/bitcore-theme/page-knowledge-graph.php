<?php
/**
 * Template Name: Knowledge Graph
 * Description: Interactive knowledge graph visualization and node management
 * @package bitcore-librarian-theme
 * @since 1.0.0
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_title('|', true, 'right'); ?> - Knowledge Graph</title>
    <?php wp_head(); ?>
    <style>
        :root {
            --bg-primary: #0a0a0f;
            --bg-secondary: #12121a;
            --bg-tertiary: #1a1a25;
            --text-primary: #f0f0f5;
            --text-secondary: #a0a0b0;
            --text-muted: #6a6a7a;
            --accent-primary: #00d4aa;
            --accent-secondary: #6366f1;
            --accent-warning: #f59e0b;
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
        .kg-container {
            max-width: 1600px;
            margin: 0 auto;
            padding: var(--space-lg);
        }
        .kg-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-lg);
            padding: var(--space-lg);
            background: linear-gradient(135deg, var(--bg-secondary), var(--bg-tertiary));
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-default);
        }
        .kg-title {
            display: flex;
            align-items: center;
            gap: var(--space-md);
        }
        .kg-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #8b5cf6, var(--accent-secondary));
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: var(--space-md);
            margin-bottom: var(--space-lg);
        }
        .stat-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-default);
            border-radius: var(--radius-md);
            padding: var(--space-md);
            text-align: center;
        }
        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--accent-primary);
            font-family: 'SF Mono', monospace;
        }
        .stat-label {
            color: var(--text-muted);
            font-size: 0.75rem;
            text-transform: uppercase;
        }
        .main-grid {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: var(--space-lg);
        }
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card-title { font-weight: 600; }
        .card-body { padding: var(--space-md); }
        .graph-canvas {
            width: 100%;
            height: 500px;
            background: radial-gradient(circle at center, var(--bg-tertiary), var(--bg-primary));
            border-radius: var(--radius-md);
            position: relative;
        }
        .node-list { max-height: 400px; overflow-y: auto; }
        .node-item {
            padding: var(--space-sm) var(--space-md);
            border-bottom: 1px solid var(--border-default);
            cursor: pointer;
            transition: background 0.2s;
        }
        .node-item:hover { background: var(--bg-tertiary); }
        .node-type {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            background: var(--accent-secondary);
            color: white;
            margin-right: var(--space-sm);
        }
        .form-group { margin-bottom: var(--space-md); }
        .form-label {
            display: block;
            margin-bottom: 4px;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        .form-input {
            width: 100%;
            padding: var(--space-sm) var(--space-md);
            background: var(--bg-tertiary);
            border: 1px solid var(--border-default);
            border-radius: 6px;
            color: var(--text-primary);
            font-size: 0.9rem;
        }
        .form-input:focus { outline: none; border-color: var(--accent-primary); }
        .btn {
            padding: var(--space-sm) var(--space-md);
            border-radius: 6px;
            border: 1px solid var(--border-default);
            background: var(--bg-tertiary);
            color: var(--text-primary);
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn:hover { background: var(--bg-secondary); border-color: var(--accent-primary); }
        .btn-primary { background: var(--accent-primary); border-color: var(--accent-primary); color: #000; }
        @media (max-width: 1200px) {
            .main-grid { grid-template-columns: 1fr; }
            .stats-row { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <div class="kg-container">
        <header class="kg-header">
            <div class="kg-title">
                <div class="kg-icon">🕸️</div>
                <div>
                    <h1>Knowledge Graph</h1>
                    <p style="color: var(--text-muted);">Node & Edge Visualization</p>
                </div>
            </div>
            <div>
                <button class="btn" id="refresh-btn">⟳ Refresh</button>
                <button class="btn btn-primary" id="export-btn">📥 Export</button>
            </div>
        </header>

        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-value" id="nodes-count">--</div>
                <div class="stat-label">Total Nodes</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="edges-count">--</div>
                <div class="stat-label">Edges</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="graph-density">--</div>
                <div class="stat-label">Density</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="components">--</div>
                <div class="stat-label">Components</div>
            </div>
        </div>

        <div class="main-grid">
            <div class="card">
                <div class="card-header">
                    <span class="card-title">Graph Visualization</span>
                    <select class="form-input" style="width: auto;" id="layout-select">
                        <option value="force">Force Layout</option>
                        <option value="radial">Radial</option>
                        <option value="tree">Tree</option>
                    </select>
                </div>
                <div class="card-body">
                    <div class="graph-canvas" id="graph-canvas">
                        <canvas id="canvas" style="width: 100%; height: 100%;"></canvas>
                    </div>
                </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: var(--space-lg);">
                <div class="card">
                    <div class="card-header">
                        <span class="card-title">Nodes</span>
                        <button class="btn" id="load-nodes">Load</button>
                    </div>
                    <div class="card-body node-list" id="node-list">
                        <p style="color: var(--text-muted); text-align: center; padding: var(--space-lg);">Click Load to fetch nodes</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <span class="card-title">Add Node</span>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="form-label">Node ID</label>
                            <input type="text" class="form-input" id="new-node-id" placeholder="entity_123">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Type</label>
                            <select class="form-input" id="new-node-type">
                                <option value="entity">Entity</option>
                                <option value="concept">Concept</option>
                                <option value="event">Event</option>
                                <option value="user">User</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Label</label>
                            <input type="text" class="form-input" id="new-node-label" placeholder="Node label">
                        </div>
                        <button class="btn btn-primary" style="width: 100%;" id="add-node-btn">+ Add Node</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php wp_footer(); ?>
</body>
</html>
