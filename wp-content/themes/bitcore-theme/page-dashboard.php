<?php
/**
 * Template Name: SaaS Dashboard
 * Description: User-centric SaaS home page showing personal data, tasks, goals, and activity.
 * @package bitcore-theme
 * @since 2.0.0
 */

// Get current user info for PHP-side rendering
$current_user = wp_get_current_user();
$is_logged_in = is_user_logged_in();
$user_display_name = $is_logged_in ? $current_user->display_name : '';
$current_datetime = current_time('l, F j, Y - g:i A');
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_title('|', true, 'right'); ?>Dashboard</title>
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
            --accent-success: #22c55e;
            --border-default: #2a2a3a;
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 16px;
            --space-xs: 4px;
            --space-sm: 8px;
            --space-md: 16px;
            --space-lg: 24px;
            --space-xl: 32px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
        }
        .saas-container { max-width: 1400px; margin: 0 auto; padding: var(--space-lg); }

        /* ====== 1. WELCOME SECTION ====== */
        .welcome-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--space-lg) var(--space-xl);
            background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-tertiary) 100%);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-default);
            margin-bottom: var(--space-lg);
        }
        .welcome-left h1 { font-size: 1.75rem; font-weight: 700; margin-bottom: var(--space-xs); }
        .welcome-left h1 .user-name { color: var(--accent-primary); }
        .welcome-datetime { color: var(--text-secondary); font-size: 0.9rem; }
        .welcome-right { display: flex; align-items: center; gap: var(--space-md); }
        .health-indicator {
            display: flex; align-items: center; gap: var(--space-xs);
            padding: 6px 14px; border-radius: 20px;
            font-size: 0.8rem; font-weight: 600;
            background: rgba(34, 197, 94, 0.15); color: var(--accent-success);
        }
        .health-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: var(--accent-success);
            animation: pulse 2s ease-in-out infinite;
        }
        .health-indicator.degraded { background: rgba(245, 158, 11, 0.15); color: var(--accent-warning); }
        .health-indicator.degraded .health-dot { background: var(--accent-warning); }
        .health-indicator.critical { background: rgba(239, 68, 68, 0.15); color: var(--accent-danger); }
        .health-indicator.critical .health-dot { background: var(--accent-danger); }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
        .btn-refresh {
            padding: 6px 16px; border-radius: var(--radius-sm);
            border: 1px solid var(--border-default); background: var(--bg-tertiary);
            color: var(--text-primary); cursor: pointer; font-size: 0.85rem;
            transition: all 0.2s;
        }
        .btn-refresh:hover { border-color: var(--accent-primary); background: var(--bg-elevated); }

        /* ====== LOGIN PROMPT ====== */
        .login-prompt {
            text-align: center; padding: var(--space-xl);
            background: var(--bg-secondary); border: 1px solid var(--border-default);
            border-radius: var(--radius-lg); margin-bottom: var(--space-lg);
        }
        .login-prompt h2 { margin-bottom: var(--space-sm); }
        .login-prompt p { color: var(--text-secondary); margin-bottom: var(--space-md); }
        .login-btn {
            display: inline-block; padding: 10px 28px;
            background: var(--accent-primary); color: #000;
            border-radius: var(--radius-sm); text-decoration: none;
            font-weight: 600; transition: all 0.2s;
        }
        .login-btn:hover { opacity: 0.9; }

        /* ====== 2. STATS ROW ====== */
        .stats-row {
            display: grid; grid-template-columns: repeat(4, 1fr);
            gap: var(--space-md); margin-bottom: var(--space-lg);
        }
        .stat-card {
            background: var(--bg-secondary); border: 1px solid var(--border-default);
            border-radius: var(--radius-lg); padding: var(--space-lg);
            cursor: pointer; transition: all 0.2s;
            text-decoration: none; display: block;
        }
        .stat-card:hover { border-color: var(--accent-primary); transform: translateY(-2px); }
        .stat-card .stat-icon { font-size: 1.5rem; margin-bottom: var(--space-sm); }
        .stat-card .stat-value {
            font-size: 2rem; font-weight: 700;
            font-family: 'SF Mono', 'Fira Code', monospace;
            color: var(--text-primary); transition: transform 0.2s;
        }
        .stat-card .stat-label {
            font-size: 0.85rem; color: var(--text-secondary); margin-top: var(--space-xs);
        }

        /* ====== CARD BASE ====== */
        .card {
            background: var(--bg-secondary); border: 1px solid var(--border-default);
            border-radius: var(--radius-lg); overflow: hidden;
        }
        .card-header {
            padding: var(--space-md) var(--space-lg);
            background: var(--bg-tertiary); border-bottom: 1px solid var(--border-default);
            display: flex; justify-content: space-between; align-items: center;
        }
        .card-title { font-weight: 600; font-size: 1rem; }
        .card-body { padding: var(--space-md); }
        .card-link {
            font-size: 0.8rem; color: var(--accent-primary);
            text-decoration: none; transition: opacity 0.2s;
        }
        .card-link:hover { opacity: 0.8; }

        /* ====== 3. CONTENT GRID ====== */
        .content-grid {
            display: grid; grid-template-columns: 2fr 1fr;
            gap: var(--space-lg); margin-bottom: var(--space-lg);
        }

        /* ====== TASKS WIDGET ====== */
        .task-item {
            display: flex; align-items: center; justify-content: space-between;
            padding: var(--space-sm) var(--space-md);
            background: var(--bg-tertiary); border-radius: var(--radius-sm);
            margin-bottom: var(--space-sm); transition: background 0.2s;
        }
        .task-item:hover { background: var(--bg-elevated); }
        .task-info { display: flex; align-items: center; gap: var(--space-sm); flex: 1; min-width: 0; }
        .task-status-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
        .task-status-dot.publish, .task-status-dot.completed { background: var(--accent-success); }
        .task-status-dot.pending, .task-status-dot.in-progress { background: var(--accent-warning); }
        .task-status-dot.draft, .task-status-dot.paused { background: var(--text-muted); }
        .task-status-dot.failed { background: var(--accent-danger); }
        .task-title {
            font-size: 0.9rem; overflow: hidden;
            text-overflow: ellipsis; white-space: nowrap;
        }
        .task-meta {
            font-size: 0.75rem; color: var(--text-muted);
            margin-left: var(--space-sm); flex-shrink: 0;
        }
        .btn-create {
            display: inline-block; padding: 8px 20px;
            background: var(--accent-primary); color: #000;
            border: none; border-radius: var(--radius-sm);
            font-weight: 600; font-size: 0.85rem;
            cursor: pointer; transition: opacity 0.2s; text-decoration: none;
        }
        .btn-create:hover { opacity: 0.9; }
        .empty-state {
            text-align: center; padding: var(--space-xl); color: var(--text-muted);
        }
        .empty-state p { margin-bottom: var(--space-sm); }
        .empty-state a { color: var(--accent-primary); text-decoration: none; }

        /* ====== GOALS WIDGET ====== */
        .goal-item {
            padding: var(--space-md); background: var(--bg-tertiary);
            border-radius: var(--radius-sm); margin-bottom: var(--space-sm);
        }
        .goal-name { font-size: 0.9rem; font-weight: 600; margin-bottom: var(--space-xs); }
        .goal-priority {
            font-size: 0.7rem; padding: 2px 8px; border-radius: 10px;
            text-transform: uppercase; font-weight: 600; margin-left: var(--space-xs);
        }
        .priority-high { background: rgba(239, 68, 68, 0.2); color: var(--accent-danger); }
        .priority-medium { background: rgba(245, 158, 11, 0.2); color: var(--accent-warning); }
        .priority-low { background: rgba(34, 197, 94, 0.2); color: var(--accent-success); }
        .progress-bar {
            height: 6px; background: var(--bg-elevated);
            border-radius: 3px; overflow: hidden; margin: var(--space-xs) 0;
        }
        .progress-fill { height: 100%; border-radius: 3px; transition: width 0.5s ease; }
        .progress-fill.low { background: var(--accent-danger); }
        .progress-fill.mid { background: var(--accent-warning); }
        .progress-fill.high { background: var(--accent-success); }
        .progress-text { font-size: 0.75rem; color: var(--text-muted); }

        /* ====== 5. ACTIVITY FEED ====== */
        .activity-feed { margin-bottom: var(--space-lg); }
        .activity-item {
            display: flex; align-items: flex-start; gap: var(--space-md);
            padding: var(--space-sm) var(--space-md);
            border-bottom: 1px solid var(--border-default);
        }
        .activity-item:last-child { border-bottom: none; }
        .activity-icon {
            flex-shrink: 0; width: 32px; height: 32px;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-size: 0.85rem; background: var(--bg-tertiary);
        }
        .activity-content { flex: 1; }
        .activity-text { font-size: 0.9rem; }
        .activity-time { font-size: 0.75rem; color: var(--text-muted); }

        /* ====== 6. QUICK ACTIONS ====== */
        .quick-actions {
            display: grid; grid-template-columns: repeat(4, 1fr);
            gap: var(--space-md); margin-bottom: var(--space-lg);
        }
        .action-btn {
            display: flex; flex-direction: column; align-items: center;
            gap: var(--space-sm); padding: var(--space-lg) var(--space-md);
            background: var(--bg-secondary); border: 1px solid var(--border-default);
            border-radius: var(--radius-lg); cursor: pointer;
            text-decoration: none; color: var(--text-primary); transition: all 0.2s;
        }
        .action-btn:hover { border-color: var(--accent-primary); transform: translateY(-2px); }
        .action-icon { font-size: 1.75rem; }
        .action-label { font-size: 0.85rem; color: var(--text-secondary); font-weight: 500; }

        /* ====== 7. AGENT STATUS ====== */
        .agent-status {
            display: flex; justify-content: center; gap: var(--space-xl);
            padding: var(--space-lg); background: var(--bg-secondary);
            border: 1px solid var(--border-default); border-radius: var(--radius-lg);
            margin-bottom: var(--space-lg);
        }
        .agent-item { display: flex; align-items: center; gap: var(--space-sm); font-size: 0.85rem; }
        .agent-dot { width: 10px; height: 10px; border-radius: 50%; }
        .agent-dot.online { background: var(--accent-success); box-shadow: 0 0 6px var(--accent-success); }
        .agent-dot.offline { background: var(--accent-danger); box-shadow: 0 0 6px var(--accent-danger); }
        .agent-dot.checking { background: var(--accent-warning); animation: pulse 1s ease-in-out infinite; }
        .agent-name { font-weight: 600; }
        .agent-time { color: var(--text-muted); font-size: 0.75rem; }

        /* ====== LOADING SKELETON ====== */
        .skeleton {
            background: linear-gradient(90deg, var(--bg-tertiary) 25%, var(--bg-elevated) 50%, var(--bg-tertiary) 75%);
            background-size: 200% 100%;
            animation: shimmer 1.5s ease-in-out infinite;
            border-radius: var(--radius-sm);
        }
        @keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

        /* ====== RESPONSIVE ====== */
        @media (max-width: 1024px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); }
            .content-grid { grid-template-columns: 1fr; }
            .quick-actions { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .welcome-section { flex-direction: column; gap: var(--space-md); text-align: center; }
            .stats-row { grid-template-columns: 1fr; }
            .quick-actions { grid-template-columns: repeat(2, 1fr); }
            .agent-status { flex-direction: column; align-items: center; gap: var(--space-sm); }
        }
    </style>
