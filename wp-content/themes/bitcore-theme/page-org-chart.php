<?php
/**
 * Template Name: Org Chart
 * Description: Interactive organization hierarchy visualization
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
        .org-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px;
            position: relative;
            z-index: 1;
        }
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
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
        .search-filter-bar {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .search-input {
            padding: 12px 20px;
            font-size: 0.9rem;
            background: rgba(17, 17, 24, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            color: #fff;
            outline: none;
            min-width: 250px;
            transition: all 0.3s;
        }
        .search-input:focus {
            border-color: #4ade80;
            box-shadow: 0 0 0 3px rgba(74, 222, 128, 0.1);
        }
        .search-input::placeholder { color: #666; }
        .filter-select {
            padding: 12px 20px;
            font-size: 0.9rem;
            background: rgba(17, 17, 24, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            color: #fff;
            cursor: pointer;
            min-width: 180px;
        }
        .filter-select option { background: #111118; }
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
        }
        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: 600;
            color: #4ade80;
        }
        .stat-card .stat-label {
            font-size: 0.85rem;
            color: #888;
            margin-top: 4px;
        }
        .org-tree {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }
        .org-level {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .level-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .level-header h3 {
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #888;
        }
        .level-header .level-count {
            padding: 4px 10px;
            font-size: 0.75rem;
            background: rgba(74, 222, 128, 0.2);
            color: #4ade80;
            border-radius: 20px;
        }
        .members-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
        }
        .member-card {
            background: rgba(17, 17, 24, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 24px;
            transition: all 0.3s;
            cursor: pointer;
        }
        .member-card:hover {
            transform: translateY(-4px);
            border-color: #4ade80;
            box-shadow: 0 12px 32px rgba(74, 222, 128, 0.15);
        }
        .member-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 16px;
        }
        .member-avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            font-weight: 600;
            color: #fff;
        }
        .member-avatar.engineering { background: linear-gradient(135deg, #818cf8, #6366f1); }
        .member-avatar.design { background: linear-gradient(135deg, #f472b6, #ec4899); }
        .member-avatar.operations { background: linear-gradient(135deg, #fb923c, #f97316); }
        .member-avatar.research { background: linear-gradient(135deg, #a78bfa, #8b5cf6); }
        .member-avatar.default { background: linear-gradient(135deg, #4ade80, #22c55e); }
        .member-info h4 {
            font-size: 1rem;
            font-weight: 600;
            color: #fff;
            margin-bottom: 2px;
        }
        .member-info .role {
            font-size: 0.85rem;
            color: #888;
        }
        .member-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 16px;
        }
        .dept-badge {
            padding: 4px 10px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-radius: 4px;
        }
        .dept-badge.engineering { background: rgba(129, 140, 248, 0.2); color: #818cf8; }
        .dept-badge.design { background: rgba(244, 114, 182, 0.2); color: #f472b6; }
        .dept-badge.operations { background: rgba(251, 146, 60, 0.2); color: #fb923c; }
        .dept-badge.research { background: rgba(167, 139, 250, 0.2); color: #a78bfa; }
        .member-contact {
            display: flex;
            gap: 12px;
        }
        .contact-btn {
            padding: 8px 16px;
            font-size: 0.8rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: #888;
            cursor: pointer;
            transition: all 0.2s;
        }
        .contact-btn:hover {
            background: rgba(74, 222, 128, 0.2);
            border-color: #4ade80;
            color: #4ade80;
        }
        .expand-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            font-size: 0.85rem;
            background: rgba(255, 255, 255, 0.05);
            border: none;
            border-radius: 8px;
            color: #888;
            cursor: pointer;
            transition: all 0.2s;
        }
        .expand-btn:hover {
            background: rgba(74, 222, 128, 0.2);
            color: #4ade80;
        }
        .expand-btn svg {
            transition: transform 0.3s;
        }
        .expand-btn.expanded svg {
            transform: rotate(180deg);
        }
        .reports-section {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            display: none;
        }
        .reports-section.visible { display: block; }
        .reports-title {
            font-size: 0.8rem;
            color: #666;
            margin-bottom: 12px;
        }
        .reports-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .report-chip {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            font-size: 0.8rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            color: #aaa;
        }
        .report-chip .mini-avatar {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4ade80, #22c55e);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.6rem;
            color: #000;
        }
        .drag-handle {
            position: absolute;
            top: 12px;
            right: 12px;
            color: #444;
            cursor: grab;
            opacity: 0;
            transition: opacity 0.2s;
        }
        .member-card:hover .drag-handle { opacity: 1; }
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
        @media (max-width: 768px) {
            .header-section { flex-direction: column; align-items: flex-start; }
            .search-filter-bar { width: 100%; }
            .search-input { min-width: 100%; }
            .members-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="cosmic-bg">
    <?php include(get_template_directory() . "/nav-header.php"); ?>
    <div id="org-chart-container" class="org-container bitcore-container">
        <section class="header-section">
            <div>
                <h1>Organization Chart</h1>
                <p class="subtitle">Interactive hierarchy visualization</p>
            </div>
            <div class="search-filter-bar">
                <input type="text" class="search-input" placeholder="Search by name or role..." id="search-input">
                <select class="filter-select" id="dept-filter">
                    <option value="">All Departments</option>
                    <option value="engineering">Engineering</option>
                    <option value="design">Design</option>
                    <option value="operations">Operations</option>
                    <option value="research">Research</option>
                </select>
            </div>
        </section>

        <section class="stats-row">
            <div class="stat-card">
                <div class="stat-value">24</div>
                <div class="stat-label">Total Members</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">6</div>
                <div class="stat-label">Departments</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">4</div>
                <div class="stat-label">Team Leads</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">3</div>
                <div class="stat-label">Open Positions</div>
            </div>
        </section>

        <div class="org-tree">
            <!-- Leadership Level -->
            <div class="org-level">
                <div class="level-header">
                    <h3>Leadership</h3>
                    <span class="level-count">2</span>
                </div>
                <div class="members-grid">
                    <div class="member-card" data-dept="operations">
                        <div class="member-header">
                            <div class="member-avatar operations">JD</div>
                            <div class="member-info">
                                <h4>Jordan Director</h4>
                                <div class="role">Chief Executive Officer</div>
                            </div>
                        </div>
                        <div class="member-meta">
                            <span class="dept-badge operations">Operations</span>
                        </div>
                        <div class="member-contact">
                            <button class="contact-btn">Email</button>
                            <button class="contact-btn">Slack</button>
                        </div>
                        <button class="expand-btn" onclick="toggleReports(this)">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                            8 Direct Reports
                        </button>
                        <div class="reports-section">
                            <div class="reports-title">Direct Reports</div>
                            <div class="reports-list">
                                <div class="report-chip"><div class="mini-avatar">AS</div> Alex Smith</div>
                                <div class="report-chip"><div class="mini-avatar">MJ</div> Maria Johnson</div>
                                <div class="report-chip"><div class="mini-avatar">DW</div> David Williams</div>
                                <div class="report-chip"><div class="mini-avatar">SC</div> Sarah Chen</div>
                            </div>
                        </div>
                    </div>
                    <div class="member-card" data-dept="operations">
                        <div class="member-header">
                            <div class="member-avatar operations">MC</div>
                            <div class="member-info">
                                <h4>Morgan CTO</h4>
                                <div class="role">Chief Technology Officer</div>
                            </div>
                        </div>
                        <div class="member-meta">
                            <span class="dept-badge operations">Operations</span>
                        </div>
                        <div class="member-contact">
                            <button class="contact-btn">Email</button>
                            <button class="contact-btn">Slack</button>
                        </div>
                        <button class="expand-btn" onclick="toggleReports(this)">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                            6 Direct Reports
                        </button>
                        <div class="reports-section">
                            <div class="reports-title">Direct Reports</div>
                            <div class="reports-list">
                                <div class="report-chip"><div class="mini-avatar">KL</div> Kevin Lee</div>
                                <div class="report-chip"><div class="mini-avatar">RP</div> Rachel Park</div>
                                <div class="report-chip"><div class="mini-avatar">TG</div> Tom Garcia</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Engineering Level -->
            <div class="org-level">
                <div class="level-header">
                    <h3>Engineering</h3>
                    <span class="level-count">8</span>
                </div>
                <div class="members-grid">
                    <div class="member-card" data-dept="engineering">
                        <div class="member-header">
                            <div class="member-avatar engineering">AS</div>
                            <div class="member-info">
                                <h4>Alex Smith</h4>
                                <div class="role">Engineering Lead</div>
                            </div>
                        </div>
                        <div class="member-meta">
                            <span class="dept-badge engineering">Engineering</span>
                        </div>
                        <div class="member-contact">
                            <button class="contact-btn">Email</button>
                            <button class="contact-btn">Slack</button>
                        </div>
                        <button class="expand-btn" onclick="toggleReports(this)">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                            4 Direct Reports
                        </button>
                        <div class="reports-section">
                            <div class="reports-title">Direct Reports</div>
                            <div class="reports-list">
                                <div class="report-chip"><div class="mini-avatar">JW</div> James Wilson</div>
                                <div class="report-chip"><div class="mini-avatar">EM</div> Emily Martinez</div>
                                <div class="report-chip"><div class="mini-avatar">NB</div> Nathan Brown</div>
                                <div class="report-chip"><div class="mini-avatar">LT</div> Lisa Thompson</div>
                            </div>
                        </div>
                    </div>
                    <div class="member-card" data-dept="engineering">
                        <div class="member-header">
                            <div class="member-avatar engineering">MJ</div>
                            <div class="member-info">
                                <h4>Maria Johnson</h4>
                                <div class="role">Senior Backend Engineer</div>
                            </div>
                        </div>
                        <div class="member-meta">
                            <span class="dept-badge engineering">Engineering</span>
                        </div>
                        <div class="member-contact">
                            <button class="contact-btn">Email</button>
                            <button class="contact-btn">Slack</button>
                        </div>
                    </div>
                    <div class="member-card" data-dept="engineering">
                        <div class="member-header">
                            <div class="member-avatar engineering">DW</div>
                            <div class="member-info">
                                <h4>David Williams</h4>
                                <div class="role">Senior Frontend Engineer</div>
                            </div>
                        </div>
                        <div class="member-meta">
                            <span class="dept-badge engineering">Engineering</span>
                        </div>
                        <div class="member-contact">
                            <button class="contact-btn">Email</button>
                            <button class="contact-btn">Slack</button>
                        </div>
                    </div>
                    <div class="member-card" data-dept="engineering">
                        <div class="member-header">
                            <div class="member-avatar engineering">SC</div>
                            <div class="member-info">
                                <h4>Sarah Chen</h4>
                                <div class="role">DevOps Engineer</div>
                            </div>
                        </div>
                        <div class="member-meta">
                            <span class="dept-badge engineering">Engineering</span>
                        </div>
                        <div class="member-contact">
                            <button class="contact-btn">Email</button>
                            <button class="contact-btn">Slack</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Design Level -->
            <div class="org-level">
                <div class="level-header">
                    <h3>Design</h3>
                    <span class="level-count">4</span>
                </div>
                <div class="members-grid">
                    <div class="member-card" data-dept="design">
                        <div class="member-header">
                            <div class="member-avatar design">KL</div>
                            <div class="member-info">
                                <h4>Kevin Lee</h4>
                                <div class="role">Design Lead</div>
                            </div>
                        </div>
                        <div class="member-meta">
                            <span class="dept-badge design">Design</span>
                        </div>
                        <div class="member-contact">
                            <button class="contact-btn">Email</button>
                            <button class="contact-btn">Slack</button>
                        </div>
                        <button class="expand-btn" onclick="toggleReports(this)">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                            3 Direct Reports
                        </button>
                        <div class="reports-section">
                            <div class="reports-title">Direct Reports</div>
                            <div class="reports-list">
                                <div class="report-chip"><div class="mini-avatar">AK</div> Anna Kim</div>
                                <div class="report-chip"><div class="mini-avatar">CP</div> Chris Paul</div>
                                <div class="report-chip"><div class="mini-avatar">DR</div> Dana Roberts</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Research Level -->
            <div class="org-level">
                <div class="level-header">
                    <h3>Research</h3>
                    <span class="level-count">6</span>
                </div>
                <div class="members-grid">
                    <div class="member-card" data-dept="research">
                        <div class="member-header">
                            <div class="member-avatar research">RP</div>
                            <div class="member-info">
                                <h4>Rachel Park</h4>
                                <div class="role">Research Lead</div>
                            </div>
                        </div>
                        <div class="member-meta">
                            <span class="dept-badge research">Research</span>
                        </div>
                        <div class="member-contact">
                            <button class="contact-btn">Email</button>
                            <button class="contact-btn">Slack</button>
                        </div>
                        <button class="expand-btn" onclick="toggleReports(this)">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                            5 Direct Reports
                        </button>
                        <div class="reports-section">
                            <div class="reports-title">Direct Reports</div>
                            <div class="reports-list">
                                <div class="report-chip"><div class="mini-avatar">MH</div> Mike Harris</div>
                                <div class="report-chip"><div class="mini-avatar">JL</div> Jennifer Lopez</div>
                                <div class="report-chip"><div class="mini-avatar">RN</div> Robert Nguyen</div>
                            </div>
                        </div>
                    </div>
                    <div class="member-card" data-dept="research">
                        <div class="member-header">
                            <div class="member-avatar research">TG</div>
                            <div class="member-info">
                                <h4>Tom Garcia</h4>
                                <div class="role">AI Researcher</div>
                            </div>
                        </div>
                        <div class="member-meta">
                            <span class="dept-badge research">Research</span>
                        </div>
                        <div class="member-contact">
                            <button class="contact-btn">Email</button>
                            <button class="contact-btn">Slack</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="action-bar">
            <button class="action-btn secondary">Export Chart</button>
            <button class="action-btn primary">Add Member</button>
        </div>
    </div>

    <script>
        function toggleReports(btn) {
            const reportsSection = btn.nextElementSibling;
            btn.classList.toggle('expanded');
            reportsSection.classList.toggle('visible');
            const count = btn.textContent.match(/\d+/)[0];
            const isOpen = btn.classList.contains('expanded');
            btn.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg> ${isOpen ? 'Hide' : count} Direct Reports`;
        }

        document.getElementById('search-input').addEventListener('input', function(e) {
            const query = e.target.value.toLowerCase();
            document.querySelectorAll('.member-card').forEach(card => {
                const name = card.querySelector('h4').textContent.toLowerCase();
                const role = card.querySelector('.role').textContent.toLowerCase();
                card.style.display = (name.includes(query) || role.includes(query)) ? 'block' : 'none';
            });
        });

        document.getElementById('dept-filter').addEventListener('change', function(e) {
            const dept = e.target.value;
            document.querySelectorAll('.member-card').forEach(card => {
                card.style.display = (!dept || card.dataset.dept === dept) ? 'block' : 'none';
            });
        });
    </script>
    <?php wp_footer(); ?>
</body>
</html>
