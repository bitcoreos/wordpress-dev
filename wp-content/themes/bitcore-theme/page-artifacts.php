<?php
/**
 * Template Name: Artifact Browser
 * Description: Browse reusable AI components from hub.bitwiki.org/artifacts
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
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0a0a0f;
            color: #e0e0e0;
            line-height: 1.6;
        }
        .artifact-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        .artifact-header {
            text-align: center;
            margin-bottom: 60px;
            padding: 60px 40px;
            background: linear-gradient(135deg, #111118 0%, #1a1a2e 100%);
            border-radius: 16px;
            border: 1px solid #2a2a3e;
        }
        .artifact-header .badge {
            display: inline-block;
            padding: 8px 16px;
            background: #4ade80;
            color: #000;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-radius: 20px;
            margin-bottom: 24px;
        }
        .artifact-header h1 {
            font-size: 2.5rem;
            font-weight: 300;
            color: #fff;
            margin-bottom: 16px;
        }
        .artifact-header p {
            font-size: 1.1rem;
            color: #888;
            max-width: 600px;
            margin: 0 auto;
        }
        .artifact-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 24px;
        }
        .artifact-card {
            background: #111118;
            border: 1px solid #1a1a2e;
            border-radius: 12px;
            padding: 28px;
            transition: all 0.3s;
            position: relative;
        }
        .artifact-card:hover {
            transform: translateY(-4px);
            border-color: #4ade80;
            box-shadow: 0 12px 32px rgba(74, 222, 128, 0.1);
        }
        .artifact-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #4ade80, #22c55e);
            opacity: 0;
            transition: opacity 0.3s;
        }
        .artifact-card:hover::before {
            opacity: 1;
        }
        .artifact-type {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            background: #1a1a2e;
            border-radius: 6px;
            font-size: 0.75rem;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 16px;
        }
        .artifact-type.json::before { content: '{ }'; color: #f59e0b; font-weight: bold; }
        .artifact-type.reference::before { content: '📋'; }
        .artifact-type.directory::before { content: '📁'; }
        .artifact-card h3 {
            font-size: 1.15rem;
            font-weight: 600;
            color: #fff;
            margin-bottom: 12px;
            line-height: 1.3;
        }
        .artifact-card p {
            color: #888;
            font-size: 0.9rem;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        .artifact-meta {
            display: flex;
            gap: 16px;
            font-size: 0.8rem;
            color: #666;
            margin-bottom: 20px;
        }
        .artifact-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .artifact-actions {
            display: flex;
            gap: 12px;
        }
        .artifact-btn {
            flex: 1;
            padding: 10px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            text-align: center;
            transition: all 0.2s;
        }
        .artifact-btn.primary {
            background: #1a1a2e;
            color: #4ade80;
            border: 1px solid #2a2a3e;
        }
        .artifact-btn.primary:hover {
            background: #4ade80;
            color: #000;
            border-color: #4ade80;
        }
        .artifact-btn.secondary {
            background: transparent;
            color: #888;
            border: 1px solid #2a2a3e;
        }
        .artifact-btn.secondary:hover {
            border-color: #888;
            color: #fff;
        }
        .artifact-preview {
            margin-top: 20px;
            padding: 16px;
            background: #0a0a0f;
            border-radius: 8px;
            border: 1px solid #1a1a2e;
            font-family: 'SF Mono', Monaco, monospace;
            font-size: 0.75rem;
            color: #888;
            max-height: 120px;
            overflow: hidden;
            position: relative;
        }
        .artifact-preview::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 40px;
            background: linear-gradient(transparent, #0a0a0f);
        }
        .artifact-preview code {
            color: #4ade80;
        }
        .artifact-preview .key { color: #60a5fa; }
        .artifact-preview .string { color: #a3e635; }
        .artifact-preview .number { color: #f472b6; }

        @media (max-width: 768px) {
            .artifact-grid {
                grid-template-columns: 1fr;
            }
            .artifact-header h1 {
                font-size: 1.75rem;
            }
        }
    </style>
</head>
<body>
    <?php include(get_template_directory() . "/nav-header.php"); ?>
    <div class="artifact-container">
        <header class="artifact-header">
            <div class="badge">Dynamic Component Library</div>
            <h1>Artifacts</h1>
            <p>Structured resources that can be ingested by AI agents. Reusable components for the BITCORE ecosystem.</p>
        </header>

        <div class="artifact-grid">
            <!-- JSON Artifacts -->
            <div class="artifact-card">
                <div class="artifact-type json">JSON</div>
                <h3>circumpunct_context.json</h3>
                <p>Core context definition for the circumpunct symbol system used across BITCORE ontologies.</p>
                <div class="artifact-meta">
                    <span>👤 @iamcapote</span>
                    <span>👁 7 views</span>
                </div>
                <div class="artifact-preview">
                    <span class="key">"symbol"</span>: <span class="string">"circumpunct"</span>,<br>
                    <span class="key">"meaning"</span>: <span class="string">"centralized consciousness"</span>,<br>
                    <span class="key">"contexts"</span>: [...]
                </div>
                <div class="artifact-actions">
                    <a href="https://hub.bitwiki.org/t/circumpunct-context-json/30145" class="artifact-btn primary" target="_blank">View Discussion</a>
                    <a href="https://hub.bitwiki.org/t/circumpunct-context-json/30145.json" class="artifact-btn secondary" target="_blank">Raw JSON</a>
                </div>
            </div>

            <div class="artifact-card">
                <div class="artifact-type json">JSON</div>
                <h3>binary.json</h3>
                <p>Binary decision framework for agent reasoning and dual-state evaluations.</p>
                <div class="artifact-meta">
                    <span>👤 @iamcapote</span>
                    <span>👁 4 views</span>
                </div>
                <div class="artifact-preview">
                    <span class="key">"type"</span>: <span class="string">"decision_framework"</span>,<br>
                    <span class="key">"states"</span>: [<span class="string">"true"</span>, <span class="string">"false"</span>],<br>
                    <span class="key">"logic"</span>: <span class="string">"classical"</span>
                </div>
                <div class="artifact-actions">
                    <a href="https://hub.bitwiki.org/t/binary-json/30145" class="artifact-btn primary" target="_blank">View Discussion</a>
                    <a href="https://hub.bitwiki.org/t/binary-json/30145.json" class="artifact-btn secondary" target="_blank">Raw JSON</a>
                </div>
            </div>

            <div class="artifact-card">
                <div class="artifact-type json">JSON</div>
                <h3>ternary.json</h3>
                <p>Three-state logic framework for nuanced agent decision making beyond binary.</p>
                <div class="artifact-meta">
                    <span>👤 @iamcapote</span>
                    <span>👁 8 views</span>
                </div>
                <div class="artifact-preview">
                    <span class="key">"type"</span>: <span class="string">"decision_framework"</span>,<br>
                    <span class="key">"states"</span>: [<span class="string">"yes"</span>, <span class="string">"no"</span>, <span class="string">"maybe"</span>],<br>
                    <span class="key">"logic"</span>: <span class="string">"three-valued"</span>
                </div>
                <div class="artifact-actions">
                    <a href="https://hub.bitwiki.org/t/ternary-json/30145" class="artifact-btn primary" target="_blank">View Discussion</a>
                    <a href="https://hub.bitwiki.org/t/ternary-json/30145.json" class="artifact-btn secondary" target="_blank">Raw JSON</a>
                </div>
            </div>

            <div class="artifact-card">
                <div class="artifact-type json">JSON</div>
                <h3>quaternary.json</h3>
                <p>Four-quadrant analysis framework for comprehensive system evaluation.</p>
                <div class="artifact-meta">
                    <span>👤 @iamcapote</span>
                    <span>👁 6 views</span>
                </div>
                <div class="artifact-preview">
                    <span class="key">"type"</span>: <span class="string">"analysis_framework"</span>,<br>
                    <span class="key">"quadrants"</span>: [<span class="string">"Q1"</span>, <span class="string">"Q2"</span>, <span class="string">"Q3"</span>, <span class="string">"Q4"</span>],<br>
                    <span class="key">"dimensions"</span>: [...]
                </div>
                <div class="artifact-actions">
                    <a href="https://hub.bitwiki.org/t/quaternary-json/30145" class="artifact-btn primary" target="_blank">View Discussion</a>
                    <a href="https://hub.bitwiki.org/t/quaternary-json/30145.json" class="artifact-btn secondary" target="_blank">Raw JSON</a>
                </div>
            </div>

            <div class="artifact-card">
                <div class="artifact-type json">JSON</div>
                <h3>biological.json</h3>
                <p>Biological system patterns and organic process modeling for agent behaviors.</p>
                <div class="artifact-meta">
                    <span>👤 @iamcapote</span>
                    <span>👁 8 views</span>
                </div>
                <div class="artifact-preview">
                    <span class="key">"type"</span>: <span class="string">"pattern_library"</span>,<br>
                    <span class="key">"patterns"</span>: [<span class="string">"growth"</span>, <span class="string">"adaptation"</span>],<br>
                    <span class="key">"source"</span>: <span class="string">"biological_systems"</span>
                </div>
                <div class="artifact-actions">
                    <a href="https://hub.bitwiki.org/t/biological-json/30145" class="artifact-btn primary" target="_blank">View Discussion</a>
                    <a href="https://hub.bitwiki.org/t/biological-json/30145.json" class="artifact-btn secondary" target="_blank">Raw JSON</a>
                </div>
            </div>

            <div class="artifact-card">
                <div class="artifact-type json">JSON</div>
                <h3>decision_optimization.json</h3>
                <p>Optimization algorithms and heuristics for agent decision-making processes.</p>
                <div class="artifact-meta">
                    <span>👤 @iamcapote</span>
                    <span>👁 5 views</span>
                </div>
                <div class="artifact-preview">
                    <span class="key">"type"</span>: <span class="string">"optimization"</span>,<br>
                    <span class="key">"algorithms"</span>: [...],<br>
                    <span class="key">"metrics"</span>: [...]
                </div>
                <div class="artifact-actions">
                    <a href="https://hub.bitwiki.org/t/decision-optimization-json/30145" class="artifact-btn primary" target="_blank">View Discussion</a>
                    <a href="https://hub.bitwiki.org/t/decision-optimization-json/30145.json" class="artifact-btn secondary" target="_blank">Raw JSON</a>
                </div>
            </div>

            <div class="artifact-card">
                <div class="artifact-type json">JSON</div>
                <h3>rational_failures.json</h3>
                <p>Catalog of rational failure modes and edge cases in agent reasoning.</p>
                <div class="artifact-meta">
                    <span>👤 @iamcapote</span>
                    <span>👁 5 views</span>
                </div>
                <div class="artifact-preview">
                    <span class="key">"type"</span>: <span class="string">"failure_catalog"</span>,<br>
                    <span class="key">"failures"</span>: [...],<br>
                    <span class="key">"mitigations"</span>: [...]
                </div>
                <div class="artifact-actions">
                    <a href="https://hub.bitwiki.org/t/rational-failures-json/30145" class="artifact-btn primary" target="_blank">View Discussion</a>
                    <a href="https://hub.bitwiki.org/t/rational-failures-json/30145.json" class="artifact-btn secondary" target="_blank">Raw JSON</a>
                </div>
            </div>

            <div class="artifact-card">
                <div class="artifact-type json">JSON</div>
                <h3>heuristic_faults.json</h3>
                <p>Common heuristic faults and bias patterns in agent cognition.</p>
                <div class="artifact-meta">
                    <span>👤 @iamcapote</span>
                    <span>👁 7 views</span>
                </div>
                <div class="artifact-preview">
                    <span class="key">"type"</span>: <span class="string">"fault_catalog"</span>,<br>
                    <span class="key">"faults"</span>: [...],<br>
                    <span class="key">"detection"</span>: [...]
                </div>
                <div class="artifact-actions">
                    <a href="https://hub.bitwiki.org/t/heuristic-faults-json/30145" class="artifact-btn primary" target="_blank">View Discussion</a>
                    <a href="https://hub.bitwiki.org/t/heuristic-faults-json/30145.json" class="artifact-btn secondary" target="_blank">Raw JSON</a>
                </div>
            </div>

            <div class="artifact-card">
                <div class="artifact-type json">JSON</div>
                <h3>cognitive_biases.json</h3>
                <p>Comprehensive catalog of cognitive biases relevant to AI agent behavior.</p>
                <div class="artifact-meta">
                    <span>👤 @iamcapote</span>
                    <span>👁 8 views</span>
                </div>
                <div class="artifact-preview">
                    <span class="key">"type"</span>: <span class="string">"bias_catalog"</span>,<br>
                    <span class="key">"biases"</span>: [...],<br>
                    <span class="key">"categories"</span>: [...]
                </div>
                <div class="artifact-actions">
                    <a href="https://hub.bitwiki.org/t/cognitive-biases-json/30145" class="artifact-btn primary" target="_blank">View Discussion</a>
                    <a href="https://hub.bitwiki.org/t/cognitive-biases-json/30145.json" class="artifact-btn secondary" target="_blank">Raw JSON</a>
                </div>
            </div>

            <!-- Reference Lists -->
            <div class="artifact-card">
                <div class="artifact-type reference">Reference</div>
                <h3>Agents Reference List</h3>
                <p>Complete reference of AI agents in the BITcore Framework with GitHub links and main sites.</p>
                <div class="artifact-meta">
                    <span>👤 @iamcapote</span>
                    <span>👁 3 views</span>
                </div>
                <div class="artifact-actions">
                    <a href="https://hub.bitwiki.org/t/agents-reference-list-for-the-bitcore-framework/41067" class="artifact-btn primary" target="_blank">View Reference</a>
                    <a href="https://github.com/bitwikiorg" class="artifact-btn secondary" target="_blank">GitHub</a>
                </div>
            </div>

            <div class="artifact-card">
                <div class="artifact-type directory">Directory</div>
                <h3>LLMs and Personas</h3>
                <p>Official directory of AI personas available on BIThub with access levels and descriptions.</p>
                <div class="artifact-meta">
                    <span>👤 @iamcapote</span>
                    <span>👁 28 views</span>
                </div>
                <div class="artifact-preview">
                    🤖 0.Null - Bare-minimum assistant<br>
                    🧠 BITcore - Deep-thinking analyst<br>
                    💬 Coraline - Friendly everyday assistant<br>
                    🗺️ Coretographer - Semantic cartographer<br>
                    🔍 Archon - Heavy-duty research brain
                </div>
                <div class="artifact-actions">
                    <a href="https://hub.bitwiki.org/t/bithub-full-list-of-llms-and-personas/30145" class="artifact-btn primary" target="_blank">View Directory</a>
                    <a href="https://hub.bitwiki.org/t/map-llms-txt-shows-map-of-ecosystem/30145" class="artifact-btn secondary" target="_blank">Map</a>
                </div>
            </div>
        </div>
    </div>
    <?php wp_footer(); ?>
</body>
</html>