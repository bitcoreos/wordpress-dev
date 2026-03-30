<?php
/**
 * Template Name: Goals
 * Description: Strategic goals tracking with OKR-style hierarchy
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
        .goals-container {
            max-width: 1400px;
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
        .filter-controls {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .filter-btn {
            padding: 8px 16px;
            font-size: 0.85rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: #888;
            cursor: pointer;
            transition: all 0.2s;
        }
        .filter-btn:hover {
            border-color: #4ade80;
            color: #4ade80;
        }
        .filter-btn.active {
            background: rgba(74, 222, 128, 0.2);
            border-color: #4ade80;
            color: #4ade80;
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
            min-width: 200px;
            transition: all 0.3s;
        }
        .search-input:focus {
            border-color: #4ade80;
            box-shadow: 0 0 0 3px rgba(74, 222, 128, 0.1);
        }
        .search-input::placeholder { color: #666; }
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 40px;
        }
        .stat-card {
            background: rgba(17, 17, 24, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
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
        .stat-card.green .stat-value { color: #4ade80; }
        .stat-card.blue .stat-value { color: #818cf8; }
        .stat-card.orange .stat-value { color: #fb923c; }
        .stat-card.red .stat-value { color: #ef4444; }
        .goals-tree {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }
        .goal-card {
            background: rgba(17, 17, 24, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.3s;
        }
        .goal-card:hover {
            border-color: rgba(255, 255, 255, 0.2);
        }
        .goal-header {
            padding: 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        .goal-top-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }
        .goal-info {
            flex: 1;
        }
        .goal-title {
            font-size: 1.15rem;
            font-weight: 600;
            color: #fff;
            margin-bottom: 8px;
        }
        .goal-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }
        .priority-badge {
            padding: 4px 10px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-radius: 4px;
        }
        .priority-badge.p1 {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }
        .priority-badge.p2 {
            background: rgba(251, 146, 60, 0.2);
            color: #fb923c;
        }
        .priority-badge.p3 {
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
        .status-badge.on-track {
            background: rgba(74, 222, 128, 0.2);
            color: #4ade80;
        }
        .status-badge.at-risk {
            background: rgba(251, 146, 60, 0.2);
            color: #fb923c;
        }
        .status-badge.blocked {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }
        .status-badge.complete {
            background: rgba(129, 140, 248, 0.2);
            color: #818cf8;
        }
        .goal-progress {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .progress-circle {
            position: relative;
            width: 64px;
            height: 64px;
        }
        .progress-circle svg {
            transform: rotate(-90deg);
        }
        .progress-circle .bg {
            fill: none;
            stroke: rgba(255, 255, 255, 0.1);
            stroke-width: 6;
        }
        .progress-circle .progress {
            fill: none;
            stroke-width: 6;
            stroke-linecap: round;
            transition: stroke-dashoffset 0.5s ease;
        }
        .progress-circle .progress.green { stroke: #4ade80; }
        .progress-circle .progress.orange { stroke: #fb923c; }
        .progress-circle .progress.blue { stroke: #818cf8; }
        .progress-circle .value {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            font-weight: 600;
            color: #fff;
        }
        .timeline-info {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.8rem;
            color: #888;
            margin-top: 8px;
        }
        .timeline-info svg {
            opacity: 0.6;
        }
        .goal-body {
            padding: 0 24px 24px;
        }
        .key-results {
            margin-top: 20px;
        }
        .key-results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .key-results-title {
            font-size: 0.85rem;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .key-results-count {
            font-size: 0.75rem;
            color: #666;
        }
        .key-result-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 10px;
            margin-bottom: 8px;
            transition: all 0.2s;
        }
        .key-result-item:hover {
            background: rgba(255, 255, 255, 0.05);
        }
        .kr-checkbox {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .kr-checkbox.checked {
            background: rgba(74, 222, 128, 0.2);
            border-color: #4ade80;
        }
        .kr-checkbox.checked::after {
            content: '✓';
            color: #4ade80;
            font-size: 0.7rem;
        }
        .kr-content {
            flex: 1;
        }
        .kr-title {
            font-size: 0.9rem;
            color: #e0e0e0;
        }
        .kr-title.completed {
            text-decoration: line-through;
            color: #888;
        }
        .kr-progress-bar {
            height: 4px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 2px;
            margin-top: 8px;
            overflow: hidden;
        }
        .kr-progress-fill {
            height: 100%;
            border-radius: 2px;
            transition: width 0.3s ease;
        }
        .kr-progress-fill.green { background: #4ade80; }
        .kr-progress-fill.orange { background: #fb923c; }
        .kr-progress-fill.blue { background: #818cf8; }
        .kr-score {
            font-size: 0.85rem;
            font-weight: 600;
            color: #fff;
            min-width: 50px;
            text-align: right;
        }
        .action-bar {
            display: flex;
            gap: 12px;
            margin-top: 32px;
            justify-content: center;
        }
        .action-btn {
            padding: 12px 24px;
            font-size: 0.9rem;
            font-weight: 500;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .action-btn.primary {
            background: linear-gradient(135deg, #4ade80, #22c55e);
            color: #000;
        }
        .action-btn.primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(74, 222, 128, 0.4);
        }
        .action-btn.secondary {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #888;
        }
        .action-btn.secondary:hover {
            border-color: #4ade80;
            color: #4ade80;
        }
        .expand-toggle {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            font-size: 0.8rem;
            background: none;
            border: none;
            color: #888;
            cursor: pointer;
            transition: all 0.2s;
        }
        .expand-toggle:hover {
            color: #4ade80;
        }
        .expand-toggle svg {
            transition: transform 0.3s;
        }
        .expand-toggle.expanded svg {
            transform: rotate(180deg);
        }
        .key-results.collapsed {
            display: none;
        }
        @media (max-width: 768px) {
            .header-section { flex-direction: column; }
            .goal-top-row { flex-direction: column; gap: 16px; }
            .goal-progress { align-self: flex-start; }
            .stats-row { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body class="cosmic-bg">
    <?php include(get_template_directory() . "/nav-header.php"); ?>
    <div id="goals-container" class="goals-container bitcore-container">
        <section class="header-section">
            <div>
                <h1>Goals & Objectives</h1>
                <p class="subtitle">Strategic goal tracking with OKR hierarchy</p>
            </div>
            <div class="filter-controls">
                <input type="text" class="search-input" placeholder="Search goals...">
                <button class="filter-btn active">All</button>
                <button class="filter-btn">On Track</button>
                <button class="filter-btn">At Risk</button>
                <button class="filter-btn">Blocked</button>
                <button class="filter-btn">Complete</button>
            </div>
        </section>

        <section class="stats-row">
            <div class="stat-card green">
                <div class="stat-value">12</div>
                <div class="stat-label">On Track</div>
            </div>
            <div class="stat-card orange">
                <div class="stat-value">5</div>
                <div class="stat-label">At Risk</div>
            </div>
            <div class="stat-card red">
                <div class="stat-value">2</div>
                <div class="stat-label">Blocked</div>
            </div>
            <div class="stat-card blue">
                <div class="stat-value">8</div>
                <div class="stat-label">Complete</div>
            </div>
        </section>

        <div class="goals-tree">
            <!-- Goal 1 -->
            <div class="goal-card">
                <div class="goal-header">
                    <div class="goal-top-row">
                        <div class="goal-info">
                            <h3 class="goal-title">Launch BITCORE Platform v2.0</h3>
                            <div class="goal-meta">
                                <span class="priority-badge p1">P1</span>
                                <span class="status-badge on-track">On Track</span>
                            </div>
                            <div class="timeline-info">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                Q2 2026 • Due: June 30, 2026
                            </div>
                        </div>
                        <div class="goal-progress">
                            <div class="progress-circle">
                                <svg width="64" height="64" viewBox="0 0 64 64">
                                    <circle class="bg" cx="32" cy="32" r="28"/>
                                    <circle class="progress green" cx="32" cy="32" r="28" stroke-dasharray="176" stroke-dashoffset="44"/>
                                </svg>
                                <span class="value">75%</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="goal-body">
                    <div class="key-results">
                        <div class="key-results-header">
                            <span class="key-results-title">Key Results</span>
                            <span class="key-results-count">3 of 4 complete</span>
                        </div>
                        <div class="key-result-item">
                            <div class="kr-checkbox checked"></div>
                            <div class="kr-content">
                                <div class="kr-title completed">Complete core architecture redesign</div>
                                <div class="kr-progress-bar"><div class="kr-progress-fill green" style="width: 100%"></div></div>
                            </div>
                            <span class="kr-score">100%</span>
                        </div>
                        <div class="key-result-item">
                            <div class="kr-checkbox checked"></div>
                            <div class="kr-content">
                                <div class="kr-title completed">Deploy multi-agent orchestration layer</div>
                                <div class="kr-progress-bar"><div class="kr-progress-fill green" style="width: 100%"></div></div>
                            </div>
                            <span class="kr-score">100%</span>
                        </div>
                        <div class="key-result-item">
                            <div class="kr-checkbox checked"></div>
                            <div class="kr-content">
                                <div class="kr-title completed">Integrate n8n workflow automation</div>
                                <div class="kr-progress-bar"><div class="kr-progress-fill green" style="width: 100%"></div></div>
                            </div>
                            <span class="kr-score">100%</span>
                        </div>
                        <div class="key-result-item">
                            <div class="kr-checkbox"></div>
                            <div class="kr-content">
                                <div class="kr-title">Launch public beta with 100 users</div>
                                <div class="kr-progress-bar"><div class="kr-progress-fill orange" style="width: 45%"></div></div>
                            </div>
                            <span class="kr-score">45%</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Goal 2 -->
            <div class="goal-card">
                <div class="goal-header">
                    <div class="goal-top-row">
                        <div class="goal-info">
                            <h3 class="goal-title">Scale Infrastructure for 10K Users</h3>
                            <div class="goal-meta">
                                <span class="priority-badge p1">P1</span>
                                <span class="status-badge at-risk">At Risk</span>
                            </div>
                            <div class="timeline-info">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                Q2 2026 • Due: May 15, 2026
                            </div>
                        </div>
                        <div class="goal-progress">
                            <div class="progress-circle">
                                <svg width="64" height="64" viewBox="0 0 64 64">
                                    <circle class="bg" cx="32" cy="32" r="28"/>
                                    <circle class="progress orange" cx="32" cy="32" r="28" stroke-dasharray="176" stroke-dashoffset="88"/>
                                </svg>
                                <span class="value">50%</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="goal-body">
                    <div class="key-results">
                        <div class="key-results-header">
                            <span class="key-results-title">Key Results</span>
                            <span class="key-results-count">2 of 4 complete</span>
                        </div>
                        <div class="key-result-item">
                            <div class="kr-checkbox checked"></div>
                            <div class="kr-content">
                                <div class="kr-title completed">Migrate to Kubernetes cluster</div>
                                <div class="kr-progress-bar"><div class="kr-progress-fill green" style="width: 100%"></div></div>
                            </div>
                            <span class="kr-score">100%</span>
                        </div>
                        <div class="key-result-item">
                            <div class="kr-checkbox checked"></div>
                            <div class="kr-content">
                                <div class="kr-title completed">Implement auto-scaling policies</div>
                                <div class="kr-progress-bar"><div class="kr-progress-fill green" style="width: 100%"></div></div>
                            </div>
                            <span class="kr-score">100%</span>
                        </div>
                        <div class="key-result-item">
                            <div class="kr-checkbox"></div>
                            <div class="kr-content">
                                <div class="kr-title">Configure CDN and edge caching</div>
                                <div class="kr-progress-bar"><div class="kr-progress-fill orange" style="width: 30%"></div></div>
                            </div>
                            <span class="kr-score">30%</span>
                        </div>
                        <div class="key-result-item">
                            <div class="kr-checkbox"></div>
                            <div class="kr-content">
                                <div class="kr-title">Load test with 10K concurrent users</div>
                                <div class="kr-progress-bar"><div class="kr-progress-fill" style="width: 0%; background: #444"></div></div>
                            </div>
                            <span class="kr-score">0%</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Goal 3 -->
            <div class="goal-card">
                <div class="goal-header">
                    <div class="goal-top-row">
                        <div class="goal-info">
                            <h3 class="goal-title">Develop AI Agent Marketplace</h3>
                            <div class="goal-meta">
                                <span class="priority-badge p2">P2</span>
                                <span class="status-badge blocked">Blocked</span>
                            </div>
                            <div class="timeline-info">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                Q3 2026 • Due: August 15, 2026
                            </div>
                        </div>
                        <div class="goal-progress">
                            <div class="progress-circle">
                                <svg width="64" height="64" viewBox="0 0 64 64">
                                    <circle class="bg" cx="32" cy="32" r="28"/>
                                    <circle class="progress" cx="32" cy="32" r="28" stroke-dasharray="176" stroke-dashoffset="158" style="stroke: #ef4444"/>
                                </svg>
                                <span class="value">10%</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="goal-body">
                    <div class="key-results">
                        <div class="key-results-header">
                            <span class="key-results-title">Key Results</span>
                            <span class="key-results-count">0 of 4 complete</span>
                        </div>
                        <div class="key-result-item">
                            <div class="kr-checkbox"></div>
                            <div class="kr-content">
                                <div class="kr-title">Design marketplace UI/UX</div>
                                <div class="kr-progress-bar"><div class="kr-progress-fill" style="width: 25%; background: #666"></div></div>
                            </div>
                            <span class="kr-score">25%</span>
                        </div>
                        <div class="key-result-item">
                            <div class="kr-checkbox"></div>
                            <div class="kr-content">
                                <div class="kr-title">Build agent submission workflow</div>
                                <div class="kr-progress-bar"><div class="kr-progress-fill" style="width: 0%; background: #444"></div></div>
                            </div>
                            <span class="kr-score">0%</span>
                        </div>
                        <div class="key-result-item">
                            <div class="kr-checkbox"></div>
                            <div class="kr-content">
                                <div class="kr-title">Implement payment integration</div>
                                <div class="kr-progress-bar"><div class="kr-progress-fill" style="width: 0%; background: #444"></div></div>
                            </div>
                            <span class="kr-score">0%</span>
                        </div>
                        <div class="key-result-item">
                            <div class="kr-checkbox"></div>
                            <div class="kr-content">
                                <div class="kr-title">Launch with 10 featured agents</div>
                                <div class="kr-progress-bar"><div class="kr-progress-fill" style="width: 0%; background: #444"></div></div>
                            </div>
                            <span class="kr-score">0%</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Goal 4 -->
            <div class="goal-card">
                <div class="goal-header">
                    <div class="goal-top-row">
                        <div class="goal-info">
                            <h3 class="goal-title">Establish Community Platform</h3>
                            <div class="goal-meta">
                                <span class="priority-badge p3">P3</span>
                                <span class="status-badge complete">Complete</span>
                            </div>
                            <div class="timeline-info">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                Q1 2026 • Completed: March 15, 2026
                            </div>
                        </div>
                        <div class="goal-progress">
                            <div class="progress-circle">
                                <svg width="64" height="64" viewBox="0 0 64 64">
                                    <circle class="bg" cx="32" cy="32" r="28"/>
                                    <circle class="progress blue" cx="32" cy="32" r="28" stroke-dasharray="176" stroke-dashoffset="0"/>
                                </svg>
                                <span class="value">100%</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="goal-body">
                    <div class="key-results">
                        <div class="key-results-header">
                            <span class="key-results-title">Key Results</span>
                            <span class="key-results-count">4 of 4 complete</span>
                        </div>
                        <div class="key-result-item">
                            <div class="kr-checkbox checked"></div>
                            <div class="kr-content">
                                <div class="kr-title completed">Deploy Discourse forum</div>
                                <div class="kr-progress-bar"><div class="kr-progress-fill blue" style="width: 100%"></div></div>
                            </div>
                            <span class="kr-score">100%</span>
                        </div>
                        <div class="key-result-item">
                            <div class="kr-checkbox checked"></div>
                            <div class="kr-content">
                                <div class="kr-title completed">Configure SSO with BITwiki</div>
                                <div class="kr-progress-bar"><div class="kr-progress-fill blue" style="width: 100%"></div></div>
                            </div>
                            <span class="kr-score">100%</span>
                        </div>
                        <div class="key-result-item">
                            <div class="kr-checkbox checked"></div>
                            <div class="kr-content">
                                <div class="kr-title completed">Create welcome content and guidelines</div>
                                <div class="kr-progress-bar"><div class="kr-progress-fill blue" style="width: 100%"></div></div>
                            </div>
                            <span class="kr-score">100%</span>
                        </div>
                        <div class="key-result-item">
                            <div class="kr-checkbox checked"></div>
                            <div class="kr-content">
                                <div class="kr-title completed">Seed with 50 initial discussions</div>
                                <div class="kr-progress-bar"><div class="kr-progress-fill blue" style="width: 100%"></div></div>
                            </div>
                            <span class="kr-score">100%</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="action-bar">
            <button class="action-btn secondary">Export Report</button>
            <button class="action-btn primary">Add Goal</button>
        </div>
    </div>
    <?php wp_footer(); ?>
</body>
</html>
