<?php
/**
 * Template Name: Artifact Builder
 * Description: Create AI artifacts with guided templates and live preview
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
        .builder-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
            position: relative;
            z-index: 1;
        }
        .hero-section {
            text-align: center;
            padding: 60px 40px;
            margin-bottom: 40px;
        }
        .hero-section h1 {
            font-size: 2.5rem;
            font-weight: 300;
            color: #fff;
            margin-bottom: 16px;
        }
        .hero-section .subtitle {
            font-size: 1.1rem;
            color: #888;
            margin-bottom: 32px;
        }
        .main-input-wrapper {
            max-width: 800px;
            margin: 0 auto;
        }
        .main-input {
            width: 100%;
            padding: 24px 32px;
            font-size: 1.25rem;
            background: rgba(17, 17, 24, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            color: #fff;
            outline: none;
            transition: all 0.3s;
        }
        .main-input:focus {
            border-color: #4ade80;
            box-shadow: 0 0 0 3px rgba(74, 222, 128, 0.1);
        }
        .main-input::placeholder {
            color: #666;
        }
        .input-hint {
            margin-top: 12px;
            font-size: 0.85rem;
            color: #666;
        }
        .tabs-section {
            margin-bottom: 32px;
        }
        .tabs {
            display: flex;
            gap: 8px;
            justify-content: center;
            flex-wrap: wrap;
            padding: 8px;
            background: rgba(17, 17, 24, 0.6);
            backdrop-filter: blur(12px);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        .tab {
            padding: 12px 24px;
            font-size: 0.9rem;
            font-weight: 500;
            color: #888;
            background: transparent;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .tab:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.05);
        }
        .tab.active {
            color: #000;
            background: linear-gradient(135deg, #4ade80, #22c55e);
        }
        .templates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        .template-card {
            background: rgba(17, 17, 24, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 24px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .template-card:hover {
            transform: translateY(-4px);
            border-color: #4ade80;
            box-shadow: 0 12px 32px rgba(74, 222, 128, 0.15);
        }
        .template-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            background: linear-gradient(135deg, rgba(74, 222, 128, 0.2), rgba(34, 197, 94, 0.2));
            border-radius: 12px;
            margin-bottom: 16px;
        }
        .template-card h3 {
            font-size: 1rem;
            font-weight: 600;
            color: #fff;
            margin-bottom: 8px;
        }
        .template-card p {
            font-size: 0.85rem;
            color: #888;
            line-height: 1.5;
        }
        .template-type {
            display: inline-block;
            padding: 4px 10px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-radius: 4px;
            margin-top: 12px;
        }
        .template-type.prompt { background: rgba(129, 140, 248, 0.2); color: #818cf8; }
        .template-type.skill { background: rgba(167, 139, 250, 0.2); color: #a78bfa; }
        .template-type.code { background: rgba(244, 114, 182, 0.2); color: #f472b6; }
        .build-btn-wrapper {
            text-align: center;
        }
        .build-btn {
            padding: 16px 48px;
            font-size: 1.1rem;
            font-weight: 600;
            color: #000;
            background: linear-gradient(135deg, #4ade80, #22c55e);
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 16px rgba(74, 222, 128, 0.3);
        }
        .build-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(74, 222, 128, 0.4);
        }
        .build-hint {
            margin-top: 12px;
            font-size: 0.85rem;
            color: #666;
        }
        @media (max-width: 768px) {
            .hero-section h1 { font-size: 1.75rem; }
            .main-input { font-size: 1rem; padding: 16px 20px; }
            .templates-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="cosmic-bg">
    <?php include(get_template_directory() . "/nav-header.php"); ?>
    <div class="builder-container">
        <section class="hero-section">
            <h1>What should your AI do?</h1>
            <p class="subtitle">Describe your artifact or choose a template below</p>
            <div class="main-input-wrapper">
                <input type="text" class="main-input" placeholder="Create a prompt that helps me analyze research papers..." id="artifact-input">
                <p class="input-hint">Press Enter or click Build to continue</p>
            </div>
        </section>

        <section class="tabs-section">
            <div class="tabs">
                <button class="tab active">All</button>
                <button class="tab">Prompts</button>
                <button class="tab">Skills</button>
                <button class="tab">Code</button>
                <button class="tab">Analytics</button>
                <button class="tab">Research</button>
                <button class="tab">Automation</button>
            </div>
        </section>

        <section class="templates-grid">
            <div class="template-card">
                <div class="template-icon">🎯</div>
                <h3>Task Decomposition</h3>
                <p>Break complex tasks into actionable steps with clear deliverables</p>
                <span class="template-type prompt">Prompt</span>
            </div>
            <div class="template-card">
                <div class="template-icon">🔬</div>
                <h3>Research Analyst</h3>
                <p>Deep research with citations, synthesis, and key findings</p>
                <span class="template-type prompt">Prompt</span>
            </div>
            <div class="template-card">
                <div class="template-icon">🧠</div>
                <h3>Reasoning Chain</h3>
                <p>Step-by-step logical reasoning with verification checkpoints</p>
                <span class="template-type skill">Skill</span>
            </div>
            <div class="template-card">
                <div class="template-icon">⚡</div>
                <h3>API Integration</h3>
                <p>Connect external APIs with error handling and retry logic</p>
                <span class="template-type code">Code</span>
            </div>
            <div class="template-card">
                <div class="template-icon">📊</div>
                <h3>Data Pipeline</h3>
                <p>Transform, validate, and process data with quality checks</p>
                <span class="template-type code">Code</span>
            </div>
            <div class="template-card">
                <div class="template-icon">🤖</div>
                <h3>Agent Coordinator</h3>
                <p>Orchestrate multi-agent workflows with task routing</p>
                <span class="template-type skill">Skill</span>
            </div>
            <div class="template-card">
                <div class="template-icon">📝</div>
                <h3>Content Generator</h3>
                <p>Create structured content with customizable tone and format</p>
                <span class="template-type prompt">Prompt</span>
            </div>
            <div class="template-card">
                <div class="template-icon">🔍</div>
                <h3>Code Review</h3>
                <p>Analyze code quality, security, and suggest improvements</p>
                <span class="template-type skill">Skill</span>
            </div>
        </section>

        <div class="build-btn-wrapper">
            <button class="build-btn">Build My Artifact</button>
            <p class="build-hint">Ctrl + Enter to build</p>
        </div>
    </div>
    <?php wp_footer(); ?>
</body>
</html>
