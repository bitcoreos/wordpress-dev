<?php
/**
 * Template Name: Work
 * Description: Unified Tasks/Goals/Missions hierarchy interface
 * @package bitcore-theme
 * @since 1.0.0
 * @version    1.0.0
 * @author     coraline
 */
if (!is_user_logged_in()) {
    wp_redirect(bitcore_get_login_url($_SERVER['REQUEST_URI']));
    exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_title('|', true, 'right'); ?> - Work</title>
    <?php wp_head(); ?>
    <style>
<?php include(get_template_directory() . '/template-parts/work-styles.php'); ?>
    </style>
</head>
<body>
<?php include(get_template_directory() . "/nav-header.php"); ?>

<div class="work-container">
    <header class="work-header">
        <div class="work-title">
            <div class="work-icon">⚡</div>
            <div>
                <h1>Work</h1>
                <p style="color: var(--text-muted);">Tasks · Goals · Missions</p>
            </div>
        </div>
        <div style="display: flex; gap: var(--space-sm);">
            <button class="btn" id="refresh-btn">⟳ Refresh</button>
        </div>
    </header>

    <!-- Tab Navigation -->
    <div class="tab-nav">
        <button class="tab-btn active" data-tab="tasks">📋 Tasks <span class="tab-count" id="tasks-count">0</span></button>
        <button class="tab-btn" data-tab="kanban">📊 Kanban <span class="tab-count" id="kanban-count">0</span></button>
        <button class="tab-btn" data-tab="goals">🎯 Goals <span class="tab-count" id="goals-count">0</span></button>
        <button class="tab-btn" data-tab="missions">🚀 Missions <span class="tab-count" id="missions-count">0</span></button>
    </div>

    <!-- Stats Row -->
    <div class="stats-row" id="stats-row">
        <div class="stat-card">
            <div class="stat-value accent" id="stat-total">--</div>
            <div class="stat-label" id="stat-total-label">Total Tasks</div>
        </div>
        <div class="stat-card">
            <div class="stat-value warning" id="stat-pending">--</div>
            <div class="stat-label" id="stat-pending-label">Pending</div>
        </div>
        <div class="stat-card">
            <div class="stat-value secondary" id="stat-active">--</div>
            <div class="stat-label" id="stat-active-label">Active</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" id="stat-done">--</div>
            <div class="stat-label" id="stat-done-label">Completed</div>
        </div>
    </div>

    <!-- Tasks Tab -->
    <div class="tab-content active" id="tab-tasks">
        <div class="main-grid">
            <div class="card">
                <div class="card-header">
                    <span class="card-title">Tasks</span>
                    <span id="task-count" style="color: var(--text-muted); font-size: 0.85rem;">Loading...</span>
                </div>
                <div class="card-body">
                    <div class="filter-bar" id="task-filter-bar">
                        <button class="filter-btn active" data-filter="all">All</button>
                        <button class="filter-btn" data-filter="inbox">Inbox</button>
                        <button class="filter-btn" data-filter="backlog">Backlog</button>
                        <button class="filter-btn" data-filter="doing">Doing</button>
                        <button class="filter-btn" data-filter="review">Review</button>
                        <button class="filter-btn" data-filter="done">Done</button>
                        <button class="filter-btn" data-filter="archived">Archived</button>
                    </div>
                    <div class="form-group" style="max-width: 260px; margin-bottom: var(--space-md);">
                        <label class="form-label">Type Filter</label>
                        <select class="form-input" id="task-type-filter">
                            <option value="all" selected>All Types</option>
                            <option value="task">Task</option>
                            <option value="goal">Goal</option>
                            <option value="milestone">Milestone</option>
                            <option value="sprint">Sprint</option>
                        </select>
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
                            <label class="form-label">Task Type</label>
                            <select class="form-input" id="new-task-type">
                                <option value="task" selected>Task</option>
                                <option value="goal">Goal</option>
                                <option value="milestone">Milestone</option>
                                <option value="sprint">Sprint</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select class="form-input" id="new-task-status">
                                <option value="inbox" selected>Inbox</option>
                                <option value="backlog">Backlog</option>
                                <option value="doing">Doing</option>
                                <option value="review">Review</option>
                                <option value="done">Done</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Assigned Agent</label>
                            <input type="text" class="form-input" id="new-task-assigned-to" placeholder="aria, forge, muse, or user id">
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
                    <div class="card-body" id="task-detail-body"></div>
                </div>
            </div>
        </div>
    </div>


    <!-- Kanban Tab -->
    <div class="tab-content" id="tab-kanban">
        <div style="display: grid; grid-template-columns: repeat(6, 1fr); gap: var(--space-sm); min-height: 500px;">
            <div class="kanban-column" data-status="inbox">
                <div class="kanban-header" style="padding: var(--space-sm); background: var(--bg-tertiary); border-radius: var(--radius-md) var(--radius-md) 0 0; border: 1px solid var(--border-default); border-bottom: none;">
                    <strong>📥 Inbox</strong> <span class="kanban-count" data-status="inbox">0</span>
                </div>
                <div class="kanban-body" id="kanban-inbox" style="padding: var(--space-sm); background: var(--bg-secondary); border: 1px solid var(--border-default); border-radius: 0 0 var(--radius-md) var(--radius-md); min-height: 400px;"></div>
            </div>
            <div class="kanban-column" data-status="backlog">
                <div class="kanban-header" style="padding: var(--space-sm); background: var(--bg-tertiary); border-radius: var(--radius-md) var(--radius-md) 0 0; border: 1px solid var(--border-default); border-bottom: none;">
                    <strong>📋 Backlog</strong> <span class="kanban-count" data-status="backlog">0</span>
                </div>
                <div class="kanban-body" id="kanban-backlog" style="padding: var(--space-sm); background: var(--bg-secondary); border: 1px solid var(--border-default); border-radius: 0 0 var(--radius-md) var(--radius-md); min-height: 400px;"></div>
            </div>
            <div class="kanban-column" data-status="doing">
                <div class="kanban-header" style="padding: var(--space-sm); background: var(--accent-warning); color: #000; border-radius: var(--radius-md) var(--radius-md) 0 0; border: 1px solid var(--border-default); border-bottom: none;">
                    <strong>⚡ Doing</strong> <span class="kanban-count" data-status="doing">0</span>
                </div>
                <div class="kanban-body" id="kanban-doing" style="padding: var(--space-sm); background: var(--bg-secondary); border: 1px solid var(--border-default); border-radius: 0 0 var(--radius-md) var(--radius-md); min-height: 400px;"></div>
            </div>
            <div class="kanban-column" data-status="review">
                <div class="kanban-header" style="padding: var(--space-sm); background: var(--accent-info); color: #fff; border-radius: var(--radius-md) var(--radius-md) 0 0; border: 1px solid var(--border-default); border-bottom: none;">
                    <strong>👀 Review</strong> <span class="kanban-count" data-status="review">0</span>
                </div>
                <div class="kanban-body" id="kanban-review" style="padding: var(--space-sm); background: var(--bg-secondary); border: 1px solid var(--border-default); border-radius: 0 0 var(--radius-md) var(--radius-md); min-height: 400px;"></div>
            </div>
            <div class="kanban-column" data-status="done">
                <div class="kanban-header" style="padding: var(--space-sm); background: var(--accent-primary); color: #000; border-radius: var(--radius-md) var(--radius-md) 0 0; border: 1px solid var(--border-default); border-bottom: none;">
                    <strong>✅ Done</strong> <span class="kanban-count" data-status="done">0</span>
                </div>
                <div class="kanban-body" id="kanban-done" style="padding: var(--space-sm); background: var(--bg-secondary); border: 1px solid var(--border-default); border-radius: 0 0 var(--radius-md) var(--radius-md); min-height: 400px;"></div>
            </div>
            <div class="kanban-column" data-status="archived">
                <div class="kanban-header" style="padding: var(--space-sm); background: var(--text-muted); color: var(--bg-primary); border-radius: var(--radius-md) var(--radius-md) 0 0; border: 1px solid var(--border-default); border-bottom: none;">
                    <strong>🗃️ Archived</strong> <span class="kanban-count" data-status="archived">0</span>
                </div>
                <div class="kanban-body" id="kanban-archived" style="padding: var(--space-sm); background: var(--bg-secondary); border: 1px solid var(--border-default); border-radius: 0 0 var(--radius-md) var(--radius-md); min-height: 400px;"></div>
            </div>
        </div>
    </div>

    <!-- Goals Tab -->
    <div class="tab-content" id="tab-goals">
        <div class="main-grid">
            <div class="card">
                <div class="card-header">
                    <span class="card-title">Goal Hierarchy</span>
                    <span id="goal-count" style="color: var(--text-muted); font-size: 0.85rem;">Loading...</span>
                </div>
                <div class="card-body">
                    <div class="filter-bar" id="goal-filter-bar">
                        <button class="filter-btn active" data-filter="all">All</button>
                        <button class="filter-btn" data-filter="mission">Missions</button>
                        <button class="filter-btn" data-filter="objective">Objectives</button>
                        <button class="filter-btn" data-filter="task">Tasks</button>
                    </div>
                    <div class="goal-tree" id="goal-tree">
                        <div class="empty-state">Loading goals...</div>
                    </div>
                </div>
            </div>
            <div style="display: flex; flex-direction: column; gap: var(--space-lg);">
                <div class="card">
                    <div class="card-header">
                        <span class="card-title">Create Goal</span>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="form-label">Title</label>
                            <input type="text" class="form-input" id="new-goal-title" placeholder="Goal title">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <textarea class="form-input form-textarea" id="new-goal-desc" placeholder="Goal description"></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Level</label>
                            <select class="form-input" id="new-goal-level">
                                <option value="mission">Mission</option>
                                <option value="objective">Objective</option>
                                <option value="task" selected>Task</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Parent Goal</label>
                            <select class="form-input" id="new-goal-parent">
                                <option value="">None (top-level)</option>
                            </select>
                        </div>
                        <button class="btn btn-primary" style="width: 100%;" id="submit-goal-btn">Create Goal</button>
                    </div>
                </div>
                <div class="card" id="goal-detail-card" style="display: none;">
                    <div class="card-header">
                        <span class="card-title">Goal Details</span>
                        <button class="btn btn-sm" id="close-goal-detail">×</button>
                    </div>
                    <div class="card-body" id="goal-detail-body"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Missions Tab -->
    <div class="tab-content" id="tab-missions">
        <div class="main-grid">
            <div class="card" style="grid-column: 1 / -1;">
                <div class="card-header">
                    <span class="card-title">Missions</span>
                    <span id="mission-count" style="color: var(--text-muted); font-size: 0.85rem;">Loading...</span>
                </div>
                <div class="card-body">
                    <div class="filter-bar" id="mission-filter-bar">
                        <button class="filter-btn active" data-filter="all">All</button>
                        <button class="filter-btn" data-filter="proposed">Proposed</button>
                        <button class="filter-btn" data-filter="approved">Approved</button>
                        <button class="filter-btn" data-filter="in-progress">In Progress</button>
                        <button class="filter-btn" data-filter="completed">Completed</button>
                        <button class="filter-btn" data-filter="archived">Archived</button>
                    </div>
                    <div class="mission-grid" id="mission-grid">
                        <div class="empty-state">Loading missions...</div>
                    </div>
                </div>
            </div>
            <div style="display: flex; flex-direction: column; gap: var(--space-lg);">
                <div class="card">
                    <div class="card-header">
                        <span class="card-title">Create Mission</span>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="form-label">Mission Name</label>
                            <input type="text" class="form-input" id="new-mission-title" placeholder="Enter mission name">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <textarea class="form-input form-textarea" id="new-mission-content" placeholder="Mission description"></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select class="form-input" id="new-mission-status">
                                <option value="proposed" selected>Proposed</option>
                                <option value="approved">Approved</option>
                                <option value="in-progress">In Progress</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Priority</label>
                            <select class="form-input" id="new-mission-priority">
                                <option value="1">1 - Critical</option>
                                <option value="2">2 - High</option>
                                <option value="3" selected>3 - Medium</option>
                                <option value="4">4 - Low</option>
                                <option value="5">5 - Minimal</option>
                            </select>
                        </div>
                        <button class="btn btn-primary" style="width: 100%;" id="submit-mission-btn">Create Mission</button>
                    </div>
                </div>
                <div class="card" id="mission-detail-card" style="display: none;">
                    <div class="card-header">
                        <span class="card-title">Mission Details</span>
                        <button class="btn btn-sm" id="close-mission-detail">×</button>
                    </div>
                    <div class="card-body" id="mission-detail-body"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
var bitcoreData = <?php echo json_encode([
    'nonce' => wp_create_nonce('wp_rest'),
    'restUrl' => rest_url('bitcore/v1/'),
    'userId' => get_current_user_id(),
    'userName' => wp_get_current_user()->display_name,
]); ?>;
</script>
<script src="<?php echo get_template_directory_uri(); ?>/assets/js/work.js"></script>

<?php wp_footer(); ?>
</body>
</html>