</head>
<body>
<?php include(get_template_directory() . "/nav-header.php"); ?>

<div class="saas-container">
    <?php if ($is_logged_in): ?>
    <!-- ====== 1. WELCOME SECTION ====== -->
    <section class="welcome-section">
        <div class="welcome-left">
            <h1>Welcome back, <span class="user-name"><?php echo esc_html($user_display_name); ?></span></h1>
            <div class="welcome-datetime" id="current-datetime"><?php echo esc_html($current_datetime); ?></div>
        </div>
        <div class="welcome-right">
            <div class="health-indicator" id="health-indicator">
                <span class="health-dot"></span>
                <span id="health-label">Checking...</span>
            </div>
            <button class="btn-refresh" id="refresh-btn">⟳ Refresh</button>
        </div>
    </section>

    <!-- ====== 2. STATS ROW ====== -->
    <div class="stats-row">
        <a href="<?php echo esc_url(get_permalink(36)); ?>" class="stat-card" id="stat-tasks">
            <div class="stat-icon">✅</div>
            <div class="stat-value" id="stat-tasks-count">--</div>
            <div class="stat-label">Active Tasks</div>
        </a>
        <a href="<?php echo esc_url(get_permalink(33)); ?>" class="stat-card" id="stat-goals">
            <div class="stat-icon">🎯</div>
            <div class="stat-value" id="stat-goals-count">--</div>
            <div class="stat-label">Active Goals</div>
        </a>
        <a href="<?php echo esc_url(get_permalink(63)); ?>" class="stat-card" id="stat-sessions">
            <div class="stat-icon">💬</div>
            <div class="stat-value" id="stat-sessions-count">--</div>
            <div class="stat-label">Sessions</div>
        </a>
        <a href="<?php echo esc_url(get_permalink(53)); ?>" class="stat-card" id="stat-approvals">
            <div class="stat-icon">📋</div>
            <div class="stat-value" id="stat-approvals-count">--</div>
            <div class="stat-label">Approvals Pending</div>
        </a>
    </div>

    <!-- ====== 3/4. TASKS + GOALS GRID ====== -->
    <div class="content-grid">
        <!-- TASKS WIDGET -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">✅ My Tasks</span>
                <div style="display:flex;gap:8px;align-items:center;">
                    <a href="<?php echo esc_url(get_permalink(36)); ?>" class="card-link">View All →</a>
                </div>
            </div>
            <div class="card-body">
                <div id="tasks-list">
                    <div class="empty-state"><p>Loading tasks...</p></div>
                </div>
                <div style="margin-top:var(--space-md);text-align:center;">
                    <a href="<?php echo esc_url(get_permalink(36)); ?>" class="btn-create">+ Create Task</a>
                </div>
            </div>
        </div>

        <!-- GOALS WIDGET -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">🎯 My Goals</span>
                <a href="<?php echo esc_url(get_permalink(33)); ?>" class="card-link">View All →</a>
            </div>
            <div class="card-body" id="goals-list">
                <div class="empty-state"><p>Loading goals...</p></div>
            </div>
        </div>
    </div>

    <!-- ====== 5. ACTIVITY FEED ====== -->
    <div class="card activity-feed">
        <div class="card-header">
            <span class="card-title">📜 Recent Activity</span>
            <span style="font-size:0.75rem;color:var(--text-muted);" id="activity-updated">Updated just now</span>
        </div>
        <div class="card-body" id="activity-list">
            <div class="empty-state"><p>Loading activity...</p></div>
        </div>
    </div>

    <!-- ====== 6. QUICK ACTIONS ====== -->
    <div class="quick-actions">
        <a href="<?php echo esc_url(get_permalink(36)); ?>" class="action-btn">
            <span class="action-icon">✅</span>
            <span class="action-label">Create Task</span>
        </a>
        <a href="<?php echo esc_url(get_permalink(63)); ?>" class="action-btn">
            <span class="action-icon">💬</span>
            <span class="action-label">Start Session</span>
        </a>
        <a href="<?php echo esc_url(get_permalink(53)); ?>" class="action-btn">
            <span class="action-icon">📋</span>
            <span class="action-label">Check Approvals</span>
        </a>
        <a href="<?php echo esc_url(get_permalink(54)); ?>" class="action-btn">
            <span class="action-icon">💰</span>
            <span class="action-label">View Budget</span>
        </a>
    </div>

    <!-- ====== 7. AGENT STATUS ====== -->
    <div class="agent-status" id="agent-status">
        <div class="agent-item">
            <span class="agent-dot checking" id="hyperion-dot"></span>
            <span class="agent-name">Hyperion</span>
            <span class="agent-time" id="hyperion-time">Checking...</span>
        </div>
        <div class="agent-item">
            <span class="agent-dot checking" id="coraline-dot"></span>
            <span class="agent-name">Coraline</span>
            <span class="agent-time" id="coraline-time">Checking...</span>
        </div>
    </div>

    <?php else: ?>
    <!-- ====== NOT LOGGED IN ====== -->
    <div class="login-prompt">
        <h2>Welcome to BITCORE</h2>
        <p>Sign in to access your dashboard, tasks, goals, and agent status.</p>
        <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" class="login-btn">Sign In</a>
    </div>
    <?php endif; ?>
</div>

<?php wp_footer(); ?>
<?php if ($is_logged_in): ?>
<script>
var bitcoreData = {
    ajaxUrl: <?php echo json_encode(admin_url('admin-ajax.php')); ?>,
    nonce: <?php echo json_encode(wp_create_nonce('bitcore_dashboard')); ?>,
    restUrl: <?php echo json_encode(rest_url('bitcore/v1/')); ?>,
    userId: <?php echo json_encode(get_current_user_id()); ?>
};
</script>
<script src="<?php echo esc_url(get_template_directory_uri() . '/assets/js/dashboard.js'); ?>"></script>
<?php endif; ?>
</body>
</html>
