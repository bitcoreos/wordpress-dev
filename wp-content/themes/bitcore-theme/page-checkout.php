<?php
/**
 * Template Name: Checkout
 * Description: Task checkout and assignment workflow
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_title('|', true, 'right'); ?><?php bloginfo('name'); ?></title>
    <?php wp_head(); ?>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(180deg, #0a0a0f 0%, #1a1a2e 50%, #0f0f1a 100%);
            color: #e0e0e0;
            min-height: 100vh;
            line-height: 1.6;
        }
        .cosmic-bg {
            position: relative;
            min-height: 100vh;
        }
        .cosmic-bg::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="10" cy="10" r="0.5" fill="%23ffffff" opacity="0.3"/><circle cx="30" cy="25" r="0.3" fill="%23ffffff" opacity="0.2"/><circle cx="50" cy="15" r="0.4" fill="%23ffffff" opacity="0.25"/><circle cx="70" cy="35" r="0.3" fill="%23ffffff" opacity="0.2"/><circle cx="90" cy="20" r="0.5" fill="%23ffffff" opacity="0.3"/></svg>');
            background-size: 200px 200px;
            opacity: 0.5;
            pointer-events: none;
        }
        .checkout-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px;
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 24px;
        }
        @media (max-width: 1024px) {
            .checkout-container { grid-template-columns: 1fr; }
        }
        .main-content {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 20px;
        }
        .header-section h1 {
            font-size: 2rem;
            font-weight: 300;
            color: #fff;
        }
        .header-section .subtitle {
            font-size: 0.95rem;
            color: #888;
            margin-top: 4px;
        }
        .stats-pills {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .stat-pill {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: rgba(17, 17, 24, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            font-size: 0.85rem;
        }
        .stat-pill .value {
            font-weight: 600;
            color: #4ade80;
        }
        .filter-bar {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .search-input {
            padding: 10px 16px;
            font-size: 0.85rem;
            background: rgba(17, 17, 24, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: #fff;
            outline: none;
            flex: 1;
            min-width: 200px;
            transition: all 0.3s;
        }
        .search-input:focus {
            border-color: #4ade80;
        }
        .search-input::placeholder { color: #666; }
        .filter-select {
            padding: 10px 16px;
            font-size: 0.85rem;
            background: rgba(17, 17, 24, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: #fff;
            cursor: pointer;
        }
        .filter-select option { background: #111118; }
        .tasks-grid {
            display: grid;
            gap: 16px;
        }
        .task-card {
            background: rgba(17, 17, 24, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.3s;
        }
        .task-card:hover {
            border-color: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }
        .task-card.claimed {
            border-color: rgba(74, 222, 128, 0.3);
            background: rgba(74, 222, 128, 0.05);
        }
        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 20px 24px;
            gap: 16px;
        }
        .task-info { flex: 1; }
        .task-title {
            font-size: 1.05rem;
            font-weight: 600;
            color: #fff;
            margin-bottom: 8px;
        }
        .task-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .task-badge {
            padding: 4px 10px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-radius: 4px;
        }
        .task-badge.priority-high {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }
        .task-badge.priority-medium {
            background: rgba(251, 191, 36, 0.2);
            color: #fbbf24;
        }
        .task-badge.priority-low {
            background: rgba(129, 140, 248, 0.2);
            color: #818cf8;
        }
        .task-badge.type-development {
            background: rgba(129, 140, 248, 0.2);
            color: #818cf8;
        }
        .task-badge.type-design {
            background: rgba(244, 114, 182, 0.2);
            color: #f472b6;
        }
        .task-badge.type-research {
            background: rgba(167, 139, 250, 0.2);
            color: #a78bfa;
        }
        .task-badge.type-documentation {
            background: rgba(74, 222, 128, 0.2);
            color: #4ade80;
        }
        .task-reward {
            text-align: right;
        }
        .reward-value {
            font-size: 1.25rem;
            font-weight: 600;
            color: #4ade80;
        }
        .reward-label {
            font-size: 0.75rem;
            color: #888;
        }
        .task-body {
            padding: 0 24px 20px;
        }
        .task-description {
            font-size: 0.9rem;
            color: #aaa;
            margin-bottom: 16px;
            line-height: 1.6;
        }
        .task-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
        }
        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .detail-label {
            font-size: 0.7rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .detail-value {
            font-size: 0.85rem;
            color: #e0e0e0;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .task-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 24px;
            background: rgba(255, 255, 255, 0.02);
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }
        .task-assignee {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .assignee-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: linear-gradient(135deg, #818cf8, #6366f1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 600;
            color: #fff;
        }
        .checkout-btn {
            padding: 10px 24px;
            font-size: 0.85rem;
            font-weight: 500;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .checkout-btn.available {
            background: linear-gradient(135deg, #4ade80, #22c55e);
            color: #000;
        }
        .checkout-btn.available:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(74, 222, 128, 0.4);
        }
        .checkout-btn.claimed {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #888;
            cursor: default;
        }
        .checkout-btn.submit {
            background: rgba(129, 140, 248, 0.2);
            border: 1px solid rgba(129, 140, 248, 0.3);
            color: #818cf8;
        }
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }
        .sidebar-card {
            background: rgba(17, 17, 24, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            overflow: hidden;
        }
        .sidebar-header {
            padding: 16px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        .sidebar-header h3 {
            font-size: 0.95rem;
            font-weight: 600;
            color: #fff;
        }
        .sidebar-content {
            padding: 16px 20px;
        }
        .active-task-item {
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding: 12px;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 10px;
            margin-bottom: 8px;
        }
        .active-task-item:last-child { margin-bottom: 0; }
        .active-task-title {
            font-size: 0.85rem;
            color: #fff;
            font-weight: 500;
        }
        .active-task-progress {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .progress-mini {
            flex: 1;
            height: 4px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 2px;
            overflow: hidden;
        }
        .progress-mini-fill {
            height: 100%;
            background: #4ade80;
            border-radius: 2px;
        }
        .progress-time {
            font-size: 0.75rem;
            color: #888;
            min-width: 50px;
            text-align: right;
        }
        .leaderboard-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        .leaderboard-item:last-child { border-bottom: none; }
        .leaderboard-rank {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
            border-radius: 50%;
        }
        .leaderboard-rank.gold { background: linear-gradient(135deg, #fbbf24, #f59e0b); color: #000; }
        .leaderboard-rank.silver { background: linear-gradient(135deg, #9ca3af, #6b7280); color: #000; }
        .leaderboard-rank.bronze { background: linear-gradient(135deg, #d97706, #b45309); color: #000; }
        .leaderboard-rank.default { background: rgba(255, 255, 255, 0.1); color: #888; }
        .leaderboard-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
            color: #fff;
        }
        .leaderboard-info { flex: 1; }
        .leaderboard-name {
            font-size: 0.85rem;
            color: #fff;
        }
        .leaderboard-stats {
            font-size: 0.7rem;
            color: #888;
        }
        .leaderboard-score {
            font-size: 0.9rem;
            font-weight: 600;
            color: #4ade80;
        }
        .time-tracker {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .time-display {
            font-size: 2rem;
            font-weight: 300;
            color: #fff;
            text-align: center;
            font-family: 'SF Mono', Monaco, monospace;
        }
        .time-controls {
            display: flex;
            gap: 8px;
            justify-content: center;
        }
        .time-btn {
            padding: 8px 16px;
            font-size: 0.8rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .time-btn.start {
            background: linear-gradient(135deg, #4ade80, #22c55e);
            color: #000;
        }
        .time-btn.stop {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }
        .time-btn.pause {
            background: rgba(251, 191, 36, 0.2);
            color: #fbbf24;
        }
        .completion-form {
            margin-top: 16px;
        }
        .completion-textarea {
            width: 100%;
            padding: 12px;
            font-size: 0.85rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: #fff;
            resize: vertical;
            min-height: 80px;
            margin-bottom: 12px;
        }
        .completion-textarea::placeholder { color: #666; }
        .submit-btn {
            width: 100%;
            padding: 12px;
            font-size: 0.9rem;
            font-weight: 500;
            background: linear-gradient(135deg, #4ade80, #22c55e);
            color: #000;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(74, 222, 128, 0.4);
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #888;
        }
        .empty-state .icon {
            font-size: 2rem;
            margin-bottom: 12px;
            opacity: 0.5;
        }
        @media (max-width: 768px) {
            .header-section { flex-direction: column; }
            .task-header { flex-direction: column; }
            .task-reward { text-align: left; }
        }
    </style>
</head>
<body class="cosmic-bg">
    <?php include(get_template_directory() . "/nav-header.php"); ?>
    <div id="checkout-container" class="checkout-container bitcore-container">
        <div class="main-content">
            <section class="header-section">
                <div>
                    <h1>Task Checkout</h1>
                    <p class="subtitle">Browse available tasks and claim your next assignment</p>
                </div>
                <div class="stats-pills">
                    <div class="stat-pill">
                        <span>Available:</span>
                        <span class="value">12</span>
                    </div>
                    <div class="stat-pill">
                        <span>In Progress:</span>
                        <span class="value">5</span>
                    </div>
                    <div class="stat-pill">
                        <span>Completed:</span>
                        <span class="value">48</span>
                    </div>
                </div>
            </section>

            <div class="filter-bar">
                <input type="text" class="search-input" placeholder="Search tasks...">
                <select class="filter-select">
                    <option value="">All Priorities</option>
                    <option value="high">High Priority</option>
                    <option value="medium">Medium Priority</option>
                    <option value="low">Low Priority</option>
                </select>
                <select class="filter-select">
                    <option value="">All Types</option>
                    <option value="development">Development</option>
                    <option value="design">Design</option>
                    <option value="research">Research</option>
                    <option value="documentation">Documentation</option>
                </select>
            </div>

            <div class="tasks-grid">
                <!-- Task 1 -->
                <div class="task-card">
                    <div class="task-header">
                        <div class="task-info">
                            <h3 class="task-title">Implement WebSocket Real-time Notifications</h3>
                            <div class="task-meta">
                                <span class="task-badge priority-high">High</span>
                                <span class="task-badge type-development">Development</span>
                            </div>
                        </div>
                        <div class="task-reward">
                            <div class="reward-value">$150</div>
                            <div class="reward-label">Reward</div>
                        </div>
                    </div>
                    <div class="task-body">
                        <p class="task-description">Implement real-time notification system using WebSockets. Should integrate with existing notification infrastructure and support multiple client connections.</p>
                        <div class="task-details">
                            <div class="detail-item">
                                <span class="detail-label">Est. Time</span>
                                <span class="detail-value">⏱️ 4-6 hours</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Deadline</span>
                                <span class="detail-value">📅 Apr 5, 2026</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Skills</span>
                                <span class="detail-value">Node.js, WebSocket</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Created</span>
                                <span class="detail-value">Mar 28, 2026</span>
                            </div>
                        </div>
                    </div>
                    <div class="task-footer">
                        <div class="task-assignee">
                            <span style="font-size: 0.8rem; color: #888;">Unassigned</span>
                        </div>
                        <button class="checkout-btn available" onclick="checkoutTask(1)">Checkout Task</button>
                    </div>
                </div>

                <!-- Task 2 -->
                <div class="task-card">
                    <div class="task-header">
                        <div class="task-info">
                            <h3 class="task-title">Design Dashboard Analytics Charts</h3>
                            <div class="task-meta">
                                <span class="task-badge priority-medium">Medium</span>
                                <span class="task-badge type-design">Design</span>
                            </div>
                        </div>
                        <div class="task-reward">
                            <div class="reward-value">$100</div>
                            <div class="reward-label">Reward</div>
                        </div>
                    </div>
                    <div class="task-body">
                        <p class="task-description">Create modern, responsive chart components for the analytics dashboard. Should include line, bar, and pie chart variations with dark theme styling.</p>
                        <div class="task-details">
                            <div class="detail-item">
                                <span class="detail-label">Est. Time</span>
                                <span class="detail-value">⏱️ 3-4 hours</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Deadline</span>
                                <span class="detail-value">📅 Apr 8, 2026</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Skills</span>
                                <span class="detail-value">Figma, SVG</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Created</span>
                                <span class="detail-value">Mar 27, 2026</span>
                            </div>
                        </div>
                    </div>
                    <div class="task-footer">
                        <div class="task-assignee">
                            <span style="font-size: 0.8rem; color: #888;">Unassigned</span>
                        </div>
                        <button class="checkout-btn available" onclick="checkoutTask(2)">Checkout Task</button>
                    </div>
                </div>

                <!-- Task 3 - Claimed -->
                <div class="task-card claimed">
                    <div class="task-header">
                        <div class="task-info">
                            <h3 class="task-title">Research AI Agent Orchestration Patterns</h3>
                            <div class="task-meta">
                                <span class="task-badge priority-medium">Medium</span>
                                <span class="task-badge type-research">Research</span>
                            </div>
                        </div>
                        <div class="task-reward">
                            <div class="reward-value">$75</div>
                            <div class="reward-label">Reward</div>
                        </div>
                    </div>
                    <div class="task-body">
                        <p class="task-description">Research and document best practices for multi-agent orchestration. Focus on coordination patterns, task routing, and conflict resolution.</p>
                        <div class="task-details">
                            <div class="detail-item">
                                <span class="detail-label">Est. Time</span>
                                <span class="detail-value">⏱️ 2-3 hours</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Deadline</span>
                                <span class="detail-value">📅 Apr 3, 2026</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Skills</span>
                                <span class="detail-value">AI, Systems Design</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Created</span>
                                <span class="detail-value">Mar 26, 2026</span>
                            </div>
                        </div>
                    </div>
                    <div class="task-footer">
                        <div class="task-assignee">
                            <div class="assignee-avatar">AS</div>
                            <span style="font-size: 0.8rem; color: #4ade80;">Alex Smith</span>
                        </div>
                        <button class="checkout-btn claimed">Claimed</button>
                    </div>
                </div>

                <!-- Task 4 -->
                <div class="task-card">
                    <div class="task-header">
                        <div class="task-info">
                            <h3 class="task-title">Write API Documentation - Endpoints v2</h3>
                            <div class="task-meta">
                                <span class="task-badge priority-low">Low</span>
                                <span class="task-badge type-documentation">Documentation</span>
                            </div>
                        </div>
                        <div class="task-reward">
                            <div class="reward-value">$50</div>
                            <div class="reward-label">Reward</div>
                        </div>
                    </div>
                    <div class="task-body">
                        <p class="task-description">Update API documentation for version 2 endpoints. Include request/response examples, error codes, and authentication requirements.</p>
                        <div class="task-details">
                            <div class="detail-item">
                                <span class="detail-label">Est. Time</span>
                                <span class="detail-value">⏱️ 2-3 hours</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Deadline</span>
                                <span class="detail-value">📅 Apr 10, 2026</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Skills</span>
                                <span class="detail-value">Technical Writing</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Created</span>
                                <span class="detail-value">Mar 25, 2026</span>
                            </div>
                        </div>
                    </div>
                    <div class="task-footer">
                        <div class="task-assignee">
                            <span style="font-size: 0.8rem; color: #888;">Unassigned</span>
                        </div>
                        <button class="checkout-btn available" onclick="checkoutTask(4)">Checkout Task</button>
                    </div>
                </div>

                <!-- Task 5 -->
                <div class="task-card">
                    <div class="task-header">
                        <div class="task-info">
                            <h3 class="task-title">Fix Mobile Responsive Issues - Dashboard</h3>
                            <div class="task-meta">
                                <span class="task-badge priority-high">High</span>
                                <span class="task-badge type-development">Development</span>
                            </div>
                        </div>
                        <div class="task-reward">
                            <div class="reward-value">$80</div>
                            <div class="reward-label">Reward</div>
                        </div>
                    </div>
                    <div class="task-body">
                        <p class="task-description">Address responsive layout issues on mobile devices. Focus on navigation menu, card layouts, and table displays.</p>
                        <div class="task-details">
                            <div class="detail-item">
                                <span class="detail-label">Est. Time</span>
                                <span class="detail-value">⏱️ 2-4 hours</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Deadline</span>
                                <span class="detail-value">📅 Apr 2, 2026</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Skills</span>
                                <span class="detail-value">CSS, Responsive</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Created</span>
                                <span class="detail-value">Mar 29, 2026</span>
                            </div>
                        </div>
                    </div>
                    <div class="task-footer">
                        <div class="task-assignee">
                            <span style="font-size: 0.8rem; color: #888;">Unassigned</span>
                        </div>
                        <button class="checkout-btn available" onclick="checkoutTask(5)">Checkout Task</button>
                    </div>
                </div>
            </div>
        </div>

        <aside class="sidebar">
            <!-- Active Tasks -->
            <div class="sidebar-card">
                <div class="sidebar-header">
                    <h3>My Active Tasks</h3>
                </div>
                <div class="sidebar-content">
                    <div class="active-task-item">
                        <div class="active-task-title">Implement User Auth Flow</div>
                        <div class="active-task-progress">
                            <div class="progress-mini">
                                <div class="progress-mini-fill" style="width: 75%"></div>
                            </div>
                            <span class="progress-time">2h 15m</span>
                        </div>
                    </div>
                    <div class="active-task-item">
                        <div class="active-task-title">Database Schema Update</div>
                        <div class="active-task-progress">
                            <div class="progress-mini">
                                <div class="progress-mini-fill" style="width: 30%"></div>
                            </div>
                            <span class="progress-time">45m</span>
                        </div>
                    </div>
                    <div class="empty-state" style="padding: 20px;">
                        <div class="icon">📋</div>
                        <p style="font-size: 0.8rem;">2 active tasks</p>
                    </div>
                </div>
            </div>

            <!-- Time Tracker -->
            <div class="sidebar-card">
                <div class="sidebar-header">
                    <h3>Time Tracker</h3>
                </div>
                <div class="sidebar-content">
                    <div class="time-tracker">
                        <div class="time-display" id="timer">00:00:00</div>
                        <div class="time-controls">
                            <button class="time-btn start" onclick="startTimer()">Start</button>
                            <button class="time-btn pause" onclick="pauseTimer()">Pause</button>
                            <button class="time-btn stop" onclick="stopTimer()">Stop</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Leaderboard -->
            <div class="sidebar-card">
                <div class="sidebar-header">
                    <h3>Leaderboard</h3>
                </div>
                <div class="sidebar-content">
                    <div class="leaderboard-item">
                        <div class="leaderboard-rank gold">1</div>
                        <div class="leaderboard-avatar" style="background: linear-gradient(135deg, #818cf8, #6366f1);">AS</div>
                        <div class="leaderboard-info">
                            <div class="leaderboard-name">Alex Smith</div>
                            <div class="leaderboard-stats">24 tasks • $2,450</div>
                        </div>
                        <div class="leaderboard-score">2,450</div>
                    </div>
                    <div class="leaderboard-item">
                        <div class="leaderboard-rank silver">2</div>
                        <div class="leaderboard-avatar" style="background: linear-gradient(135deg, #f472b6, #ec4899);">MJ</div>
                        <div class="leaderboard-info">
                            <div class="leaderboard-name">Maria Johnson</div>
                            <div class="leaderboard-stats">21 tasks • $1,890</div>
                        </div>
                        <div class="leaderboard-score">1,890</div>
                    </div>
                    <div class="leaderboard-item">
                        <div class="leaderboard-rank bronze">3</div>
                        <div class="leaderboard-avatar" style="background: linear-gradient(135deg, #fb923c, #f97316);">DW</div>
                        <div class="leaderboard-info">
                            <div class="leaderboard-name">David Williams</div>
                            <div class="leaderboard-stats">18 tasks • $1,650</div>
                        </div>
                        <div class="leaderboard-score">1,650</div>
                    </div>
                    <div class="leaderboard-item">
                        <div class="leaderboard-rank default">4</div>
                        <div class="leaderboard-avatar" style="background: linear-gradient(135deg, #a78bfa, #8b5cf6);">SC</div>
                        <div class="leaderboard-info">
                            <div class="leaderboard-name">Sarah Chen</div>
                            <div class="leaderboard-stats">15 tasks • $1,320</div>
                        </div>
                        <div class="leaderboard-score">1,320</div>
                    </div>
                </div>
            </div>

            <!-- Submit Completion -->
            <div class="sidebar-card">
                <div class="sidebar-header">
                    <h3>Submit Completion</h3>
                </div>
                <div class="sidebar-content">
                    <div class="completion-form">
                        <textarea class="completion-textarea" placeholder="Describe what you completed..."></textarea>
                        <button class="submit-btn">Submit for Review</button>
                    </div>
                </div>
            </div>
        </aside>
    </div>

    <script>
        let timerInterval = null;
        let seconds = 0;

        function formatTime(secs) {
            const h = Math.floor(secs / 3600);
            const m = Math.floor((secs % 3600) / 60);
            const s = secs % 60;
            return [h, m, s].map(v => v.toString().padStart(2, '0')).join(':');
        }

        function startTimer() {
            if (!timerInterval) {
                timerInterval = setInterval(() => {
                    seconds++;
                    document.getElementById('timer').textContent = formatTime(seconds);
                }, 1000);
            }
        }

        function pauseTimer() {
            clearInterval(timerInterval);
            timerInterval = null;
        }

        function stopTimer() {
            pauseTimer();
            seconds = 0;
            document.getElementById('timer').textContent = '00:00:00';
        }

        function checkoutTask(id) {
            const card = document.querySelector(`.task-card:nth-child(${id})`);
            if (card) {
                card.classList.add('claimed');
                const btn = card.querySelector('.checkout-btn');
                btn.textContent = 'Claimed';
                btn.classList.remove('available');
                btn.classList.add('claimed');
            }
        }
    </script>
    <?php wp_footer(); ?>
</body>
</html>
