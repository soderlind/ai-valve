# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

[0.2.0]: https://github.com/soderlind/ai-valve/releases/tag/0.2.0
[0.1.0]: https://github.com/soderlind/ai-valve/releases/tag/0.1.0
