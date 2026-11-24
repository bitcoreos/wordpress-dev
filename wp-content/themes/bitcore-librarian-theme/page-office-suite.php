<?php
/**
 * Template Name: BITCORE Office Suite
 * Description: Full-featured personal workspace with BitFlux avatar
 * @package    Bitcore\Theme
 * @since      2026.11.23
 * @version    1.0.0
 * @author     coraline
 */

get_header();
?>

<div class="bf-office-suite">

    <!-- Header -->
    <header class="bf-header">
        <div class="bf-header-brand">
            <a href="<?php echo home_url(); ?>" class="bf-header-logo">
                <span>◈</span>
                <span>BITCORE</span>
            </a>
        </div>

        <nav class="bf-header-nav">
            <a href="#dashboard">Dashboard</a>
            <a href="#research">Research</a>
            <a href="#knowledge">Knowledge</a>
            <a href="#agents">Agents</a>
        </nav>

        <div class="bf-header-actions">
            <!-- BitFlux Avatar in Header -->
            <div id="bitflux-header-avatar" data-bitflux-avatar data-bitflux-size="48" data-bitflux-state="welcome"></div>

            <button class="bf-btn-icon" title="Notifications">
                <span class="bf-icon">🔔</span>
            </button>
            <button class="bf-btn-icon" title="Settings">
                <span class="bf-icon">⚙️</span>
            </button>
        </div>
    </header>

    <!-- Main Content -->
    <main class="bf-main">

        <!-- Left Sidebar -->
        <aside class="bf-sidebar bf-sidebar-left">

            <!-- Character Widget -->
            <div class="bf-widget bf-col-12" data-widget="character">
                <div class="bf-widget-header">
                    <h3 class="bf-widget-title">🧙 Avatar</h3>
                    <div class="bf-widget-actions">
                        <button class="bf-widget-action" title="Customize">✎</button>
                    </div>
                </div>
                <div class="bf-character-card">
                    <div class="bf-character-avatar">
                        <div id="bitflux-character-avatar" data-bitflux-avatar data-bitflux-size="80" data-bitflux-state="welcome"></div>
                    </div>
                    <div class="bf-character-level">Level 5 Researcher</div>
                    <div class="bf-character-xp-bar">
                        <div class="bf-character-xp-fill" style="width: 67%;"></div>
                    </div>
                    <div class="bf-character-currency">
                        <div class="bf-currency-item">
                            <span>🪙</span>
                            <span class="bf-currency-value">1,247</span>
                        </div>
                        <div class="bf-currency-item">
                            <span>⚡</span>
                            <span class="bf-currency-value">89</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions Widget -->
            <div class="bf-widget bf-col-12" data-widget="quick-actions">
                <div class="bf-widget-header">
                    <h3 class="bf-widget-title">⚡ Quick Actions</h3>
                </div>
                <div class="bf-feature-grid">
                    <div class="bf-feature-card" onclick="bitfluxSetState('agent-task')">
                        <div class="bf-feature-icon">🔍</div>
                        <div class="bf-feature-title">New Research</div>
                    </div>
                    <div class="bf-feature-card" onclick="bitfluxSetState('chat-ready')">
                        <div class="bf-feature-icon">💬</div>
                        <div class="bf-feature-title">Chat</div>
                    </div>
                    <div class="bf-feature-card" onclick="bitfluxSetState('process')">
                        <div class="bf-feature-icon">📊</div>
                        <div class="bf-feature-title">Analytics</div>
                    </div>
                    <div class="bf-feature-card" onclick="bitfluxSetState('swarm-guard')">
                        <div class="bf-feature-icon">🐝</div>
                        <div class="bf-feature-title">Swarm</div>
                    </div>
                </div>
            </div>

        </aside>

        <!-- Center Content -->
        <div class="bf-content">

            <!-- Hero with BitFlux -->
            <section class="bf-hero">
                <h1 class="bf-hero-title">Welcome to Your Office</h1>
                <p class="bf-hero-subtitle">Your personal workspace in the BITCORE ecosystem</p>
            </section>

            <!-- BitFlux Centerpiece -->
            <section class="bf-bitflux-center">
                <div class="bf-bitflux-avatar-large">
                    <div id="bitflux-centerpiece" data-bitflux-avatar data-bitflux-size="180" data-bitflux-state="welcome" data-bitflux-interactive="true"></div>
                </div>
                <div class="bf-bitflux-status">
                    <div class="bf-bitflux-state" id="bitflux-current-state">WELCOME</div>
                    <div class="bf-bitflux-message" id="bitflux-message">Click me to interact</div>
                </div>
            </section>

            <!-- Research Intent Form -->
            <section class="bf-widget" data-widget="research-intent">
                <div class="bf-widget-header">
                    <h3 class="bf-widget-title">🎯 Submit Research Intent</h3>
                </div>
                <form id="research-intent-form" class="bf-research-form">
                    <div class="bf-form-group">
                        <textarea 
                            id="research-input" 
                            class="bf-form-control" 
                            rows="3" 
                            placeholder="What would you like to research?"
                            onfocus="bitfluxSetState('listen')"
                            onblur="bitfluxSetState('welcome')"
                        ></textarea>
                    </div>
                    <div class="bf-form-actions">
                        <label class="bf-radio">
                            <input type="radio" name="scope" value="quick" checked>
                            <span>Quick (5-10 min)</span>
                        </label>
                        <label class="bf-radio">
                            <input type="radio" name="scope" value="deep">
                            <span>Deep (30-60 min)</span>
                        </label>
                        <button type="submit" class="bf-btn bf-btn-primary" onclick="bitfluxSetState('agent-task')">
                            Dispatch to Swarm
                        </button>
                    </div>
                </form>
            </section>

        </div>

        <!-- Right Sidebar -->
        <aside class="bf-sidebar bf-sidebar-right">

            <!-- Stats Widget -->
            <div class="bf-widget bf-col-12" data-widget="stats">
                <div class="bf-widget-header">
                    <h3 class="bf-widget-title">📊 Stats</h3>
                </div>
                <div class="bf-widget-stats" id="stats-container">
                    <div class="bf-stat-item">
                        <div class="bf-stat-value">75</div>
                        <div class="bf-stat-label">Articles</div>
                    </div>
                    <div class="bf-stat-item">
                        <div class="bf-stat-value bf-accent">58</div>
                        <div class="bf-stat-label">24h Activity</div>
                    </div>
                    <div class="bf-stat-item">
                        <div class="bf-stat-value">1,247</div>
                        <div class="bf-stat-label">Gold</div>
                    </div>
                    <div class="bf-stat-item">
                        <div class="bf-stat-value">89</div>
                        <div class="bf-stat-label">Credits</div>
                    </div>
                </div>
            </div>

            <!-- Activity Graph Widget -->
            <div class="bf-widget bf-col-12" data-widget="activity">
                <div class="bf-widget-header">
                    <h3 class="bf-widget-title">📈 Activity</h3>
                </div>
                <div class="bf-activity-graph">
                    <div class="bf-activity-bar" style="height: 30%;"></div>
                    <div class="bf-activity-bar" style="height: 45%;"></div>
                    <div class="bf-activity-bar" style="height: 60%;"></div>
                    <div class="bf-activity-bar" style="height: 40%;"></div>
                    <div class="bf-activity-bar" style="height: 75%;"></div>
                    <div class="bf-activity-bar" style="height: 55%;"></div>
                    <div class="bf-activity-bar" style="height: 80%;"></div>
                    <div class="bf-activity-bar" style="height: 65%;"></div>
                    <div class="bf-activity-bar" style="height: 90%;"></div>
                    <div class="bf-activity-bar bf-peak" style="height: 100%;"></div>
                    <div class="bf-activity-bar" style="height: 70%;"></div>
                    <div class="bf-activity-bar" style="height: 50%;"></div>
                </div>
            </div>

            <!-- Live Feed Widget -->
            <div class="bf-widget bf-col-12" data-widget="feed">
                <div class="bf-widget-header">
                    <h3 class="bf-widget-title">📰 Live Feed</h3>
                </div>
                <div class="bf-feed-list" id="live-feed">
                    <div class="bf-feed-item">
                        <div class="bf-feed-pulse bf-new"></div>
                        <div class="bf-feed-content">
                            <div class="bf-feed-text">Hyperion completed research on OpenClaw</div>
                            <div class="bf-feed-meta">2m ago</div>
                        </div>
                    </div>
                    <div class="bf-feed-item">
                        <div class="bf-feed-pulse"></div>
                        <div class="bf-feed-content">
                            <div class="bf-feed-text">New article published to bitwiki</div>
                            <div class="bf-feed-meta">15m ago</div>
                        </div>
                    </div>
                    <div class="bf-feed-item">
                        <div class="bf-feed-pulse"></div>
                        <div class="bf-feed-content">
                            <div class="bf-feed-text">Coraline dispatched swarm task</div>
                            <div class="bf-feed-meta">1h ago</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Research Queue Widget -->
            <div class="bf-widget bf-col-12" data-widget="queue">
                <div class="bf-widget-header">
                    <h3 class="bf-widget-title">🎯 Research Queue</h3>
                </div>
                <div class="bf-queue-list" id="research-queue">
                    <div class="bf-queue-item bf-status-active">
                        <div class="bf-queue-status bf-active"></div>
                        <div class="bf-queue-title">OpenClaw patterns</div>
                        <div class="bf-queue-meta">2m</div>
                    </div>
                    <div class="bf-queue-item bf-status-pending">
                        <div class="bf-queue-status bf-pending"></div>
                        <div class="bf-queue-title">BitFlux states</div>
                        <div class="bf-queue-meta">5m</div>
                    </div>
                    <div class="bf-queue-item bf-status-completed">
                        <div class="bf-queue-status bf-completed"></div>
                        <div class="bf-queue-title">Widget architecture</div>
                        <div class="bf-queue-meta">1h</div>
                    </div>
                </div>
            </div>

        </aside>

    </main>

