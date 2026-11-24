<?php
/**
 * Template Name: Artifact Marketplace
 * Description: Browse, buy, and sell artifacts
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
        .marketplace-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
            position: relative;
            z-index: 1;
        }
        .header-section {
            text-align: center;
            padding: 40px 20px;
            margin-bottom: 24px;
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
        .sub-tabs {
            display: flex;
            gap: 4px;
            justify-content: center;
            margin-bottom: 32px;
            padding: 6px;
            background: rgba(17, 17, 24, 0.6);
            backdrop-filter: blur(12px);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        .sub-tab {
            padding: 12px 28px;
            font-size: 0.9rem;
            font-weight: 500;
            color: #888;
            background: transparent;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .sub-tab:hover { color: #fff; background: rgba(255, 255, 255, 0.05); }
        .sub-tab.active { color: #000; background: linear-gradient(135deg, #4ade80, #22c55e); }
        .filters-row {
            display: flex;
            gap: 12px;
            margin-bottom: 32px;
            flex-wrap: wrap;
        }
        .search-input {
            flex: 1;
            min-width: 200px;
            padding: 12px 18px;
            font-size: 0.95rem;
            background: rgba(17, 17, 24, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            color: #fff;
            outline: none;
        }
        .search-input::placeholder { color: #666; }
        .filter-select {
            padding: 12px 18px;
            font-size: 0.9rem;
            background: rgba(17, 17, 24, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            color: #fff;
            cursor: pointer;
        }
        .filter-select option { background: #111118; }
        .artifacts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 24px;
        }
        .artifact-card {
            background: rgba(17, 17, 24, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 24px;
            transition: all 0.3s;
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
            margin-bottom: 12px;
        }
        .type-badge {
            padding: 6px 12px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            border-radius: 6px;
        }
        .type-badge.prompt { background: rgba(129, 140, 248, 0.2); color: #818cf8; }
        .type-badge.skill { background: rgba(167, 139, 250, 0.2); color: #a78bfa; }
        .type-badge.code { background: rgba(244, 114, 182, 0.2); color: #f472b6; }
        .price-tag {
            font-size: 1.1rem;
            font-weight: 600;
            color: #4ade80;
        }
        .price-tag.free { color: #888; }
        .artifact-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #fff;
            margin-bottom: 8px;
        }
        .artifact-desc {
            font-size: 0.85rem;
            color: #888;
            margin-bottom: 16px;
            line-height: 1.5;
        }
        .card-stats {
            display: flex;
            gap: 16px;
            font-size: 0.8rem;
            color: #666;
            margin-bottom: 12px;
        }
        .stars { color: #fbbf24; letter-spacing: 2px; }
        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 12px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }
        .seller-info {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .seller-avatar {
            width: 28px;
            height: 28px;
            background: linear-gradient(135deg, #818cf8, #a78bfa);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
            color: #fff;
        }
        .seller-name { font-size: 0.85rem; color: #888; }
        .buy-btn {
            padding: 8px 20px;
            font-size: 0.85rem;
            font-weight: 600;
            color: #000;
            background: linear-gradient(135deg, #4ade80, #22c55e);
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .buy-btn:hover { transform: scale(1.05); }
        /* Skeleton Loading */
        .skeleton {
            background: linear-gradient(90deg, #1a1a2e 25%, #2a2a3e 50%, #1a1a2e 75%);
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
            border-radius: 8px;
        }
        @keyframes shimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        .skeleton-card {
            background: rgba(17, 17, 24, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 24px;
        }
        .skeleton-header { height: 24px; width: 60%; margin-bottom: 16px; }
        .skeleton-text { height: 14px; width: 100%; margin-bottom: 8px; }
        .skeleton-text:last-child { width: 80%; }
        @media (max-width: 768px) {
            .header-section h1 { font-size: 1.75rem; }
            .filters-row { flex-direction: column; }
            .artifacts-grid { grid-template-columns: 1fr; }
            .sub-tabs { flex-wrap: wrap; }
        }
    </style>
</head>
<body class="cosmic-bg">
    <?php include(get_template_directory() . "/nav-header.php"); ?>
    <div class="marketplace-container">
        <section class="header-section">
            <h1>Artifact Marketplace</h1>
            <p class="subtitle">Discover, buy, and sell premium AI artifacts</p>
        </section>

        <div class="sub-tabs">
            <button class="sub-tab active">Browse</button>
            <button class="sub-tab">My Purchases</button>
            <button class="sub-tab">Earnings</button>
        </div>

        <div class="filters-row">
            <input type="text" class="search-input" placeholder="Search marketplace...">
            <select class="filter-select">
                <option>All Categories</option>
                <option>Prompts</option>
                <option>Skills</option>
                <option>Code Snippets</option>
            </select>
            <select class="filter-select">
                <option>Sort: Popular</option>
                <option>Sort: Newest</option>
                <option>Sort: Price: Low to High</option>
                <option>Sort: Price: High to Low</option>
            </select>
        </div>

        <div class="artifacts-grid">
            <div class="artifact-card">
                <div class="card-header">
                    <span class="type-badge prompt">Prompt</span>
                    <span class="price-tag">$4.99</span>
                </div>
                <h3 class="artifact-title">Advanced Research Analyst</h3>
                <p class="artifact-desc">Comprehensive research with citations, synthesis, and structured outputs for academic workflows</p>
                <div class="card-stats">
                    <span class="stars">★★★★★</span>
                    <span>1.2k downloads</span>
                </div>
                <div class="card-footer">
                    <div class="seller-info">
                        <div class="seller-avatar">JD</div>
                        <span class="seller-name">john_dev</span>
                    </div>
                    <button class="buy-btn">Buy Now</button>
                </div>
            </div>

            <div class="artifact-card">
                <div class="card-header">
                    <span class="type-badge skill">Skill</span>
                    <span class="price-tag">$9.99</span>
                </div>
                <h3 class="artifact-title">Multi-Agent Orchestrator</h3>
                <p class="artifact-desc">Coordinate multiple AI agents with task routing, load balancing, and failure recovery</p>
                <div class="card-stats">
                    <span class="stars">★★★★☆</span>
                    <span>856 downloads</span>
                </div>
                <div class="card-footer">
                    <div class="seller-info">
                        <div class="seller-avatar">SK</div>
                        <span class="seller-name">skill_master</span>
                    </div>
                    <button class="buy-btn">Buy Now</button>
                </div>
            </div>

            <div class="artifact-card">
                <div class="card-header">
                    <span class="type-badge code">Code</span>
                    <span class="price-tag free">Free</span>
                </div>
                <h3 class="artifact-title">REST API Handler</h3>
                <p class="artifact-desc">Production-ready REST API integration with error handling, retries, and caching</p>
                <div class="card-stats">
                    <span class="stars">★★★★★</span>
                    <span>3.4k downloads</span>
                </div>
                <div class="card-footer">
                    <div class="seller-info">
                        <div class="seller-avatar">AB</div>
                        <span class="seller-name">api_builder</span>
                    </div>
                    <button class="buy-btn">Get Free</button>
                </div>
            </div>

            <div class="artifact-card">
                <div class="card-header">
                    <span class="type-badge prompt">Prompt</span>
                    <span class="price-tag">$2.99</span>
                </div>
                <h3 class="artifact-title">Code Review Expert</h3>
                <p class="artifact-desc">Analyze code quality, security vulnerabilities, and suggest improvements with examples</p>
                <div class="card-stats">
                    <span class="stars">★★★★☆</span>
                    <span>678 downloads</span>
                </div>
                <div class="card-footer">
                    <div class="seller-info">
                        <div class="seller-avatar">CR</div>
                        <span class="seller-name">code_reviewer</span>
                    </div>
                    <button class="buy-btn">Buy Now</button>
                </div>
            </div>

            <!-- Skeleton Loading Example -->
            <div class="skeleton-card">
                <div class="skeleton skeleton-header"></div>
                <div class="skeleton skeleton-text"></div>
                <div class="skeleton skeleton-text"></div>
                <div class="skeleton skeleton-text"></div>
            </div>
        </div>
    </div>
    <?php wp_footer(); ?>
</body>
</html>
