
        /* Agent Grid */
        .agent-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: var(--space-lg);
            margin-bottom: var(--space-lg);
        }

        /* Agent Card */
        .agent-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-default);
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: border-color 0.2s, box-shadow 0.2s;
            position: relative;
        }
        .agent-card:hover {
            border-color: var(--accent-primary);
            box-shadow: 0 0 20px rgba(0, 212, 170, 0.08);
        }
        .agent-card.active {
            border-color: var(--accent-primary);
            box-shadow: 0 0 24px rgba(0, 212, 170, 0.12);
        }
        .agent-card.active::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--accent-primary), var(--accent-secondary));
        }

        .card-emoji {
            font-size: 2.5rem;
            padding: var(--space-lg) var(--space-lg) 0;
            line-height: 1;
        }
        .card-body {
            padding: var(--space-md) var(--space-lg) var(--space-lg);
        }
        .card-name {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .card-style {
            font-size: 0.8rem;
            color: var(--accent-secondary);
            font-weight: 500;
            margin-bottom: var(--space-sm);
        }
        .card-desc {
            font-size: 0.85rem;
            color: var(--text-secondary);
            line-height: 1.5;
            margin-bottom: var(--space-md);
        }
        .card-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: var(--space-md);
        }
        .card-badge.active {
            background: rgba(0, 212, 170, 0.15);
            color: var(--accent-primary);
        }
        .card-badge.inactive {
            background: var(--bg-elevated);
            color: var(--text-muted);
        }
        .card-badge .pulse {
            width: 6px; height: 6px;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        .card-badge.active .pulse { background: var(--accent-primary); }
        .card-badge.inactive .pulse { background: var(--text-muted); animation: none; }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        .card-actions {
            display: flex;
            gap: var(--space-sm);
        }
        .btn {
            padding: var(--space-sm) var(--space-md);
            border-radius: 6px;
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
        .btn-secondary {
            background: var(--accent-secondary);
            border-color: var(--accent-secondary);
            color: #fff;
        }
        .btn-secondary:hover { opacity: 0.9; }
        .btn-sm { padding: 4px 10px; font-size: 0.75rem; }

        /* Loading state */
        .loading-overlay {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 200px;
            color: var(--text-muted);
        }
        .spinner {
            width: 24px; height: 24px;
            border: 3px solid var(--border-default);
            border-top-color: var(--accent-primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin-right: var(--space-sm);
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Error state */
        .error-state {
            text-align: center;
            padding: var(--space-xl);
            color: var(--accent-danger);
        }
        .error-state p { color: var(--text-secondary); margin-top: var(--space-sm); }

        /* Toast notification */
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

        /* Mobile responsive */
