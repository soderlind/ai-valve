# AI Valve

Control, meter, and permission-gate AI usage from plugins that connect through the WordPress 7 AI connector.

> Tested using [Virtual Media Folders AI Organizer](https://github.com/soderlind/vmfa-ai-organizer?tab=readme-ov-file#virtual-media-folders-ai-organizer)

## Features

- **Per-plugin access control** — Allow or deny individual plugins from making AI requests.
- **Token budgets** — Set daily and monthly token limits per plugin and globally.
- **Context restrictions** — Control which execution contexts (admin, frontend, cron, REST, AJAX, CLI) may trigger AI calls.
- **Usage dashboard** — See token consumption at a glance with summary cards, progress bars, and per-plugin breakdowns.
- **Request logging** — Every AI request is logged with provider, model, capability, tokens, and caller attribution.
- **Budget alerts** — Admin notices and optional email when usage approaches or exceeds limits.
- **Automatic updates** — Receives updates directly from GitHub releases.

## Requirements

- WordPress 7.0+
- PHP 8.3+
- A configured AI provider in **Settings → AI Services**

## Installation

1. Download `ai-valve.zip` from the [latest release](https://github.com/soderlind/ai-valve/releases/latest).
2. In WordPress, go to **Plugins → Add New → Upload Plugin** and upload the zip.
3. Activate the plugin.
4. Go to **Settings → AI Valve** to configure.

### From source

```bash
git clone https://github.com/soderlind/ai-valve.git
cd ai-valve
composer install
```

## Usage

After activation, AI Valve intercepts all calls made through `wp_ai_client_prompt()`. Navigate to **Settings → AI Valve**:

- **Dashboard** — Token usage for today and this month, per-plugin access/budget controls, provider breakdown, and recent requests.
- **Settings** — Master switch, default policy, context restrictions, global budgets, and alert configuration.
- **Logs** — Filterable request log with pagination.

## How It Works

AI Valve hooks into three WordPress 7 AI connector events:

| Hook | Purpose |
|---|---|
| `wp_ai_client_prevent_prompt` | Gate requests — evaluate policy, inject event dispatcher |
| `wp_ai_client_before_generate_result` | Capture caller attribution |
| `wp_ai_client_after_generate_result` | Log token usage and update counters |

Caller attribution uses `debug_backtrace()` to identify which plugin initiated the AI request.

> **Note:** WordPress 7.0 has a core bug where the event dispatcher is not passed to the SDK PromptBuilder. AI Valve includes a reflection-based workaround that injects the dispatcher automatically. See [docs/howto-intercept-wp7-ai-requests.md](docs/howto-intercept-wp7-ai-requests.md) for details.

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
    RequestInterceptor.php    WP 7 AI hook wiring + dispatcher fix
    PolicyEngine.php          Allow/deny/budget evaluation
    CallerDetector.php        Backtrace → plugin slug
  Tracking/
    LogRepository.php         Custom DB table CRUD
    UsageTracker.php          Rolling token counters
  Admin/AdminPage.php         Settings page (dashboard, settings, logs)
  Alert/AlertManager.php      Budget threshold notices + email
  REST/UsageController.php    REST API endpoints
```

## Credits

Heavily inspired by the [WordPress AI Connectors Need More Friction, Not Less](https://thewp.world/wordpress-ai-connectors-need-more-friction-not-less/) article.

## License

GPL-2.0-or-later — see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).
