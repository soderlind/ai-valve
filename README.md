# AI Valve

Control, meter, and permission-gate AI usage from plugins that connect through the WordPress 7 AI connector.

> Inspired by [WordPress AI Connectors Need More Friction, Not Less](https://thewp.world/wordpress-ai-connectors-need-more-friction-not-less/). Works with WordPress 7 RC2. Tested with [WordPress AI](https://wordpress.org/plugins/ai/), [Virtual Media Folders AI Organizer](https://github.com/soderlind/vmfa-ai-organizer), and [AI Provider for Azure OpenAI](https://github.com/soderlind/ai-provider-for-azure-openai).

<img width="100%" alt="AI Valve dashboard" src="https://github.com/user-attachments/assets/3b619ba2-1432-4029-be3f-7556bcb991e2" />

## Features

- **Per-plugin access control** — Allow or deny individual plugins from making AI requests.
- **Token budgets** — Set daily and monthly token limits per plugin and globally.
- **Context restrictions** — Control which execution contexts (admin, frontend, cron, REST, AJAX, CLI) may trigger AI calls.
- **Usage dashboard** — Token consumption at a glance: summary cards, progress bars, and per-plugin breakdowns.
- **Request logging** — Every AI request is logged with provider, model, capability, tokens, and caller attribution.
- **Budget alerts** — Admin notices and optional email when usage approaches or exceeds limits.
- **Developer hooks** — Filter and action hooks to extend behaviour without modifying the plugin.
- **Automatic updates** — Receives updates directly from GitHub releases.

## Requirements

- WordPress 7.0+
- PHP 8.3+
- A configured AI provider in **Settings → Connectors**

## Installation

1. Download [`ai-valve.zip`](https://github.com/soderlind/ai-valve/releases/latest/download/ai-valve.zip)
2. Upload via **Plugins → Add New → Upload Plugin**
3. Activate the plugin
4. Go to **Settings → AI Valve** to configure

The plugin updates itself automatically via GitHub releases using [plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker).

### From source

```bash
git clone https://github.com/soderlind/ai-valve.git
cd ai-valve
composer install
npm install && npm run build
```

## Usage

After activation, AI Valve intercepts all calls made through `wp_ai_client_prompt()`. Navigate to **Settings → AI Valve**:

- **Dashboard** — Token usage for today and this month, per-plugin access/budget controls, provider breakdown, and recent requests.
- **Settings** — Master switch, default policy, context restrictions, global budgets, and alert configuration.
- **Logs** — Filterable, paginated request log with purge controls.

## How It Works

AI Valve hooks into three WordPress 7 AI connector events:

| Hook | Purpose |
|---|---|
| `wp_ai_client_prevent_prompt` | Gate requests — evaluate policy |
| `wp_ai_client_before_generate_result` | Insert a pending log row with caller attribution |
| `wp_ai_client_after_generate_result` | Update the pending row with token usage and status |

A pending log row is created *before* the AI provider is called. If the provider throws (auth error, timeout, bad deployment), a shutdown handler marks the row as `error` so failed requests are never lost.

Caller attribution uses `debug_backtrace()` to identify which plugin initiated the request.

When a request is blocked the calling plugin receives a `WP_Error` with code `prompt_prevented`. See [docs/how-blocking-works.md](docs/how-blocking-works.md) for the full explanation.

## Developer Hooks

AI Valve exposes hooks so you can extend its behaviour from another plugin or `functions.php` without editing the source.

| Hook | Type | Purpose |
|---|---|---|
| `ai_valve_plugin_policy` | filter | Override the allow/deny decision for any plugin |
| `ai_valve_request_denied` | action | React when a request is blocked |
| `ai_valve_request_completed` | action | React when a request succeeds (token counts available) |

See **[docs/hooks.md](docs/hooks.md)** for signatures, parameter descriptions, and examples.

## FAQ

### How do I block all plugins and only allow specific ones?

1. Go to **Settings → AI Valve → Settings**.
2. Set the **Default policy** to **Deny**.
3. Switch to the **Dashboard** tab.
4. In the **Per-plugin access** table, set each plugin you want to permit to **Allow**.

Everything not explicitly allowed will be denied.

### Can I override the policy programmatically?

Yes — use the `ai_valve_plugin_policy` filter. See [docs/hooks.md](docs/hooks.md).

## Development

### Tests

```bash
# PHP (PHPUnit 11 + Brain Monkey)
composer install
vendor/bin/phpunit

# JavaScript (Vitest)
npm install
npx vitest run
```

### Project structure

```
ai-valve.php                  Bootstrap
class-github-updater.php      GitHub release updater
src/
  Plugin.php                  Hook registration orchestrator
  Settings/Settings.php       Options read/write/sanitize
  Interceptor/
    RequestInterceptor.php    WP 7 AI hook wiring + pending-row logging
    PolicyEngine.php          Allow/deny/budget evaluation
    CallerDetector.php        Backtrace → plugin slug
  Tracking/
    LogRepository.php         Custom DB table CRUD
    UsageTracker.php          Rolling token counters
  Admin/AdminPage.php         Settings page (dashboard, settings, logs)
  Alert/AlertManager.php      Budget threshold notices + email
  REST/UsageController.php    REST API endpoints
docs/                         Developer documentation
```

## Documentation

See [docs/README.md](docs/README.md) for a full index.

## License

GPL-2.0-or-later — see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).
