<?php
/**
 * Navigation Header for BITCORE WordPress Surface
 * Include this in all page templates
 * @package    Bitcore\Theme
 * @since      2026.11.23
 * @version    1.0.0
 * @author     coraline
 */
?>
<nav class="bitcore-nav">
    <div class="nav-brand">
        <a href="/" class="nav-logo">
            <span class="nav-symbol">◉</span>
            <span class="nav-title">BITCORE</span>
        </a>
        <span class="nav-tagline">Intelligence Factory</span>
    </div>

    <div class="nav-links">
        <a href="/bitcore-librarian/" class="nav-link <?php echo is_page('bitcore-librarian') ? 'active' : ''; ?>">
            <span class="nav-icon">🧠</span>
            <span class="nav-label">Librarian</span>
        </a>
        <a href="/live-feed/" class="nav-link <?php echo is_page('live-feed') ? 'active' : ''; ?>">
            <span class="nav-icon">📡</span>
            <span class="nav-label">Live Feed</span>
        </a>
        <a href="/artifacts/" class="nav-link <?php echo is_page('artifacts') ? 'active' : ''; ?>">
            <span class="nav-icon">📦</span>
            <span class="nav-label">Artifacts</span>
        </a>
        <a href="/accelerate/" class="nav-link <?php echo is_page('accelerate') ? 'active' : ''; ?>">
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

        <a href="https://hub.bitwiki.org" class="nav-btn primary" target="_blank">
            Enter BIThub →
        </a>
    </div>
</nav>

<style>
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
}
.nav-brand {
    display: flex;
    align-items: center;
    gap: 12px;
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
    gap: 8px;
}
.nav-link {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    color: #888;
    text-decoration: none;
    border-radius: 8px;
    font-size: 0.9rem;
    transition: all 0.2s;
}
.nav-link:hover, .nav-link.active {
    background: #1a1a2e;
    color: #fff;
}
.nav-link.active {
    color: #4ade80;
}
.nav-icon {
    font-size: 1rem;
}
.nav-actions {
    display: flex;
    align-items: center;
    gap: 12px;
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
@media (max-width: 1024px) {
    .bitcore-nav {
        flex-wrap: wrap;
        gap: 16px;
    }
    .nav-links {
        order: 3;
        width: 100%;
        justify-content: center;
    }
}
@media (max-width: 768px) {
    .nav-label {
        display: none;
    }
    .artifact-selector {
        display: none;
    }
}
</style>

<script>
document.getElementById('inject-artifact')?.addEventListener('click', function() {
    const select = document.getElementById('artifact-dropdown');
    const artifact = select.value;
    if (!artifact) {
        alert('Select an artifact first');
        return;
    }

    // Dispatch event for pages to listen to
    window.dispatchEvent(new CustomEvent('artifact:inject', {
        detail: { artifact: artifact, timestamp: Date.now() }
    }));

    // Visual feedback
    this.textContent = 'Injected!';
    this.style.background = '#4ade80';
    this.style.color = '#000';
    setTimeout(() => {
        this.textContent = 'Inject';
        this.style.background = '';
        this.style.color = '';
    }, 1500);
});
</script>