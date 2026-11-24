<?php
/**
 * Template Name: Artifact Editor
 * Description: Two-column editor for creating/editing artifacts
 * @package    Bitcore\Theme
 * @since      2026.11.23
 * @version    1.0.0
 * @author     coraline
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_title('|', true, 'right'); ?><?php bloginfo('name'); ?></title>
    <?php wp_head(); ?>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(180deg, #0a0a0f 0%, #1a1a2e 50%, #0f0f1a 100%);
            color: #e0e0e0;
            min-height: 100vh;
            line-height: 1.6;
        }
        .cosmic-bg {
            position: relative;
            min-height: 100vh;
        }
        .cosmic-bg::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="10" cy="10" r="0.5" fill="%23ffffff" opacity="0.3"/><circle cx="30" cy="25" r="0.3" fill="%23ffffff" opacity="0.2"/><circle cx="50" cy="15" r="0.4" fill="%23ffffff" opacity="0.25"/><circle cx="70" cy="35" r="0.3" fill="%23ffffff" opacity="0.2"/><circle cx="90" cy="20" r="0.5" fill="%23ffffff" opacity="0.3"/><circle cx="20" cy="50" r="0.4" fill="%23ffffff" opacity="0.2"/><circle cx="40" cy="70" r="0.3" fill="%23ffffff" opacity="0.25"/><circle cx="60" cy="60" r="0.5" fill="%23ffffff" opacity="0.3"/><circle cx="80" cy="80" r="0.3" fill="%23ffffff" opacity="0.2"/><circle cx="15" cy="85" r="0.4" fill="%23ffffff" opacity="0.25"/></svg>');
            background-size: 200px 200px;
            opacity: 0.5;
            pointer-events: none;
        }
        .editor-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 24px;
            position: relative;
            z-index: 1;
        }
        .editor-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .editor-header h1 {
            font-size: 1.5rem;
            font-weight: 500;
            color: #fff;
        }
        .header-actions {
            display: flex;
            gap: 12px;
        }
        .btn {
            padding: 10px 20px;
            font-size: 0.9rem;
            font-weight: 500;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-secondary {
            color: #e0e0e0;
            background: rgba(255, 255, 255, 0.1);
        }
        .btn-secondary:hover { background: rgba(255, 255, 255, 0.15); }
        .btn-primary {
            color: #000;
            background: linear-gradient(135deg, #4ade80, #22c55e);
        }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(74, 222, 128, 0.3); }
        .editor-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }
        .editor-panel {
            background: rgba(17, 17, 24, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 24px;
        }
        .panel-title {
            font-size: 0.85rem;
            font-weight: 600;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            font-size: 0.9rem;
            font-weight: 500;
            color: #e0e0e0;
            margin-bottom: 8px;
        }
        .form-input, .form-textarea, .form-select {
            width: 100%;
            padding: 12px 16px;
            font-size: 0.95rem;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            color: #fff;
            outline: none;
            transition: all 0.2s;
        }
        .form-input:focus, .form-textarea:focus, .form-select:focus {
            border-color: #4ade80;
        }
        .form-textarea {
            min-height: 120px;
            resize: vertical;
            font-family: 'Monaco', 'Menlo', monospace;
            font-size: 0.85rem;
            line-height: 1.6;
        }
        .form-select option { background: #111118; }
        .variable-hint {
            margin-top: 6px;
            font-size: 0.8rem;
            color: #666;
        }
        .variable-hint code {
            background: rgba(129, 140, 248, 0.2);
            color: #818cf8;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
        }
        /* Preview Panel */
        .preview-content {
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 20px;
            font-family: 'Monaco', 'Menlo', monospace;
            font-size: 0.85rem;
            line-height: 1.7;
            min-height: 300px;
            color: #e0e0e0;
            white-space: pre-wrap;
        }
        .preview-content .keyword { color: #f472b6; }
        .preview-content .variable { color: #818cf8; }
        .preview-content .string { color: #4ade80; }
        /* Quality Score Panel */
        .quality-panel {
            margin-top: 24px;
            padding: 20px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 12px;
        }
        .quality-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: #fff;
            margin-bottom: 16px;
        }
        .quality-metric {
            margin-bottom: 16px;
        }
        .quality-metric:last-child { margin-bottom: 0; }
        .metric-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 6px;
        }
        .metric-label { font-size: 0.85rem; color: #888; }
        .metric-value { font-size: 0.85rem; font-weight: 600; color: #4ade80; }
        .progress-bar {
            height: 6px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 3px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #4ade80, #22c55e);
            border-radius: 3px;
            transition: width 0.3s;
        }
        /* Test Section */
        .test-section {
            margin-top: 24px;
            padding: 20px;
            background: rgba(129, 140, 248, 0.1);
            border: 1px solid rgba(129, 140, 248, 0.2);
            border-radius: 12px;
        }
        .test-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: #818cf8;
            margin-bottom: 12px;
        }
        .test-input {
            width: 100%;
            padding: 12px;
            font-size: 0.9rem;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(129, 140, 248, 0.2);
            border-radius: 8px;
            color: #fff;
            margin-bottom: 12px;
        }
        .test-btn {
            padding: 10px 24px;
            font-size: 0.85rem;
            font-weight: 600;
            color: #fff;
            background: linear-gradient(135deg, #818cf8, #6366f1);
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }
        /* Accordion */
        .accordion {
            margin-top: 24px;
        }
        .accordion-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 16px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .accordion-header:hover { background: rgba(255, 255, 255, 0.08); }
        .accordion-title { font-size: 0.9rem; font-weight: 500; color: #e0e0e0; }
        .accordion-icon { color: #888; transition: transform 0.2s; }
        .accordion-content {
            padding: 16px;
            display: none;
        }
        .accordion-content.active { display: block; }
        .technique-item {
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        .technique-item:last-child { border-bottom: none; }
        .technique-name {
            font-size: 0.9rem;
            font-weight: 500;
            color: #fff;
        }
        .technique-desc {
            font-size: 0.8rem;
            color: #888;
        }
        @media (max-width: 1024px) {
            .editor-layout { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .editor-header { flex-direction: column; gap: 16px; }
            .editor-header h1 { font-size: 1.25rem; }
        }
    </style>
</head>
<body class="cosmic-bg">
    <?php include(get_template_directory() . "/nav-header.php"); ?>
    <div class="editor-container">
        <div class="editor-header">
            <h1>Artifact Editor</h1>
            <div class="header-actions">
                <button class="btn btn-secondary">Save Draft</button>
                <button class="btn btn-primary">Publish Artifact</button>
            </div>
        </div>

        <div class="editor-layout">
            <!-- Left Panel: Form -->
            <div class="editor-panel">
                <div class="panel-title">Artifact Configuration</div>

                <div class="form-group">
                    <label class="form-label">Artifact Type</label>
                    <select class="form-select">
                        <option value="prompt">Prompt</option>
                        <option value="skill">Skill</option>
                        <option value="code">Code Snippet</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Role</label>
                    <input type="text" class="form-input" placeholder="e.g., You are a research analyst...">
                </div>

                <div class="form-group">
                    <label class="form-label">Context</label>
                    <textarea class="form-textarea" placeholder="Provide background context and constraints..."></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Task</label>
                    <textarea class="form-textarea" placeholder="Describe the specific task to perform..."></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Constraints</label>
                    <textarea class="form-textarea" placeholder="List any limitations or requirements..."></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Variables</label>
                    <input type="text" class="form-input" placeholder="{{topic}}, {{format}}, {{length}}">
                    <p class="variable-hint">Use <code>{{variable}}</code> syntax for dynamic inputs</p>
                </div>
            </div>

            <!-- Right Panel: Preview -->
            <div class="editor-panel">
                <div class="panel-title">Live Preview</div>
                <div class="preview-content"><span class="keyword">ROLE:</span> You are a research analyst...

<span class="keyword">CONTEXT:</span>
Background information and constraints...

<span class="keyword">TASK:</span>
Perform the following analysis...

<span class="keyword">VARIABLES:</span>
- <span class="variable">{{topic}}</span>: The subject to analyze
- <span class="variable">{{format}}</span>: Output format
- <span class="variable">{{length}}</span>: Word count target

<span class="keyword">CONSTRAINTS:</span>
- Maximum 500 words
- Include citations
- Neutral tone</div>

                <!-- Quality Score Panel -->
                <div class="quality-panel">
                    <div class="quality-title">Quality Score</div>
                    <div class="quality-metric">
                        <div class="metric-header">
                            <span class="metric-label">Clarity</span>
                            <span class="metric-value">85%</span>
                        </div>
                        <div class="progress-bar"><div class="progress-fill" style="width: 85%;"></div></div>
                    </div>
                    <div class="quality-metric">
                        <div class="metric-header">
                            <span class="metric-label">Specificity</span>
                            <span class="metric-value">72%</span>
                        </div>
                        <div class="progress-bar"><div class="progress-fill" style="width: 72%;"></div></div>
                    </div>
                    <div class="quality-metric">
                        <div class="metric-header">
                            <span class="metric-label">Completeness</span>
                            <span class="metric-value">90%</span>
                        </div>
                        <div class="progress-bar"><div class="progress-fill" style="width: 90%;"></div></div>
                    </div>
                </div>

                <!-- Test Section -->
                <div class="test-section">
                    <div class="test-title">Test Input</div>
                    <input type="text" class="test-input" placeholder="Enter test values for variables...">
                    <button class="test-btn">Test with AI</button>
                </div>

                <!-- Advanced Techniques Accordion -->
                <div class="accordion">
                    <div class="accordion-header" onclick="this.nextElementSibling.classList.toggle('active')">
                        <span class="accordion-title">Advanced Techniques</span>
                        <span class="accordion-icon">▼</span>
                    </div>
                    <div class="accordion-content">
                        <div class="technique-item">
                            <div class="technique-name">Chain of Thought</div>
                            <div class="technique-desc">Add step-by-step reasoning instructions</div>
                        </div>
                        <div class="technique-item">
                            <div class="technique-name">Few-Shot Examples</div>
                            <div class="technique-desc">Include example inputs and outputs</div>
                        </div>
                        <div class="technique-item">
                            <div class="technique-name">Self-Verification</div>
                            <div class="technique-desc">Add output validation criteria</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php wp_footer(); ?>
</body>
</html>
