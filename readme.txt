=== AI Valve ===
Contributors: PerS
Tags: ai, governance, tokens, cost-control, connector
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.3
Stable tag: 1.1.3
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Control, meter, and permission-gate AI usage from plugins that connect through the WordPress 7 AI connector.

== Description ==

AI Valve gives site administrators visibility and control over how plugins use the built-in WordPress AI connector.

= Features =

* **Per-plugin access control** — Allow or deny individual plugins from making AI requests.
* **Token budgets** — Set daily and monthly token limits per plugin and globally.
* **Context restrictions** — Control which execution contexts (admin, frontend, cron, REST, AJAX, CLI) may trigger AI calls.
* **Usage dashboard** — See token consumption at a glance with summary cards, progress bars, and per-plugin breakdowns.
* **Request logging** — Every AI request is logged with provider, model, capability, tokens, and caller attribution.
* **Budget alerts** — Admin notices and optional email when usage approaches or exceeds limits.

= Requirements =

* WordPress 7.0 or later
* PHP 8.3 or later
* A configured AI provider in Settings → Connectors

== Installation ==

1. Install AI Valve from the WordPress plugin directory, or upload `ai-valve.zip` via Plugins → Add New → Upload Plugin.
2. Activate the plugin.
3. Go to Settings → AI Valve to configure.

== Frequently Asked Questions ==

= What are tokens? =

Tokens are the units AI models use to measure input and output. Roughly 1 token ≈ ¾ of a word. Both the text you send (prompt tokens) and the text the AI returns (completion tokens) count toward your usage.

= What does "limit = 0" mean? =

A limit of 0 means unlimited — no cap is enforced. Set a positive number to restrict token usage.

= How does AI Valve identify which plugin made an AI request? =

It walks the PHP call stack (`debug_backtrace()`) and matches file paths against the plugins directory to determine the originating plugin slug.

= Will this work with future WordPress updates? =

Yes. AI Valve relies only on the stable public hooks (`wp_ai_client_prevent_prompt`, `wp_ai_client_before_generate_result`, `wp_ai_client_after_generate_result`) provided by the WordPress AI connector API.

= Are there developer hooks? =

