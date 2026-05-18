# BITCORE Core Changelog

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
