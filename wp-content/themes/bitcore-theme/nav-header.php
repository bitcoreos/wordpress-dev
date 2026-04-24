<?php
/**
 * Navigation Header for BITCORE WordPress Surface
 * Links only to EXISTING pages using slug-based resolution for resilience.
 * Include: <?php include(get_template_directory() . '/nav-header.php'); ?>
 */

// Resolve pages by slug — returns URL or null if missing
function _bitcore_nav_url($slug) {
    $page = get_page_by_path($slug);
    return $page && !is_wp_error($page) ? get_permalink($page->ID) : null;
}

// Helper: active state by slug
function _bitcore_nav_active($slug) {
    $page = get_page_by_path($slug);
    return $page && is_page($page->ID) ? 'active' : '';
}

// Core pages that MUST exist for LIVE readiness
$nav_pages = [
    'dashboard'   => ['icon' => '📊', 'label' => 'Dashboard'],
    'work'        => ['icon' => '💼', 'label' => 'Work'],
    'tasks'       => ['icon' => '✅', 'label' => 'Tasks'],
    'agents'      => ['icon' => '🤖', 'label' => 'Agents'],
    'chat'        => ['icon' => '💬', 'label' => 'Chat'],
];

// Secondary pages (shown if they exist)
$secondary_pages = [
    'goals'       => ['icon' => '🎯', 'label' => 'Goals'],
    'approvals'   => ['icon' => '📋', 'label' => 'Approvals'],
    'budget'      => ['icon' => '💰', 'label' => 'Budget'],
    'org-chart'   => ['icon' => '🏛️', 'label' => 'Org Chart'],
    'sessions'    => ['icon' => '🔌', 'label' => 'Sessions'],
    'checkout'    => ['icon' => '🛒', 'label' => 'Checkout'],
];
?>
<nav class="bitcore-nav">
    <div class="nav-brand">
        <a href="<?php echo esc_url(home_url('/')); ?>" class="nav-logo">
            <span class="nav-symbol">◉</span>
            <span class="nav-title">BITCORE</span>
        </a>
        <span class="nav-tagline">Intelligence Factory</span>
    </div>

    <div class="nav-links">
        <a href="<?php echo esc_url(home_url('/')); ?>" class="nav-link <?php echo is_front_page() ? 'active' : ''; ?>">
            <span class="nav-icon">🏠</span>
            <span class="nav-label">Home</span>
        </a>

        <?php foreach ($nav_pages as $slug => $meta):
            $url = _bitcore_nav_url($slug);
            if (!$url) continue;
        ?>
        <a href="<?php echo esc_url($url); ?>" class="nav-link <?php echo _bitcore_nav_active($slug); ?>">
            <span class="nav-icon"><?php echo $meta['icon']; ?></span>
            <span class="nav-label"><?php echo esc_html($meta['label']); ?></span>
        </a>
        <?php endforeach; ?>

        <!-- Secondary Dropdown -->
        <div class="nav-dropdown">
            <button class="nav-link nav-dropdown-trigger">
                <span class="nav-icon">⚙️</span>
                <span class="nav-label">More</span>
                <span class="nav-caret">▾</span>
            </button>
            <div class="nav-dropdown-menu">
                <?php foreach ($secondary_pages as $slug => $meta):
                    $url = _bitcore_nav_url($slug);
                    if (!$url) continue;
                ?>
                <a href="<?php echo esc_url($url); ?>" class="nav-dropdown-item <?php echo _bitcore_nav_active($slug); ?>">
                    <span class="nav-icon"><?php echo $meta['icon']; ?></span> <?php echo esc_html($meta['label']); ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="nav-actions">
        <?php if (is_user_logged_in()): ?>
        <div class="nav-user">
            <span class="nav-user-icon">👤</span>
            <span class="nav-user-name"><?php echo esc_html(wp_get_current_user()->display_name); ?></span>
        </div>
        <?php endif; ?>

        <a href="https://hub.bitwiki.org" class="nav-btn primary" target="_blank">
            Enter BIThub →
        </a>
    </div>
</nav>

<style>
/* === Navigation Bar === */
.bitcore-nav {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 32px;
    background: #0d0d14;
    border-bottom: 1px solid #1a1a2e;
    position: sticky;
    top: 0;
    z-index: 1000;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}
.nav-brand { display: flex; align-items: center; gap: 12px; flex-shrink: 0; }
.nav-logo { display: flex; align-items: center; gap: 8px; text-decoration: none; color: #fff; font-weight: 600; font-size: 1.1rem; }
.nav-symbol { color: #4ade80; font-size: 1.3rem; }
.nav-tagline { color: #666; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; }
.nav-links { display: flex; gap: 4px; align-items: center; }
.nav-link {
    display: flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px;
    color: #a0a0b0; text-decoration: none; font-size: 0.9rem; font-weight: 500;
    transition: all 0.2s ease; white-space: nowrap;
}
.nav-link:hover, .nav-link.active { background: rgba(74, 222, 128, 0.1); color: #4ade80; }
.nav-icon { font-size: 1rem; }
.nav-dropdown { position: relative; }
.nav-dropdown-trigger { cursor: pointer; background: none; border: none; }
.nav-dropdown-menu {
    display: none; position: absolute; top: 100%; left: 0; margin-top: 8px;
    background: #161622; border: 1px solid #2a2a3e; border-radius: 10px; padding: 8px;
    min-width: 180px; box-shadow: 0 10px 40px rgba(0,0,0,0.4);
}
.nav-dropdown:hover .nav-dropdown-menu { display: block; }
.nav-dropdown-item {
    display: flex; align-items: center; gap: 8px; padding: 8px 12px;
    border-radius: 6px; color: #a0a0b0; text-decoration: none; font-size: 0.85rem;
}
.nav-dropdown-item:hover, .nav-dropdown-item.active { background: rgba(74, 222, 128, 0.1); color: #4ade80; }
.nav-actions { display: flex; align-items: center; gap: 16px; }
.nav-user { display: flex; align-items: center; gap: 6px; color: #a0a0b0; font-size: 0.85rem; }
.nav-btn {
    padding: 8px 18px; border-radius: 8px; font-size: 0.85rem; font-weight: 600;
    text-decoration: none; transition: all 0.2s ease;
}
.nav-btn.primary { background: #4ade80; color: #0d0d14; }
.nav-btn.primary:hover { background: #22c55e; transform: translateY(-1px); }

/* Mobile */
@media (max-width: 900px) {
    .bitcore-nav { flex-wrap: wrap; padding: 12px 16px; }
    .nav-links { order: 3; width: 100%; overflow-x: auto; padding-top: 8px; }
    .nav-label { display: none; }
}
</style>
