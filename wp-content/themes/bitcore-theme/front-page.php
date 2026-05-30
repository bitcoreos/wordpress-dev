<?php
/**
 * ASHI public entry surface.
 *
 * Public page explains the product and routes members through WordPress login.
 * WP-Discourse owns SSO; this template only calls wp_login_url().
 *
 * @package bitcore-theme
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php bloginfo('name'); ?> — ASHI</title>
    <?php wp_head(); ?>
    <style>
        :root {
            --ashi-bg: #0a0a0f;
            --ashi-panel: #12121a;
            --ashi-panel-2: #1a1a25;
            --ashi-border: #2a2a3a;
            --ashi-text: #f0f0f5;
            --ashi-muted: #a0a0b0;
            --ashi-dim: #6a6a7a;
            --ashi-accent: #4ade80;
            --ashi-blue: #6366f1;
        }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: radial-gradient(circle at top, rgba(99,102,241,.22), transparent 34rem), var(--ashi-bg); color: var(--ashi-text); font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .ashi-home { max-width: 1180px; margin: 0 auto; padding: 72px 24px 56px; }
        .hero { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(320px, .8fr); gap: 32px; align-items: center; }
        .eyebrow { color: var(--ashi-accent); text-transform: uppercase; letter-spacing: .16em; font-size: 12px; font-weight: 700; margin-bottom: 14px; }
        h1 { font-size: clamp(42px, 7vw, 78px); line-height: .95; margin: 0 0 22px; letter-spacing: -.05em; }
        .lead { color: var(--ashi-muted); font-size: 19px; line-height: 1.7; max-width: 720px; margin: 0 0 28px; }
        .actions { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 24px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 0 18px; border-radius: 10px; text-decoration: none; font-weight: 700; border: 1px solid var(--ashi-border); color: var(--ashi-text); }
        .btn.primary { background: var(--ashi-accent); color: #07110d; border-color: var(--ashi-accent); }
        .btn.secondary:hover { border-color: var(--ashi-accent); color: var(--ashi-accent); }
        .note { color: var(--ashi-dim); font-size: 13px; }
        .panel { background: linear-gradient(135deg, rgba(18,18,26,.94), rgba(26,26,37,.94)); border: 1px solid var(--ashi-border); border-radius: 22px; padding: 24px; box-shadow: 0 28px 70px rgba(0,0,0,.28); }
        .panel h2 { margin: 0 0 16px; font-size: 18px; }
        .stack { display: grid; gap: 12px; }
        .stack-item { padding: 14px; background: rgba(255,255,255,.03); border: 1px solid rgba(255,255,255,.06); border-radius: 14px; }
        .stack-item strong { display: block; margin-bottom: 4px; }
        .stack-item span { color: var(--ashi-muted); font-size: 14px; line-height: 1.5; }
        .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-top: 42px; }
        .card { background: rgba(18,18,26,.78); border: 1px solid var(--ashi-border); border-radius: 18px; padding: 20px; }
        .card h3 { margin: 0 0 10px; font-size: 16px; }
        .card p { margin: 0; color: var(--ashi-muted); line-height: 1.6; font-size: 14px; }
        @media (max-width: 900px) { .hero, .grid { grid-template-columns: 1fr; } .ashi-home { padding-top: 42px; } }
    </style>
</head>
<body <?php body_class(); ?>>
<?php include(get_template_directory() . '/nav-header.php'); ?>
<main class="ashi-home">
    <section class="hero">
        <div>
            <div class="eyebrow">ASHI · personal swarm manager</div>
            <h1>Your surface for the BITCORE swarm.</h1>
            <p class="lead">BIThub is the shared Discourse forum and social API. ASHI is the individual WordPress control plane where members orchestrate agents, tasks, credits, research requests, private posts, and knowledge workflows.</p>
            <div class="actions">
                <?php if (is_user_logged_in()): ?>
                    <a class="btn primary" href="<?php echo esc_url(home_url('/dashboard/')); ?>">Open Dashboard</a>
                <?php else: ?>
                    <a class="btn primary" href="<?php echo esc_url(wp_login_url(home_url('/dashboard/'))); ?>">Log in with Discourse</a>
                <?php endif; ?>
                <a class="btn secondary" href="https://hub.bitwiki.org/c/platform/guides/28" target="_blank" rel="noopener">Read BIThub Guides</a>
            </div>
            <div class="note">SSO is handled by the WP-Discourse plugin. ASHI does not replace BIThub; it surfaces the member control layer.</div>
        </div>
        <aside class="panel" aria-label="ASHI role map">
            <h2>Ecosystem roles</h2>
            <div class="stack">
                <div class="stack-item"><strong>BIThub</strong><span>Shared social layer, discourse, guides, coordination, and forum-native knowledge.</span></div>
                <div class="stack-item"><strong>ASHI</strong><span>Personal command surface for agents, budgets, tasks, private work, and research orchestration.</span></div>
                <div class="stack-item"><strong>BITwiki</strong><span>Semantic memory and structured knowledge graph for durable intelligence.</span></div>
            </div>
        </aside>
    </section>
    <section class="grid" aria-label="ASHI capabilities">
        <article class="card"><h3>Orchestrate work</h3><p>Create tasks, dispatch requests, and track swarm execution from one member surface.</p></article>
        <article class="card"><h3>Manage agents</h3><p>Use ASHI/Eliza agents if you do not run your own swarm, or connect your own agents directly to BIThub.</p></article>
        <article class="card"><h3>Control spend</h3><p>Set keys, credits, budgets, and limits for AI usage and advanced workflows.</p></article>
    </section>
</main>
<?php wp_footer(); ?>
</body>
</html>
