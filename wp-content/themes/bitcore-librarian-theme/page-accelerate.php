<?php
/**
 * Template Name: Accelerate
 * Description: Intelligence Factory - compile, condense, accelerate knowledge artifacts
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
<?php include(get_template_directory() . '/template-parts/accelerate-styles.php'); ?>
    </style>
</head>
<body>
    <?php include(get_template_directory() . '/nav-header.php'); ?>

    <div class="accelerate-container">
        <header class="accelerate-header">
            <div class="badge">Intelligence Factory</div>
            <h1>Accelerate</h1>
            <p>Compile, condense, and distill knowledge artifacts. Supercharge your AI workflows with reusable cognitive components.</p>
            <div class="accelerate-tagline">
                <div class="tagline-item"><span>⚡</span> Compile</div>
                <div class="tagline-item"><span>🔥</span> Condense</div>
                <div class="tagline-item"><span>💎</span> Distill</div>
            </div>
        </header>

        <div class="factory-grid">
            <!-- Input Panel -->
            <div class="input-panel">
                <h2>Input</h2>
                <textarea class="input-area" id="factory-input" placeholder="Paste your content here...

- Raw research notes
- Agent conversation logs
- Unstructured data
- Code snippets
- Documentation"></textarea>
                <div class="input-actions">
                    <button class="input-btn primary" id="btn-compile">⚡ Compile</button>
                    <button class="input-btn secondary" id="btn-clear">Clear</button>
                </div>
            </div>

            <!-- Accelerator Panel -->
            <div class="accelerator-panel">
                <h2>Accelerators</h2>
                <div class="accelerator-grid">
                    <button class="accelerator-btn" data-artifact="binary.json">
                        <div class="btn-name">Binary Logic</div>
                        <div class="btn-desc">True/False decisions</div>
                    </button>
                    <button class="accelerator-btn" data-artifact="ternary.json">
                        <div class="btn-name">Ternary Logic</div>
                        <div class="btn-desc">Yes/No/Maybe</div>
                    </button>
                    <button class="accelerator-btn" data-artifact="quaternary.json">
                        <div class="btn-name">Quaternary</div>
                        <div class="btn-desc">4-quadrant analysis</div>
                    </button>
                    <button class="accelerator-btn" data-artifact="cognitive_biases.json">
                        <div class="btn-name">Bias Check</div>
                        <div class="btn-desc">Detect biases</div>
                    </button>
                    <button class="accelerator-btn" data-artifact="decision_optimization.json">
                        <div class="btn-name">Optimize</div>
                        <div class="btn-desc">Decision algorithms</div>
                    </button>
                    <button class="accelerator-btn" data-artifact="biological.json">
                        <div class="btn-name">Biological</div>
                        <div class="btn-desc">Organic patterns</div>
                    </button>
                </div>
                <div class="artifact-loaded" id="artifact-status">
                    No artifact loaded. Select from above or use the dropdown in the nav.
                </div>
            </div>

            <!-- Output Panel -->
            <div class="output-panel">
                <h2>Output</h2>
                <div class="output-area" id="factory-output">
                    <div class="output-placeholder">
                        <div class="placeholder-icon">⚡</div>
                        <p>Your accelerated output will appear here</p>
                        <p style="font-size: 0.85rem; margin-top: 8px;">Select an accelerator and click Compile</p>
                    </div>
                </div>
                <div class="status-bar">
                    <div class="status-item" id="status-input">
                        <span class="status-dot"></span>
                        Input
                    </div>
                    <div class="status-item" id="status-artifact">
                        <span class="status-dot"></span>
                        Artifact
                    </div>
                    <div class="status-item" id="status-process">
                        <span class="status-dot"></span>
                        Processing
                    </div>
                    <div class="status-item" id="status-output">
                        <span class="status-dot"></span>
                        Output
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <div class="quick-action" onclick="window.open('https://hub.bitwiki.org', '_blank')">
                <div class="action-icon">💬</div>
                <div class="action-name">Discuss</div>
                <div class="action-desc">Join BIThub</div>
            </div>
            <div class="quick-action" onclick="window.open('https://bitwiki.org', '_blank')">
                <div class="action-icon">📚</div>
                <div class="action-name">Research</div>
                <div class="action-desc">Browse BITwiki</div>
            </div>
            <div class="quick-action" onclick="window.location.href='/artifacts/'">
                <div class="action-icon">📦</div>
                <div class="action-name">Browse</div>
                <div class="action-desc">All Artifacts</div>
            </div>
            <div class="quick-action" onclick="window.location.href='/live-feed/'">
                <div class="action-icon">📡</div>
                <div class="action-name">Monitor</div>
                <div class="action-desc">Live Activity</div>
            </div>
        </div>
    </div>

    <script>
    // Artifact selection
    let selectedArtifact = null;

    document.querySelectorAll('.accelerator-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.accelerator-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            selectedArtifact = this.dataset.artifact;
            document.getElementById('artifact-status').innerHTML = 
                'Loaded: <span class="loaded-name">' + selectedArtifact + '</span>';
            document.getElementById('status-artifact').classList.add('active');
        });
    });

    // Listen for nav dropdown artifact injection
    window.addEventListener('artifact:inject', function(e) {
        const artifact = e.detail.artifact;
        selectedArtifact = artifact;
        document.getElementById('artifact-status').innerHTML = 
            'Loaded from nav: <span class="loaded-name">' + artifact + '</span>';
        document.getElementById('status-artifact').classList.add('active');

        // Highlight matching button
        document.querySelectorAll('.accelerator-btn').forEach(btn => {
            btn.classList.remove('active');
            if (btn.dataset.artifact === artifact) {
                btn.classList.add('active');
            }
        });
    });

    // Compile button
    document.getElementById('btn-compile').addEventListener('click', function() {
        const input = document.getElementById('factory-input').value;
        if (!input.trim()) {
            alert('Please enter some content to compile');
            return;
        }
        if (!selectedArtifact) {
            alert('Please select an accelerator');
            return;
        }

        // Show processing
        document.getElementById('status-input').classList.add('active');
        document.getElementById('status-process').classList.add('active');

        // Simulate processing (in real implementation, this would call n8n)
        setTimeout(() => {
            const output = document.getElementById('factory-output');
            output.innerHTML = `<div style="color: #4ade80; margin-bottom: 16px;">
                ⚡ Compiled with ${selectedArtifact}
            </div>
            <div style="color: #888;">
                <p><strong>Input processed:</strong> ${input.length} characters</p>
                <p><strong>Artifact applied:</strong> ${selectedArtifact}</p>
                <p><strong>Status:</strong> Ready for n8n dispatch</p>
                <br>
                <p style="color: #666;">In production, this would send to n8n webhook for agent processing.</p>
            </div>`;
            document.getElementById('status-output').classList.add('active');
        }, 800);
    });

    // Clear button
    document.getElementById('btn-clear').addEventListener('click', function() {
        document.getElementById('factory-input').value = '';
        document.getElementById('factory-output').innerHTML = `
            <div class="output-placeholder">
                <div class="placeholder-icon">⚡</div>
                <p>Your accelerated output will appear here</p>
            </div>`;
        document.querySelectorAll('.status-item').forEach(s => s.classList.remove('active'));
    });
    </script>

    <?php wp_footer(); ?>
</body>
</html>