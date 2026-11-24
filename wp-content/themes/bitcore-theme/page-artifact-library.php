<?php
/**
 * Template Name: Artifact Library
 * Description: User's saved artifacts with search/filter
 * @package    Bitcore\Theme
 * @since      2026.11.23
 * @version    1.0.0
 * @author     coraline
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
            background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="10" cy="10" r="0.5" fill="%23ffffff" opacity="0.3"/><circle cx="30" cy="25" r="0.3" fill="%23ffffff" opacity="0.2"/><circle cx="50" cy="15" r="0.4" fill="%23ffffff" opacity="0.25"/><circle cx="70" cy="35" r="0.3" fill="%23ffffff" opacity="0.2"/><circle cx="90" cy="20" r="0.5" fill="%23ffffff" opacity="0.3"/><circle cx="20" cy="50" r="0.4" fill="%23ffffff" opacity="0.2"/><circle cx="40" cy="70" r="0.3" fill="%23ffffff" opacity="0.25"/><circle cx="60" cy="60" r="0.5" fill="%23ffffff" opacity="0.3"/><circle cx="80" cy="80" r="0.3" fill="%23ffffff" opacity="0.2"/><circle cx="15" cy="85" r="0.4" fill="%23ffffff" opacity="0.25"/></svg>');
            background-size: 200px 200px;
            opacity: 0.5;
            pointer-events: none;
        }
        .library-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
            position: relative;
            z-index: 1;
        }
        .header-section {
            text-align: center;
            padding: 40px 20px;
            margin-bottom: 32px;
        }
        .header-section h1 {
            font-size: 2.5rem;
            font-weight: 300;
            color: #fff;
            margin-bottom: 12px;
        }
        .header-section .subtitle {
            font-size: 1.1rem;
            color: #888;
        }
        .search-section {
            display: flex;
            gap: 12px;
            max-width: 600px;
            margin: 0 auto 40px;
        }
        .search-input {
            flex: 1;
            padding: 14px 20px;
            font-size: 1rem;
            background: rgba(17, 17, 24, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: #fff;
            outline: none;
            transition: all 0.3s;
        }
        .search-input:focus {
            border-color: #4ade80;
            box-shadow: 0 0 0 3px rgba(74, 222, 128, 0.1);
        }
        .search-input::placeholder { color: #666; }
        .filter-dropdown {
            padding: 14px 20px;
            font-size: 0.9rem;
            background: rgba(17, 17, 24, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: #fff;
            cursor: pointer;
            min-width: 140px;
        }
        .filter-dropdown option { background: #111118; }
        .artifacts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
        }
        .artifact-card {
            background: rgba(17, 17, 24, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 24px;
            transition: all 0.3s;
            cursor: pointer;
        }
        .artifact-card:hover {
            transform: translateY(-4px);
            border-color: #4ade80;
            box-shadow: 0 12px 32px rgba(74, 222, 128, 0.15);
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }
        .type-badge {
            padding: 6px 12px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-radius: 6px;
        }
        .type-badge.prompt { background: rgba(129, 140, 248, 0.2); color: #818cf8; }
        .type-badge.skill { background: rgba(167, 139, 250, 0.2); color: #a78bfa; }
        .type-badge.code { background: rgba(244, 114, 182, 0.2); color: #f472b6; }
        .quality-badge {
            padding: 4px 10px;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            border-radius: 4px;
        }
        .quality-badge.verified { background: rgba(74, 222, 128, 0.2); color: #4ade80; }
        .quality-badge.community { background: rgba(129, 140, 248, 0.2); color: #818cf8; }
        .artifact-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #fff;
            margin-bottom: 8px;
        }
        .artifact-desc {
            font-size: 0.9rem;
            color: #888;
            line-height: 1.5;
            margin-bottom: 16px;
        }
        .card-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8rem;
            color: #666;
        }
        .version-tag {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 4px;
        }
        .empty-state {
            text-align: center;
            padding: 80px 40px;
            background: rgba(17, 17, 24, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
        }
        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 24px;
            opacity: 0.5;
        }
        .empty-state h2 {
            font-size: 1.5rem;
            font-weight: 500;
            color: #fff;
            margin-bottom: 12px;
        }
        .empty-state p {
            color: #888;
            margin-bottom: 24px;
        }
        .cta-btn {
            display: inline-block;
            padding: 14px 32px;
            font-size: 1rem;
            font-weight: 600;
            color: #000;
            background: linear-gradient(135deg, #4ade80, #22c55e);
            border: none;
            border-radius: 12px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
        }
        .cta-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(74, 222, 128, 0.4);
        }
        .stats-bar {
            display: flex;
            gap: 32px;
            justify-content: center;
            margin-bottom: 40px;
            padding: 20px;
            background: rgba(17, 17, 24, 0.6);
            backdrop-filter: blur(12px);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        .stat-item {
            text-align: center;
        }
        .stat-value {
            font-size: 1.75rem;
            font-weight: 600;
            color: #4ade80;
        }
        .stat-label {
            font-size: 0.8rem;
            color: #888;
        }
        @media (max-width: 768px) {
            .header-section h1 { font-size: 1.75rem; }
            .search-section { flex-direction: column; }
            .artifacts-grid { grid-template-columns: 1fr; }
            .stats-bar { flex-wrap: wrap; gap: 16px; }
        }
    </style>
</head>
<body class="cosmic-bg">
    <?php include(get_template_directory() . "/nav-header.php"); ?>
    <div class="library-container">
        <section class="header-section">
            <h1>My Artifacts</h1>
            <p class="subtitle">Your personal collection of AI artifacts</p>
        </section>

        <div class="stats-bar">
            <div class="stat-item">
                <div class="stat-value">12</div>
                <div class="stat-label">Total Artifacts</div>
            </div>
            <div class="stat-item">
                <div class="stat-value">5</div>
                <div class="stat-label">Prompts</div>
            </div>
            <div class="stat-item">
                <div class="stat-value">4</div>
                <div class="stat-label">Skills</div>
            </div>
            <div class="stat-item">
                <div class="stat-value">3</div>
                <div class="stat-label">Code Snippets</div>
            </div>
        </div>

        <div class="search-section">
            <input type="text" class="search-input" placeholder="Search artifacts...">
            <select class="filter-dropdown">
                <option>All Types</option>
                <option>Prompts</option>
                <option>Skills</option>
                <option>Code</option>
            </select>
        </div>

        <div class="artifacts-grid">
            <div class="artifact-card">
                <div class="card-header">
                    <span class="type-badge prompt">Prompt</span>
                    <span class="quality-badge verified">VERIFIED</span>
                </div>
                <h3 class="artifact-title">Research Analysis Pipeline</h3>
                <p class="artifact-desc">Deep research with citations, synthesis, and structured key findings extraction</p>
                <div class="card-meta">
                    <span class="version-tag">v2.1</span>
                    <span>Updated 2 days ago</span>
                </div>
            </div>

            <div class="artifact-card">
                <div class="card-header">
                    <span class="type-badge skill">Skill</span>
                    <span class="quality-badge community">COMMUNITY</span>
                </div>
                <h3 class="artifact-title">Reasoning Chain Engine</h3>
                <p class="artifact-desc">Step-by-step logical reasoning with verification checkpoints and error handling</p>
                <div class="card-meta">
                    <span class="version-tag">v1.4</span>
                    <span>Updated 5 days ago</span>
                </div>
            </div>

            <div class="artifact-card">
                <div class="card-header">
                    <span class="type-badge code">Code</span>
                    <span class="quality-badge verified">VERIFIED</span>
                </div>
                <h3 class="artifact-title">API Integration Handler</h3>
                <p class="artifact-desc">Connect external APIs with error handling, retry logic, and rate limiting</p>
                <div class="card-meta">
                    <span class="version-tag">v3.0</span>
                    <span>Updated 1 week ago</span>
                </div>
            </div>

            <div class="artifact-card">
                <div class="card-header">
                    <span class="type-badge prompt">Prompt</span>
                    <span class="quality-badge community">COMMUNITY</span>
                </div>
                <h3 class="artifact-title">Task Decomposition Framework</h3>
                <p class="artifact-desc">Break complex tasks into actionable steps with clear deliverables</p>
                <div class="card-meta">
                    <span class="version-tag">v1.2</span>
                    <span>Updated 1 week ago</span>
                </div>
            </div>
        </div>
    </div>
    <?php wp_footer(); ?>
</body>
</html>
