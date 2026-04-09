<?php
/**
 * Navigation Header for BITCORE WordPress Surface
 * Comprehensive navigation linking all 26 pages organized by category.
 * Include this in all page templates: <?php include(get_template_directory() . '/nav-header.php'); ?>
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
        <!-- Primary Links -->
        <a href="<?php echo esc_url(home_url('/')); ?>" class="nav-link <?php echo is_front_page() ? 'active' : ''; ?>">
            <span class="nav-icon">🏠</span>
            <span class="nav-label">Home</span>
        </a>
        <a href="<?php echo esc_url(get_permalink(29)); ?>" class="nav-link <?php echo is_page(29) ? 'active' : ''; ?>">
            <span class="nav-icon">📊</span>
            <span class="nav-label">Dashboard</span>
        </a>
        <a href="<?php echo esc_url(get_permalink(31)); ?>" class="nav-link <?php echo is_page(31) ? 'active' : ''; ?>">
            <span class="nav-icon">📡</span>
            <span class="nav-label">Feed</span>
        </a>

        <!-- Operations Dropdown -->
        <div class="nav-dropdown">
            <button class="nav-link nav-dropdown-trigger <?php echo (is_page(36) || is_page(63) || is_page(38)) ? 'active' : ''; ?>">
                <span class="nav-icon">⚙️</span>
                <span class="nav-label">Operations</span>
                <span class="nav-caret">▾</span>
            </button>
            <div class="nav-dropdown-menu">
                <a href="<?php echo esc_url(get_permalink(36)); ?>" class="nav-dropdown-item <?php echo is_page(36) ? 'active' : ''; ?>">
                    <span class="nav-icon">✅</span> Tasks
                </a>
                <a href="<?php echo esc_url(get_permalink(63)); ?>" class="nav-dropdown-item <?php echo is_page(63) ? 'active' : ''; ?>">
                    <span class="nav-icon">💬</span> Sessions
                </a>
                <a href="<?php echo esc_url(get_permalink(38)); ?>" class="nav-dropdown-item <?php echo is_page(38) ? 'active' : ''; ?>">
                    <span class="nav-icon">📁</span> Workspaces
                </a>
            </div>
        </div>

        <!-- Intelligence Dropdown -->
        <div class="nav-dropdown">
            <button class="nav-link nav-dropdown-trigger <?php echo (is_page(33) || is_page(32) || is_page(25)) ? 'active' : ''; ?>">
                <span class="nav-icon">🧠</span>
                <span class="nav-label">Intelligence</span>
                <span class="nav-caret">▾</span>
            </button>
            <div class="nav-dropdown-menu">
                <a href="<?php echo esc_url(get_permalink(33)); ?>" class="nav-dropdown-item <?php echo is_page(33) ? 'active' : ''; ?>">
                    <span class="nav-icon">🕸️</span> Knowledge Graph
                </a>
                <a href="<?php echo esc_url(get_permalink(32)); ?>" class="nav-dropdown-item <?php echo is_page(32) ? 'active' : ''; ?>">
                    <span class="nav-icon">🤖</span> Identities
                </a>
                <a href="<?php echo esc_url(get_permalink(25)); ?>" class="nav-dropdown-item <?php echo is_page(25) ? 'active' : ''; ?>">
                    <span class="nav-icon">⚡</span> Abilities
                </a>
            </div>
        </div>

        <!-- Management Dropdown -->
        <div class="nav-dropdown">
            <button class="nav-link nav-dropdown-trigger <?php echo (is_page(53) || is_page(54) || is_page(55) || is_page(56) || is_page(57)) ? 'active' : ''; ?>">
                <span class="nav-icon">📋</span>
                <span class="nav-label">Management</span>
                <span class="nav-caret">▾</span>
            </button>
            <div class="nav-dropdown-menu">
                <a href="<?php echo esc_url(get_permalink(53)); ?>" class="nav-dropdown-item <?php echo is_page(53) ? 'active' : ''; ?>">
                    <span class="nav-icon">🏛️</span> Org Chart
                </a>
                <a href="<?php echo esc_url(get_permalink(54)); ?>" class="nav-dropdown-item <?php echo is_page(54) ? 'active' : ''; ?>">
                    <span class="nav-icon">💰</span> Budget
                </a>
                <a href="<?php echo esc_url(get_permalink(55)); ?>" class="nav-dropdown-item <?php echo is_page(55) ? 'active' : ''; ?>">
                    <span class="nav-icon">🎯</span> Goals
                </a>
                <a href="<?php echo esc_url(get_permalink(56)); ?>" class="nav-dropdown-item <?php echo is_page(56) ? 'active' : ''; ?>">
                    <span class="nav-icon">✅</span> Approvals
                </a>
                <a href="<?php echo esc_url(get_permalink(57)); ?>" class="nav-dropdown-item <?php echo is_page(57) ? 'active' : ''; ?>">
                    <span class="nav-icon">🛒</span> Checkout
                </a>
            </div>
        </div>

        <!-- Artifacts Dropdown -->
        <div class="nav-dropdown">
            <button class="nav-link nav-dropdown-trigger <?php echo (is_page(27) || is_page(58) || is_page(62) || is_page(59) || is_page(60) || is_page(61)) ? 'active' : ''; ?>">
                <span class="nav-icon">📦</span>
                <span class="nav-label">Artifacts</span>
                <span class="nav-caret">▾</span>
            </button>
            <div class="nav-dropdown-menu">
                <a href="<?php echo esc_url(get_permalink(27)); ?>" class="nav-dropdown-item <?php echo is_page(27) ? 'active' : ''; ?>">
                    <span class="nav-icon">📦</span> Artifacts
                </a>
                <a href="<?php echo esc_url(get_permalink(58)); ?>" class="nav-dropdown-item <?php echo is_page(58) ? 'active' : ''; ?>">
                    <span class="nav-icon">🔨</span> Builder
                </a>
                <a href="<?php echo esc_url(get_permalink(62)); ?>" class="nav-dropdown-item <?php echo is_page(62) ? 'active' : ''; ?>">
                    <span class="nav-icon">✏️</span> Editor
                </a>
                <a href="<?php echo esc_url(get_permalink(59)); ?>" class="nav-dropdown-item <?php echo is_page(59) ? 'active' : ''; ?>">
                    <span class="nav-icon">📚</span> Library
                </a>
                <a href="<?php echo esc_url(get_permalink(60)); ?>" class="nav-dropdown-item <?php echo is_page(60) ? 'active' : ''; ?>">
                    <span class="nav-icon">🛍️</span> Marketplace
                </a>
                <a href="<?php echo esc_url(get_permalink(61)); ?>" class="nav-dropdown-item <?php echo is_page(61) ? 'active' : ''; ?>">
                    <span class="nav-icon">💲</span> Pricing
                </a>
            </div>
        </div>

        <!-- Tools Dropdown -->
        <div class="nav-dropdown">
            <button class="nav-link nav-dropdown-trigger <?php echo (is_page(34) || is_page(37) || is_page(30) || is_page(35) || is_page(28)) ? 'active' : ''; ?>">
                <span class="nav-icon">🔧</span>
                <span class="nav-label">Tools</span>
                <span class="nav-caret">▾</span>
            </button>
            <div class="nav-dropdown-menu">
                <a href="<?php echo esc_url(get_permalink(34)); ?>" class="nav-dropdown-item <?php echo is_page(34) ? 'active' : ''; ?>">
                    <span class="nav-icon">🔧</span> MCP Tools
                </a>
                <a href="<?php echo esc_url(get_permalink(37)); ?>" class="nav-dropdown-item <?php echo is_page(37) ? 'active' : ''; ?>">
                    <span class="nav-icon">🔗</span> Webhooks
                </a>
                <a href="<?php echo esc_url(get_permalink(30)); ?>" class="nav-dropdown-item <?php echo is_page(30) ? 'active' : ''; ?>">
                    <span class="nav-icon">📡</span> Events
                </a>
                <a href="<?php echo esc_url(get_permalink(35)); ?>" class="nav-dropdown-item <?php echo is_page(35) ? 'active' : ''; ?>">
                    <span class="nav-icon">📝</span> Office Suite
                </a>
                <a href="<?php echo esc_url(get_permalink(28)); ?>" class="nav-dropdown-item <?php echo is_page(28) ? 'active' : ''; ?>">
                    <span class="nav-icon">🖥️</span> Command Center
                </a>
            </div>
        </div>

        <!-- Accelerate (standalone) -->
        <a href="<?php echo esc_url(get_permalink(26)); ?>" class="nav-link <?php echo is_page(26) ? 'active' : ''; ?>">
            <span class="nav-icon">⚡</span>
            <span class="nav-label">Accelerate</span>
        </a>
    </div>

    <div class="nav-actions">
        <div class="artifact-selector">
            <select id="artifact-dropdown" class="artifact-dropdown">
                <option value="">⚡ Load Artifact...</option>
                <optgroup label="Decision Frameworks">
                    <option value="binary.json" data-type="decision">Binary Logic</option>
                    <option value="ternary.json" data-type="decision">Ternary Logic</option>
                    <option value="quaternary.json" data-type="decision">Quaternary Analysis</option>
                </optgroup>
                <optgroup label="Cognitive Patterns">
                    <option value="biological.json" data-type="pattern">Biological Patterns</option>
                    <option value="cognitive_biases.json" data-type="pattern">Cognitive Biases</option>
                    <option value="heuristic_faults.json" data-type="pattern">Heuristic Faults</option>
                    <option value="rational_failures.json" data-type="pattern">Rational Failures</option>
                </optgroup>
                <optgroup label="Optimization">
                    <option value="decision_optimization.json" data-type="optimization">Decision Optimization</option>
                    <option value="circumpunct_context.json" data-type="context">Circumpunct Context</option>
                </optgroup>
                <optgroup label="Agent Resources">
                    <option value="agents-reference" data-type="reference">Agents Reference</option>
                    <option value="llms-personas" data-type="directory">LLMs & Personas</option>
                </optgroup>
            </select>
            <button id="inject-artifact" class="nav-btn">Inject</button>
        </div>

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

.artifact-selector {
    display: flex;
    gap: 8px;
}

.artifact-dropdown {
    padding: 10px 14px;
    background: #1a1a2e;
    border: 1px solid #2a2a3e;
    border-radius: 6px;
    color: #e0e0e0;
    font-size: 0.85rem;
    min-width: 180px;
    cursor: pointer;
}

.artifact-dropdown optgroup {
    color: #666;
    font-weight: 600;
}

.artifact-dropdown option {
    color: #e0e0e0;
    padding: 4px;
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
    .artifact-selector {
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
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.nav-dropdown')) {
            document.querySelectorAll('.nav-dropdown-menu').forEach(function(menu) {
                menu.style.display = '';
            });
        }
    });

    // Toggle dropdown on click for mobile
    document.querySelectorAll('.nav-dropdown-trigger').forEach(function(trigger) {
        trigger.addEventListener('click', function(e) {
            var menu = this.nextElementSibling;
            var isOpen = menu.style.display === 'block';
            // Close all dropdowns first
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
