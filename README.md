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

Measured 2026-11-23 on the committed tree inside `holobiont-wordpress-wordpress-1` (PHP 8.3.36).

| Gate | Result |
|---|---|
| `php -l`, whole tree | **440/440 clean**, 0 parse errors |
| PHPStan level 5 | **passes, exit 0** — 232 errors baselined in `phpstan-baseline.neon` |
| Files over 500 LOC | **0** |
| Metadata headers | **0** missing `@package` / `@version` / `@since` across 315 files |
| Behavioural tests | **70 tests / 897 assertions, 0 fail, 0 error, 0 skip** |

### Reproducing the PHPStan gate

The analysis toolchain (WordPress stubs, 14 project stubs, bootstrap, config) lives in `../toolchain/` beside this repo and is committed for reproducibility.

```bash
# Repository root, with the toolchain present as a sibling directory
php phpstan.phar analyse -c phpstan.neon --no-progress
```

The committed baseline records pre-existing debt so the gate stays enforceable: new code cannot add errors. Regenerate only through `toolchain/phpstan-gen.neon`, which omits the baseline include (PHPStan will not start when an included baseline file is absent).

### Test suites

- PHPUnit 10.5.67 — `phpunit.xml.dist`, 24 tests / 48 assertions.
- PHPUnit 9.6.39 — `config/phpunit.xml.dist`, 17 tests / 21 assertions (WordPress test library requires ≤9).
- 29 standalone contract scripts — `tests/run-standalone.sh`, 828 assertions.

The two-engine split is deliberate: the WordPress test library calls `PHPUnit\Util\Test::parseTestMethodAnnotations()`, removed in PHPUnit 10.

## Last Updated

2026-11-23
