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

Historical verification from 2026-11-23 used isolated PHP runs; see the later reverification note in `merge-plan-unified.md`. These checks do not prove the plugin works on a live WordPress request.

| Gate | Result |
|---|---|
| `php -l`, `wp-content` | **440/440 clean** on PHP 8.3.36 |
| PHPStan level 5 | **Exit 0 with a baseline**: 183 entries suppress 228 existing diagnostics |
| Active files over 500 LOC | **0**; archived files excluded |
| Metadata headers | **0 missing** `@package` / `@version` / `@since` across 380 production PHP files in both plugins and themes |
| Isolated test suites | **41 PHPUnit tests / 69 assertions**, plus **29 standalone plugin scripts** and **10 theme contract scripts**; all exit 0. No deployed-plugin request was tested. |

### Reproducing the PHPStan gate

The analysis toolchain (WordPress stubs, project stubs, bootstrap, config) is a **local sibling** at `../toolchain/`; it is **not tracked in this repository**. PHPStan 2.3.7 and the local toolchain are required to reproduce this run. On the Pi's PHP container, with this repository and the sibling toolchain copied to a disposable directory:

```bash
php phpstan.phar analyse -c phpstan.neon --debug --no-progress
```

The baseline records unresolved diagnostics; a passing result is **baseline-backed**, not zero source defects. The baseline-generation config is local at `../toolchain/phpstan-gen.neon`, not available from this repository alone.

### Test suites

- PHPUnit 10.5.67 — `phpunit.xml.dist`, 24 tests / 48 assertions.
- PHPUnit 9.6.39 — `config/phpunit.xml.dist`, 17 tests / 21 assertions (WordPress test library requires ≤9).
- 29 standalone contract scripts — `tests/run-standalone.sh`, 828 assertions.

The two-engine split is deliberate: the WordPress test library calls `PHPUnit\Util\Test::parseTestMethodAnnotations()`, removed in PHPUnit 10.

## Last Updated

2026-11-23
