<?php
/**
 * Template: Final Dashboard
 * BitFlux in left panel, clean header
 * @package     Bitcore
 * @since       1.0.0
 * @version     1.0.0
 */
?>
<div class="bitcore-saas-container">

    <!-- Clean Header -->
    <header class="bitcore-header">
        <div class="bitcore-brand">
            <div class="bitcore-logo-mark">◈</div>
            <div class="bitcore-brand-text">
                <h1>BITCORE Librarian</h1>
                <span class="bitcore-subtitle">AI-Human Convergence Platform</span>
            </div>
        </div>

        <div class="bitcore-system-status">
            <div class="status-indicator active">
                <span class="status-dot"></span>
                <span class="status-text">System Online</span>
            </div>
            <div class="core-status">
                <span class="core-badge coraline">Coraline</span>
                <span class="core-badge hyperion">Hyperion</span>
            </div>
        </div>
    </header>

    <!-- Stats Bar -->
    <div class="bitcore-stats-bar">
        <div class="stat-item">
            <span class="stat-value">75</span>
            <span class="stat-label">Articles</span>
        </div>
        <div class="stat-item">
            <span class="stat-value bf-accent">58</span>
            <span class="stat-label">24h Activity</span>
        </div>
        <div class="stat-item">
            <span class="stat-value">1,247</span>
            <span class="stat-label">Gold</span>
        </div>
        <div class="stat-item">
            <span class="stat-value">89</span>
            <span class="stat-label">Credits</span>
        </div>
    </div>

    <!-- Main Grid -->
    <div class="bitcore-grid">

        <!-- Left: Intent Interface with BitFlux -->
        <section class="bitcore-panel bitcore-intent-panel">
            <div class="panel-header">
                <h2>Submit Research Intent</h2>
                <span class="panel-badge">Input</span>
            </div>

            <!-- BitFlux Avatar Section -->
            <div class="bitflux-section">
                <div class="bitflux-avatar-container">
                    <div id="bitflux-avatar" class="bitflux-avatar-wrapper" data-state="welcome">
                        <object type="image/svg+xml" data="<?php echo get_template_directory_uri(); ?>/assets/svg/bitflux.svg" width="80" height="80" id="bitflux-svg">
                            <div class="bitflux-fallback">◈</div>
                        </object>
                    </div>
                    <div class="bitflux-info">
                        <span class="bitflux-state-label" id="bitflux-state-text">WELCOME</span>
                        <span class="bitflux-hint">Click avatar to interact</span>
                    </div>
                </div>

                <!-- State Controls -->
                <div class="bitflux-controls-compact">
                    <button class="state-btn active" data-state="welcome" onclick="setBitFluxState('welcome')" title="Welcome">👋</button>
                    <button class="state-btn" data-state="listen" onclick="setBitFluxState('listen')" title="Listen">👂</button>
                    <button class="state-btn" data-state="chat-ready" onclick="setBitFluxState('chat-ready')" title="Chat">💬</button>
                    <button class="state-btn" data-state="focused-shield" onclick="setBitFluxState('focused-shield')" title="Shield">🛡️</button>
                    <button class="state-btn" data-state="agent-task" onclick="setBitFluxState('agent-task')" title="Task">⚙️</button>
                    <button class="state-btn" data-state="process" onclick="setBitFluxState('process')" title="Process">⚡</button>
                    <button class="state-btn" data-state="swarm-guard" onclick="setBitFluxState('swarm-guard')" title="Guard">🔒</button>
                    <button class="state-btn" data-state="crown-mode" onclick="setBitFluxState('crown-mode')" title="Crown">👑</button>
                    <button class="state-btn" data-state="portal-enter" onclick="setBitFluxState('portal-enter')" title="Portal">🌀</button>
                    <button class="state-btn" data-state="error-404" onclick="setBitFluxState('error-404')" title="Error">⚠️</button>
                    <button class="state-btn spin-btn" onclick="toggleSpin()" title="Spin">↻</button>
                </div>
            </div>

            <div class="intent-interface">
                <div class="intent-scope-selector">
                    <button class="scope-btn active" data-scope="quick" onclick="setBitFluxState('chat-ready')">
                        <span class="scope-icon">⚡</span>
                        <span class="scope-label">Quick</span>
                        <span class="scope-time">5-10 min</span>
                    </button>
                    <button class="scope-btn" data-scope="deep" onclick="setBitFluxState('focused-shield')">
                        <span class="scope-icon">🔬</span>
                        <span class="scope-label">Deep</span>
                        <span class="scope-time">30-60 min</span>
                    </button>
                    <button class="scope-btn" data-scope="explore" onclick="setBitFluxState('portal-enter')">
                        <span class="scope-icon">🔭</span>
                        <span class="scope-label">Explore</span>
                        <span class="scope-time">Open</span>
                    </button>
                </div>

                <div class="intent-input-area">
                    <textarea
                        id="bitcore-intent-input"
                        placeholder="What would you like to research? Describe your topic, question, or goal..."
                        rows="4"
                        onfocus="setBitFluxState('listen')"
                        onblur="setBitFluxState('welcome')"
                    ></textarea>
                    <div class="intent-actions">
                        <span class="intent-hint">Press Enter to submit</span>
                        <button id="bitcore-submit" class="submit-btn" onclick="setBitFluxState('agent-task')">
                            <span>Dispatch to Swarm</span>
                            <span class="btn-arrow">→</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Recent Intents -->
            <div class="recent-intents">
                <h3>Recent Requests</h3>
                <div class="intent-list">
                    <div class="intent-item">
                        <span class="intent-topic">Agent workflow patterns</span>
                        <span class="intent-scope-tag deep">Deep</span>
                        <span class="intent-status complete">Complete</span>
                    </div>
                    <div class="intent-item">
                        <span class="intent-topic">Lightning Network governance</span>
                        <span class="intent-scope-tag quick">Quick</span>
                        <span class="intent-status active">Active</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Center: Convergence Visualization -->
        <section class="bitcore-panel bitcore-convergence-panel">
            <div class="panel-header">
                <h2>Live Convergence</h2>
                <span class="panel-badge">Real-time</span>
            </div>

            <!-- Dual Core Visualization -->
            <div class="dual-core-viz">
                <div class="core-node coraline-node">
                    <div class="core-avatar">🧠</div>
                    <div class="core-info">
                        <span class="core-name">Coraline</span>
                        <span class="core-role">Orchestration</span>
                    </div>
                    <div class="core-state" data-state="active">
                        <span class="state-indicator"></span>
                        <span class="state-text">Dispatching</span>
                    </div>
                </div>

                <div class="convergence-bridge">
                    <div class="bridge-line"></div>
                    <div class="bridge-pulse"></div>
                    <div class="bridge-data">
                        <span class="data-packets">3 subordinates</span>
                        <span class="data-latency">45ms</span>
                    </div>
                </div>

                <div class="core-node hyperion-node">
                    <div class="core-avatar">⚡</div>
                    <div class="core-info">
                        <span class="core-name">Hyperion</span>
                        <span class="core-role">Execution</span>
                    </div>
                    <div class="core-state" data-state="active">
                        <span class="state-indicator"></span>
                        <span class="state-text">Validating</span>
                    </div>
                </div>
            </div>

            <!-- Guard→Do→Verify State Machine -->
            <div class="state-machine">
                <h3>Processing Pipeline</h3>
                <div class="state-flow">
                    <div class="state-step active" data-step="guard">
                        <div class="step-icon">🛡️</div>
                        <div class="step-label">Guard</div>
                        <div class="step-detail">Validating inputs</div>
                    </div>
                    <div class="state-connector active"></div>
                    <div class="state-step" data-step="do">
                        <div class="step-icon">⚙️</div>
                        <div class="step-label">Do</div>
                        <div class="step-detail">Executing task</div>
                    </div>
                    <div class="state-connector"></div>
                    <div class="state-step" data-step="verify">
                        <div class="step-icon">✓</div>
                        <div class="step-label">Verify</div>
                        <div class="step-detail">Checking outputs</div>
                    </div>
                </div>
            </div>

            <!-- Subordinate Swarm -->
            <div class="swarm-visualization">
                <h3>Subordinate Swarm</h3>
                <div class="swarm-grid">
                    <div class="swarm-node active">
                        <div class="node-icon">🔍</div>
                        <div class="node-label">Query-1</div>
                        <div class="node-progress">
                            <div class="progress-bar" style="width: 78%"></div>
                        </div>
                    </div>
                    <div class="swarm-node active">
                        <div class="node-icon">📚</div>
                        <div class="node-label">Query-2</div>
                        <div class="node-progress">
                            <div class="progress-bar" style="width: 45%"></div>
                        </div>
                    </div>
                    <div class="swarm-node waiting">
                        <div class="node-icon">🧩</div>
                        <div class="node-label">Synthesis</div>
                        <div class="node-status">Queued</div>
                    </div>
                    <div class="swarm-node waiting">
                        <div class="node-icon">🔐</div>
                        <div class="node-label">Validate</div>
                        <div class="node-status">Queued</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Right: Activity + Knowledge + Queue -->
        <section class="bitcore-panel bitcore-knowledge-panel">
            <div class="panel-header">
                <h2>Activity & Knowledge</h2>
                <span class="panel-badge">BITwiki</span>
            </div>

            <!-- Activity Graph -->
            <div class="activity-section">
                <h3>24h Activity</h3>
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

            <!-- Live Feed -->
            <div class="live-feed-section">
                <h3>Live Feed</h3>
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

            <!-- Ontology Navigation -->
            <div class="ontology-nav">
                <div class="ontology-node root">
                    <span class="node-name">Research</span>
                    <span class="node-count">156</span>
                </div>
                <div class="ontology-children">
                    <div class="ontology-node">
                        <span class="node-name">Agent Workflows</span>
                        <span class="node-count">23</span>
                    </div>
                    <div class="ontology-node active">
                        <span class="node-name">Governance</span>
                        <span class="node-count">18</span>
                    </div>
                    <div class="ontology-node">
                        <span class="node-name">Cryptography</span>
                        <span class="node-count">42</span>
                    </div>
                </div>
            </div>

            <!-- Queue Status -->
            <div class="queue-section">
                <h3>Research Queue</h3>
                <div class="queue-stats">
                    <div class="stat-item">
                        <span class="stat-value">3</span>
                        <span class="stat-label">Active</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value">12</span>
                        <span class="stat-label">Queued</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value">89</span>
                        <span class="stat-label">Complete</span>
                    </div>
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

            <!-- Epistemic Summary -->
            <div class="epistemic-summary">
                <h3>System Confidence</h3>
                <div class="confidence-meter">
                    <div class="meter-track">
                        <div class="meter-fill" style="width: 87%"></div>
                    </div>
                    <div class="meter-labels">
                        <span>0.87 avg</span>
                        <span>Verified</span>
                    </div>
                </div>
            </div>
        </section>

    </div>

    <!-- Footer -->
    <footer class="bitcore-footer">
        <div class="footer-left">
            <span>BITCORE v0.1.0</span>
            <span class="footer-separator">•</span>
            <a href="https://bitwiki.org" target="_blank">BITwiki</a>
            <span class="footer-separator">•</span>
            <a href="https://hub.bitwiki.org" target="_blank">BIThub</a>
        </div>
        <div class="footer-right">
            <span class="observer-badge">👁 Observer Mode</span>
        </div>
    </footer>

</div>

<?php include BITCORE_LIBRARIAN_PATH . 'templates/template-parts/dashboard-scripts.php'; ?>