</div>

<script>
/**
 * BitFlux State Controller
 * Global functions for state management
 */

// Initialize BitFlux when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Wait for BitFlux to be ready
    setTimeout(function() {
        initializeBitFluxInteractions();
    }, 500);
});

function initializeBitFluxInteractions() {
    // Get the centerpiece BitFlux
    var centerpiece = document.getElementById('bitflux-centerpiece');
    if (centerpiece && window.BitFluxAvatar) {
        var avatar = new BitFluxAvatar('bitflux-centerpiece', {
            defaultState: 'welcome',
            size: 180,
            interactive: true,
            debug: false
        });

        // Listen for state changes
        avatar.on('stateChange', function(data) {
            updateBitFluxDisplay(data.current);
        });

        avatar.on('click', function() {
            showBitFluxMenu();
        });

        // Store globally for access
        window.bitfluxCenterpiece = avatar;
    }

    // Initialize header avatar
    var headerAvatar = document.getElementById('bitflux-header-avatar');
    if (headerAvatar && window.BitFluxAvatar) {
        new BitFluxAvatar('bitflux-header-avatar', {
            defaultState: 'welcome',
            size: 48,
            interactive: true
        });
    }

    // Initialize character avatar
    var charAvatar = document.getElementById('bitflux-character-avatar');
    if (charAvatar && window.BitFluxAvatar) {
        new BitFluxAvatar('bitflux-character-avatar', {
            defaultState: 'welcome',
            size: 80,
            interactive: true
        });
    }
}

