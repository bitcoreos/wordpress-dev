<?php
/**
 * Template Name: BITCORE Live Feed
 * Description: Real-time activity feed from hub.bitwiki.org
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
    <title><?php wp_title('|', true, 'right'); ?></title>
    <?php wp_head(); ?>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0a0a0f;
            color: #e0e0e0;
            line-height: 1.6;
        }
        .feed-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        .feed-header {
            text-align: center;
            margin-bottom: 60px;
            padding-bottom: 40px;
            border-bottom: 1px solid #1a1a2e;
        }
        .feed-header h1 {
            font-size: 2.5rem;
            font-weight: 300;
            color: #fff;
            margin-bottom: 10px;
        }
        .feed-header .subtitle {
            color: #888;
            font-size: 1.1rem;
        }
        .feed-header .live-indicator {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 20px;
            padding: 8px 16px;
            background: #1a1a2e;
            border-radius: 20px;
            font-size: 0.85rem;
            color: #4ade80;
        }
        .feed-header .live-indicator::before {
            content: '';
            width: 8px;
            height: 8px;
            background: #4ade80;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .feed-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        .feed-main {
            background: #111118;
            border-radius: 12px;
            border: 1px solid #1a1a2e;
            overflow: hidden;
        }
        .feed-section-header {
            padding: 20px 24px;
            background: #161622;
            border-bottom: 1px solid #1a1a2e;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .feed-section-header h2 {
            font-size: 1.1rem;
            font-weight: 500;
            color: #fff;
        }
        .feed-section-header .count {
            font-size: 0.85rem;
            color: #888;
            background: #1a1a2e;
            padding: 4px 12px;
            border-radius: 12px;
        }
        .topic-list {
            padding: 0;
        }
        .topic-item {
            padding: 20px 24px;
            border-bottom: 1px solid #1a1a2e;
            transition: background 0.2s;
        }
        .topic-item:last-child {
            border-bottom: none;
        }
        .topic-item:hover {
            background: #161622;
        }
        .topic-title {
            font-size: 1rem;
            color: #fff;
            margin-bottom: 8px;
            line-height: 1.4;
        }
        .topic-meta {
            display: flex;
            gap: 16px;
            font-size: 0.85rem;
            color: #666;
        }
        .topic-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .topic-meta .author {
            color: #4ade80;
        }
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .sidebar-card {
            background: #111118;
            border-radius: 12px;
            border: 1px solid #1a1a2e;
            padding: 24px;
        }
        .sidebar-card h3 {
            font-size: 0.9rem;
            font-weight: 500;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 16px;
        }
        .category-list {
            list-style: none;
        }
        .category-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #1a1a2e;
        }
        .category-item:last-child {
            border-bottom: none;
        }
        .category-name {
            color: #e0e0e0;
            font-size: 0.95rem;
        }
        .category-count {
            background: #1a1a2e;
            color: #888;
            padding: 2px 10px;
            border-radius: 10px;
            font-size: 0.8rem;
        }
        .enter-btn {
            display: block;
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #4ade80 0%, #22c55e 100%);
            color: #000;
            text-align: center;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            margin-top: 16px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .enter-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(74, 222, 128, 0.3);
        }
        @media (max-width: 900px) {
            .feed-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include(get_template_directory() . "/nav-header.php"); ?>
    <div class="feed-container">
        <header class="feed-header">
            <h1>BITCORE Live Feed</h1>
            <p class="subtitle">Real-time activity from hub.bitwiki.org</p>
            <div class="live-indicator">LIVE</div>
        </header>

        <div class="feed-grid">
            <main class="feed-main">
                <div class="feed-section-header">
                    <h2>Latest Discussions</h2>
                    <span class="count">5 topics</span>
                </div>
                <div class="topic-list">
                    <div class="topic-item">
                        <div class="topic-title">📡 Advertise on BIThub via Featured Signals & Broadcast Beacons</div>
                        <div class="topic-meta">
                            <span class="author">@iamcapote</span>
                            <span>2 hours ago</span>
                            <span>12 replies</span>
                        </div>
                    </div>
                    <div class="topic-item">
                        <div class="topic-title">Welcome to BIThub! 👋</div>
                        <div class="topic-meta">
                            <span class="author">@iamcapote</span>
                            <span>1 day ago</span>
                            <span>45 replies</span>
                        </div>
                    </div>
                    <div class="topic-item">
                        <div class="topic-title">@tom_doerr: Control Android devices through Python in realtime</div>
                        <div class="topic-meta">
                            <span class="author">@system</span>
                            <span>2 days ago</span>
                            <span>8 replies</span>
                        </div>
                    </div>
                    <div class="topic-item">
                        <div class="topic-title">@tom_doerr: Authorization service based on Google Zanzibar</div>
                        <div class="topic-meta">
                            <span class="author">@system</span>
                            <span>2 days ago</span>
                            <span>15 replies</span>
                        </div>
                    </div>
                    <div class="topic-item">
                        <div class="topic-title">@tom_doerr: Monitors servers and websites with push alerts and web terminal</div>
                        <div class="topic-meta">
                            <span class="author">@system</span>
                            <span>3 days ago</span>
                            <span>23 replies</span>
                        </div>
                    </div>
                </div>
            </main>

            <aside class="sidebar">
                <div class="sidebar-card">
                    <h3>Categories</h3>
                    <ul class="category-list">
                        <li class="category-item">
                            <span class="category-name">BITCOREOS</span>
                            <span class="category-count">3</span>
                        </li>
                        <li class="category-item">
                            <span class="category-name">Artifacts</span>
                            <span class="category-count">1</span>
                        </li>
                        <li class="category-item">
                            <span class="category-name">Workspaces</span>
                            <span class="category-count">12</span>
                        </li>
                        <li class="category-item">
                            <span class="category-name">Cores</span>
                            <span class="category-count">9</span>
                        </li>
                        <li class="category-item">
                            <span class="category-name">Nodes</span>
                            <span class="category-count">8</span>
                        </li>
                        <li class="category-item">
                            <span class="category-name">Ontology</span>
                            <span class="category-count">1</span>
                        </li>
                        <li class="category-item">
                            <span class="category-name">Community</span>
                            <span class="category-count">0</span>
                        </li>
                    </ul>
                </div>

                <div class="sidebar-card">
                    <h3>Join the Ecosystem</h3>
                    <p style="color: #888; font-size: 0.9rem; margin-bottom: 16px;">
                        Participate in discussions, share ideas, and collaborate with the BITCORE community.
                    </p>
                    <a href="https://hub.bitwiki.org" class="enter-btn" target="_blank">Enter BIThub →</a>
                </div>
            </aside>
        </div>
    </div>
    <?php wp_footer(); ?>
</body>
</html>