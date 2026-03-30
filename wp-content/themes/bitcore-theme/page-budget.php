<?php
/**
 * Template Name: Budget
 * Description: Financial budget tracking and visualization
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
        .budget-container {
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
        .header-actions {
            display: flex;
            gap: 12px;
        }
        .action-btn {
            padding: 10px 20px;
            font-size: 0.85rem;
            font-weight: 500;
            border: none;
            border-radius: 8px;
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
        .overview-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        .overview-card {
            background: rgba(17, 17, 24, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 28px;
            transition: all 0.3s;
        }
        .overview-card:hover {
            border-color: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }
        .overview-card.alert {
            border-color: rgba(239, 68, 68, 0.3);
        }
        .overview-card.alert .card-icon {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }
        .card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }
        .card-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            background: rgba(74, 222, 128, 0.2);
            border-radius: 10px;
        }
        .card-label {
            font-size: 0.85rem;
            color: #888;
        }
        .card-value {
            font-size: 2.25rem;
            font-weight: 600;
            color: #fff;
            margin-bottom: 8px;
        }
        .card-value.green { color: #4ade80; }
        .card-value.orange { color: #fb923c; }
        .card-value.red { color: #ef4444; }
        .card-value.blue { color: #818cf8; }
        .card-change {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
        }
        .card-change.positive { color: #4ade80; }
        .card-change.negative { color: #ef4444; }
        .main-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
            margin-bottom: 40px;
        }
        @media (max-width: 1024px) {
            .main-grid { grid-template-columns: 1fr; }
        }
        .section-card {
            background: rgba(17, 17, 24, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            overflow: hidden;
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        .section-header h2 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #fff;
        }
        .section-tabs {
            display: flex;
            gap: 4px;
            background: rgba(255, 255, 255, 0.05);
            padding: 4px;
            border-radius: 8px;
        }
        .section-tab {
            padding: 6px 12px;
            font-size: 0.8rem;
            background: none;
            border: none;
            color: #888;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .section-tab:hover { color: #fff; }
        .section-tab.active {
            background: rgba(74, 222, 128, 0.2);
            color: #4ade80;
        }
        .section-content {
            padding: 24px;
        }
        .category-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .category-item {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .category-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .category-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .category-icon {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            border-radius: 8px;
        }
        .category-icon.dev { background: rgba(129, 140, 248, 0.2); }
        .category-icon.infra { background: rgba(251, 146, 60, 0.2); }
        .category-icon.ai { background: rgba(167, 139, 250, 0.2); }
        .category-icon.marketing { background: rgba(244, 114, 182, 0.2); }
        .category-icon.operations { background: rgba(74, 222, 128, 0.2); }
        .category-name {
            font-size: 0.95rem;
            color: #fff;
            font-weight: 500;
        }
        .category-amounts {
            text-align: right;
        }
        .amount-spent {
            font-size: 0.95rem;
            color: #fff;
            font-weight: 600;
        }
        .amount-budget {
            font-size: 0.8rem;
            color: #888;
        }
        .progress-wrapper {
            position: relative;
            height: 8px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            overflow: hidden;
        }
        .progress-bar {
            height: 100%;
            border-radius: 4px;
            transition: width 0.5s ease;
        }
        .progress-bar.green { background: linear-gradient(90deg, #4ade80, #22c55e); }
        .progress-bar.orange { background: linear-gradient(90deg, #fb923c, #f97316); }
        .progress-bar.red { background: linear-gradient(90deg, #ef4444, #dc2626); }
        .progress-bar.blue { background: linear-gradient(90deg, #818cf8, #6366f1); }
        .alert-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            font-size: 0.7rem;
            font-weight: 600;
            border-radius: 4px;
            margin-left: 8px;
        }
        .alert-badge.warning {
            background: rgba(251, 191, 36, 0.2);
            color: #fbbf24;
        }
        .alert-badge.danger {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }
        .timeline-placeholder {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        .timeline-title {
            font-size: 0.9rem;
            color: #888;
        }
        .timeline-legend {
            display: flex;
            gap: 16px;
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.75rem;
            color: #888;
        }
        .legend-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }
        .legend-dot.actual { background: #4ade80; }
        .legend-dot.projected { background: #818cf8; }
        .chart-area {
            height: 200px;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 12px;
            display: flex;
            align-items: flex-end;
            padding: 20px;
            gap: 8px;
        }
        .chart-bar-group {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }
        .chart-bars {
            display: flex;
            gap: 4px;
            align-items: flex-end;
            height: 140px;
        }
        .chart-bar {
            width: 16px;
            border-radius: 4px 4px 0 0;
            transition: height 0.5s ease;
        }
        .chart-bar.actual { background: linear-gradient(180deg, #4ade80, #22c55e); }
        .chart-bar.projected { background: linear-gradient(180deg, #818cf8, #6366f1); }
        .chart-label {
            font-size: 0.7rem;
            color: #666;
        }
        .transactions-section {
            margin-top: 40px;
        }
        .transactions-list {
            display: flex;
            flex-direction: column;
        }
        .transaction-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        .transaction-item:last-child { border-bottom: none; }
        .transaction-info {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .transaction-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            font-size: 1rem;
        }
        .transaction-icon.expense { background: rgba(239, 68, 68, 0.2); }
        .transaction-icon.income { background: rgba(74, 222, 128, 0.2); }
        .transaction-details h4 {
            font-size: 0.9rem;
            color: #fff;
            margin-bottom: 2px;
        }
        .transaction-details .meta {
            font-size: 0.8rem;
            color: #888;
        }
        .transaction-amount {
            font-size: 1rem;
            font-weight: 600;
        }
        .transaction-amount.expense { color: #ef4444; }
        .transaction-amount.income { color: #4ade80; }
        .filter-bar {
            display: flex;
            gap: 12px;
            padding: 16px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            flex-wrap: wrap;
        }
        .filter-input {
            padding: 8px 16px;
            font-size: 0.85rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: #fff;
            outline: none;
        }
        .filter-input:focus {
            border-color: #4ade80;
        }
        .filter-select {
            padding: 8px 16px;
            font-size: 0.85rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: #fff;
            cursor: pointer;
        }
        .filter-select option { background: #111118; }
        @media (max-width: 768px) {
            .header-section { flex-direction: column; }
            .overview-cards { grid-template-columns: 1fr 1fr; }
            .card-value { font-size: 1.75rem; }
        }
    </style>
</head>
<body class="cosmic-bg">
    <?php include(get_template_directory() . "/nav-header.php"); ?>
    <div id="budget-container" class="budget-container bitcore-container">
        <section class="header-section">
            <div>
                <h1>Budget Dashboard</h1>
                <p class="subtitle">Financial tracking and visualization</p>
            </div>
            <div class="header-actions">
                <button class="action-btn secondary">Export CSV</button>
                <button class="action-btn primary">Add Transaction</button>
            </div>
        </section>

        <section class="overview-cards">
            <div class="overview-card">
                <div class="card-header">
                    <div class="card-icon">💰</div>
                    <span class="card-label">Total Budget</span>
                </div>
                <div class="card-value blue">$124,500</div>
                <div class="card-change positive">↑ 12% from last quarter</div>
            </div>
            <div class="overview-card">
                <div class="card-header">
                    <div class="card-icon">📤</div>
                    <span class="card-label">Total Spent</span>
                </div>
                <div class="card-value orange">$87,340</div>
                <div class="card-change negative">↓ 5% under projection</div>
            </div>
            <div class="overview-card">
                <div class="card-header">
                    <div class="card-icon">💵</div>
                    <span class="card-label">Remaining</span>
                </div>
                <div class="card-value green">$37,160</div>
                <div class="card-change positive">30% of budget left</div>
            </div>
            <div class="overview-card alert">
                <div class="card-header">
                    <div class="card-icon">⚠️</div>
                    <span class="card-label">Over Budget</span>
                </div>
                <div class="card-value red">2</div>
                <div class="card-change negative">Categories need attention</div>
            </div>
        </section>

        <div class="main-grid">
            <div class="section-card">
                <div class="section-header">
                    <h2>Category Breakdown</h2>
                    <div class="section-tabs">
                        <button class="section-tab active">This Month</button>
                        <button class="section-tab">Quarter</button>
                        <button class="section-tab">Year</button>
                    </div>
                </div>
                <div class="section-content">
                    <div class="category-list">
                        <div class="category-item">
                            <div class="category-header">
                                <div class="category-info">
                                    <div class="category-icon dev">💻</div>
                                    <span class="category-name">Development</span>
                                </div>
                                <div class="category-amounts">
                                    <div class="amount-spent">$32,400 <span class="alert-badge warning">85%</span></div>
                                    <div class="amount-budget">of $38,000</div>
                                </div>
                            </div>
                            <div class="progress-wrapper">
                                <div class="progress-bar orange" style="width: 85%"></div>
                            </div>
                        </div>
                        <div class="category-item">
                            <div class="category-header">
                                <div class="category-info">
                                    <div class="category-icon infra">☁️</div>
                                    <span class="category-name">Infrastructure</span>
                                </div>
                                <div class="category-amounts">
                                    <div class="amount-spent">$28,150 <span class="alert-badge danger">112%</span></div>
                                    <div class="amount-budget">of $25,000</div>
                                </div>
                            </div>
                            <div class="progress-wrapper">
                                <div class="progress-bar red" style="width: 100%"></div>
                            </div>
                        </div>
                        <div class="category-item">
                            <div class="category-header">
                                <div class="category-info">
                                    <div class="category-icon ai">🤖</div>
                                    <span class="category-name">AI / APIs</span>
                                </div>
                                <div class="category-amounts">
                                    <div class="amount-spent">$15,800</div>
                                    <div class="amount-budget">of $28,000</div>
                                </div>
                            </div>
                            <div class="progress-wrapper">
                                <div class="progress-bar green" style="width: 56%"></div>
                            </div>
                        </div>
                        <div class="category-item">
                            <div class="category-header">
                                <div class="category-info">
                                    <div class="category-icon marketing">📢</div>
                                    <span class="category-name">Marketing</span>
                                </div>
                                <div class="category-amounts">
                                    <div class="amount-spent">$8,240 <span class="alert-badge danger">103%</span></div>
                                    <div class="amount-budget">of $8,000</div>
                                </div>
                            </div>
                            <div class="progress-wrapper">
                                <div class="progress-bar red" style="width: 100%"></div>
                            </div>
                        </div>
                        <div class="category-item">
                            <div class="category-header">
                                <div class="category-info">
                                    <div class="category-icon operations">⚙️</div>
                                    <span class="category-name">Operations</span>
                                </div>
                                <div class="category-amounts">
                                    <div class="amount-spent">$2,750</div>
                                    <div class="amount-budget">of $15,500</div>
                                </div>
                            </div>
                            <div class="progress-wrapper">
                                <div class="progress-bar green" style="width: 18%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="section-card">
                <div class="section-header">
                    <h2>Spending Timeline</h2>
                </div>
                <div class="section-content">
                    <div class="timeline-placeholder">
                        <div class="timeline-header">
                            <span class="timeline-title">Monthly Overview</span>
                            <div class="timeline-legend">
                                <div class="legend-item"><div class="legend-dot actual"></div> Actual</div>
                                <div class="legend-item"><div class="legend-dot projected"></div> Projected</div>
                            </div>
                        </div>
                        <div class="chart-area">
                            <div class="chart-bar-group">
                                <div class="chart-bars">
                                    <div class="chart-bar actual" style="height: 60px"></div>
                                    <div class="chart-bar projected" style="height: 70px"></div>
                                </div>
                                <span class="chart-label">Jan</span>
                            </div>
                            <div class="chart-bar-group">
                                <div class="chart-bars">
                                    <div class="chart-bar actual" style="height: 80px"></div>
                                    <div class="chart-bar projected" style="height: 85px"></div>
                                </div>
                                <span class="chart-label">Feb</span>
                            </div>
                            <div class="chart-bar-group">
                                <div class="chart-bars">
                                    <div class="chart-bar actual" style="height: 95px"></div>
                                    <div class="chart-bar projected" style="height: 90px"></div>
                                </div>
                                <span class="chart-label">Mar</span>
                            </div>
                            <div class="chart-bar-group">
                                <div class="chart-bars">
                                    <div class="chart-bar actual" style="height: 110px"></div>
                                    <div class="chart-bar projected" style="height: 100px"></div>
                                </div>
                                <span class="chart-label">Apr</span>
                            </div>
                            <div class="chart-bar-group">
                                <div class="chart-bars">
                                    <div class="chart-bar actual" style="height: 85px"></div>
                                    <div class="chart-bar projected" style="height: 95px"></div>
                                </div>
                                <span class="chart-label">May</span>
                            </div>
                            <div class="chart-bar-group">
                                <div class="chart-bars">
                                    <div class="chart-bar actual" style="height: 70px"></div>
                                    <div class="chart-bar projected" style="height: 80px"></div>
                                </div>
                                <span class="chart-label">Jun</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="section-card transactions-section">
            <div class="section-header">
                <h2>Recent Transactions</h2>
            </div>
            <div class="filter-bar">
                <input type="text" class="filter-input" placeholder="Search transactions...">
                <select class="filter-select">
                    <option value="">All Categories</option>
                    <option value="development">Development</option>
                    <option value="infrastructure">Infrastructure</option>
                    <option value="ai">AI / APIs</option>
                    <option value="marketing">Marketing</option>
                    <option value="operations">Operations</option>
                </select>
                <select class="filter-select">
                    <option value="">All Types</option>
                    <option value="expense">Expenses</option>
                    <option value="income">Income</option>
                </select>
            </div>
            <div class="section-content">
                <div class="transactions-list">
                    <div class="transaction-item">
                        <div class="transaction-info">
                            <div class="transaction-icon expense">☁️</div>
                            <div class="transaction-details">
                                <h4>AWS Infrastructure</h4>
                                <span class="meta">Infrastructure • Mar 28, 2026</span>
                            </div>
                        </div>
                        <div class="transaction-amount expense">-$4,250.00</div>
                    </div>
                    <div class="transaction-item">
                        <div class="transaction-info">
                            <div class="transaction-icon expense">🤖</div>
                            <div class="transaction-details">
                                <h4>OpenAI API Credits</h4>
                                <span class="meta">AI / APIs • Mar 27, 2026</span>
                            </div>
                        </div>
                        <div class="transaction-amount expense">-$2,800.00</div>
                    </div>
                    <div class="transaction-item">
                        <div class="transaction-info">
                            <div class="transaction-icon income">💰</div>
                            <div class="transaction-details">
                                <h4>Grant Funding - Q2</h4>
                                <span class="meta">Income • Mar 25, 2026</span>
                            </div>
                        </div>
                        <div class="transaction-amount income">+$25,000.00</div>
                    </div>
                    <div class="transaction-item">
                        <div class="transaction-info">
                            <div class="transaction-icon expense">💻</div>
                            <div class="transaction-details">
                                <h4>Contractor Payment</h4>
                                <span class="meta">Development • Mar 24, 2026</span>
                            </div>
                        </div>
                        <div class="transaction-amount expense">-$8,500.00</div>
                    </div>
                    <div class="transaction-item">
                        <div class="transaction-info">
                            <div class="transaction-icon expense">📢</div>
                            <div class="transaction-details">
                                <h4>Marketing Campaign</h4>
                                <span class="meta">Marketing • Mar 22, 2026</span>
                            </div>
                        </div>
                        <div class="transaction-amount expense">-$3,200.00</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php wp_footer(); ?>
</body>
</html>
