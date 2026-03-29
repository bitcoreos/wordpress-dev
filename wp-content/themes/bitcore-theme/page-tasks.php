<?php
/**
 * Template Name: Task Manager
 * Description: Task management and dispatch interface
 * @package bitcore-librarian-theme
 * @since 1.0.0
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_title('|', true, 'right'); ?> - Task Manager</title>
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
        .task-container { max-width: 1400px; margin: 0 auto; padding: var(--space-lg); }
        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-lg);
            padding: var(--space-lg);
            background: linear-gradient(135deg, var(--bg-secondary), var(--bg-tertiary));
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-default);
        }
        .task-title { display: flex; align-items: center; gap: var(--space-md); }
        .task-icon {
            width: 48px; height: 48px;
            background: linear-gradient(135deg, #3b82f6, var(--accent-secondary));
            border-radius: var(--radius-md);
            display: flex; align-items: center; justify-content: center;
            font-size: 24px;
        }
        .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: var(--space-md); margin-bottom: var(--space-lg); }
        .stat-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-default);
            border-radius: var(--radius-md);
            padding: var(--space-md);
            text-align: center;
        }
        .stat-value { font-size: 1.75rem; font-weight: 700; font-family: 'SF Mono', monospace; }
        .stat-value.accent { color: var(--accent-primary); }
        .stat-value.secondary { color: var(--accent-secondary); }
        .stat-value.warning { color: var(--accent-warning); }
        .stat-value.danger { color: var(--accent-danger); }
        .stat-label { color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; margin-top: 4px; }
        .main-grid { display: grid; grid-template-columns: 1fr 350px; gap: var(--space-lg); }
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
        .filter-bar { display: flex; gap: var(--space-sm); margin-bottom: var(--space-md); flex-wrap: wrap; }
        .filter-btn {
            padding: 6px 14px;
            border-radius: 20px;
            border: 1px solid var(--border-default);
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.2s;
        }
        .filter-btn:hover { background: var(--bg-tertiary); }
        .filter-btn.active { background: var(--accent-primary); border-color: var(--accent-primary); color: #000; }
        .task-list { max-height: 500px; overflow-y: auto; }
        .task-item {
            padding: var(--space-md);
            border-bottom: 1px solid var(--border-default);
            cursor: pointer;
            transition: background 0.2s;
        }
        .task-item:hover { background: var(--bg-tertiary); }
        .task-item-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-sm); }
        .task-name { font-weight: 600; }
        .task-status {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .task-status.pending { background: var(--bg-elevated); color: var(--text-muted); }
        .task-status.running { background: var(--accent-warning); color: #000; }
        .task-status.completed { background: var(--accent-primary); color: #000; }
        .task-status.failed { background: var(--accent-danger); color: #fff; }
        .task-desc { font-size: 0.85rem; color: var(--text-secondary); margin-bottom: var(--space-sm); }
        .task-meta { display: flex; gap: var(--space-lg); font-size: 0.75rem; color: var(--text-muted); }
        .btn {
            padding: var(--space-sm) var(--space-md);
            border-radius: 6px;
            border: 1px solid var(--border-default);
            background: var(--bg-tertiary);
            color: var(--text-primary);
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.85rem;
        }
        .btn:hover { background: var(--bg-elevated); border-color: var(--accent-primary); }
        .btn-primary { background: var(--accent-primary); border-color: var(--accent-primary); color: #000; }
        .btn-danger { background: var(--accent-danger); border-color: var(--accent-danger); color: #fff; }
        .btn-sm { padding: 4px 10px; font-size: 0.75rem; }
        .form-group { margin-bottom: var(--space-md); }
        .form-label { display: block; margin-bottom: 4px; font-size: 0.85rem; color: var(--text-secondary); }
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
        .form-textarea { min-height: 100px; resize: vertical; }
        .empty-state { text-align: center; padding: 40px; color: var(--text-muted); }
        @media (max-width: 900px) {
            .main-grid { grid-template-columns: 1fr; }
            .stats-row { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <div class="task-container">
        <header class="task-header">
            <div class="task-title">
                <div class="task-icon">📋</div>
                <div>
                    <h1>Task Manager</h1>
                    <p style="color: var(--text-muted);">Dispatch & Monitor Tasks</p>
                </div>
            </div>
            <div style="display: flex; gap: var(--space-sm);">
                <button class="btn" id="refresh-btn">⟳ Refresh</button>
                <button class="btn btn-primary" id="create-task-btn">+ New Task</button>
            </div>
        </header>

        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-value accent" id="total-tasks">--</div>
                <div class="stat-label">Total Tasks</div>
            </div>
            <div class="stat-card">
                <div class="stat-value warning" id="pending-tasks">--</div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-value secondary" id="running-tasks">--</div>
                <div class="stat-label">Running</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="completed-tasks">--</div>
                <div class="stat-label">Completed</div>
            </div>
        </div>

        <div class="main-grid">
            <div class="card">
                <div class="card-header">
                    <span class="card-title">Tasks</span>
                    <span id="task-count" style="color: var(--text-muted); font-size: 0.85rem;">Loading...</span>
                </div>
                <div class="card-body">
                    <div class="filter-bar" id="filter-bar">
                        <button class="filter-btn active" data-filter="all">All</button>
                        <button class="filter-btn" data-filter="pending">Pending</button>
                        <button class="filter-btn" data-filter="running">Running</button>
                        <button class="filter-btn" data-filter="completed">Completed</button>
                        <button class="filter-btn" data-filter="failed">Failed</button>
                    </div>
                    <div class="task-list" id="task-list">
                        <div class="empty-state">Loading tasks...</div>
                    </div>
                </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: var(--space-lg);">
                <div class="card">
                    <div class="card-header">
                        <span class="card-title">Create Task</span>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="form-label">Task Name</label>
                            <input type="text" class="form-input" id="new-task-name" placeholder="Enter task name">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <textarea class="form-input form-textarea" id="new-task-desc" placeholder="Task description"></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Priority</label>
                            <select class="form-input" id="new-task-priority">
                                <option value="low">Low</option>
                                <option value="normal" selected>Normal</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                        <button class="btn btn-primary" style="width: 100%;" id="submit-task-btn">Create Task</button>
                    </div>
                </div>

                <div class="card" id="task-detail-card" style="display: none;">
                    <div class="card-header">
                        <span class="card-title">Task Details</span>
                        <button class="btn btn-sm" id="close-detail">×</button>
                    </div>
                    <div class="card-body" id="task-detail-body">
                        <!-- Filled dynamically -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php wp_footer(); ?>
</body>
</html>
