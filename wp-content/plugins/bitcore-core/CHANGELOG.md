# BITCORE Core Changelog

## [1.3.0] - 2026-05-28

### Added
- Standalone regression harness for ASHI local validation.
- Contract tests covering ElizaOS bridge payload/auth, credits, x402, SSO permission capture, MCP wiring, task unification, knowledge graph, SSE scalability, org-chart simplification, provisioning hooks, and theme route contracts.
- Unified Credits API and manager for subscription quotas, x402 overages, and agent limits.
- Discourse permission capture snapshot scaffolding for ASHI authorization without rebuilding SSO.

### Changed
- Hardened ElizaOS bridge configuration so auth token defaults are empty and API-key headers are only sent when configured.
- Consolidated task-family APIs and UI behavior around canonical `/bitcore/v1/tasks`.
- Consolidated theme navigation and page contracts around the six-route ASHI surface.
- Replaced legacy/stub connector behavior with validated connector response handling.

### Fixed
- Removed committed ElizaOS token literal from active code, stale fix scripts, and documentation.
- Rejected non-2xx connector responses before decoding successful payloads.
- Prevented task update side effects when `wp_update_post` fails.
- Completed retention, rate-limit, SSE, MCP, and route-registration regressions with local green validation.

### Validation
- `tools/ashi-local-regression.sh`: 307 PHP files syntax-checked, 39 executable tests passed, 0 failed.
- Integration-only PHP unit tests remain inventoried separately pending WordPress test bootstrap.


## [1.2.1] - 2026-05-18

### Fixed
- Admin panel invisible: added missing `require_once` for admin/menu-registration.php (dead code since 2026-03-29)
- Autoloader mismatch: BITCORE_Rest_* classes failed to load from class-* prefixed files
- REST API registration: used fake namespace class names instead of actual BITCORE\Rest\* namespaces
- Webhooks fatal: RuntimeException every 15 minutes, guarded with try/catch
- tasks.php fatal: Call to undefined method prepare_response_for_collection(), replaced with ->get_data()
- Database schema: Added missing `requested_at` column to wp_bitcore_approvals

### Changed
- Version tracking: Established semantic versioning discipline
- Branch policy: merge branch is ground truth for ASHI deployment

## [1.2.0] - 2026-03-02

### Added
- Unified BITCORE plugin from hyperion + coraline branches
- Paperclip integration Phase 1-3
- PSR-4 autoloader for integration modules
- Admin menu registration (but never loaded — bug)
- REST API routes for tasks, health, research, SSE events

## [1.0.0] - 2026-02-20

### Added
- Initial BITCORE Core plugin
- WordPress themes and plugins
