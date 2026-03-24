# Plan: AIValve — WordPress AI Usage Control Plugin

**Plugin Name:** AIValve  
**Slug:** `ai-valve`  
**Text Domain:** `ai-valve`  
**Namespace:** `AIValve`  
**Requires PHP:** 8.3  
**Requires WP:** 7.0  

## TL;DR
Build a WordPress plugin that intercepts all WP 7 AI connector requests to provide per-plugin permission control, usage metering/logging, budget thresholds, and an admin dashboard. Leverages three core hooks: `wp_ai_client_prevent_prompt` (gate), `wp_ai_client_before_generate_result` (log start), `wp_ai_client_after_generate_result` (log tokens/result).

## Architecture

### Hook interception points (WP 7 core)
- `wp_ai_client_prevent_prompt` — filter, receives `(bool $prevent, WP_AI_Client_Prompt_Builder $builder)`. Our gate/ACL layer.
- `wp_ai_client_before_generate_result` — action, receives `BeforeGenerateResultEvent` (messages, model, capability). Start timing + attribution.
- `wp_ai_client_after_generate_result` — action, receives `AfterGenerateResultEvent` (messages, model, capability, result with TokenUsage). Log tokens.
- `wp_supports_ai` — filter, global kill switch (respect, don't override).

### Caller attribution challenge
Events don't carry caller plugin info. Use `debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)` to walk the call stack and identify which plugin directory the call originated from, mapping to plugin slug via WP plugin API.

### Data available per request
- Provider ID/name: `$event->getModel()->providerMetadata()->getId()`
- Model ID/name: `$event->getModel()->metadata()->getId()`
- Capability: `$event->getCapability()`
- Token usage (after): `$event->getResult()->getTokenUsage()` → promptTokens, completionTokens, totalTokens
- Context: `is_admin()`, `wp_doing_cron()`, `wp_doing_ajax()`, REST request detection

## Steps

### Phase 1: Core Infrastructure
1. Scaffold plugin bootstrap (`ai-valve.php`) with header, `AIValve` namespace, Composer PSR-4 autoloader, constants (`AI_VALVE_VERSION`, `AI_VALVE_FILE`, `AI_VALVE_DIR`)
2. Create custom DB table `{prefix}ai_valve_log` for request logging (id, plugin_slug, provider_id, model_id, capability, context, prompt_tokens, completion_tokens, total_tokens, status, created_at)
3. Activation hook: create table via `dbDelta()`. Store schema version in option `ai_valve_db_version`.
4. Create `AIValve\Settings\Settings` class — stores plugin options in `ai_valve_settings`:
   - Global enable/disable
   - Per-plugin allow/deny list
   - Context restrictions (admin-only, no-cron, no-frontend)
   - Budget thresholds (per-plugin daily/monthly token limits, global limits)
   - Alert thresholds (warning at X% of budget)

### Phase 2: Interception Engine (*depends on Phase 1*)
5. Create `AIValve\Interceptor\CallerDetector` — walks `debug_backtrace()` to find calling plugin slug by matching file paths against `WP_PLUGIN_DIR` and active plugins list
6. Create `AIValve\Interceptor\PolicyEngine` — evaluates whether a request should be allowed based on:
   - Plugin slug allow/deny
   - Context (admin/frontend/cron)
   - Budget remaining for plugin and global
   - Provider/model restrictions
7. Create `AIValve\Interceptor\RequestInterceptor`:
   - Hooks `wp_ai_client_prevent_prompt` (priority 10) — calls CallerDetector + PolicyEngine, returns true to block if policy denies
   - Hooks `wp_ai_client_before_generate_result` (priority 10) — logs request start, stores request ID in static property for correlation
   - Hooks `wp_ai_client_after_generate_result` (priority 10) — logs token usage, updates running totals in options/transients
8. Create `AIValve\Tracking\UsageTracker` — maintains rolling token counts per plugin, per provider, per day/month. Uses options + object cache for fast reads, batch-writes to DB.

### Phase 3: Admin Dashboard (*depends on Phase 1, parallel with Phase 2*)
9. Create `AIValve\Admin\AdminPage` — registers Settings → AIValve page
10. Dashboard tab: usage overview
    - Total requests today/this month
    - Tokens consumed per plugin (table + bar chart)
    - Tokens consumed per provider/model
    - Context breakdown (admin/cron/frontend)
    - Recent requests log (paginated table)
11. Settings tab: permissions & thresholds
    - Per-plugin toggles (auto-discovered from active plugins that use AI)
    - Context restrictions checkboxes
    - Budget fields (daily/monthly token limits per plugin, global)
    - Warning threshold percentage
12. Logs tab: searchable/filterable request log
    - Filter by plugin, provider, model, context, date range
    - Export CSV

### Phase 4: Notifications & Alerts (*depends on Phase 2*)
13. Create `AIValve\Alert\AlertManager`:
    - Admin notices when budget threshold reached
    - Optional email notification on budget exceeded
    - Dashboard widget showing current usage vs limits

### Phase 5: REST API (*parallel with Phase 3*)
14. Create `AIValve\REST\UsageController` — endpoints for:
    - `GET /ai-valve/v1/usage` — usage stats (for dashboard AJAX)
    - `GET /ai-valve/v1/logs` — paginated log entries
    - `POST /ai-valve/v1/settings` — update settings
    - Permission: `manage_options`

## Relevant files to create
- `ai-valve/ai-valve.php` — main bootstrap
- `ai-valve/composer.json` — PSR-4 autoload for `AIValve\\` → `src/`
- `ai-valve/src/Plugin.php` — hook registration orchestrator
- `ai-valve/src/Interceptor/CallerDetector.php` — backtrace → plugin slug
- `ai-valve/src/Interceptor/PolicyEngine.php` — allow/deny/budget evaluation
- `ai-valve/src/Interceptor/RequestInterceptor.php` — hook wiring
- `ai-valve/src/Tracking/UsageTracker.php` — token/request counters
- `ai-valve/src/Tracking/LogRepository.php` — DB table CRUD
- `ai-valve/src/Settings/Settings.php` — options read/write
- `ai-valve/src/Admin/AdminPage.php` — admin menu + page rendering
- `ai-valve/src/Admin/DashboardWidget.php` — WP dashboard widget
- `ai-valve/src/Alert/AlertManager.php` — threshold notifications
- `ai-valve/src/REST/UsageController.php` — REST endpoints
- `ai-valve/uninstall.php` — cleanup on uninstall

## Reference files (existing, read-only)
- `/wp-includes/ai-client/class-wp-ai-client-prompt-builder.php` — `wp_ai_client_prevent_prompt` filter, `__call()` dispatch
- `/wp-includes/ai-client/adapters/class-wp-ai-client-event-dispatcher.php` — event→action bridge
- `/wp-includes/php-ai-client/src/Events/BeforeGenerateResultEvent.php` — pre-request event data
- `/wp-includes/php-ai-client/src/Events/AfterGenerateResultEvent.php` — post-request event data (TokenUsage!)
- `/wp-includes/php-ai-client/src/Results/DTO/GenerativeAiResult.php` — result with token usage
- `/wp-includes/ai-client.php` — `wp_supports_ai()`, `wp_ai_client_prompt()`
- `/wp-includes/connectors.php` — connector registry functions

## Verification
1. Activate plugin → no errors, DB table `{prefix}ai_valve_log` created.
2. Trigger AI request via vmfa-ai-organizer → log entry with correct plugin slug, provider, model, tokens.
3. Deny a plugin in settings → next AI request returns `WP_Error('prompt_prevented')`.
4. Set daily token limit to 100 → requests blocked after limit reached.
5. Check admin dashboard shows accurate usage data.
6. Disable cron context → cron-based AI requests blocked.
7. Run `wp option get ai_valve_settings` to verify persistence.
8. Deactivate + delete → `uninstall.php` removes table and `ai_valve_*` options.

## Decisions
- **Custom DB table** (`{prefix}ai_valve_log`) — high-volume writes, aggregation queries
- **`debug_backtrace()`** for caller detection — only option; cached per request
- **Rolling counters** in options with date keys — simple, no cron for resets
- **PHP-rendered admin** (Settings API) for v1; React upgrade path for v2
- **Scope**: WP 7 AI connector only (not raw `wp_remote_post()` to AI APIs)
- **PHP 8.3**: constructor promotion, readonly props, typed props, match, enums
- **Namespace**: `AIValve` via Composer PSR-4 (`AIValve\\` → `src/`)
- **Prefixes**: option `ai_valve_`, DB table `ai_valve_`, REST `ai-valve/v1`

## Further Considerations
1. **Backtrace reliability**: `debug_backtrace()` may not always resolve to a clean plugin slug (e.g., mu-plugins, theme code, WP-CLI). Recommendation: fall back to "unknown" and show in logs for manual review.
2. **Token estimation for prevent_prompt**: At the prevent stage, we don't know token count yet. Budget checks against remaining budget use past consumption only; the request that exceeds the limit will still go through, then future ones are blocked. Alternative: estimate tokens from prompt length, but adds complexity.
3. **Multisite**: Should budgets be per-site or network-wide? Recommendation: per-site for v1, network-aware option for v2.
