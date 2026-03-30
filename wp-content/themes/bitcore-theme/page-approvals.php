<?php
/**
 * Template Name: Approvals
 * Description: Pending approval requests queue
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
        .approvals-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
            position: relative;
            z-index: 1;
        }
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 32px;
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
        .batch-actions {
            display: flex;
            gap: 12px;
        }
        .batch-btn {
            padding: 10px 20px;
            font-size: 0.85rem;
            font-weight: 500;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .batch-btn.approve {
            background: rgba(74, 222, 128, 0.2);
            border: 1px solid rgba(74, 222, 128, 0.3);
            color: #4ade80;
        }
        .batch-btn.approve:hover {
            background: rgba(74, 222, 128, 0.3);
        }
        .batch-btn.reject {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #ef4444;
        }
        .batch-btn.reject:hover {
            background: rgba(239, 68, 68, 0.3);
        }
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }
        .stat-card {
            background: rgba(17, 17, 24, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .stat-card:hover {
            border-color: rgba(255, 255, 255, 0.2);
        }
        .stat-card.active {
            border-color: #4ade80;
        }
        .stat-card .stat-value {
            font-size: 1.75rem;
            font-weight: 600;
        }
        .stat-card .stat-label {
            font-size: 0.8rem;
            color: #888;
            margin-top: 4px;
        }
        .stat-card.yellow .stat-value { color: #fbbf24; }
        .stat-card.green .stat-value { color: #4ade80; }
        .stat-card.red .stat-value { color: #ef4444; }
        .stat-card.blue .stat-value { color: #818cf8; }
        .filter-bar {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
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
            min-width: 140px;
        }
        .filter-select option { background: #111118; }
        .approvals-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .approval-card {
            background: rgba(17, 17, 24, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.3s;
        }
        .approval-card:hover {
            border-color: rgba(255, 255, 255, 0.2);
        }
        .approval-card.selected {
            border-color: #4ade80;
            box-shadow: 0 0 0 1px rgba(74, 222, 128, 0.3);
        }
        .approval-header {
            display: flex;
            align-items: center;
            padding: 20px 24px;
            gap: 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        .checkbox-wrapper {
            display: flex;
            align-items: center;
        }
        .custom-checkbox {
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .custom-checkbox:hover {
            border-color: #4ade80;
        }
        .custom-checkbox.checked {
            background: rgba(74, 222, 128, 0.2);
            border-color: #4ade80;
        }
        .custom-checkbox.checked::after {
            content: '✓';
            color: #4ade80;
            font-size: 0.75rem;
        }
        .requester-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            font-weight: 600;
            color: #fff;
        }
        .requester-avatar.avatar-1 { background: linear-gradient(135deg, #818cf8, #6366f1); }
        .requester-avatar.avatar-2 { background: linear-gradient(135deg, #f472b6, #ec4899); }
        .requester-avatar.avatar-3 { background: linear-gradient(135deg, #fb923c, #f97316); }
        .requester-avatar.avatar-4 { background: linear-gradient(135deg, #a78bfa, #8b5cf6); }
        .requester-avatar.avatar-5 { background: linear-gradient(135deg, #4ade80, #22c55e); }
        .approval-info {
            flex: 1;
        }
        .approval-title {
            font-size: 1rem;
            font-weight: 600;
            color: #fff;
            margin-bottom: 4px;
        }
        .approval-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            font-size: 0.8rem;
            color: #888;
        }
        .approval-meta span {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .type-badge {
            padding: 4px 10px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-radius: 4px;
        }
        .type-badge.budget { background: rgba(74, 222, 128, 0.2); color: #4ade80; }
        .type-badge.access { background: rgba(129, 140, 248, 0.2); color: #818cf8; }
        .type-badge.deploy { background: rgba(251, 146, 60, 0.2); color: #fb923c; }
        .type-badge.content { background: rgba(244, 114, 182, 0.2); color: #f472b6; }
        .type-badge.hire { background: rgba(167, 139, 250, 0.2); color: #a78bfa; }
        .priority-indicator {
            padding: 4px 10px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-radius: 4px;
        }
        .priority-indicator.high {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }
        .priority-indicator.medium {
            background: rgba(251, 191, 36, 0.2);
            color: #fbbf24;
        }
        .priority-indicator.low {
            background: rgba(129, 140, 248, 0.2);
            color: #818cf8;
        }
        .status-badge {
            padding: 4px 10px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-radius: 4px;
        }
        .status-badge.pending {
            background: rgba(251, 191, 36, 0.2);
            color: #fbbf24;
        }
        .status-badge.approved {
            background: rgba(74, 222, 128, 0.2);
            color: #4ade80;
        }
        .status-badge.rejected {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }
        .approval-body {
            padding: 20px 24px;
        }
        .approval-description {
            font-size: 0.9rem;
            color: #aaa;
            margin-bottom: 16px;
            line-height: 1.6;
        }
        .approval-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            padding: 16px;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 10px;
        }
        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .detail-label {
            font-size: 0.75rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .detail-value {
            font-size: 0.9rem;
            color: #e0e0e0;
        }
        .approval-actions {
            display: flex;
            gap: 12px;
            padding: 16px 24px;
            background: rgba(255, 255, 255, 0.02);
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }
        .action-btn {
            padding: 10px 24px;
            font-size: 0.85rem;
            font-weight: 500;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .action-btn.approve {
            background: linear-gradient(135deg, #4ade80, #22c55e);
            color: #000;
        }
        .action-btn.approve:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(74, 222, 128, 0.4);
        }
        .action-btn.reject {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #ef4444;
        }
        .action-btn.reject:hover {
            background: rgba(239, 68, 68, 0.3);
        }
        .action-btn.details {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #888;
        }
        .action-btn.details:hover {
            border-color: #4ade80;
            color: #4ade80;
        }
        .empty-state {
            text-align: center;
            padding: 80px 40px;
            background: rgba(17, 17, 24, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
        }
        .empty-state .icon {
            font-size: 3rem;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        .empty-state h3 {
            font-size: 1.25rem;
            color: #fff;
            margin-bottom: 8px;
        }
        .empty-state p {
            font-size: 0.9rem;
            color: #888;
        }
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.8);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        .modal-overlay.visible { display: flex; }
        .modal-content {
            background: rgba(17, 17, 24, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 32px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .modal-header h3 {
            font-size: 1.25rem;
            color: #fff;
        }
        .modal-close {
            background: none;
            border: none;
            color: #888;
            font-size: 1.5rem;
            cursor: pointer;
        }
        .modal-close:hover { color: #fff; }
        .modal-body p {
            margin-bottom: 16px;
            color: #aaa;
            line-height: 1.6;
        }
        .modal-footer {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 24px;
        }
        @media (max-width: 768px) {
            .header-section { flex-direction: column; }
            .batch-actions { width: 100%; justify-content: stretch; }
            .batch-btn { flex: 1; }
            .approval-header { flex-wrap: wrap; }
            .approval-actions { flex-wrap: wrap; }
            .action-btn { flex: 1; text-align: center; }
        }
    </style>
</head>
<body class="cosmic-bg">
    <?php include(get_template_directory() . "/nav-header.php"); ?>
    <div id="approvals-container" class="approvals-container bitcore-container">
        <section class="header-section">
            <div>
                <h1>Approvals Queue</h1>
                <p class="subtitle">Pending requests requiring your review</p>
            </div>
            <div class="batch-actions">
                <button class="batch-btn approve" onclick="batchApprove()">Approve Selected</button>
                <button class="batch-btn reject" onclick="batchReject()">Reject Selected</button>
            </div>
        </section>

        <section class="stats-row">
            <div class="stat-card yellow active">
                <div class="stat-value">8</div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card green">
                <div class="stat-value">24</div>
                <div class="stat-label">Approved</div>
            </div>
            <div class="stat-card red">
                <div class="stat-value">3</div>
                <div class="stat-label">Rejected</div>
            </div>
            <div class="stat-card blue">
                <div class="stat-value">35</div>
                <div class="stat-label">Total</div>
            </div>
        </section>

        <div class="filter-bar">
            <input type="text" class="search-input" placeholder="Search approvals...">
            <select class="filter-select">
                <option value="">All Types</option>
                <option value="budget">Budget</option>
                <option value="access">Access</option>
                <option value="deploy">Deploy</option>
                <option value="content">Content</option>
                <option value="hire">Hire</option>
            </select>
            <select class="filter-select">
                <option value="">All Priorities</option>
                <option value="high">High</option>
                <option value="medium">Medium</option>
                <option value="low">Low</option>
            </select>
            <select class="filter-select">
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
            </select>
        </div>

        <div class="approvals-list">
            <!-- Approval 1 -->
            <div class="approval-card" data-id="1">
                <div class="approval-header">
                    <div class="checkbox-wrapper">
                        <div class="custom-checkbox" onclick="toggleCheckbox(this)"></div>
                    </div>
                    <div class="requester-avatar avatar-1">AS</div>
                    <div class="approval-info">
                        <h3 class="approval-title">Budget Increase Request - Infrastructure</h3>
                        <div class="approval-meta">
                            <span class="type-badge budget">Budget</span>
                            <span class="priority-indicator high">High</span>
                            <span>By Alex Smith</span>
                            <span>Mar 29, 2026</span>
                        </div>
                    </div>
                    <span class="status-badge pending">Pending</span>
                </div>
                <div class="approval-body">
                    <p class="approval-description">Requesting additional $5,000 for cloud infrastructure scaling to support the upcoming product launch and expected traffic surge.</p>
                    <div class="approval-details">
                        <div class="detail-item">
                            <span class="detail-label">Amount</span>
                            <span class="detail-value">$5,000.00</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Category</span>
                            <span class="detail-value">Infrastructure</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Justification</span>
                            <span class="detail-value">Product launch scaling</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Expected ROI</span>
                            <span class="detail-value">2.5x within 3 months</span>
                        </div>
                    </div>
                </div>
                <div class="approval-actions">
                    <button class="action-btn approve" onclick="approveItem(1)">Approve</button>
                    <button class="action-btn reject" onclick="rejectItem(1)">Reject</button>
                    <button class="action-btn details" onclick="showDetails(1)">View Details</button>
                </div>
            </div>

            <!-- Approval 2 -->
            <div class="approval-card" data-id="2">
                <div class="approval-header">
                    <div class="checkbox-wrapper">
                        <div class="custom-checkbox" onclick="toggleCheckbox(this)"></div>
                    </div>
                    <div class="requester-avatar avatar-2">MJ</div>
                    <div class="approval-info">
                        <h3 class="approval-title">Production Deployment - v2.1.0</h3>
                        <div class="approval-meta">
                            <span class="type-badge deploy">Deploy</span>
                            <span class="priority-indicator high">High</span>
                            <span>By Maria Johnson</span>
                            <span>Mar 28, 2026</span>
                        </div>
                    </div>
                    <span class="status-badge pending">Pending</span>
                </div>
                <div class="approval-body">
                    <p class="approval-description">Production deployment for version 2.1.0 containing critical security patches and performance improvements. All tests passing in staging.</p>
                    <div class="approval-details">
                        <div class="detail-item">
                            <span class="detail-label">Version</span>
                            <span class="detail-value">v2.1.0</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Environment</span>
                            <span class="detail-value">Production</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Tests</span>
                            <span class="detail-value">142/142 Passed</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Rollback</span>
                            <span class="detail-value">Automated</span>
                        </div>
                    </div>
                </div>
                <div class="approval-actions">
                    <button class="action-btn approve" onclick="approveItem(2)">Approve</button>
                    <button class="action-btn reject" onclick="rejectItem(2)">Reject</button>
                    <button class="action-btn details" onclick="showDetails(2)">View Details</button>
                </div>
            </div>

            <!-- Approval 3 -->
            <div class="approval-card" data-id="3">
                <div class="approval-header">
                    <div class="checkbox-wrapper">
                        <div class="custom-checkbox" onclick="toggleCheckbox(this)"></div>
                    </div>
                    <div class="requester-avatar avatar-3">DW</div>
                    <div class="approval-info">
                        <h3 class="approval-title">API Access Request - External Partner</h3>
                        <div class="approval-meta">
                            <span class="type-badge access">Access</span>
                            <span class="priority-indicator medium">Medium</span>
                            <span>By David Williams</span>
                            <span>Mar 28, 2026</span>
                        </div>
                    </div>
                    <span class="status-badge pending">Pending</span>
                </div>
                <div class="approval-body">
                    <p class="approval-description">Partner company requesting API access for integration with their platform. Scoped to read-only endpoints with rate limiting.</p>
                    <div class="approval-details">
                        <div class="detail-item">
                            <span class="detail-label">Partner</span>
                            <span class="detail-value">TechCorp Inc.</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Scope</span>
                            <span class="detail-value">Read-only API</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Rate Limit</span>
                            <span class="detail-value">1000 req/hour</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Duration</span>
                            <span class="detail-value">12 months</span>
                        </div>
                    </div>
                </div>
                <div class="approval-actions">
                    <button class="action-btn approve" onclick="approveItem(3)">Approve</button>
                    <button class="action-btn reject" onclick="rejectItem(3)">Reject</button>
                    <button class="action-btn details" onclick="showDetails(3)">View Details</button>
                </div>
            </div>

            <!-- Approval 4 -->
            <div class="approval-card" data-id="4">
                <div class="approval-header">
                    <div class="checkbox-wrapper">
                        <div class="custom-checkbox" onclick="toggleCheckbox(this)"></div>
                    </div>
                    <div class="requester-avatar avatar-4">SC</div>
                    <div class="approval-info">
                        <h3 class="approval-title">Blog Post Publication - AI Agents Guide</h3>
                        <div class="approval-meta">
                            <span class="type-badge content">Content</span>
                            <span class="priority-indicator low">Low</span>
                            <span>By Sarah Chen</span>
                            <span>Mar 27, 2026</span>
                        </div>
                    </div>
                    <span class="status-badge pending">Pending</span>
                </div>
                <div class="approval-body">
                    <p class="approval-description">Comprehensive guide on implementing AI agents for enterprise workflows. Ready for publication on the company blog.</p>
                    <div class="approval-details">
                        <div class="detail-item">
                            <span class="detail-label">Word Count</span>
                            <span class="detail-value">3,200 words</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Category</span>
                            <span class="detail-value">Technical</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">SEO Score</span>
                            <span class="detail-value">85/100</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Schedule</span>
                            <span class="detail-value">Apr 2, 2026</span>
                        </div>
                    </div>
                </div>
                <div class="approval-actions">
                    <button class="action-btn approve" onclick="approveItem(4)">Approve</button>
                    <button class="action-btn reject" onclick="rejectItem(4)">Reject</button>
                    <button class="action-btn details" onclick="showDetails(4)">View Details</button>
                </div>
            </div>

            <!-- Approval 5 -->
            <div class="approval-card" data-id="5">
                <div class="approval-header">
                    <div class="checkbox-wrapper">
                        <div class="custom-checkbox" onclick="toggleCheckbox(this)"></div>
                    </div>
                    <div class="requester-avatar avatar-5">KL</div>
                    <div class="approval-info">
                        <h3 class="approval-title">Contractor Hire - Senior Frontend Developer</h3>
                        <div class="approval-meta">
                            <span class="type-badge hire">Hire</span>
                            <span class="priority-indicator medium">Medium</span>
                            <span>By Kevin Lee</span>
                            <span>Mar 26, 2026</span>
                        </div>
                    </div>
                    <span class="status-badge pending">Pending</span>
                </div>
                <div class="approval-body">
                    <p class="approval-description">Hiring recommendation for senior frontend developer to support the dashboard redesign project. 6-month contract with option to extend.</p>
                    <div class="approval-details">
                        <div class="detail-item">
                            <span class="detail-label">Position</span>
                            <span class="detail-value">Sr. Frontend Dev</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Rate</span>
                            <span class="detail-value">$85/hour</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Duration</span>
                            <span class="detail-value">6 months</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Start Date</span>
                            <span class="detail-value">Apr 15, 2026</span>
                        </div>
                    </div>
                </div>
                <div class="approval-actions">
                    <button class="action-btn approve" onclick="approveItem(5)">Approve</button>
                    <button class="action-btn reject" onclick="rejectItem(5)">Reject</button>
                    <button class="action-btn details" onclick="showDetails(5)">View Details</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Detail Modal -->
    <div class="modal-overlay" id="detailModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Request Details</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>Loading details...</p>
            </div>
            <div class="modal-footer">
                <button class="action-btn reject" onclick="rejectFromModal()">Reject</button>
                <button class="action-btn approve" onclick="approveFromModal()">Approve</button>
            </div>
        </div>
    </div>

    <script>
        function toggleCheckbox(el) {
            el.classList.toggle('checked');
            el.closest('.approval-card').classList.toggle('selected');
        }

        function approveItem(id) {
            const card = document.querySelector(`[data-id="${id}"]`);
            card.querySelector('.status-badge').textContent = 'Approved';
            card.querySelector('.status-badge').className = 'status-badge approved';
            card.style.opacity = '0.6';
        }

        function rejectItem(id) {
            const card = document.querySelector(`[data-id="${id}"]`);
            card.querySelector('.status-badge').textContent = 'Rejected';
            card.querySelector('.status-badge').className = 'status-badge rejected';
            card.style.opacity = '0.6';
        }

        function batchApprove() {
            document.querySelectorAll('.approval-card.selected').forEach(card => {
                const id = card.dataset.id;
                approveItem(id);
                card.classList.remove('selected');
                card.querySelector('.custom-checkbox').classList.remove('checked');
            });
        }

        function batchReject() {
            document.querySelectorAll('.approval-card.selected').forEach(card => {
                const id = card.dataset.id;
                rejectItem(id);
                card.classList.remove('selected');
                card.querySelector('.custom-checkbox').classList.remove('checked');
            });
        }

        function showDetails(id) {
            document.getElementById('detailModal').classList.add('visible');
        }

        function closeModal() {
            document.getElementById('detailModal').classList.remove('visible');
        }

        function approveFromModal() {
            closeModal();
        }

        function rejectFromModal() {
            closeModal();
        }
    </script>
    <?php wp_footer(); ?>
</body>
</html>