Yes. See the [developer hooks documentation](https://github.com/soderlind/ai-valve/blob/main/docs/hooks.md) for available filters and actions.

= Does AI Valve work on multisite? =

Yes. Each subsite has its own log table, settings, and budgets.

= What happens when a plugin is blocked? =

The plugin receives a `WP_Error` with code `prompt_prevented` instead of an AI response. The denied request is logged with the reason. See [how-blocking-works.md](https://github.com/soderlind/ai-valve/blob/main/docs/how-blocking-works.md) for the full explanation.

= How do I block all plugins and only allow specific ones? =

1. Go to Settings → AI Valve → Settings.
2. Set the Default policy to Deny.
3. Switch to the Dashboard tab.
4. In the Per-plugin access table, set the plugins you want to allow to Allow.

Only explicitly allowed plugins will be able to make AI requests; everything else is denied by default.

==  Screenshots ==

1. Dashboard with usage summary cards and per-plugin breakdown.

== Changelog ==

= 1.1.3 =
* Changed: Updated WordPress.org icons and banner.
* Fixed: Improved compatibility with custom plugin directory layouts and unique plugin-owned identifiers.
* Fixed: Resolved npm dependency security advisories.

= 1.1.2 =
* Changed: Refactored code structure for improved readability and maintainability.

= 1.1.1 =
* Fixed: Dashboard usage date buckets now keep Today, plugin totals, and Recent Requests aligned around database/PHP day boundaries.
* Fixed: WordPress 6.8 SelectControl deprecation warnings in the admin UI.
* Added: WordPress.org icon and banner assets.

= 1.1.0 =
* Removed: GitHub release updater for WordPress.org distribution.
* Removed: plugin-update-checker dependency and bundled updater files.
* Fixed: Added direct-access guards and Plugin Check cleanup for production files.

= 1.0.4 =
* Changed: Update npm dependencies to latest versions.

= 1.0.3 =
* Added: `aivalve_plugin_policy` filter to override allow/deny policy programmatically.
* Added: `aivalve_request_denied` action, fired when a request is blocked.
* Added: `aivalve_request_completed` action, fired after every successful request.

= 1.0.2 =
* Fixed: Resolve multiple security vulnerabilities in transitive dependencies.

= 1.0.1 =
* Added: FAQ entry on deny-by-default allowlist setup.

= 1.0.0 =
* Fixed: AI requests that fail (auth errors, timeouts, bad deployments) now logged with status = 'error'.
* Fixed: Schema migrations run on every load (version-gated) so in-place updates work without deactivate/activate.
* Changed: on_before_generate inserts a pending log row immediately; on_after_generate updates it.
* Changed: Shutdown handler catches orphaned pending rows and marks them as errors.
* Added: LogRepository::update() for updating existing log rows by ID.

= 0.6.0 =
* Added: Request duration tracking (duration_ms column, schema v3).
* Added: Log retention setting — auto-delete logs older than N days via daily cron.
* Added: Purge all logs REST endpoint (DELETE /logs) and Danger Zone UI on Logs tab.
* Added: Time-range preset selector (24h / 7d / 30d / This month) on Logs tab.
* Added: Combined Provider / Model column in log tables with duration display.
* Added: Dropdown filters for Plugin, Provider, and Model on Logs tab (GET /logs/filters).
* Changed: Database schema upgraded to v3.
* Changed: Moved Danger Zone (purge) from Settings to Logs tab.

= 0.5.0 =
* Removed: Reflection-based event dispatcher injection workaround (fixed in WP 7 RC1).
* Changed: Tested up to WP 7.0-RC1.

= 0.4.0 =
* Changed: Admin UI rebuilt as a React single-page application.
* Changed: Settings, dashboard, and logs now render client-side via the REST API.
* Added: REST endpoint `GET /settings` for reading all plugin settings.
* Added: `by_context`, `recent`, and `known_slugs` fields in the `GET /usage` response.
* Added: `date_from` and `date_to` filter parameters on the `GET /logs` endpoint.
* Added: Dedicated CSS file for admin styles (replaces inline styles).

= 0.3.0 =
* Added: Model filter on the Logs tab and CSV export.
* Added: Provider & Model breakdown table on the Dashboard.
* Added: Per-plugin token bar chart on the Dashboard.
* Added: Per-provider token counters.
* Changed: Providers & Contexts tables displayed side by side.
* Changed: Plugin list only shows plugins that used the AI connector.

= 0.2.0 =
* Fixed: Status column widened — denial reasons were silently truncated.
* Fixed: "Denied" log filter now matches all denial variants.
* Fixed: Respect the `wp_supports_ai` filter as a global kill switch.
* Added: Date range filter (From / To) on the Logs tab.
* Added: Context breakdown table on the Dashboard tab.
* Added: Per-plugin budget threshold alerts (admin notices).
* Added: CSV export button on the Logs tab.
* Changed: Logs filter bar uses flex layout for better fit.

= 0.1.0 =
* Initial release.
* Per-plugin access control (allow/deny).
* Global and per-plugin daily/monthly token budgets.
* Context restrictions (admin, frontend, cron, REST, AJAX, CLI).
* Usage dashboard with summary cards and progress bars.
* Request logging with provider, model, capability, and token counts.
* Budget alert notices and optional email notifications.
* REST API endpoints for usage and log data.
* Workaround for WordPress 7.0 event dispatcher bug.

== Upgrade Notice ==

= 0.4.0 =
Admin UI rebuilt with React for faster, client-side rendering.

= 0.3.0 =
Dashboard enhancements: model filter, provider+model breakdown, per-plugin bar chart, side-by-side layout.

= 0.2.0 =
Bug fixes for log status storage and filtering. New: date range filter, context breakdown, per-plugin alerts, CSV export.

= 0.1.0 =
Initial release.
