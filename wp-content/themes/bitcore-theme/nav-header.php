<?php
/**
 * Navigation Header for BITCORE WordPress Surface
 * Core SaaS navigation — 9 pages only.
 * Include in page templates: <?php include(get_template_directory() . '/nav-header.php'); ?>
 */
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
        <a href="<?php echo esc_url(get_permalink(75)); ?>" class="nav-link <?php echo is_page(75) ? 'active' : ''; ?>">
            <span class="nav-icon">⚙️</span>
            <span class="nav-label">Work</span>
        </a>
        <a href="<?php echo esc_url(get_permalink(76)); ?>" class="nav-link <?php echo is_page(76) ? 'active' : ''; ?>">
            <span class="nav-icon">🤖</span>
            <span class="nav-label">Agents</span>
        </a>
        <a href="<?php echo esc_url(get_permalink(77)); ?>" class="nav-link <?php echo is_page(77) ? 'active' : ''; ?>">
            <span class="nav-icon">💬</span>
            <span class="nav-label">Chat</span>
        </a>

        <!-- Management Dropdown -->
        <div class="nav-dropdown">
            <button class="nav-link nav-dropdown-trigger <?php echo (is_page(63) || is_page(56) || is_page(55) || is_page(54) || is_page(53)) ? 'active' : ''; ?>">
                <span class="nav-icon">📋</span>
                <span class="nav-label">Manage</span>
                <span class="nav-caret">▾</span>
            </button>
            <div class="nav-dropdown-menu">
                <a href="<?php echo esc_url(get_permalink(63)); ?>" class="nav-dropdown-item <?php echo is_page(63) ? 'active' : ''; ?>">
                    <span class="nav-icon">💬</span> Sessions
                </a>
                <a href="<?php echo esc_url(get_permalink(56)); ?>" class="nav-dropdown-item <?php echo is_page(56) ? 'active' : ''; ?>">
                    <span class="nav-icon">✅</span> Approvals
                </a>
                <a href="<?php echo esc_url(get_permalink(55)); ?>" class="nav-dropdown-item <?php echo is_page(55) ? 'active' : ''; ?>">
                    <span class="nav-icon">🎯</span> Goals
                </a>
                <a href="<?php echo esc_url(get_permalink(54)); ?>" class="nav-dropdown-item <?php echo is_page(54) ? 'active' : ''; ?>">
                    <span class="nav-icon">💰</span> Budget
                </a>
                <a href="<?php echo esc_url(get_permalink(53)); ?>" class="nav-dropdown-item <?php echo is_page(53) ? 'active' : ''; ?>">
                    <span class="nav-icon">🏛️</span> Org Chart
                </a>
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

        <a href="https://hub.bitwiki.org" class="nav-btn primary" target="blank">
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

.nav-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
}

.nav-logo {
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    color: #fff;
    font-weight: 600;
    font-size: 1.1rem;
}

.nav-symbol {
    color: #4ade80;
    font-size: 1.3rem;
}

.nav-tagline {
    color: #666;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.nav-links {
    display: flex;
    gap: 4px;
    align-items: center;
}

.nav-link {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    color: #888;
    text-decoration: none;
    border-radius: 8px;
    font-size: 0.85rem;
    transition: all 0.2s;
    cursor: pointer;
    background: none;
    border: none;
    font-family: inherit;
    white-space: nowrap;
}

.nav-link:hover {
    background: #1a1a2e;
    color: #fff;
}

.nav-link.active {
    background: #1a1a2e;
    color: #4ade80;
}

.nav-icon {
    font-size: 1rem;
}

/* === Dropdown Navigation === */
.nav-dropdown {
    position: relative;
}

.nav-dropdown-trigger {
    /* Inherits .nav-link styles */
}

.nav-caret {
    font-size: 0.65rem;
    margin-left: 2px;
    opacity: 0.6;
}

.nav-dropdown-menu {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    min-width: 200px;
    background: #12121a;
    border: 1px solid #2a2a3e;
    border-radius: 10px;
    padding: 6px 0;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
    z-index: 1001;
}

.nav-dropdown:hover .nav-dropdown-menu,
.nav-dropdown:focus-within .nav-dropdown-menu {
    display: block;
}

.nav-dropdown-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    color: #a0a0b0;
    text-decoration: none;
    font-size: 0.85rem;
    transition: all 0.15s;
}

.nav-dropdown-item:hover {
    background: #1e1e2e;
    color: #fff;
}

.nav-dropdown-item.active {
    color: #4ade80;
}

/* === Nav Actions === */
.nav-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
}

.nav-user {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: #1a1a2e;
    border: 1px solid #2a2a3e;
    border-radius: 6px;
    color: #e0e0e0;
    font-size: 0.85rem;
}

.nav-user-icon {
    font-size: 1rem;
}

.nav-user-name {
    max-width: 120px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.nav-btn {
    padding: 10px 18px;
    background: #1a1a2e;
    border: 1px solid #2a2a3e;
    border-radius: 6px;
    color: #888;
    font-size: 0.85rem;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s;
}

.nav-btn:hover {
    border-color: #4ade80;
    color: #4ade80;
}

.nav-btn.primary {
    background: linear-gradient(135deg, #4ade80 0%, #22c55e 100%);
    color: #000;
    border: none;
    font-weight: 500;
}

.nav-btn.primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(74, 222, 128, 0.3);
}

/* === Responsive === */
@media (max-width: 1200px) {
    .nav-label {
        display: none;
    }
    .nav-caret {
        display: none;
    }
    .nav-dropdown-item {
        font-size: 0.8rem;
    }
}

@media (max-width: 1024px) {
    .bitcore-nav {
        flex-wrap: wrap;
        gap: 12px;
        padding: 12px 16px;
    }
    .nav-links {
        order: 3;
        width: 100%;
        justify-content: center;
        flex-wrap: wrap;
    }
    .nav-tagline {
        display: none;
    }
}

@media (max-width: 768px) {
    .nav-label {
        display: none;
    }
    .nav-caret {
        display: none;
    }
    .nav-user-name {
        display: none;
    }
    .nav-dropdown-menu {
        min-width: 160px;
    }
}
</style>

<script>
/* Dropdown keyboard accessibility and click-outside close */
document.addEventListener('DOMContentLoaded', function() {
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.nav-dropdown')) {
            document.querySelectorAll('.nav-dropdown-menu').forEach(function(menu) {
                menu.style.display = '';
            });
        }
    });

    document.querySelectorAll('.nav-dropdown-trigger').forEach(function(trigger) {
        trigger.addEventListener('click', function(e) {
            var menu = this.nextElementSibling;
            var isOpen = menu.style.display === 'block';
            document.querySelectorAll('.nav-dropdown-menu').forEach(function(m) {
                m.style.display = '';
            });
            if (!isOpen) {
                menu.style.display = 'block';
            }
        });
    });
});
</script>
