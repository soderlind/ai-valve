# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.3] - 2026-05-03

### Added

- `ai_valve_plugin_policy` filter — override the allow/deny policy for any plugin programmatically.
- `ai_valve_request_denied` action — fires when a request is blocked, with plugin slug, context, and reason code.
- `ai_valve_request_completed` action — fires after every successful request with full token and timing data.
- Developer hooks documentation (`docs/hooks.md`) with signatures, parameter tables, and examples.
- Documentation index (`docs/README.md`).

### Changed

- README rewritten with Developer Hooks section and links to new documentation.

## [1.0.2] - 2026-05-03

### Fixed

- Resolve multiple security vulnerabilities in transitive dependencies.

### Changed

- Update README: add inspiration note and remove duplicate credit section.

## [1.0.1] - 2026-04-15

### Fixed

- Removed stale beta6 dispatcher workaround note and doc from README.

### Changed

- Hook table and project structure descriptions updated to reflect the pending-row pattern.

### Added

- FAQ entry: how to deny all plugins by default and allow specific ones.

## [1.0.0] - 2026-04-15

### Fixed

- AI requests that fail (auth errors, timeouts, bad deployments) are now logged with `status = 'error'` instead of being silently lost.
- Schema migrations now run on every load (version-gated) via `LogRepository::maybe_upgrade()`, so in-place file updates without deactivate/activate no longer leave the DB in an outdated state.

### Changed

- `on_before_generate` now inserts a pending log row immediately, ensuring every request that enters the AI pipeline is visible — even if the SDK throws before the after-event fires.
- `on_after_generate` updates the pending row with token counts and `status = 'allowed'` instead of inserting a new row.
- Added shutdown handler to catch orphaned pending rows and mark them as errors.
- Added `LogRepository::update()` method for updating existing log rows by ID.
- Extracted `LogRepository::run_migrations()` from `activate()`; new `maybe_upgrade()` calls it when the stored schema version is behind.

## [0.6.0] - 2026-03-25

### Added

- Request duration tracking (`duration_ms` column, schema v3).
- Log retention setting — automatically delete logs older than N days via daily cron.
- Purge all logs REST endpoint (`DELETE /logs`) and Danger Zone UI on the Logs tab.
- Time-range preset selector (24h / 7d / 30d / This month) on the Logs tab.
- Combined Provider / Model column in log tables with duration display.
- Dropdown filters for Plugin, Provider, and Model on Logs tab, populated from logged data via `GET /logs/filters`.

### Changed

- Database schema upgraded to v3 with safe migration.
- Moved Danger Zone (purge) from Settings to Logs tab.

## [0.5.0] - 2026-03-25

### Removed

- Reflection-based event dispatcher injection workaround — fixed in WP 7 RC1 (core now passes `AiClient::getEventDispatcher()` in the `WP_AI_Client_Prompt_Builder` constructor).

### Changed

- Tested up to WP 7.0-RC1.
- Updated howto documentation to mark the core bug as resolved.

## [0.4.0] - 2026-03-24

### Added

- REST endpoint `GET /settings` for reading all plugin settings.
- `by_context`, `recent`, and `known_slugs` fields in the `GET /usage` response.
- `date_from` and `date_to` filter parameters on the `GET /logs` endpoint.
- Dedicated CSS file for admin styles.

### Changed

- Admin UI rebuilt as a React single-page application using `@wordpress/scripts`.
- Settings, dashboard, and logs now render client-side via the REST API.
- AdminPage.php reduced from 850+ lines to a thin shell (~160 lines).
- Build pipeline produces `build/index.js`, `build/index.css`, and `build/index.asset.php` with WP dependency extraction.

## [0.3.0] - 2026-03-24

### Added

- Model filter on the Logs tab and CSV export.
- Provider & Model breakdown table on the Dashboard (replaces provider-only table).
- Per-plugin token bar chart on the Dashboard plugins table.
- Per-provider daily/monthly token counters in UsageTracker.
- `model_id` filter parameter on the REST `/logs` endpoint.
- `by_provider_model` data in the REST `/usage` response.
- How-blocking-works documentation (`docs/how-blocking-works.md`) linked from READMEs.

### Changed

- Providers & Contexts tables displayed side by side on the Dashboard.
- Plugin list on Dashboard only shows plugins that have used the AI connector.

## [0.2.0] - 2026-03-24

### Fixed

- Status column widened from `VARCHAR(16)` to `VARCHAR(64)` — denial reasons were silently truncated.
- "Denied" log filter now matches all denial variants (`denied:*`) instead of exact `denied` only.
- Respect the `wp_supports_ai` filter as a global kill switch.

### Added

- Date range filter (From / To) on the Logs tab.
- Context breakdown table on the Dashboard tab.
- Per-plugin budget threshold alerts (admin notices).
- CSV export button on the Logs tab (honours current filters).

### Changed

- Logs filter bar uses flex layout — all controls on one line when space allows.
- Plugin and Provider filter inputs narrowed for better fit.

## [0.1.0] - 2026-03-24

### Added

- Per-plugin access control (allow/deny).
- Global and per-plugin daily/monthly token budgets.
- Context restrictions (admin, frontend, cron, REST, AJAX, CLI).
- Usage dashboard with summary cards, progress bars, and per-plugin controls.
- Request logging with provider, model, capability, and token counts.
- Budget alert notices and optional email notifications.
- REST API endpoints for usage and log data.
- Workaround for WordPress 7.0 event dispatcher bug.
- GitHub release updater for automatic updates.

[1.0.3]: https://github.com/soderlind/ai-valve/releases/tag/1.0.3
[1.0.2]: https://github.com/soderlind/ai-valve/releases/tag/1.0.2
[1.0.1]: https://github.com/soderlind/ai-valve/releases/tag/1.0.1
[1.0.0]: https://github.com/soderlind/ai-valve/releases/tag/1.0.0
[0.6.0]: https://github.com/soderlind/ai-valve/releases/tag/0.6.0
[0.5.0]: https://github.com/soderlind/ai-valve/releases/tag/0.5.0
[0.4.0]: https://github.com/soderlind/ai-valve/releases/tag/0.4.0
[0.3.0]: https://github.com/soderlind/ai-valve/releases/tag/0.3.0
[0.2.0]: https://github.com/soderlind/ai-valve/releases/tag/0.2.0
[0.1.0]: https://github.com/soderlind/ai-valve/releases/tag/0.1.0
