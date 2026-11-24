<?php
/**
 * Accelerate page styles partial.
 *
 * @package    Bitcore\Theme
 * @since      2026.11.23
 * @version    1.0.0
 * @author     coraline
 */
?>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0a0a0f;
            color: #e0e0e0;
            line-height: 1.6;
        }
        .accelerate-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        /* Header */
        .accelerate-header {
            text-align: center;
            margin-bottom: 60px;
            padding: 60px 40px;
            background: linear-gradient(135deg, #111118 0%, #1a1a2e 100%);
            border-radius: 16px;
            border: 1px solid #2a2a3e;
            position: relative;
            overflow: hidden;
        }
        .accelerate-header::before {
            content: '⚡';
            position: absolute;
            top: 20px;
            right: 40px;
            font-size: 4rem;
            opacity: 0.1;
        }
        .accelerate-header .badge {
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
        .accelerate-header h1 {
            font-size: 2.5rem;
            font-weight: 300;
            color: #fff;
            margin-bottom: 16px;
        }
        .accelerate-header p {
            font-size: 1.1rem;
            color: #888;
            max-width: 600px;
            margin: 0 auto 30px;
        }
        .accelerate-tagline {
            display: flex;
            justify-content: center;
            gap: 40px;
            flex-wrap: wrap;
            margin-top: 30px;
        }
        .tagline-item {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #666;
            font-size: 0.9rem;
        }
        .tagline-item span {
            color: #4ade80;
            font-size: 1.2rem;
        }

        /* Factory Grid */
        .factory-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }

        /* Input Panel */
        .input-panel {
            background: #111118;
            border: 1px solid #1a1a2e;
            border-radius: 12px;
            padding: 28px;
        }
        .input-panel h2 {
            font-size: 1.1rem;
            font-weight: 500;
            color: #fff;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .input-panel h2::before {
            content: '📥';
        }
        .input-area {
            width: 100%;
            min-height: 200px;
            padding: 16px;
            background: #0a0a0f;
            border: 1px solid #2a2a3e;
            border-radius: 8px;
            color: #e0e0e0;
            font-family: 'SF Mono', Monaco, monospace;
            font-size: 0.85rem;
            resize: vertical;
            margin-bottom: 16px;
        }
        .input-area::placeholder {
            color: #444;
        }
        .input-actions {
            display: flex;
            gap: 12px;
        }
        .input-btn {
            flex: 1;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        .input-btn.primary {
            background: linear-gradient(135deg, #4ade80 0%, #22c55e 100%);
            color: #000;
            border: none;
        }
        .input-btn.primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(74, 222, 128, 0.3);
        }
        .input-btn.secondary {
            background: #1a1a2e;
            color: #888;
            border: 1px solid #2a2a3e;
        }
        .input-btn.secondary:hover {
            border-color: #4ade80;
            color: #4ade80;
        }

        /* Accelerator Panel */
        .accelerator-panel {
            background: #111118;
            border: 1px solid #1a1a2e;
            border-radius: 12px;
            padding: 28px;
        }
        .accelerator-panel h2 {
            font-size: 1.1rem;
            font-weight: 500;
            color: #fff;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .accelerator-panel h2::before {
            content: '⚡';
        }
        .accelerator-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }
        .accelerator-btn {
            padding: 16px;
            background: #0a0a0f;
            border: 1px solid #2a2a3e;
            border-radius: 8px;
            color: #888;
            cursor: pointer;
            transition: all 0.2s;
            text-align: left;
        }
        .accelerator-btn:hover {
            border-color: #4ade80;
            color: #fff;
            background: #1a1a2e;
        }
        .accelerator-btn.active {
            border-color: #4ade80;
            background: rgba(74, 222, 128, 0.1);
            color: #4ade80;
        }
        .accelerator-btn .btn-name {
            font-weight: 500;
            font-size: 0.9rem;
            margin-bottom: 4px;
        }
        .accelerator-btn .btn-desc {
            font-size: 0.75rem;
            opacity: 0.7;
        }
        .artifact-loaded {
            padding: 12px 16px;
            background: #0a0a0f;
            border: 1px solid #2a2a3e;
            border-radius: 8px;
            font-size: 0.85rem;
            color: #666;
        }
        .artifact-loaded .loaded-name {
            color: #4ade80;
            font-weight: 500;
        }

        /* Output Panel */
        .output-panel {
            grid-column: 1 / -1;
            background: #111118;
            border: 1px solid #1a1a2e;
            border-radius: 12px;
            padding: 28px;
        }
        .output-panel h2 {
            font-size: 1.1rem;
            font-weight: 500;
            color: #fff;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .output-panel h2::before {
            content: '📤';
        }
        .output-area {
            width: 100%;
            min-height: 300px;
            padding: 20px;
            background: #0a0a0f;
            border: 1px solid #2a2a3e;
            border-radius: 8px;
            color: #e0e0e0;
            font-family: 'SF Mono', Monaco, monospace;
            font-size: 0.85rem;
            line-height: 1.6;
        }
        .output-placeholder {
            color: #444;
            text-align: center;
            padding: 100px 0;
        }
        .output-placeholder .placeholder-icon {
            font-size: 3rem;
            margin-bottom: 16px;
            opacity: 0.3;
        }

        /* Status Bar */
        .status-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 24px;
            background: #0d0d14;
            border: 1px solid #1a1a2e;
            border-radius: 8px;
            margin-top: 20px;
        }
        .status-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            color: #666;
        }
        .status-item.active {
            color: #4ade80;
        }
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #333;
        }
        .status-item.active .status-dot {
            background: #4ade80;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-top: 40px;
        }
        .quick-action {
            padding: 24px;
            background: #111118;
            border: 1px solid #1a1a2e;
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .quick-action:hover {
            border-color: #4ade80;
            transform: translateY(-2px);
        }
        .quick-action .action-icon {
            font-size: 2rem;
            margin-bottom: 12px;
        }
        .quick-action .action-name {
            font-weight: 500;
            color: #fff;
            margin-bottom: 4px;
        }
        .quick-action .action-desc {
            font-size: 0.8rem;
            color: #666;
        }

        @media (max-width: 900px) {
            .factory-grid {
                grid-template-columns: 1fr;
            }
            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
        }
