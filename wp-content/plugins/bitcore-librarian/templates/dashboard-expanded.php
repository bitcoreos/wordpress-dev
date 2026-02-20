<?php
/**
 * Expanded BITCORE Command Center Dashboard
 * Incorporates BITstream + BitHub Cockpit + Features
 */
?>
<div class="bitcore-command-center">
    
    <!-- Hero Section (BITstream style) -->
    <section class="hero-section">
        <div class="hero-badge">
            <span class="status-dot"></span>
            SYSTEM ACTIVE • RECURSIVE LEARNING ENABLED
        </div>
        <h1 class="hero-title">
            <span class="gradient-text">The Intelligence</span>
            <span class="white-text">Factory</span>
        </h1>
        <p class="hero-subtitle">
            Recursive cognition for multi-dimensional systems. Aggregating the 
            frontiers of knowledge into a unified, living stream.
        </p>
        <div class="hero-actions">
            <button class="btn btn-primary">
                <span>Explore Feed</span>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M5 12h14M12 5l7 7-7 7"/>
                </svg>
            </button>
            <button class="btn btn-secondary">
                <span>Enter BIThub</span>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6M15 3h6v6M10 14L21 3"/>
                </svg>
            </button>
        </div>
    </section>
    
    <!-- Stats Cards (BITstream style) -->
    <section class="stats-section">
        <div class="stat-card">
            <div class="stat-icon">📚</div>
            <div class="stat-value">75</div>
            <div class="stat-label">TOTAL ARTICLES</div>
        </div>
        <div class="stat-card highlight">
            <div class="stat-icon">📈</div>
            <div class="stat-value">58</div>
            <div class="stat-label">LAST 24H</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🕐</div>
            <div class="stat-value">17m</div>
            <div class="stat-label">LATEST</div>
        </div>
    </section>
    
    <!-- Activity Graph -->
    <section class="activity-section">
        <div class="section-header">
            <h3>Activity — Last 24 Hours</h3>
            <span class="section-meta">58 articles</span>
        </div>
        <div class="activity-chart">
            <div class="chart-bar" style="height: 20%"></div>
            <div class="chart-bar" style="height: 35%"></div>
            <div class="chart-bar" style="height: 45%"></div>
            <div class="chart-bar" style="height: 30%"></div>
            <div class="chart-bar" style="height: 25%"></div>
            <div class="chart-bar" style="height: 40%"></div>
            <div class="chart-bar" style="height: 55%"></div>
            <div class="chart-bar" style="height: 35%"></div>
            <div class="chart-bar" style="height: 20%"></div>
            <div class="chart-bar" style="height: 30%"></div>
            <div class="chart-bar" style="height: 25%"></div>
            <div class="chart-bar highlight" style="height: 85%"></div>
        </div>
        <div class="chart-labels">
            <span>Now</span>
            <span>12h ago</span>
            <span>24h ago</span>
        </div>
    </section>
    
    <!-- Gamification Panel (BitHub Cockpit style) -->
    <section class="gamification-section">
        <div class="character-card">
            <div class="character-avatar">
                <div class="avatar-ring level-5">
                    <span class="avatar-emoji">🧙‍♂️</span>
                </div>
                <div class="level-badge">Lv. 5</div>
            </div>
            <div class="character-stats">
                <div class="character-name">Knowledge Seeker</div>
                <div class="xp-bar">
                    <div class="xp-fill" style="width: 67%"></div>
                    <span class="xp-text">2,340 / 3,500 XP</span>
                </div>
                <div class="currency-row">
                    <div class="currency">
                        <span class="currency-icon">🪙</span>
                        <span class="currency-value">1,247</span>
                        <span class="currency-label">Gold</span>
                    </div>
                    <div class="currency">
                        <span class="currency-icon">⚡</span>
                        <span class="currency-value">89</span>
                        <span class="currency-label">Credits</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="achievements-row">
            <div class="achievement unlocked" title="First Research">🔬</div>
            <div class="achievement unlocked" title="10 Articles">📚</div>
            <div class="achievement unlocked" title="Validator">✅</div>
            <div class="achievement locked" title="Swarm Master">🐝</div>
            <div class="achievement locked" title="Knowledge Architect">🏛️</div>
        </div>
    </section>
    
    <!-- Live Feed -->
    <section class="feed-section">
        <div class="section-header">
            <h3>Live Feed</h3>
            <div class="feed-filters">
                <button class="filter active">All</button>
                <button class="filter">🤖 AI</button>
                <button class="filter">🔬 Science</button>
                <button class="filter">💻 Coding</button>
            </div>
        </div>
        <div class="feed-list">
            <div class="feed-item">
                <div class="feed-indicator"></div>
                <div class="feed-content">
                    <div class="feed-meta">
                        <span class="feed-tag coding">CODING</span>
                        <span class="feed-time">17 minutes ago</span>
                        <span class="feed-source">by system</span>
                    </div>
                    <div class="feed-title">@tom_doerr: Self-hosted platform for diagrams and knowledge management</div>
                    <div class="feed-excerpt">This is a companion discussion topic for the original entry at https://x.com/tom_doerr/status/2023967617560551508...</div>
                </div>
            </div>
            <div class="feed-item">
                <div class="feed-indicator"></div>
                <div class="feed-content">
                    <div class="feed-meta">
                        <span class="feed-tag coding">CODING</span>
                        <span class="feed-time">32 minutes ago</span>
                        <span class="feed-source">by system</span>
                    </div>
                    <div class="feed-title">@tom_doerr: Agent skills for design guidelines</div>
                    <div class="feed-excerpt">This is a companion discussion topic for the original entry at https://x.com/tom_doerr/status/2023963795115086187...</div>
                </div>
            </div>
            <div class="feed-item">
                <div class="feed-indicator"></div>
                <div class="feed-content">
                    <div class="feed-meta">
                        <span class="feed-tag science">SCIENCE</span>
                        <span class="feed-time">1 hour ago</span>
                        <span class="feed-source">by coraline</span>
                    </div>
                    <div class="feed-title">Research completed: Lightning Network governance patterns</div>
                    <div class="feed-excerpt">Confidence: 0.89 | Subordinates: 4 | Output: bitwiki.org/Lightning_Governance</div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Features Grid (6 cards) -->
    <section class="features-section">
        <div class="section-header centered">
            <span class="section-badge">Features</span>
            <h2>Everything You Need to Thrive</h2>
            <p>A comprehensive productivity suite designed for the BitWiki community</p>
        </div>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">🏆</div>
                <h4>Habitica-Style Gamification</h4>
                <p>Build your RPG character, earn XP & gold for completing tasks, lose HP for missed dailies.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🎯</div>
                <h4>Tasks, Habits & Dailies</h4>
                <p>Track habits, manage daily routines, and organize to-dos with a proven behavior-change system.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📅</div>
                <h4>Calendar & Planning</h4>
                <p>Unified calendar view with goals, weekly reviews, and progress tracking.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🕸️</div>
                <h4>Knowledge Graph</h4>
                <p>Visualize connections between your notes, tasks, and BitWiki contributions.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">⚡</div>
                <h4>Workflow Automation</h4>
                <p>Create triggers and automations to streamline repetitive tasks.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">👥</div>
                <h4>Community Integration</h4>
                <p>Connect with BitHub forum and collaborate with the BitWiki community.</p>
            </div>
        </div>
    </section>
    
    <!-- Research Intent (original feature) -->
    <section class="research-section">
        <div class="section-header">
            <h3>Submit Research Intent</h3>
            <span class="credits-badge">⚡ 89 credits available</span>
        </div>
        <div class="intent-form">
            <textarea class="intent-input" placeholder="What do you want to research? (e.g., 'Agent workflow patterns in distributed systems')" rows="3"></textarea>
            <div class="intent-options">
                <label class="option">
                    <input type="radio" name="scope" value="quick" checked>
                    <span class="option-label">Quick</span>
                    <span class="option-desc">5-10 min • 5 credits</span>
                </label>
                <label class="option">
                    <input type="radio" name="scope" value="deep">
                    <span class="option-label">Deep</span>
                    <span class="option-desc">30-60 min • 20 credits</span>
                </label>
                <label class="option">
                    <input type="radio" name="scope" value="explore">
                    <span class="option-label">Explore</span>
                    <span class="option-desc">Multi-hour • 50 credits</span>
                </label>
            </div>
            <button class="btn btn-primary btn-full">
                <span>Dispatch to Swarm</span>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/>
                </svg>
            </button>
        </div>
    </section>
    
    <!-- Powered By -->
    <section class="powered-section">
        <div class="powered-card">
            <div class="powered-label">POWERED BY BITCORE</div>
            <p>The recursive cognition engine that unifies agents, knowledge, and operations into a single self-correcting system.</p>
            <a href="https://bitwiki.org" target="_blank" class="powered-link">
                Learn more 
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M5 12h14M12 5l7 7-7 7"/>
                </svg>
            </a>
        </div>
    </section>
    
</div>