function bitfluxSetState(state) {
    if (window.bitfluxCenterpiece) {
        window.bitfluxCenterpiece.setState(state);
    }
    updateBitFluxDisplay(state);
}

function updateBitFluxDisplay(state) {
    var stateLabel = document.getElementById('bitflux-current-state');
    var messageLabel = document.getElementById('bitflux-message');

    if (stateLabel) {
        stateLabel.textContent = state.toUpperCase().replace('-', ' ');
    }

    if (messageLabel) {
        var messages = {
            'welcome': 'Click me to interact',
            'listen': 'I'm listening...',
            'chat-ready': 'Ready to chat!',
            'focused-shield': 'Protected mode active',
            'agent-task': 'Processing your request...',
            'process': 'Analyzing data...',
            'swarm-guard': 'Coordinating agents...',
            'crown-mode': 'Admin mode active',
            'portal-enter': 'Welcome to BITCORE',
            'error-404': 'Something went wrong'
        };
        messageLabel.textContent = messages[state] || 'Click me to interact';
    }
}

function showBitFluxMenu() {
    // Could show a modal or dropdown menu
    console.log('BitFlux menu opened');
}

// Form submission
document.getElementById('research-intent-form')?.addEventListener('submit', function(e) {
    e.preventDefault();
    var input = document.getElementById('research-input').value;
    if (input.trim()) {
        bitfluxSetState('agent-task');
        // Submit to n8n webhook
        console.log('Submitting research:', input);
    }
});
</script>

<?php get_footer(); ?>
