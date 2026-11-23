# BITCORE WordPress Development Environment

Private repository containing WordPress pages, plugins, and themes for the BITCORE ecosystem.

Repository: https://github.com/bitcoreos/wordpress-dev

## Structure

```
wp-content/
├── plugins/
│   ├── bitcore-core/          # 311 PHP files
│   └── bitcore-librarian/
└── themes/
    ├── bitcore-theme/
    └── bitcore-librarian-theme/
```

## Plugins

- **bitcore-core** — core BITCORE functionality: REST API, MCP server, Eliza bridge, connectors, infrastructure (queue, cache, webhooks, multitenancy, swarm), WP-CLI commands.
- **bitcore-librarian** — knowledge management, artifacts, live feed.

## Themes

- **bitcore-theme** — agent chat, agent roster, work surfaces.
- **bitcore-librarian-theme** — knowledge surfaces with BitFlux avatar integration.

## Branch model

Single `main` branch. The March 2026 dual-branch merge (coraline + hyperion) was consolidated into `main` on 2026-11-22; merge tips `e2a1eac`, `0719591`, `20c8ac4`, `c1d8796`. Closure record: `merge-plan-unified.md`, section **FINALIZATION RECORD (2026-11-22)**.

## Verification status

- `php -l`, whole tree: **422/422 clean**, PHP 8.3.36 (2026-11-22).
- PHPStan level 5: not run — no toolchain available.
- Behavioural tests: not run — no WP test bootstrap available.
- 26 files exceed 500 LOC; `infrastructure/cli/commands.php` carries a documented exception.

## Last Updated

2026-11-22
