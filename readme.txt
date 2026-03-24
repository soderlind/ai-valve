=== AI Valve ===
Contributors: PerS
Tags: ai, tokens, metering, permissions, usage
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.3
Stable tag: 0.3.0
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
* **Automatic updates** — Receives updates directly from GitHub releases.

= Requirements =

* WordPress 7.0 or later
* PHP 8.3 or later
* A configured AI provider in Settings → Connectors

== Installation ==

1. Download `ai-valve.zip` from the [latest release](https://github.com/soderlind/ai-valve/releases/latest).
2. In WordPress, go to Plugins → Add New → Upload Plugin and upload the zip.
3. Activate the plugin.
4. Go to Settings → AI Valve to configure.

== Frequently Asked Questions ==

= What are tokens? =

Tokens are the units AI models use to measure input and output. Roughly 1 token ≈ ¾ of a word. Both the text you send (prompt tokens) and the text the AI returns (completion tokens) count toward your usage.

= What does "limit = 0" mean? =

A limit of 0 means unlimited — no cap is enforced. Set a positive number to restrict token usage.

= How does AI Valve identify which plugin made an AI request? =

It walks the PHP call stack (`debug_backtrace()`) and matches file paths against the plugins directory to determine the originating plugin slug.

= Will this work when the WordPress core bug is fixed? =

Yes. AI Valve includes a workaround that checks whether the event dispatcher is already set. Once WordPress core passes it properly, the workaround is automatically skipped.

= Does AI Valve work on multisite? =

Yes. Each subsite has its own log table, settings, and budgets.

= What happens when a plugin is blocked? =

The plugin receives a `WP_Error` with code `prompt_prevented` instead of an AI response. The denied request is logged with the reason. See [how-blocking-works.md](https://github.com/soderlind/ai-valve/blob/main/docs/how-blocking-works.md) for the full explanation.

== Changelog ==

= 0.3.0 =
* Added: Model filter on the Logs tab and CSV export.
* Added: Provider & Model breakdown table on the Dashboard.
* Added: Per-plugin token bar chart on the Dashboard.
* Added: Per-provider token counters.
* Added: How-blocking-works documentation.
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
* GitHub release updater for automatic updates.

== Upgrade Notice ==

= 0.3.0 =
Dashboard enhancements: model filter, provider+model breakdown, per-plugin bar chart, side-by-side layout.

= 0.2.0 =
Bug fixes for log status storage and filtering. New: date range filter, context breakdown, per-plugin alerts, CSV export.

= 0.1.0 =
Initial release.
