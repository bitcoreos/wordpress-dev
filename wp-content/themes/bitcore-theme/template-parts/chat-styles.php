<?php
/**
 * Agent Chat template part: agent chat style block.
 *
 * Extracted byte-for-byte from page-chat.php (RULING 1 split).
 *
 * @package bitcore-librarian-theme
 * @subpackage template-parts
 * @version 1.0.0
 * @since 1.2.0
 */
?>    <style>
        :root {
            --bg-primary: #0a0a0f;
            --bg-secondary: #12121a;
            --bg-tertiary: #1a1a25;
            --bg-elevated: #22222e;
            --text-primary: #f0f0f5;
            --text-secondary: #a0a0b0;
            --text-muted: #6a6a7a;
            --accent-primary: #00d4aa;
            --accent-secondary: #6366f1;
            --accent-warning: #f59e0b;
            --accent-danger: #ef4444;
            --accent-success: #22c55e;
            --border-default: #2a2a3a;
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 16px;
            --space-xs: 4px;
            --space-sm: 8px;
            --space-md: 16px;
            --space-lg: 24px;
            --space-xl: 32px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* Chat Layout */
        .chat-wrapper {
            display: flex;
            flex-direction: column;
            height: 100vh;
            max-width: 900px;
            margin: 0 auto;
            width: 100%;
        }

        /* Header */
        .chat-header {
            display: flex;
            align-items: center;
            gap: var(--space-md);
            padding: var(--space-md) var(--space-lg);
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-default);
            flex-shrink: 0;
        }
        .chat-back {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 4px;
            transition: color 0.2s;
        }
        .chat-back:hover { color: var(--accent-primary); }
        .chat-agent-info {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            flex: 1;
        }
        .chat-agent-emoji {
            font-size: 1.5rem;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-tertiary);
            border-radius: var(--radius-md);
        }
        .chat-agent-name {
            font-weight: 600;
            font-size: 1.1rem;
        }
        .chat-agent-style {
            font-size: 0.75rem;
            color: var(--accent-secondary);
        }
        .chat-agent-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.7rem;
            color: var(--text-muted);
        }
        .chat-agent-status .dot {
            width: 7px; height: 7px;
            border-radius: 50%;
            background: var(--accent-success);
        }
        .chat-agent-status .dot.inactive { background: var(--text-muted); }
        .chat-header-actions {
            display: flex;
            gap: var(--space-sm);
            align-items: center;
        }

        /* Rate Limit Bar */
        .rate-bar {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            padding: var(--space-xs) var(--space-md);
            background: var(--bg-tertiary);
            border-bottom: 1px solid var(--border-default);
            font-size: 0.75rem;
            color: var(--text-muted);
            flex-shrink: 0;
        }
        .rate-bar .rate-track {
            flex: 1;
            height: 4px;
            background: var(--bg-elevated);
            border-radius: 2px;
            overflow: hidden;
        }
        .rate-bar .rate-fill {
            height: 100%;
            background: var(--accent-primary);
            border-radius: 2px;
            transition: width 0.3s;
        }
        .rate-bar .rate-fill.warning { background: var(--accent-warning); }
        .rate-bar .rate-fill.danger { background: var(--accent-danger); }

        /* Messages Area */
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: var(--space-lg);
            display: flex;
            flex-direction: column;
            gap: var(--space-md);
        }

        .chat-messages::-webkit-scrollbar {
            width: 6px;
        }
        .chat-messages::-webkit-scrollbar-track {
            background: var(--bg-primary);
        }
        .chat-messages::-webkit-scrollbar-thumb {
            background: var(--border-default);
            border-radius: 3px;
        }

        /* Empty State */
        .chat-empty {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            text-align: center;
            padding: var(--space-xl);
        }
        .chat-empty .empty-emoji {
            font-size: 3rem;
            margin-bottom: var(--space-md);
        }
        .chat-empty h3 {
            font-size: 1.1rem;
            color: var(--text-secondary);
            margin-bottom: var(--space-xs);
        }
        .chat-empty p {
            font-size: 0.85rem;
            max-width: 400px;
        }

        /* Message Bubbles */
        .message {
            display: flex;
            flex-direction: column;
            max-width: 75%;
            animation: fadeIn 0.2s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .message.user {
            align-self: flex-end;
        }
        .message.agent {
            align-self: flex-start;
        }
        .message-bubble {
            padding: var(--space-sm) var(--space-md);
            border-radius: var(--radius-md);
            line-height: 1.5;
            font-size: 0.9rem;
            word-wrap: break-word;
        }
        .message.user .message-bubble {
            background: var(--accent-secondary);
            color: #fff;
            border-bottom-right-radius: var(--radius-xs, 4px);
        }
        .message.agent .message-bubble {
            background: var(--bg-elevated);
            color: var(--text-primary);
            border-bottom-left-radius: var(--radius-xs, 4px);
        }
        .message-meta {
            font-size: 0.7rem;
            color: var(--text-muted);
            margin-top: var(--space-xs);
            padding: 0 var(--space-xs);
        }
        .message.user .message-meta {
            text-align: right;
        }

        /* Thinking Indicator */
        .thinking-indicator {
            display: none;
            align-self: flex-start;
            flex-direction: column;
            max-width: 75%;
        }
        .thinking-indicator.visible {
            display: flex;
        }
        .thinking-dots {
            display: flex;
            gap: 4px;
            padding: var(--space-sm) var(--space-md);
            background: var(--bg-elevated);
            border-radius: var(--radius-md);
            border-bottom-left-radius: 4px;
        }
        .thinking-dots span {
            width: 8px; height: 8px;
            background: var(--text-muted);
            border-radius: 50%;
            animation: bounce 1.4s infinite;
        }
        .thinking-dots span:nth-child(2) { animation-delay: 0.2s; }
        .thinking-dots span:nth-child(3) { animation-delay: 0.4s; }
        @keyframes bounce {
            0%, 80%, 100% { transform: translateY(0); opacity: 0.4; }
            40% { transform: translateY(-6px); opacity: 1; }
        }
        .thinking-label {
            font-size: 0.7rem;
            color: var(--text-muted);
            margin-top: var(--space-xs);
            padding: 0 var(--space-xs);
        }

        /* Input Area */
        .chat-input-area {
            padding: var(--space-md) var(--space-lg);
            background: var(--bg-secondary);
            border-top: 1px solid var(--border-default);
            flex-shrink: 0;
        }
        .session-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-sm);
            font-size: 0.75rem;
            color: var(--text-muted);
        }
        .session-controls select {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: 1px solid var(--border-default);
            border-radius: var(--radius-sm);
            padding: 4px var(--space-sm);
            font-size: 0.75rem;
            font-family: inherit;
        }
        .chat-input-row {
            display: flex;
            gap: var(--space-sm);
        }
        .chat-input {
            flex: 1;
            padding: var(--space-sm) var(--space-md);
            background: var(--bg-tertiary);
            border: 1px solid var(--border-default);
            border-radius: var(--radius-md);
            color: var(--text-primary);
            font-size: 0.9rem;
            font-family: inherit;
            resize: none;
            min-height: 44px;
            max-height: 120px;
            outline: none;
            transition: border-color 0.2s;
        }
        .chat-input:focus {
            border-color: var(--accent-primary);
        }
        .chat-input::placeholder {
            color: var(--text-muted);
        }
        .btn {
            padding: var(--space-sm) var(--space-md);
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-default);
            background: var(--bg-tertiary);
            color: var(--text-primary);
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.85rem;
            font-family: inherit;
        }
        .btn:hover { background: var(--bg-elevated); border-color: var(--accent-primary); }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-primary {
            background: var(--accent-primary);
            border-color: var(--accent-primary);
            color: #000;
            font-weight: 500;
        }
        .btn-primary:hover { opacity: 0.9; }
        .btn-primary:disabled { opacity: 0.5; }
        .btn-sm { padding: 4px 10px; font-size: 0.75rem; }
        .btn-danger {
            color: var(--accent-danger);
            border-color: var(--accent-danger);
        }
        .btn-danger:hover { background: rgba(239, 68, 68, 0.1); }

        .starter-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 18px;
            width: min(760px, 100%);
        }
        .starter-btn {
            border: 1px solid var(--border-default);
            border-radius: 10px;
            background: var(--bg-tertiary);
            color: var(--text-primary);
            padding: 12px;
            text-align: left;
            cursor: pointer;
            font: inherit;
        }
        .starter-btn:hover { border-color: var(--accent-primary); }
        .starter-btn span { display: block; color: var(--text-muted); font-size: 0.78rem; margin-top: 4px; }

        /* Toast */
        .toast {
            position: fixed;
            top: var(--space-lg);
            right: var(--space-lg);
            padding: var(--space-md) var(--space-lg);
            background: var(--bg-elevated);
            border: 1px solid var(--border-default);
            border-radius: var(--radius-md);
            color: var(--text-primary);
            font-size: 0.875rem;
            z-index: 1000;
            opacity: 0;
            transform: translateY(-10px);
            transition: all 0.3s;
            pointer-events: none;
        }
        .toast.show {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }
        .toast.success { border-left: 4px solid var(--accent-success); }
        .toast.error { border-left: 4px solid var(--accent-danger); }
        .toast.warning { border-left: 4px solid var(--accent-warning); }

        /* Error message */
        .error-message {
            text-align: center;
            padding: var(--space-md);
            color: var(--accent-danger);
            font-size: 0.85rem;
        }

        /* Mobile */
        @media (max-width: 900px) {
            .chat-wrapper {
                max-width: 100%;
            }
            .chat-header {
                padding: var(--space-sm) var(--space-md);
            }
            .chat-messages {
                padding: var(--space-md);
            }
            .starter-grid { grid-template-columns: 1fr; }
            .chat-input-area {
                padding: var(--space-sm) var(--space-md);
            }
            .message {
                max-width: 85%;
            }
            .chat-agent-style {
                display: none;
            }
        }
    </style>