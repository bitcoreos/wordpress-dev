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
        .work-container { max-width: 1400px; margin: 0 auto; padding: var(--space-lg); }

        /* Header */
        .work-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: var(--space-lg); padding: var(--space-lg);
            background: linear-gradient(135deg, var(--bg-secondary), var(--bg-tertiary));
            border-radius: var(--radius-lg); border: 1px solid var(--border-default);
        }
        .work-title { display: flex; align-items: center; gap: var(--space-md); }
        .work-icon {
            width: 48px; height: 48px;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            border-radius: var(--radius-md);
            display: flex; align-items: center; justify-content: center;
            font-size: 24px;
        }

        /* Tab Navigation */
        .tab-nav {
            display: flex; gap: var(--space-sm);
            margin-bottom: var(--space-lg);
            border-bottom: 2px solid var(--border-default);
            padding-bottom: var(--space-sm);
        }
        .tab-btn {
            padding: var(--space-sm) var(--space-md);
            border: none; background: transparent; color: var(--text-muted);
            cursor: pointer; font-size: 0.95rem; font-weight: 600;
            border-radius: var(--radius-md) var(--radius-md) 0 0;
            transition: all 0.2s; position: relative;
        }
        .tab-btn:hover { color: var(--text-primary); background: var(--bg-tertiary); }
        .tab-btn.active {
            color: var(--accent-primary); background: var(--bg-tertiary);
        }
        .tab-btn.active::after {
            content: ''; position: absolute; bottom: -10px; left: 0; right: 0;
            height: 2px; background: var(--accent-primary);
        }
        .tab-btn .tab-count {
            display: inline-block; margin-left: 6px;
            background: var(--bg-elevated); color: var(--text-muted);
            padding: 1px 8px; border-radius: 12px; font-size: 0.7rem;
        }
        .tab-btn.active .tab-count { background: var(--accent-primary); color: #000; }

        /* Tab Content */
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* Stats Row */
        .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: var(--space-md); margin-bottom: var(--space-lg); }
        .stat-card {
            background: var(--bg-secondary); border: 1px solid var(--border-default);
            border-radius: var(--radius-md); padding: var(--space-md); text-align: center;
        }
        .stat-value { font-size: 1.75rem; font-weight: 700; font-family: 'SF Mono', monospace; }
        .stat-value.accent { color: var(--accent-primary); }
        .stat-value.secondary { color: var(--accent-secondary); }
        .stat-value.warning { color: var(--accent-warning); }
        .stat-value.danger { color: var(--accent-danger); }
        .stat-value.info { color: var(--accent-info); }
        .stat-label { color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; margin-top: 4px; }

        /* Main Grid */
        .main-grid { display: grid; grid-template-columns: 1fr 350px; gap: var(--space-lg); }

        /* Cards */
        .card {
            background: var(--bg-secondary); border: 1px solid var(--border-default);
            border-radius: var(--radius-lg); overflow: hidden;
        }
        .card-header {
            padding: var(--space-md) var(--space-lg); background: var(--bg-tertiary);
            border-bottom: 1px solid var(--border-default);
            display: flex; justify-content: space-between; align-items: center;
        }
        .card-title { font-weight: 600; }
        .card-body { padding: var(--space-md); }

        /* Filter Bar */
        .filter-bar { display: flex; gap: var(--space-sm); margin-bottom: var(--space-md); flex-wrap: wrap; }
        .filter-btn {
            padding: 6px 14px; border-radius: 20px; border: 1px solid var(--border-default);
            background: transparent; color: var(--text-secondary);
            cursor: pointer; font-size: 0.8rem; transition: all 0.2s;
        }
        .filter-btn:hover { background: var(--bg-tertiary); }
        .filter-btn.active { background: var(--accent-primary); border-color: var(--accent-primary); color: #000; }

        /* Task Items */
        .task-list { max-height: 500px; overflow-y: auto; }
        .task-item {
            padding: var(--space-md); border-bottom: 1px solid var(--border-default);
            cursor: pointer; transition: background 0.2s;
        }
        .task-item:hover { background: var(--bg-tertiary); }
        .task-item-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-sm); }
        .task-name { font-weight: 600; }
        .task-status {
            padding: 4px 10px; border-radius: 12px; font-size: 0.7rem;
            font-weight: 600; text-transform: uppercase;
        }
        .task-status.pending { background: var(--bg-elevated); color: var(--text-muted); }
        .task-status.running { background: var(--accent-warning); color: #000; }
        .task-status.completed { background: var(--accent-primary); color: #000; }
        .task-status.failed { background: var(--accent-danger); color: #fff; }
        .task-desc { font-size: 0.85rem; color: var(--text-secondary); margin-bottom: var(--space-sm); }
        .task-meta { display: flex; gap: var(--space-lg); font-size: 0.75rem; color: var(--text-muted); }

        /* Goal Tree */
        .goal-tree { padding: var(--space-sm); }
        .goal-node {
            padding: var(--space-sm) var(--space-md); margin-bottom: var(--space-sm);
            border-left: 3px solid var(--border-default);
            border-radius: 0 var(--radius-md) var(--radius-md) 0;
            background: var(--bg-tertiary); cursor: pointer; transition: all 0.2s;
        }
        .goal-node:hover { background: var(--bg-elevated); border-left-color: var(--accent-primary); }
        .goal-node.level-mission { border-left-color: var(--accent-primary); margin-left: 0; }
        .goal-node.level-objective { border-left-color: var(--accent-secondary); margin-left: 24px; }
        .goal-node.level-task { border-left-color: var(--accent-warning); margin-left: 48px; }
        .goal-header { display: flex; justify-content: space-between; align-items: center; }
        .goal-title-text { font-weight: 600; font-size: 0.9rem; }
        .goal-level {
            padding: 2px 8px; border-radius: 10px; font-size: 0.65rem;
            text-transform: uppercase; font-weight: 600;
        }
        .goal-level.mission { background: var(--accent-primary); color: #000; }
        .goal-level.objective { background: var(--accent-secondary); color: #fff; }
        .goal-level.task { background: var(--accent-warning); color: #000; }
        .goal-desc { font-size: 0.8rem; color: var(--text-muted); margin-top: 4px; }
        .goal-progress {
            height: 4px; background: var(--bg-elevated); border-radius: 2px;
            margin-top: 6px; overflow: hidden;
        }
        .goal-progress-bar { height: 100%; background: var(--accent-primary); border-radius: 2px; transition: width 0.3s; }

        /* Mission Cards */
        .mission-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: var(--space-md); }
        .mission-card {
            background: var(--bg-tertiary); border: 1px solid var(--border-default);
            border-radius: var(--radius-md); padding: var(--space-md);
            cursor: pointer; transition: all 0.2s;
        }
        .mission-card:hover { border-color: var(--accent-primary); background: var(--bg-elevated); }
        .mission-card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: var(--space-sm); }
        .mission-title { font-weight: 600; font-size: 0.95rem; }
        .mission-status {
            padding: 3px 10px; border-radius: 12px; font-size: 0.65rem;
            font-weight: 600; text-transform: uppercase; white-space: nowrap;
        }
        .mission-status.proposed { background: var(--bg-elevated); color: var(--text-muted); }
        .mission-status.approved { background: var(--accent-info); color: #fff; }
        .mission-status.in-progress { background: var(--accent-warning); color: #000; }
        .mission-status.completed { background: var(--accent-primary); color: #000; }
        .mission-status.archived { background: var(--text-muted); color: var(--bg-primary); }
        .mission-desc { font-size: 0.85rem; color: var(--text-muted); margin-bottom: var(--space-sm); }
        .mission-meta { display: flex; justify-content: space-between; font-size: 0.75rem; color: var(--text-muted); }
        .mission-priority {
            padding: 2px 8px; border-radius: 8px; font-size: 0.7rem;
            background: var(--bg-elevated); color: var(--text-secondary);
        }

        /* Buttons */
        .btn {
            padding: var(--space-sm) var(--space-md); border-radius: 6px;
            border: 1px solid var(--border-default); background: var(--bg-tertiary);
            color: var(--text-primary); cursor: pointer; transition: all 0.2s; font-size: 0.85rem;
        }
        .btn:hover { background: var(--bg-elevated); border-color: var(--accent-primary); }
        .btn-primary { background: var(--accent-primary); border-color: var(--accent-primary); color: #000; }
        .btn-danger { background: var(--accent-danger); border-color: var(--accent-danger); color: #fff; }
        .btn-sm { padding: 4px 10px; font-size: 0.75rem; }

        /* Forms */
        .form-group { margin-bottom: var(--space-md); }
        .form-label { display: block; margin-bottom: 4px; font-size: 0.85rem; color: var(--text-secondary); }
        .form-input {
            width: 100%; padding: var(--space-sm) var(--space-md);
            background: var(--bg-tertiary); border: 1px solid var(--border-default);
            border-radius: 6px; color: var(--text-primary); font-size: 0.9rem;
        }
        .form-input:focus { outline: none; border-color: var(--accent-primary); }
        .form-textarea { min-height: 100px; resize: vertical; }

        .empty-state { text-align: center; padding: 40px; color: var(--text-muted); }

        /* Responsive */
        @media (max-width: 900px) {
            .main-grid { grid-template-columns: 1fr; }
            .stats-row { grid-template-columns: repeat(2, 1fr); }
            .mission-grid { grid-template-columns: 1fr; }
        }
